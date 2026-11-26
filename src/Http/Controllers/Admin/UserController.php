<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Role;
use Escalated\Laravel\Support\AccessFeatures;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Surface enough of the host User table for an admin to grant or revoke
 * agent / admin access from the panel. The switches write the `is_admin`
 * and `is_agent` columns the install command tells hosts to add. On a host
 * without those columns (Spatie, a custom pivot, ...) the page still lists
 * users, shows each one's access as the staff gates decide it, and leaves
 * the switches read-only: change access in the host app, or override this
 * controller in your own routes.
 */
class UserController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        $query = Escalated::userQuery();
        $roleColumns = $this->roleColumns($query->getModel());

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => Escalated::applyUserSearch($q, $search));
        }

        foreach (['is_admin', 'is_agent'] as $column) {
            if (in_array($column, $roleColumns, true)) {
                $query->orderByDesc($column);
            }
        }

        $users = $query
            ->orderBy($query->getModel()->getKeyName())
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->getKey(),
                'name' => $this->displayName($user),
                'email' => $user->email,
                // Read from the column when there is one, else from the gates.
                'is_admin' => in_array('is_admin', $roleColumns, true)
                    ? (bool) $user->is_admin
                    : StaffAccess::isAdmin($user),
                'is_agent' => in_array('is_agent', $roleColumns, true)
                    ? (bool) $user->is_agent
                    : StaffAccess::isAgent($user),
            ]);

        $props = [
            'users' => $users,
            'filters' => ['search' => $search ?: ''],
            'currentUserId' => $request->user()?->getKey(),
        ];

        // Without both columns the switches have nothing to write.
        if (count($roleColumns) < 2) {
            $props['roleToggles'] = false;
            $props['notice'] = __('Agent and admin access comes from your application here, so it is shown but cannot be changed on this page.');
        }

        // Escalated roles, only while roles & permissions is enforced: each
        // user's role slugs, and the roles an admin can give them.
        if (AccessFeatures::rolesPermissions()) {
            $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);
            $assigned = Escalated::query(Escalated::table('role_user'))
                ->whereIn('user_id', $users->getCollection()->pluck('id')->all())
                ->get(['user_id', 'role_id'])
                ->groupBy('user_id');
            $slugs = $roles->pluck('slug', 'id');

            $users->through(fn (array $row) => $row + [
                'roles' => collect($assigned->get($row['id'], []))
                    ->map(fn ($pivot) => $slugs[$pivot->role_id] ?? null)
                    ->filter()->sort()->values()->all(),
            ]);
            $props['roles'] = $roles->map(fn (Role $role) => $role->only(['id', 'name', 'slug']))->all();
        }

        return $this->renderer->render('Escalated/Admin/Users/Index', $props);
    }

    /**
     * Replace a user's Escalated roles (roles & permissions on only).
     */
    public function updateRoles(Request $request, int|string $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_ids' => 'present|array',
            'role_ids.*' => 'integer|exists:'.Role::class.',id',
        ]);

        /** @var Model $target */
        $target = Escalated::userQuery()->findOrFail($user);
        $roleIds = array_values(array_unique(array_map('intval', $validated['role_ids'])));

        // Taking away your own role management would leave nobody able to undo it.
        if ((string) $request->user()?->getKey() === (string) $target->getKey()
            && ! Role::whereIn('id', $roleIds)->whereHas('permissions', fn ($q) => $q->where('slug', 'role.manage'))->exists()) {
            return back()->with('error', __('You cannot remove your own role management.'));
        }

        Escalated::query(Escalated::table('role_user'))->where('user_id', $target->getKey())->delete();
        foreach ($roleIds as $roleId) {
            Escalated::query(Escalated::table('role_user'))->insert(['user_id' => $target->getKey(), 'role_id' => $roleId]);
        }

        return back()->with('success', __('User updated.'));
    }

    public function updateRole(Request $request, int|string $user): RedirectResponse
    {
        // These flags are global host permissions, not merchant roles.
        abort_if(app(TenantContext::class)->enabled(), 403, 'Manage merchant roles through the host application.');
        $validated = $request->validate([
            'role' => 'required|in:admin,agent',
            'value' => 'required|boolean',
        ]);

        /** @var Model $target */
        $target = Escalated::userQuery()->findOrFail($user);

        if (count($this->roleColumns($target)) < 2) {
            return back()->with('error', __('Agent and admin access comes from your application here, so it cannot be changed on this page.'));
        }

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

    /** The display name, or null when the user only has an email. */
    protected function displayName(Model $user): ?string
    {
        $name = Escalated::userDisplayName($user);

        return $name === $user->getAttribute('email') ? null : $name;
    }

    /**
     * Which of `is_admin` / `is_agent` the host's users table has.
     *
     * @return list<string>
     */
    protected function roleColumns(Model $model): array
    {
        try {
            $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        } catch (\Throwable) {
            return ['is_admin', 'is_agent'];
        }

        return array_values(array_intersect(['is_admin', 'is_agent'], $columns));
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
