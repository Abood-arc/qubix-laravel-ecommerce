<?php

it('should map every route in an array-valued acl entry to the same permission key', function () {
    config()->set('acl', [
        [
            'key'   => 'settings.users.edit',
            'name'  => 'admin::app.acl.edit',
            'route' => ['admin.settings.users.edit', 'admin.settings.users.update'],
            'sort'  => 1,
        ],
    ]);

    // Reset the static caches inside Acl so the new config is read.
    $acl = new \DigitalLabs\Core\Acl;

    $roles = $acl->getRoles();

    expect($roles['admin.settings.users.edit'])->toBe('settings.users.edit')
        ->and($roles['admin.settings.users.update'])->toBe('settings.users.edit');
});
