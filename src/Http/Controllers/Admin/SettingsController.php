<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Support\SecretMask;
use Escalated\Laravel\Support\WidgetOrigins;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Settings', [
            'settings' => $this->getSettings(),
            // Exactly the keys update() saves. The shared Settings page hides
            // the controls this host does not store (live chat and widget
            // appearance live elsewhere here) instead of discarding them.
            'supported_settings' => array_keys($this->rules()),
        ]);
    }

    /**
     * The settings this page saves.
     */
    protected function rules(): array
    {
        return [
            'guest_tickets_enabled' => ['required', 'boolean'],
            'allow_customer_close' => ['required', 'boolean'],
            'auto_close_resolved_after_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_attachments_per_reply' => ['required', 'integer', 'min:1', 'max:20'],
            'max_attachment_size_kb' => ['required', 'integer', 'min:512', 'max:102400'],
            'ticket_reference_prefix' => ['required', 'string', 'max:10', 'alpha_num'],
            'show_powered_by' => ['sometimes', 'boolean'],
            'inbound_email_enabled' => ['sometimes', 'boolean'],
            'inbound_email_adapter' => ['sometimes', 'string', 'in:mailgun,postmark,ses,imap'],
            'inbound_email_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mailgun_signing_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postmark_inbound_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ses_region' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ses_topic_arn' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['sometimes', 'nullable', 'string', 'in:ssl,tls,none'],
            'imap_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_mailbox' => ['sometimes', 'nullable', 'string', 'max:255'],
            'knowledge_base_enabled' => ['sometimes', 'boolean'],
            'knowledge_base_public' => ['sometimes', 'boolean'],
            'knowledge_base_feedback_enabled' => ['sometimes', 'boolean'],
            'widget_enabled' => ['sometimes', 'boolean'],
            'widget_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'widget_position' => ['sometimes', 'string', 'in:bottom-right,bottom-left'],
            'widget_greeting' => ['sometimes', 'nullable', 'string', 'max:255'],
            'widget_departments' => ['sometimes', 'nullable', 'string', 'max:1000', 'regex:/^[0-9,\s]*$/'],
            'widget_allowed_origins' => ['sometimes', 'nullable', 'string', 'max:5000', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
                    if (WidgetOrigins::normalizePattern($entry) === null) {
                        $fail(__('escalated::messages.settings.invalid_widget_origin', ['origin' => $entry]));
                    }
                }
            }],
        ];
    }

    public function update(Request $request)
    {
        $validated = $request->validate($this->rules());

        $sensitiveKeys = ['mailgun_signing_key', 'postmark_inbound_token', 'imap_password'];

        foreach ($validated as $key => $value) {
            // Skip sensitive fields that still contain the masked placeholder
            if (in_array($key, $sensitiveKeys, true) && $this->isMaskedValue($value)) {
                continue;
            }

            EscalatedSettings::set($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return redirect()->back()->with('success', __('escalated::messages.settings.updated'));
    }

    protected function getSettings(): array
    {
        return [
            'guest_tickets_enabled' => EscalatedSettings::getBool('guest_tickets_enabled', true),
            'allow_customer_close' => EscalatedSettings::getBool('allow_customer_close', true),
            'auto_close_resolved_after_days' => EscalatedSettings::getInt('auto_close_resolved_after_days', 7),
            'max_attachments_per_reply' => EscalatedSettings::getInt('max_attachments_per_reply', 5),
            'max_attachment_size_kb' => EscalatedSettings::getInt('max_attachment_size_kb', 10240),
            'ticket_reference_prefix' => EscalatedSettings::get('ticket_reference_prefix', 'ESC'),
            'show_powered_by' => EscalatedSettings::getBool('show_powered_by', true),
            'inbound_email_enabled' => EscalatedSettings::getBool('inbound_email_enabled', (bool) config('escalated.inbound_email.enabled', false)),
            'inbound_email_adapter' => EscalatedSettings::get('inbound_email_adapter', config('escalated.inbound_email.adapter', 'mailgun')),
            'inbound_email_address' => EscalatedSettings::get('inbound_email_address', config('escalated.inbound_email.address', '')),
            'mailgun_signing_key' => $this->maskSecret(EscalatedSettings::get('mailgun_signing_key', config('escalated.inbound_email.mailgun.signing_key', ''))),
            'postmark_inbound_token' => $this->maskSecret(EscalatedSettings::get('postmark_inbound_token', config('escalated.inbound_email.postmark.token', ''))),
            'ses_region' => EscalatedSettings::get('ses_region', config('escalated.inbound_email.ses.region', 'us-east-1')),
            'ses_topic_arn' => EscalatedSettings::get('ses_topic_arn', config('escalated.inbound_email.ses.topic_arn', '')),
            'imap_host' => EscalatedSettings::get('imap_host', config('escalated.inbound_email.imap.host', '')),
            'imap_port' => EscalatedSettings::getInt('imap_port', (int) config('escalated.inbound_email.imap.port', 993)),
            'imap_encryption' => EscalatedSettings::get('imap_encryption', config('escalated.inbound_email.imap.encryption', 'ssl')),
            'imap_username' => EscalatedSettings::get('imap_username', config('escalated.inbound_email.imap.username', '')),
            'imap_password' => $this->maskSecret(EscalatedSettings::get('imap_password', config('escalated.inbound_email.imap.password', ''))),
            'imap_mailbox' => EscalatedSettings::get('imap_mailbox', config('escalated.inbound_email.imap.mailbox', 'INBOX')),
            'knowledge_base_enabled' => EscalatedSettings::getBool('knowledge_base_enabled', true),
            'knowledge_base_public' => EscalatedSettings::getBool('knowledge_base_public', true),
            'knowledge_base_feedback_enabled' => EscalatedSettings::getBool('knowledge_base_feedback_enabled', true),
            'widget_enabled' => EscalatedSettings::getBool('widget_enabled', false),
            'widget_color' => EscalatedSettings::get('widget_color', '#4F46E5'),
            'widget_position' => EscalatedSettings::get('widget_position', 'bottom-right'),
            'widget_greeting' => EscalatedSettings::get('widget_greeting', 'Hi there! How can we help?'),
            'widget_departments' => EscalatedSettings::get('widget_departments', ''),
            'widget_allowed_origins' => EscalatedSettings::get('widget_allowed_origins', ''),
        ];
    }

    /**
     * Check if a value looks like a masked placeholder (contains only * after optional prefix).
     */
    protected function isMaskedValue(?string $value): bool
    {
        return SecretMask::isMasked($value);
    }

    /**
     * Mask a secret value for display: shows whether it is set, and nothing
     * of the value itself.
     */
    protected function maskSecret(?string $value): string
    {
        return SecretMask::mask($value);
    }
}
