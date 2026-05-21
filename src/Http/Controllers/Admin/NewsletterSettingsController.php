<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsletterSettingsController extends Controller
{
    public const KEYS = [
        'default_from' => 'string',
        'default_reply_to' => 'string',
        'default_theme' => 'string',
        'rate_limit_per_minute' => 'number',
        'batch_size' => 'number',
        'tracking_enabled' => 'boolean',
    ];

    public function __construct(protected EscalatedUiRenderer $ui) {}

    public function show(): mixed
    {
        $settings = [];
        foreach (self::KEYS as $k => $_) {
            $row = EscalatedSettings::where('key', "newsletter.{$k}")->first();
            $settings[$k] = $row?->value ?? config("escalated.newsletters.{$k}");
        }
        $themes = ['default', 'branded'];

        return $this->ui->render('Escalated/Admin/Newsletters/Settings', compact('settings', 'themes'));
    }

    public function update(Request $request): mixed
    {
        $data = $request->validate([
            'default_from' => 'nullable|email',
            'default_reply_to' => 'nullable|email',
            'default_theme' => 'required|string|max:64',
            'rate_limit_per_minute' => 'required|integer|min:1|max:10000',
            'batch_size' => 'required|integer|min:1|max:1000',
            'tracking_enabled' => 'required|boolean',
        ]);
        foreach (self::KEYS as $k => $type) {
            $value = $data[$k] ?? null;
            EscalatedSettings::updateOrCreate(
                ['key' => "newsletter.{$k}"],
                [
                    'value' => is_bool($value) ? (string) (int) $value : (string) ($value ?? ''),
                    'type' => $type,
                    'group' => 'newsletter',
                ],
            );
        }

        return redirect('/admin/newsletters/settings');
    }
}
