<?php

it('should map every route in an array-valued acl entry to the same permission key', function () {
    config()->set('acl', [
        [
            'key' => 'settings.users.edit',
            'name' => 'admin::app.acl.edit',
            'route' => ['admin.settings.users.edit', 'admin.settings.users.update'],
            'sort' => 1,
        ],
    ]);

    // Reset the static caches inside Acl so the new config is read.
    $acl = new \DigitalLabs\Core\Acl;

    $roles = $acl->getRoles();

    expect($roles['admin.settings.users.edit'])->toBe('settings.users.edit')
        ->and($roles['admin.settings.users.update'])->toBe('settings.users.edit');
});

use DigitalLabs\User\Models\Admin as AdminModel;
use DigitalLabs\User\Models\Role;

use function Pest\Laravel\putJson;

it('should forbid a restricted admin from updating a role', function () {
    $restrictedRole = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions' => ['dashboard'],
    ]);

    $restrictedAdmin = AdminModel::factory()->create([
        'role_id' => $restrictedRole->id,
    ]);

    $this->actingAs($restrictedAdmin, 'admin');

    putJson(route('admin.settings.roles.update', $restrictedRole->id), [
        'name' => 'Escalated',
        'description' => 'Escalated',
        'permission_type' => 'all',
    ])->assertUnauthorized();

    expect($restrictedRole->fresh()->permission_type)->toBe('custom');
});

it('should deny a restricted admin on an unmapped mutating admin route', function () {
    $restrictedRole = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions' => ['dashboard'],
    ]);

    $this->actingAs(AdminModel::factory()->create(['role_id' => $restrictedRole->id]), 'admin');

    \Illuminate\Support\Facades\Route::put('admin/__acl_probe', fn () => 'reached')
        ->name('admin.__acl_probe')
        ->middleware(['web', 'admin']);

    putJson('/admin/__acl_probe')->assertForbidden();
});

it('should not treat an array-valued route as a nested acl item when building the permission tree', function () {
    config()->set('acl', [
        [
            'key' => 'catalog',
            'name' => 'admin::app.acl.catalog',
            'route' => ['admin.catalog.products.index', 'admin.catalog.products.update'],
            'sort' => 1,
        ],
    ]);

    $acl = new \DigitalLabs\Core\Acl;

    $items = $acl->getItems();

    expect($items)->toHaveCount(1)
        ->and($items->first()->children)->toHaveCount(0);
});
