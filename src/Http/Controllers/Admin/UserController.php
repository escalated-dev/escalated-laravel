<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Surface enough of the host User table for an admin to grant or revoke
 * agent / admin access from the panel. The default install pins this to
 * the `is_admin` and `is_agent` columns the install command tells hosts
 * to add — hosts using a different role implementation (Spatie, custom
 * pivot, etc.) should override this controller in their own routes.
 */
class UserController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        $userClass = Escalated::userModel();
        $query = $userClass::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('email', 'like', $term);
                if ($this->columnExists($q->getModel(), 'name')) {
                    $q->orWhere('name', 'like', $term);
                }
            });
        }

        $users = $query
            ->orderByDesc('is_admin')
            ->orderByDesc('is_agent')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->getKey(),
                'name' => $user->name ?? null,
                'email' => $user->email,
                'is_admin' => (bool) ($user->is_admin ?? false),
                'is_agent' => (bool) ($user->is_agent ?? false),
            ]);

        return $this->renderer->render('Escalated/Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search ?: ''],
            'currentUserId' => $request->user()?->getKey(),
        ]);
    }

    public function updateRole(Request $request, int|string $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,agent',
            'value' => 'required|boolean',
        ]);

        $userClass = Escalated::userModel();
        /** @var Model $target */
        $target = $userClass::query()->findOrFail($user);

        // Don't let an admin demote themselves and lock themselves out of
        // the admin panel they're trying to use.
        if ($validated['role'] === 'admin'
            && ! $validated['value']
            && $request->user()
            && (string) $request->user()->getKey() === (string) $target->getKey()) {
            return back()->with('error', __('You cannot remove your own admin role.'));
        }

        $updates = [];
        if ($validated['role'] === 'admin') {
            $updates['is_admin'] = $validated['value'];
            // Admins are agents; flipping admin off does not also revoke agent
            // (an ex-admin can still answer tickets unless explicitly demoted).
            if ($validated['value']) {
                $updates['is_agent'] = true;
            }
        } else {
            $updates['is_agent'] = $validated['value'];
            if (! $validated['value'] && ($target->is_admin ?? false)) {
                // Revoking agent from an admin would leave the admin gate on
                // but the agent gate off — confusing. Demote them fully.
                $updates['is_admin'] = false;
            }
        }

        $target->forceFill($updates)->save();

        return back()->with('success', __('User updated.'));
    }

    protected function columnExists(Model $model, string $column): bool
    {
        try {
            return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return true;
        }
    }
}
