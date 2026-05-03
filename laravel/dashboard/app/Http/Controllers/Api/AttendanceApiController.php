<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AttendanceApiController extends Controller
{
    public function __construct(private AttendanceStatusService $statusService) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'type'        => 'required|in:check_in,check_out',
            'confidence'  => 'required|numeric|between:0,1',
            'recorded_at' => 'required',
            'image'       => 'nullable|string',
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

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $data['user_id'], 'work_date' => $workDate],
            ['device_id' => $device->id, 'status' => 'absent']
        );

        $this->applyRecord($attendance, $user, $data['type'], $recordedAt, (float) $data['confidence'], $imagePath, $device->id);

        return response()->json([
            'id'        => $attendance->id,
            'work_date' => $workDate,
            'status'    => $attendance->status,
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
                'user_id'     => 'required|integer|exists:users,id',
                'type'        => 'required|in:check_in,check_out',
                'confidence'  => 'required|numeric|between:0,1',
                'recorded_at' => 'required',
                'image'       => 'nullable|string',
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

                $attendance = Attendance::firstOrCreate(
                    ['user_id' => $record['user_id'], 'work_date' => $workDate],
                    ['device_id' => $device->id, 'status' => 'absent']
                );

                $this->applyRecord($attendance, $user, $record['type'], $recordedAt, (float) $record['confidence'], $imagePath, $device->id);
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

    private function applyRecord(
        Attendance $attendance,
        User $user,
        string $type,
        string $recordedAt,
        float $confidence,
        ?string $imagePath,
        int $deviceId
    ): void {
        if ($type === 'check_in') {
            if ($attendance->check_in_at !== null) {
                return; // already checked in today
            }

            $status = $this->statusService->calculateStatus($user, $recordedAt);

            $attendance->update([
                'device_id'           => $deviceId,
                'check_in_at'         => $recordedAt,
                'check_in_confidence' => $confidence,
                'check_in_image'      => $imagePath,
                'status'              => $status,
            ]);
        } else {
            if ($attendance->check_out_at !== null) {
                return; // already checked out today
            }

            $checkOutStatus = $this->statusService->calculateCheckOutStatus($user, $recordedAt);

            // early_leave overrides present/late; otherwise keep the check-in status
            $finalStatus = $checkOutStatus === 'early_leave' ? 'early_leave' : $attendance->status;

            $attendance->update([
                'check_out_at'         => $recordedAt,
                'check_out_confidence' => $confidence,
                'check_out_image'      => $imagePath,
                'status'               => $finalStatus,
            ]);
        }
    }

    // Accept unix timestamp (int/string) or datetime string
    private function parseTimestamp(mixed $value): string
    {
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        return date('Y-m-d H:i:s', strtotime((string) $value));
    }

    // Decode base64, save to storage/app/public/attendances/, return relative path
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
