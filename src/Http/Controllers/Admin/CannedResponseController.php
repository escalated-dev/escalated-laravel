<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Http\Requests\StoreCannedResponseRequest;
use Escalated\Laravel\Models\CannedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class CannedResponseController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/CannedResponses/Index', [
            'responses' => CannedResponse::with('creator')->get(),
        ]);
    }

    public function store(StoreCannedResponseRequest $request): RedirectResponse
    {
        CannedResponse::create(array_merge($request->validated(), [
            'created_by' => $request->user()->getKey(),
        ]));

        return back()->with('success', __('escalated::messages.canned_response.created'));
    }

    public function update(CannedResponse $cannedResponse, StoreCannedResponseRequest $request): RedirectResponse
    {
        $cannedResponse->update($request->validated());

        return back()->with('success', __('escalated::messages.canned_response.updated'));
    }

    public function destroy(CannedResponse $cannedResponse): RedirectResponse
    {
        $cannedResponse->delete();

        return back()->with('success', __('escalated::messages.canned_response.deleted'));
    }
}
