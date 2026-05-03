<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateDailyAbsences extends Command
{
    protected $signature = 'attendances:generate-absences
                            {--date= : Work date Y-m-d, defaults to today}';

    protected $description = 'Create absent records for employees with no attendance entry today';

    public function handle(): void
    {
        $date = $this->option('date') ?? now()->toDateString();

        $allUserIds = User::whereNull('deleted_at')->pluck('id');

        $presentIds = Attendance::where('work_date', $date)->pluck('user_id');

        $absentIds = $allUserIds->diff($presentIds);

        if ($absentIds->isEmpty()) {
            $this->info("No missing records for {$date}.");
            return;
        }

        $rows = $absentIds->map(fn ($id) => [
            'user_id'    => $id,
            'work_date'  => $date,
            'status'     => 'absent',
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all();

        Attendance::insert($rows);

        $this->info("Created {$absentIds->count()} absent record(s) for {$date}.");
    }
}
