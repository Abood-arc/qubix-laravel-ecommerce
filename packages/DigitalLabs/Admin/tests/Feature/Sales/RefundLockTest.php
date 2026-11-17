<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

use function Pest\Laravel\post;

/*
 * A double-clicked Refund button sends two requests. Refunds for one order run one at a
 * time, so a second request that cannot get the order's refund lock is turned away.
 */

it('turns away a refund while another refund for the same order is still running', function () {
    $this->loginAsAdmin();

    $orderId = 987654;

    $running = Cache::lock('admin-refund:order:'.$orderId, 60);

    expect($running->get())->toBeTrue();

    // Waiting for the lock advances the clock instead of really sleeping.
    Sleep::fake(syncWithCarbon: true);

    post(route('admin.sales.refunds.store', $orderId), ['refund' => ['items' => []]])
        ->assertRedirect()
        ->assertSessionHas('error', trans('admin::app.sales.refunds.create.in-progress'));

    $running->release();
});
