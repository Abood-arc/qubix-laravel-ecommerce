<?php

namespace DigitalLabs\FPC\Support;

use Spatie\ResponseCache\Facades\ResponseCache;

class CacheClearer
{
    /**
     * Whether ResponseCache::clear() has already run for this request.
     */
    protected bool $cleared = false;

    /**
     * Clear the response cache at most once per request.
     *
     * ResponseCache::clear() is a blanket, parameterless wipe, so calling it more
     * than once in a request is pure redundancy, never a correctness requirement
     * — but it isn't free: some admin actions (e.g. invoicing an order with
     * several non-stockable, quantity-tracked line items) dispatch
     * catalog.product.update.after once per item, which would otherwise mean
     * that many synchronous full-cache-directory wipes in a single request.
     *
     * Registered as a scoped container binding (see FPCServiceProvider), with
     * reset() also wired to the app's terminating callback. Two different
     * lifecycles need two different reset mechanisms:
     *   - A queue:work process (docker-compose.prod.yml's `queue` service)
     *     never rebuilds its container between jobs. Laravel's queue Worker
     *     calls Container::forgetScopedInstances() after every job precisely
     *     to reset bindings like this one — a plain singleton would be
     *     invisible to that and stay "already cleared" for the worker's
     *     entire lifetime after the first trigger.
     *   - Nothing calls forgetScopedInstances() for an HTTP request, a
     *     console command, or between Pest's simulated requests within one
     *     test method (each get()/postJson() call runs the full kernel,
     *     including terminate(), while sharing one container) — that's what
     *     the terminating() callback resets instead.
     */
    public function clearOnce(): void
    {
        if ($this->cleared) {
            return;
        }

        $this->cleared = true;

        ResponseCache::clear();
    }

    /**
     * Allow the next clearOnce() call to actually clear again. Called from the
     * app's terminating callback (see FPCServiceProvider) at the end of every
     * request/console-kernel lifecycle.
     */
    public function reset(): void
    {
        $this->cleared = false;
    }
}
