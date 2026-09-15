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
     * Registered as a container singleton (see FPCServiceProvider), with reset()
     * wired to the app's terminating callback rather than relying on container
     * lifetime: those coincide in production (a fresh container per PHP-FPM
     * request), but not under Pest, where one test method's container can span
     * several actual HTTP-kernel requests (each get()/postJson() call runs the
     * full kernel, including terminate()) — relying on container lifetime alone
     * would let an earlier request's clear (e.g. a factory's afterCreating side
     * effect) silently suppress a later, unrelated one in the same test.
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
