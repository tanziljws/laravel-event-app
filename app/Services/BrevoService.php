<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    protected $fromEmail;
    protected $fromName;

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key');
        $this->fromEmail = config('mail.from.address');
        $this->fromName = config('mail.from.name');
    }

    /**
     * Send email via Brevo API
     *
     * @param string $to Email recipient
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlContent HTML content
     * @param string|null $textContent Plain text content (optional)
     * @return array
     */
    public function sendEmail(
        string $to,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): array {
        try {
            $payload = [
                'sender' => [
                    'name' => $this->fromName,
                    'email' => $this->fromEmail,
                ],
                'to' => [
                    [
                        'email' => $to,
                        'name' => $toName,
                    ],
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ];

            if ($textContent) {
                $payload['textContent'] = $textContent;
            }

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'api-key' => $this->apiKey,
                'content-type' => 'application/json',
            ])->post($this->apiUrl, $payload);

            if ($response->successful()) {
                Log::info('Brevo email sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'message_id' => $response->json('messageId'),
                ]);

                return [
                    'success' => true,
                    'message_id' => $response->json('messageId'),
                    'response' => $response->json(),
                ];
            } else {
                Log::error('Brevo email failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json()['message'] ?? 'Failed to send email',
                    'status' => $response->status(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Brevo email exception', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email with template view
     *
     * @param string $to
     * @param string $toName
     * @param string $subject
     * @param string $view
     * @param array $data
     * @return array
     */
    public function sendEmailWithView(
        string $to,
        string $toName,
        string $subject,
        string $view,
        array $data = []
    ): array {
        $htmlContent = view($view, $data)->render();

        return $this->sendEmail($to, $toName, $subject, $htmlContent);
    }
}

