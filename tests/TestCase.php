<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    /**
     * Give every test throwaway disks. The test database is separate, but the disks were the
     * real storage/app folders: deleting a test product or category ran the observers'
     * Storage::deleteDirectory('product/{id}') against the store's own images, and tests left
     * sitemaps, logos and uploads behind.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_keys(config('filesystems.disks')) as $disk) {
            Storage::fake($disk);
        }
    }
}
