<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Carbon\Carbon;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataRetentionController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    protected array $retentionDaysMap = [
        'never' => null,
        '90_days' => 90,
        '180_days' => 180,
        '365_days' => 365,
        '1_year' => 365,
        '2_years' => 730,
        '3_years' => 1095,
        '5_years' => 1825,
    ];

    public function index()
    {
        return $this->renderer->render('Escalated/Admin/Settings/DataRetention', [
            'settings' => [
                'retention_closed_tickets' => EscalatedSettings::get('retention_closed_tickets', 'never'),
                'retention_attachments' => EscalatedSettings::get('retention_attachments', 'never'),
                'retention_audit_logs' => EscalatedSettings::get('retention_audit_logs', 'never'),
                'retention_user_data_gdpr' => EscalatedSettings::getBool('retention_user_data_gdpr', false),
            ],
            'purgePreview' => $this->getPurgePreview(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'retention_closed_tickets' => ['required', 'string', 'in:never,1_year,2_years,3_years,5_years'],
            'retention_attachments' => ['required', 'string', 'in:never,1_year,2_years,3_years,5_years'],
            'retention_audit_logs' => ['required', 'string', 'in:never,90_days,180_days,365_days'],
            'retention_user_data_gdpr' => ['required', 'boolean'],
        ]);

        foreach ($validated as $key => $value) {
            EscalatedSettings::set($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return redirect()->back()->with('success', 'Data retention settings updated.');
    }

    protected function getPurgePreview(): array
    {
        $preview = [
            'tickets' => 0,
            'attachments' => 0,
            'audit_logs' => 0,
        ];

        try {
            // Closed tickets preview
            $ticketSetting = EscalatedSettings::get('retention_closed_tickets', 'never');
            $ticketDays = $this->retentionDaysMap[$ticketSetting] ?? null;
            if ($ticketDays !== null) {
                $cutoff = Carbon::now()->subDays($ticketDays);
                $preview['tickets'] = DB::table(Escalated::table('tickets'))
                    ->where('status', 'closed')
                    ->where('closed_at', '<', $cutoff)
                    ->whereNull('deleted_at')
                    ->count();
            }

            // Attachments preview
            $attachSetting = EscalatedSettings::get('retention_attachments', 'never');
            $attachDays = $this->retentionDaysMap[$attachSetting] ?? null;
            if ($attachDays !== null && Schema::hasTable(Escalated::table('attachments'))) {
                $cutoff = Carbon::now()->subDays($attachDays);
                $preview['attachments'] = DB::table(Escalated::table('attachments'))
                    ->where('created_at', '<', $cutoff)
                    ->count();
            }

            // Audit logs preview
            $auditSetting = EscalatedSettings::get('retention_audit_logs', 'never');
            $auditDays = $this->retentionDaysMap[$auditSetting] ?? null;
            if ($auditDays !== null && Schema::hasTable(Escalated::table('audit_logs'))) {
                $cutoff = Carbon::now()->subDays($auditDays);
                $preview['audit_logs'] = DB::table(Escalated::table('audit_logs'))
                    ->where('created_at', '<', $cutoff)
                    ->count();
            }
        } catch (\Throwable) {
            // Tables may not exist yet
        }

        return $preview;
    }
}
