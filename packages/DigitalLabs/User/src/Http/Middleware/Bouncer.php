<?php

namespace DigitalLabs\User\Http\Middleware;

use Illuminate\Support\Facades\Route;

class Bouncer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, \Closure $next, $guard = 'admin')
    {
        if (! auth()->guard($guard)->check()) {
            return redirect()->route('admin.session.create');
        }

        /**
         * If user status is changed by admin. Then session should be
         * logged out.
         */
        if (! (bool) auth()->guard($guard)->user()->status) {
            auth()->guard($guard)->logout();

            return redirect()->route('admin.session.create');
        }

        /**
         * If somehow the user deleted all permissions, then it should be
         * auto logged out and need to contact the administrator again.
         */
        if ($this->isPermissionsEmpty()) {
            auth()->guard('admin')->logout();

            session()->flash('error', trans('admin::app.error.403.message'));

            return redirect()->route('admin.session.create');
        }

        return $next($request);
    }

    /**
     * Check for user, if they have empty permissions or not except admin.
     *
     * @return bool
     */
    public function isPermissionsEmpty()
    {
        if (! $role = auth()->guard('admin')->user()->role) {
            abort(401, 'This action is unauthorized.');
        }

        if ($role->permission_type === 'all') {
            return false;
        }

        if (
            $role->permission_type !== 'all'
            && empty($role->permissions)
        ) {
            return true;
        }

        $this->checkIfAuthorized();

        return false;
    }

    /**
     * Check authorization.
     *
     * Read requests stay permissive when a route has no ACL entry, matching
     * the historical behaviour. State-changing requests fail closed instead:
     * an unmapped POST/PUT/PATCH/DELETE is denied rather than silently
     * allowed, so a newly added route cannot reopen the escalation hole.
     */
    public function checkIfAuthorized()
    {
        $roles = acl()->getRoles();

        $routeName = Route::currentRouteName();

        if (isset($roles[$routeName])) {
            bouncer()->allow($roles[$routeName]);

            return;
        }

        if (! request()->isMethodSafe()) {
            abort(403, 'This action is unauthorized.');
        }
    }
}
