<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Models\EscalatedSettings;

class BounceSuppressionStore
{
    private const KEY = 'newsletter.suppressed_emails';

    public function markBounced(string $email): void
    {
        $this->mark($email);
    }

    public function markComplained(string $email): void
    {
        $this->mark($email);
    }

    public function isBounced(string $email): bool
    {
        return in_array(strtolower($email), $this->load(), true);
    }

    /**
     * @param  array<string>  $emails
     * @return array<string>
     */
    public function filterSendable(array $emails): array
    {
        $suppressed = array_flip($this->load());

        return array_values(array_filter($emails, fn ($e) => ! isset($suppressed[strtolower($e)])));
    }

    private function mark(string $email): void
    {
        $list = $this->load();
        $email = strtolower($email);
        if (! in_array($email, $list, true)) {
            $list[] = $email;
            EscalatedSettings::updateOrCreate(
                ['key' => self::KEY],
                ['value' => json_encode($list), 'type' => 'json', 'group' => 'newsletter'],
            );
        }
    }

    /** @return array<string> */
    private function load(): array
    {
        $row = EscalatedSettings::where('key', self::KEY)->first();
        if (! $row || ! $row->value) {
            return [];
        }
        $decoded = json_decode($row->value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
