<?php

namespace DigitalLabs\DataTransfer\Repositories;

use DigitalLabs\Core\Eloquent\Repository;
use DigitalLabs\DataTransfer\Contracts\ImportBatch;

class ImportBatchRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return ImportBatch::class;
    }
}
