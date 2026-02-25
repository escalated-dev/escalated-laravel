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
     * Check DKIM status for a domain.
     *
     * @param  string  $domain  The domain to check
     * @return string  TODO: Returns DKIM status ('verified', 'pending', 'failed', 'unknown')
     */
    public function checkDkimStatus(string $domain): string
    {
        // TODO: Implement DKIM status check
        // This would typically:
        // 1. Query DNS for DKIM TXT records
        // 2. Validate the record format and key
        // 3. Return the status
        return 'unknown';
    }
}
