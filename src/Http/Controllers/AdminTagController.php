<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Http\Requests\StoreTagRequest;
use Escalated\Laravel\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminTagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Escalated/Admin/Tags/Index', ['tags' => Tag::withCount('tickets')->get()]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create($request->validated());

        return back()->with('success', __('escalated::messages.tag.created'));
    }

    public function update(Tag $tag, StoreTagRequest $request): RedirectResponse
    {
        $tag->update($request->validated());

        return back()->with('success', __('escalated::messages.tag.updated'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return back()->with('success', __('escalated::messages.tag.deleted'));
    }
}
