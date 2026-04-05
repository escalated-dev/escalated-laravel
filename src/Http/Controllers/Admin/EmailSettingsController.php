<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Services\EmailChannelService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmailSettingsController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(EmailChannelService $service): mixed
    {
        return $this->renderer->render('Escalated/Admin/Settings/EmailSettings', [
            'addresses' => $service->getAddresses(),
            'defaultReplyAddress' => $service->getDefaultReplyAddress(),
        ]);
    }

    public function update(Request $request, EmailChannelService $service)
    {
        $validated = $request->validate([
            'addresses' => ['required', 'array'],
            'addresses.*.email' => ['required', 'email', 'max:255'],
            'addresses.*.display_name' => ['nullable', 'string', 'max:255'],
            'addresses.*.department_id' => ['nullable', 'integer'],
            'addresses.*.dkim_status' => ['nullable', 'string', 'in:verified,pending,failed,unknown'],
            'default_reply_address' => ['nullable', 'email', 'max:255'],
        ]);

        $service->saveAddresses($validated['addresses']);

        if (isset($validated['default_reply_address'])) {
            $service->setDefaultReplyAddress($validated['default_reply_address']);
        }

        return redirect()->back()->with('success', 'Email settings updated.');
    }
}
