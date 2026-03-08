<?php

namespace DigitalLabs\Admin\Listeners;

class Admin
{
    /**
     * Send mail on updating password.
     *
     * @param  \DigitalLabs\User\Models\Admin  $admin
     * @return void
     */
    public function afterPasswordUpdated($admin) {}
}
