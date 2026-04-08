<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\EscalatedSettings;

class EmailChannelService
{
    /**
     * Get all configured email addresses.
     */
    public function getAddresses(): array
    {
        $json = EscalatedSettings::get('email_addresses', '[]');

        return is_string($json) ? json_decode($json, true) ?? [] : (array) $json;
    }

    /**
     * Validate and save email addresses configuration.
     */
    public function saveAddresses(array $addresses): void
    {
        $validated = [];

        foreach ($addresses as $address) {
            $validated[] = [
                'email' => $address['email'] ?? '',
                'display_name' => $address['display_name'] ?? '',
                'department_id' => $address['department_id'] ?? null,
                'dkim_status' => $address['dkim_status'] ?? 'unknown',
            ];
        }

        EscalatedSettings::set('email_addresses', json_encode($validated));
    }

    /**
     * Get the default reply address.
     */
    public function getDefaultReplyAddress(): string
    {
        return EscalatedSettings::get('default_reply_address', '');
    }

    /**
     * Set the default reply address.
     */
    public function setDefaultReplyAddress(string $email): void
    {
        EscalatedSettings::set('default_reply_address', $email);
    }

    /**
     * Check DKIM status for a domain by querying DNS TXT records.
     *
     * Checks the default DKIM selector "escalated._domainkey.{domain}" and
     * common selectors (default, google, selector1, selector2).
     *
     * @param  string  $domain  The domain to check
     * @return string DKIM status: 'verified', 'pending', 'failed', or 'unknown'
     */
    public function checkDkimStatus(string $domain): string
    {
        $domain = trim(strtolower($domain));
        if ($domain === '') {
            return 'unknown';
        }

        $selectors = ['escalated', 'default', 'google', 'selector1', 'selector2', 'k1'];

        foreach ($selectors as $selector) {
            $dkimDomain = "{$selector}._domainkey.{$domain}";

            try {
                $records = @dns_get_record($dkimDomain, DNS_TXT);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $records || ! is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                $txt = $record['txt'] ?? '';
                if ($this->isDkimRecord($txt)) {
                    return $this->validateDkimRecord($txt);
                }
            }
        }

        return 'pending';
    }

    /**
     * Check if a TXT record looks like a DKIM record.
     */
    protected function isDkimRecord(string $txt): bool
    {
        return str_contains($txt, 'v=DKIM1') || str_contains($txt, 'k=rsa');
    }

    /**
     * Validate a DKIM TXT record's format and key presence.
     */
    protected function validateDkimRecord(string $txt): string
    {
        if (! str_contains($txt, 'v=DKIM1')) {
            return 'failed';
        }

        if (str_contains($txt, 'p=') && ! str_contains($txt, 'p=;') && ! str_contains($txt, 'p= ')) {
            // Has a public key — extract and validate
            if (preg_match('/p=([A-Za-z0-9+\/=]+)/', $txt, $matches)) {
                $keyData = $matches[1];
                if (strlen($keyData) >= 100) {
                    return 'verified';
                }
            }
        }

        return 'failed';
    }
}
