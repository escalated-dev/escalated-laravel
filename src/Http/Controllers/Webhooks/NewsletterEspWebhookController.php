<?php

namespace Escalated\Laravel\Http\Controllers\Webhooks;

use Escalated\Laravel\Services\Newsletter\NewsletterTrackerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsletterEspWebhookController extends Controller
{
    public function __construct(private readonly NewsletterTrackerService $tracker) {}

    public function postmark(Request $request): mixed
    {
        $type = (string) $request->input('RecordType');
        $token = $this->tokenFromMessageId((string) $request->input('MessageID'));
        match ($type) {
            'Open' => $this->tracker->recordOpen($token),
            'Click' => $this->tracker->recordClick($token, (string) $request->input('OriginalLink', '')),
            'Bounce' => $this->tracker->recordBounce(
                $token,
                $this->isHardBounce($request->input('Type')) ? 'hard' : 'soft',
                (string) $request->input('Description', ''),
            ),
            'SpamComplaint' => $this->tracker->recordComplaint($token),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    public function mailgun(Request $request): mixed
    {
        $event = (string) $request->input('event-data.event');
        $messageId = (string) $request->input('event-data.message.headers.message-id');
        $token = $this->tokenFromMessageId($messageId);
        match ($event) {
            'opened' => $this->tracker->recordOpen($token),
            'clicked' => $this->tracker->recordClick($token, (string) $request->input('event-data.url', '')),
            'failed' => $request->input('event-data.severity') === 'permanent'
                ? $this->tracker->recordBounce($token, 'hard', (string) $request->input('event-data.delivery-status.description', ''))
                : $this->tracker->recordBounce($token, 'soft', (string) $request->input('event-data.delivery-status.description', '')),
            'complained' => $this->tracker->recordComplaint($token),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    public function ses(Request $request): mixed
    {
        $body = $request->input('Message');
        if (is_string($body)) {
            $body = json_decode($body, true);
        }
        $eventType = $body['eventType'] ?? null;
        $messageId = $body['mail']['messageId'] ?? '';
        $token = $this->tokenFromMessageId($messageId);
        match ($eventType) {
            'Open' => $this->tracker->recordOpen($token),
            'Click' => $this->tracker->recordClick($token, $body['click']['link'] ?? ''),
            'Bounce' => ($body['bounce']['bounceType'] ?? '') === 'Permanent'
                ? $this->tracker->recordBounce($token, 'hard', $body['bounce']['bounceSubType'] ?? null)
                : $this->tracker->recordBounce($token, 'soft', $body['bounce']['bounceSubType'] ?? null),
            'Complaint' => $this->tracker->recordComplaint($token),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    public function sendgrid(Request $request): mixed
    {
        foreach ((array) $request->json()->all() as $event) {
            $messageId = $event['smtp-id'] ?? $event['sg_message_id'] ?? '';
            $token = $this->tokenFromMessageId($messageId);
            match ($event['event'] ?? null) {
                'open' => $this->tracker->recordOpen($token),
                'click' => $this->tracker->recordClick($token, $event['url'] ?? ''),
                'bounce' => $this->tracker->recordBounce(
                    $token,
                    ($event['type'] ?? '') === 'blocked' ? 'hard' : 'soft',
                    $event['reason'] ?? null,
                ),
                'dropped' => $this->tracker->recordBounce($token, 'hard', $event['reason'] ?? null),
                'spamreport' => $this->tracker->recordComplaint($token),
                default => null,
            };
        }

        return response()->json(['ok' => true]);
    }

    private function isHardBounce(?string $postmarkType): bool
    {
        return in_array($postmarkType, ['HardBounce', 'BadEmailAddress', 'BlockedRecipient'], true);
    }

    private function tokenFromMessageId(string $messageId): string
    {
        if (preg_match('/n-\d+-([A-Za-z0-9]+)@/', $messageId, $m)) {
            return $m[1];
        }
        if (preg_match('/^n-\d+-([A-Za-z0-9]+)$/', explode('@', $messageId)[0] ?? '', $m)) {
            return $m[1];
        }

        return '';
    }
}
