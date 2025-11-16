<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Services\BrevoService;

class SendOtpJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $user;
    public $otp;
    public $type;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $otp, string $type)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $subject = $this->type === 'verification' 
            ? 'Verifikasi Email - EduFest' 
            : 'Reset Password - EduFest';

        $brevoService = new BrevoService();
        
        $result = $brevoService->sendEmailWithView(
            $this->user->email,
            $this->user->name,
            $subject,
            'emails.otp',
            [
                'user' => $this->user,
                'otp' => $this->otp,
                'type' => $this->type
            ]
        );

        // Log hasil pengiriman
        if (!$result['success']) {
            \Log::error('Failed to send OTP email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $result['error'] ?? 'Unknown error'
            ]);
            throw new \Exception('Failed to send OTP email: ' . ($result['error'] ?? 'Unknown error'));
        }
    }
}
