<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Http\Requests\StoreSavedViewRequest;
use Escalated\Laravel\Models\SavedView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SavedViewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $views = SavedView::forUser($request->user()->getKey())
            ->orderBy('position')
            ->get();

        return response()->json($views);
    }

    public function store(StoreSavedViewRequest $request): JsonResponse
    {
        $maxPosition = SavedView::forUser($request->user()->getKey())->max('position') ?? 0;

        $view = SavedView::create([
            ...$request->validated(),
            'user_id' => $request->user()->getKey(),
            'position' => $maxPosition + 1,
        ]);

        return response()->json($view, 201);
    }

    public function update(SavedView $savedView, StoreSavedViewRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($savedView->user_id !== null && $savedView->user_id !== $user->getKey()) {
            abort(403);
        }

        $savedView->update($request->validated());

        return response()->json($savedView);
    }

    public function destroy(SavedView $savedView, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($savedView->user_id !== null && $savedView->user_id !== $user->getKey()) {
            abort(403);
        }

        $savedView->delete();

        return response()->json(['message' => 'View deleted.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($request->input('ids') as $position => $id) {
            SavedView::where('id', $id)
                ->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user()->getKey())
                        ->orWhere('is_shared', true);
                })
                ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Views reordered.']);
    }
}
