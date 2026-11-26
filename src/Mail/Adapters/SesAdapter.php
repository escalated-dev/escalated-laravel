<?php

namespace Escalated\Laravel\Mail\Adapters;

use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SesAdapter implements InboundAdapter
{
    /**
     * Parse an AWS SES/SNS inbound notification into an InboundMessage.
     *
     * SES forwards emails via SNS notifications. The SNS payload contains:
     * - Type: "Notification"
     * - Message: JSON string with SES mail/receipt data
     *   - mail.source, mail.destination, mail.headers, mail.commonHeaders
     *   - receipt: processing results
     *   - content: raw MIME message (if configured for raw delivery)
     *
     * For SNS subscription confirmation, Type will be "SubscriptionConfirmation"
     * and we must visit the SubscribeURL to confirm.
     */
    public function parseRequest(Request $request): InboundMessage
    {
        $payload = $request->json()->all();

        // Handle SNS subscription confirmation
        if (($payload['Type'] ?? '') === 'SubscriptionConfirmation') {
            $this->confirmSubscription($payload);

            // Return a minimal message that the service will see as processed
            return new InboundMessage(
                fromEmail: 'sns-confirmation@amazonaws.com',
                fromName: 'AWS SNS',
                toEmail: (string) EscalatedSettings::get('inbound_email_address', config('escalated.inbound_email.address', '')),
                subject: '[SNS Subscription Confirmation]',
                bodyText: 'SNS subscription confirmed.',
                bodyHtml: null,
            );
        }

        $message = json_decode($payload['Message'] ?? '{}', true);
        $mail = $message['mail'] ?? [];
        $commonHeaders = $mail['commonHeaders'] ?? [];
        $headers = $this->parseHeaders($mail['headers'] ?? []);

        $fromAddresses = $commonHeaders['from'] ?? [$mail['source'] ?? ''];
        $fromRaw = is_array($fromAddresses) ? ($fromAddresses[0] ?? '') : $fromAddresses;

        $toAddresses = $commonHeaders['to'] ?? ($mail['destination'] ?? []);
        $toRaw = is_array($toAddresses) ? ($toAddresses[0] ?? '') : $toAddresses;

        $subject = $commonHeaders['subject'] ?? ($headers['Subject'] ?? '');

        // Extract body from raw content if available
        $bodyText = null;
        $bodyHtml = null;
        $attachments = [];

        $content = $message['content'] ?? null;
        if ($content) {
            $parsed = $this->parseRawMime($content);
            $bodyText = $parsed['bodyText'];
            $bodyHtml = $parsed['bodyHtml'];
            $attachments = $parsed['attachments'];
        }

        return new InboundMessage(
            fromEmail: $this->extractEmail($fromRaw),
            fromName: $this->extractName($fromRaw) ?: null,
            toEmail: $this->extractEmail($toRaw),
            subject: $subject,
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            messageId: $commonHeaders['messageId'] ?? ($headers['Message-ID'] ?? null),
            inReplyTo: $headers['In-Reply-To'] ?? null,
            references: $headers['References'] ?? null,
            headers: $headers,
            attachments: $attachments,
        );
    }

    /**
     * Verify the AWS SNS message signature.
     *
     * Validates:
     * 1. The TopicArn matches the configured ARN
     * 2. The message signature is valid using the SNS signing certificate
     */
    public function verifyRequest(Request $request): bool
    {
        $payload = $request->json()->all();

        // Verify the TopicArn matches configuration (required — reject if not configured)
        $configuredArn = EscalatedSettings::get('ses_topic_arn', config('escalated.inbound_email.ses.topic_arn'));
        if (empty($configuredArn)) {
            Log::warning('Escalated: SES Topic ARN not configured — rejecting request.');

            return false;
        }

        $topicArn = $payload['TopicArn'] ?? '';
        if ($topicArn !== $configuredArn) {
            return false;
        }

        // Verify the SNS message signature
        return $this->verifySnsSignature($payload);
    }

    /**
     * Confirm an SNS subscription by visiting the SubscribeURL.
     * Validates the URL is a legitimate Amazon SNS endpoint before fetching (SSRF prevention).
     */
    protected function confirmSubscription(array $payload): void
    {
        $subscribeUrl = $payload['SubscribeURL'] ?? null;

        if (! $subscribeUrl || ! $this->isValidSnsUrl($subscribeUrl)) {
            Log::warning('Escalated: Rejected SNS SubscribeURL — not a valid Amazon SNS URL.', [
                'url' => $subscribeUrl ?? '(none)',
            ]);

            return;
        }

        // Verify the subscription confirmation signature before confirming
        if (! $this->verifySnsSignature($payload)) {
            Log::warning('Escalated: Rejected SNS subscription confirmation — invalid signature.');

            return;
        }

        try {
            Http::get($subscribeUrl);
            Log::info('Escalated: SNS subscription confirmed.', ['topic' => $payload['TopicArn'] ?? 'unknown']);
        } catch (\Throwable $e) {
            Log::error('Escalated: Failed to confirm SNS subscription.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify the SNS message signature using the certificate.
     */
    protected function verifySnsSignature(array $payload): bool
    {
        $certUrl = $payload['SigningCertURL'] ?? '';

        // Validate the certificate URL is from Amazon
        if (! $this->isValidSnsUrl($certUrl)) {
            return false;
        }

        $signature = base64_decode($payload['Signature'] ?? '', true);
        if ($signature === false) {
            return false;
        }

        try {
            $response = Http::get($certUrl);
            if (! $response->successful()) {
                return false;
            }

            $certificate = openssl_get_publickey($response->body());
            if ($certificate === false) {
                return false;
            }

            $stringToSign = $this->buildSnsSigningString($payload);

            $result = openssl_verify($stringToSign, $signature, $certificate, OPENSSL_ALGO_SHA1);

            return $result === 1;
        } catch (\Throwable $e) {
            Log::warning('Escalated: SNS signature verification failed.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Validate that the SigningCertURL is a legitimate Amazon SNS URL.
     */
    protected function isValidSnsUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (! $parsed || ($parsed['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = $parsed['host'] ?? '';

        return (bool) preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/', $host);
    }

    /**
     * Build the signing string for SNS signature verification.
     */
    protected function buildSnsSigningString(array $payload): string
    {
        $type = $payload['Type'] ?? '';

        if ($type === 'Notification') {
            $keys = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
        } else {
            $keys = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        }

        $stringToSign = '';
        foreach ($keys as $key) {
            if (isset($payload[$key])) {
                $stringToSign .= "{$key}\n{$payload[$key]}\n";
            }
        }

        return $stringToSign;
    }

    /**
     * Parse a raw MIME message into text/html bodies and attachments.
     */
    protected function parseRawMime(string $rawMime): array
    {
        $result = [
            'bodyText' => null,
            'bodyHtml' => null,
            'attachments' => [],
        ];

        // Simple MIME parsing for common cases
        // For production, consider using a dedicated MIME parser library
        if (preg_match('/Content-Type:\s*multipart\/\w+;\s*boundary="?([^"\s;]+)"?/i', $rawMime, $matches)) {
            $boundary = $matches[1];
            $parts = explode("--{$boundary}", $rawMime);

            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part) || $part === '--') {
                    continue;
                }

                // Separate headers and body
                $segments = preg_split('/\r?\n\r?\n/', $part, 2);
                if (count($segments) < 2) {
                    continue;
                }

                [$partHeaders, $partBody] = $segments;

                $contentType = '';
                if (preg_match('/Content-Type:\s*([^\s;]+)/i', $partHeaders, $ctMatch)) {
                    $contentType = strtolower($ctMatch[1]);
                }

                $isAttachment = (bool) preg_match('/Content-Disposition:\s*attachment/i', $partHeaders);

                if ($isAttachment) {
                    $filename = 'attachment';
                    if (preg_match('/filename="?([^";\r\n]+)"?/i', $partHeaders, $fnMatch)) {
                        $filename = trim($fnMatch[1]);
                    }

                    $encoding = '';
                    if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $partHeaders, $encMatch)) {
                        $encoding = strtolower($encMatch[1]);
                    }

                    $decoded = $this->decodeContent($partBody, $encoding);

                    $result['attachments'][] = [
                        'filename' => $filename,
                        'content' => $decoded,
                        'contentType' => $contentType ?: 'application/octet-stream',
                        'size' => strlen($decoded),
                    ];
                } elseif ($contentType === 'text/plain' && $result['bodyText'] === null) {
                    $encoding = '';
                    if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $partHeaders, $encMatch)) {
                        $encoding = strtolower($encMatch[1]);
                    }
                    $result['bodyText'] = $this->decodeContent($partBody, $encoding);
                } elseif ($contentType === 'text/html' && $result['bodyHtml'] === null) {
                    $encoding = '';
                    if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $partHeaders, $encMatch)) {
                        $encoding = strtolower($encMatch[1]);
                    }
                    $result['bodyHtml'] = $this->decodeContent($partBody, $encoding);
                }
            }
        } else {
            // Single-part message — treat entire content as text body
            $segments = preg_split('/\r?\n\r?\n/', $rawMime, 2);
            if (count($segments) === 2) {
                $result['bodyText'] = trim($segments[1]);
            } else {
                $result['bodyText'] = trim($rawMime);
            }
        }

        return $result;
    }

    /**
     * Decode content based on the Content-Transfer-Encoding.
     */
    protected function decodeContent(string $content, string $encoding): string
    {
        return match ($encoding) {
            'base64' => base64_decode(trim($content), true) ?: $content,
            'quoted-printable' => quoted_printable_decode($content),
            default => $content,
        };
    }

    /**
     * Extract email address from a "Name <email>" formatted string.
     */
    protected function extractEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return $matches[1];
        }

        return trim($from);
    }

    /**
     * Extract display name from a "Name <email>" formatted string.
     */
    protected function extractName(string $from): string
    {
        if (preg_match('/^(.+?)\s*</', $from, $matches)) {
            return trim($matches[1], '" ');
        }

        return '';
    }

    /**
     * Parse SES mail headers into key => value pairs.
     */
    protected function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            if (isset($header['name'], $header['value'])) {
                $parsed[$header['name']] = $header['value'];
            }
        }

        return $parsed;
    }
}
