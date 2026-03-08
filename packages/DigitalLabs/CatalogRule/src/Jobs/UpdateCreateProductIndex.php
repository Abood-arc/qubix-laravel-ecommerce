<?php

namespace DigitalLabs\CatalogRule\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DigitalLabs\CatalogRule\Helpers\CatalogRuleIndex;

class UpdateCreateProductIndex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  \DigitalLabs\Product\Contracts\Product  $product
     * @return void
     */
    public function __construct(protected $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app(CatalogRuleIndex::class)->reIndexProduct($this->product);
    }
}
