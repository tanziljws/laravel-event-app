<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Event;
use App\Services\BrevoService;

class SendRegistrationTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $event;
    protected $token;

    public function __construct(User $user, Event $event, string $token)
    {
        $this->user = $user;
        $this->event = $event;
        $this->token = $token;
    }

    public function handle(): void
    {
        $subject = "Token Kehadiran - {$this->event->title}";
        
        $message = "
        <h2>Pendaftaran Berhasil!</h2>
        <p>Halo {$this->user->name},</p>
        
        <p>Terima kasih telah mendaftar untuk kegiatan <strong>{$this->event->title}</strong>.</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #e91e63; margin-top: 0;'>Token Kehadiran Anda:</h3>
            <div style='font-size: 24px; font-weight: bold; color: #333; letter-spacing: 2px; text-align: center; background: white; padding: 15px; border-radius: 4px; border: 2px dashed #e91e63;'>
                {$this->token}
            </div>
            <p style='margin-bottom: 0; color: #666; font-size: 14px;'><strong>Penting:</strong> Simpan token ini dengan baik. Anda akan memerlukan token ini untuk mengisi daftar hadir saat kegiatan berlangsung.</p>
        </div>
        
        <h3>Detail Kegiatan:</h3>
        <ul>
            <li><strong>Nama Kegiatan:</strong> {$this->event->title}</li>
            <li><strong>Tanggal:</strong> " . \Carbon\Carbon::parse($this->event->event_date)->format('d F Y') . "</li>
            <li><strong>Waktu:</strong> {$this->event->start_time} WIB</li>
            <li><strong>Lokasi:</strong> {$this->event->location}</li>
        </ul>
        
        <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
            <h4 style='margin-top: 0; color: #856404;'>Cara Menggunakan Token:</h4>
            <ol style='color: #856404; margin-bottom: 0;'>
                <li>Datang ke lokasi kegiatan sesuai jadwal</li>
                <li>Buka halaman daftar hadir di website EduFest</li>
                <li>Masukkan token 10 digit yang tertera di atas</li>
                <li>Klik submit untuk mencatat kehadiran Anda</li>
            </ol>
        </div>
        
        <p>Jika Anda memiliki pertanyaan, silakan hubungi panitia melalui email ini.</p>
        
        <p>Terima kasih,<br>
        <strong>Tim EduFest SMKN 4 Bogor</strong></p>
        ";

        $brevoService = new BrevoService();
        
        $brevoService->sendEmail(
            $this->user->email,
            $this->user->name,
            $subject,
            $message
        );
    }
}
