<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Services\AttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AttendanceApiController extends Controller
{
    public function __construct(private AttendanceStatusService $statusService) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'           => 'required|integer|exists:users,id',
            'type'              => 'required|in:check_in,check_out',
            'confidence'        => 'required|numeric|between:0,1',
            'recorded_at'       => 'required',
            'image'             => 'nullable|string',
            'shift_schedule_id' => 'nullable|integer|exists:shift_schedules,id',
        ]);

        $device     = $request->attributes->get('device');
        $recordedAt = $this->parseTimestamp($data['recorded_at']);
        $workDate   = date('Y-m-d', strtotime($recordedAt));

        $user = User::withTrashed()->find($data['user_id']);
        if (!$user || $user->deleted_at) {
            return response()->json(['message' => 'User not found or deleted'], 422);
        }

        $imagePath = null;
        if (!empty($data['image'])) {
            $imagePath = $this->saveImage($data['image'], $data['user_id'], $workDate, $data['type']);
        }

        if (!empty($data['shift_schedule_id'])) {
            $shift = ShiftSchedule::with('template')->find($data['shift_schedule_id']);
        } else {
            $shift = $this->resolveActiveShift($user, $workDate);
            if (!$shift) {
                return response()->json(['message' => 'No active shift for this employee on this date'], 422);
            }
        }

        $attendance = $this->findOrCreateAttendance(
            $data['user_id'], $workDate, $shift?->id, $device->id
        );

        $this->applyRecord($attendance, $user, $data['type'], $recordedAt, (float) $data['confidence'], $imagePath, $device->id, $shift);

        return response()->json([
            'id'                => $attendance->id,
            'work_date'         => $workDate,
            'shift_schedule_id' => $attendance->shift_schedule_id,
            'status'            => $attendance->status,
        ], 201);
    }

    public function batch(Request $request)
    {
        $records = $request->input('records', []);

        if (!is_array($records) || count($records) === 0) {
            return response()->json(['message' => 'No records provided'], 422);
        }

        $records = array_slice($records, 0, 500);
        $device  = $request->attributes->get('device');

        $saved   = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($records as $index => $record) {
            $validator = Validator::make($record, [
                'user_id'           => 'required|integer|exists:users,id',
                'type'              => 'required|in:check_in,check_out',
                'confidence'        => 'required|numeric|between:0,1',
                'recorded_at'       => 'required',
                'image'             => 'nullable|string',
                'shift_schedule_id' => 'nullable|integer|exists:shift_schedules,id',
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[$index] = $validator->errors()->toArray();
                continue;
            }

            try {
                $recordedAt = $this->parseTimestamp($record['recorded_at']);
                $workDate   = date('Y-m-d', strtotime($recordedAt));

                $user = User::withTrashed()->find($record['user_id']);
                if (!$user || $user->deleted_at) {
                    $skipped++;
                    continue;
                }

                $imagePath = null;
                if (!empty($record['image'])) {
                    $imagePath = $this->saveImage($record['image'], $record['user_id'], $workDate, $record['type']);
                }

                if (!empty($record['shift_schedule_id'])) {
                    $shift = ShiftSchedule::with('template')->find($record['shift_schedule_id']);
                } else {
                    $shift = $this->resolveActiveShift($user, $workDate);
                    if (!$shift) {
                        $skipped++;
                        $errors[$index] = 'No active shift for this employee on this date';
                        continue;
                    }
                }

                $attendance = $this->findOrCreateAttendance(
                    $record['user_id'], $workDate, $shift?->id, $device->id
                );

                $this->applyRecord($attendance, $user, $record['type'], $recordedAt, (float) $record['confidence'], $imagePath, $device->id, $shift);
                $saved++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[$index] = $e->getMessage();
            }
        }

        return response()->json([
            'saved'   => $saved,
            'skipped' => $skipped,
            'errors'  => $errors,
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Tìm ca active của nhân viên trong ngày workDate.
     * Logic giống ShiftActiveController: ưu tiên employee-level trước department-level.
     */
    private function resolveActiveShift(User $user, string $workDate): ?ShiftSchedule
    {
        $date = Carbon::parse($workDate);

        $candidates = ShiftSchedule::with('template')
            ->where('is_active', true)
            ->where('start_date', '<=', $date)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->where(function ($q) use ($user) {
                $q->where(fn($q2) => $q2
                    ->where('assignee_type', 'employee')
                    ->where('assignee_id', $user->id));
                if ($user->department_id) {
                    $q->orWhere(fn($q2) => $q2
                        ->where('assignee_type', 'department')
                        ->where('assignee_id', $user->department_id));
                }
            })
            ->get();

        return $candidates
            ->filter(fn($s) => $s->appliesToDate($date))
            ->sortBy(fn($s) => $s->assignee_type === 'employee' ? 0 : 1)
            ->first();
    }

    /**
     * When shift_schedule_id is given, key on (user_id, work_date, shift_schedule_id)
     * so employees can have multiple shifts on the same day.
     * When null, key on (user_id, work_date) — backward-compat for no-shift employees.
     */
    private function findOrCreateAttendance(int $userId, string $workDate, ?int $shiftId, int $deviceId): Attendance
    {
        if ($shiftId !== null) {
            return Attendance::firstOrCreate(
                ['user_id' => $userId, 'work_date' => $workDate, 'shift_schedule_id' => $shiftId],
                ['device_id' => $deviceId, 'status' => 'absent']
            );
        }

        return Attendance::firstOrCreate(
            ['user_id' => $userId, 'work_date' => $workDate],
            ['device_id' => $deviceId, 'status' => 'absent']
        );
    }

    private function applyRecord(
        Attendance $attendance,
        User $user,
        string $type,
        string $recordedAt,
        float $confidence,
        ?string $imagePath,
        int $deviceId,
        ?ShiftSchedule $shift = null
    ): void {
        if ($type === 'check_in') {
            if ($attendance->check_in_at !== null) {
                return;
            }

            $status = $this->statusService->calculateStatus($user, $recordedAt, $shift);

            $attendance->update([
                'device_id'           => $deviceId,
                'check_in_at'         => $recordedAt,
                'check_in_confidence' => $confidence,
                'check_in_image'      => $imagePath,
                'status'              => $status,
            ]);
        } else {
            if ($attendance->check_out_at !== null) {
                return;
            }

            $checkOutStatus = $this->statusService->calculateCheckOutStatus($user, $recordedAt, $shift);
            $finalStatus    = $checkOutStatus === 'early_leave' ? 'early_leave' : $attendance->status;

            $attendance->update([
                'check_out_at'         => $recordedAt,
                'check_out_confidence' => $confidence,
                'check_out_image'      => $imagePath,
                'status'               => $finalStatus,
            ]);
        }
    }

    private function parseTimestamp(mixed $value): string
    {
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        return date('Y-m-d H:i:s', strtotime((string) $value));
    }

    private function saveImage(string $base64, int $userId, string $workDate, string $type): ?string
    {
        try {
            $imageData = base64_decode($base64, strict: true);
            if ($imageData === false) {
                return null;
            }

            $path = "attendances/{$workDate}/{$userId}_{$type}.jpg";
            Storage::disk('public')->put($path, $imageData);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
