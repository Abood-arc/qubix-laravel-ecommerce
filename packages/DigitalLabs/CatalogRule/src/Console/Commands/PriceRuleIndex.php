<?php

namespace DigitalLabs\CatalogRule\Console\Commands;

use DigitalLabs\CatalogRule\Helpers\CatalogRuleIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class PriceRuleIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:price-rule:index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically updates catalog rule price index information (eg. rule_price)';

    /**
     * Create a new command instance.
     *
     * @param  \DigitalLabs\CatalogRuleProduct\Helpers\CatalogRuleIndex  $catalogRuleIndexHelper
     * @return void
     */
    public function __construct(protected CatalogRuleIndex $catalogRuleIndexHelper)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! $this->catalogRuleIndexHelper->reIndexComplete()) {
            // reIndexComplete() already reported the underlying exception.
            // Skip the event on failure: FPC's listener on it clears the
            // whole response cache, and doing that after a half-finished
            // reindex would serve freshly-cached pages built from stale or
            // incomplete prices instead of just the pre-existing cached
            // ones — worse than leaving the cache alone until a future
            // successful run clears it for real. Non-zero exit so a failed
            // run shows up as failed to the scheduler and any monitoring on
            // top of it, rather than looking identical to success.
            $this->error('Price rule reindex failed — response cache was not cleared. See the logged exception for details.');

            return self::FAILURE;
        }

        Event::dispatch('catalog.price_rule.reindex.after');

        return self::SUCCESS;
    }
}
