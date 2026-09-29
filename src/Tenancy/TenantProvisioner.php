<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Database\Seeders\PermissionSeeder;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\EscalatedSettings;
use LogicException;

class TenantProvisioner
{
    public function provision(string $tenant): void
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            throw new LogicException('Enable tenancy before provisioning an account.');
        }
        $context->run($tenant, fn () => Escalated::db()->transaction(function () {
            // Never copy credentials or customized settings from another tenant.
            foreach ([
                'guest_tickets_enabled' => '1', 'allow_customer_close' => '1',
                'auto_close_resolved_after_days' => '7', 'max_attachments_per_reply' => '5',
                'max_attachment_size_kb' => '10240', 'ticket_reference_prefix' => 'ESC',
                'show_powered_by' => '1', 'knowledge_base_enabled' => '1',
                'knowledge_base_public' => '1', 'knowledge_base_feedback_enabled' => '1',
                'email_logo_url' => null, 'email_accent_color' => '#2d3748', 'email_footer_text' => null,
            ] as $key => $value) {
                EscalatedSettings::firstOrCreate(['key' => $key], ['value' => $value]);
            }
            app(PermissionSeeder::class)->run();
        }));
    }
}
