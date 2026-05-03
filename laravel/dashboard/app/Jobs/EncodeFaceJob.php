<?php

namespace App\Jobs;

use App\Models\FaceEncoding;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EncodeFaceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public User   $employee,
        public string $imagePath  // path trong storage/app/local
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::path($this->imagePath);

        if (!file_exists($fullPath)) {
            Log::error("[EncodeFaceJob] File không tồn tại: {$fullPath}");
            return;
        }

        // Gọi Python script để tính face encoding
        $scriptPath = base_path('../pi4/face_encode_single.py');
        $command    = escapeshellcmd("python \"{$scriptPath}\" \"{$fullPath}\"");
        $output     = shell_exec($command);

        if (!$output) {
            Log::error("[EncodeFaceJob] Python script không trả về kết quả. User: {$this->employee->id}");
            return;
        }

        $result = json_decode($output, true);

        if (empty($result['encoding'])) {
            Log::warning("[EncodeFaceJob] Không tìm thấy khuôn mặt trong ảnh. User: {$this->employee->id}");
            return;
        }

        FaceEncoding::create([
            'user_id'    => $this->employee->id,
            'encoding'   => $result['encoding'],
            'image_path' => $this->imagePath,
            'created_at' => now(),
        ]);

        Log::info("[EncodeFaceJob] Mã hóa thành công. User: {$this->employee->id}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[EncodeFaceJob] Thất bại sau {$this->tries} lần. User: {$this->employee->id}. Lỗi: {$e->getMessage()}");
    }
}
