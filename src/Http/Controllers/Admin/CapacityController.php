<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\AgentCapacity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class CapacityController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $capacities = AgentCapacity::with('user')
            ->orderBy('user_id')
            ->get()
            ->map(function ($cap) {
                return [
                    'id' => $cap->id,
                    'user_id' => $cap->user_id,
                    'agent_name' => $cap->user?->name ?? 'Unknown',
                    'channel' => $cap->channel,
                    'max_concurrent' => $cap->max_concurrent,
                    'current_count' => $cap->current_count,
                    'load_percentage' => $cap->loadPercentage(),
                ];
            });

        return $this->renderer->render('Escalated/Admin/Capacity/Index', [
            'capacities' => $capacities,
        ]);
    }

    public function update(Request $request, AgentCapacity $capacity): RedirectResponse
    {
        $request->validate([
            'max_concurrent' => 'required|integer|min:1|max:999',
        ]);

        $capacity->update([
            'max_concurrent' => $request->integer('max_concurrent'),
        ]);

        return back()->with('success', 'Capacity updated.');
    }
}
