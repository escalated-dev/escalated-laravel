<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class CsatSettingsController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Settings/CsatSettings', [
            'settings' => $this->getSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csat_question_text' => ['required', 'string', 'max:500'],
            'csat_scale' => ['required', 'string', 'in:1-3,1-5'],
            'csat_delivery_trigger' => ['required', 'string', 'in:on_resolve,delayed,manual'],
            'csat_delay_hours' => ['required', 'integer', 'min:0', 'max:168'],
        ]);

        foreach ($validated as $key => $value) {
            EscalatedSettings::set($key, (string) $value);
        }

        return redirect()->back()->with('success', 'CSAT settings updated.');
    }

    protected function getSettings(): array
    {
        return [
            'csat_question_text' => EscalatedSettings::get('csat_question_text', 'How would you rate your support experience?'),
            'csat_scale' => EscalatedSettings::get('csat_scale', '1-5'),
            'csat_delivery_trigger' => EscalatedSettings::get('csat_delivery_trigger', 'on_resolve'),
            'csat_delay_hours' => EscalatedSettings::getInt('csat_delay_hours', 0),
        ];
    }
}
