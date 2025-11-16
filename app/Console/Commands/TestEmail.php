<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BrevoService;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        try {
            $brevoService = new BrevoService();
            
            $htmlContent = "
                <html>
                <body>
                    <h2>Test Email dari Laravel Event App</h2>
                    <p>Ini adalah email test untuk memverifikasi konfigurasi Brevo API.</p>
                    <p>Jika Anda menerima email ini, berarti konfigurasi Brevo API sudah berfungsi dengan baik!</p>
                    <hr>
                    <p style='color: #666; font-size: 12px;'>Email ini dikirim pada: " . now()->format('d F Y H:i:s') . "</p>
                </body>
                </html>
            ";
            
            $result = $brevoService->sendEmail(
                $email,
                'Test User',
                'Test Email - Laravel Event App',
                $htmlContent
            );
            
            if ($result['success']) {
                $this->info("✅ Test email sent successfully to {$email}");
                $this->info("Message ID: " . ($result['message_id'] ?? 'N/A'));
            } else {
                $this->error("❌ Failed to send email: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
        }
    }
}
