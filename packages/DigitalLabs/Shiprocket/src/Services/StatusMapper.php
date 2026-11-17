<?php

namespace DigitalLabs\Shiprocket\Services;

use LogicException;

/**
 * Maps a Shiprocket tracking update to a parcel status (Task 7).
 */
class StatusMapper
{
    public function map(array $payload): ?string
    {
        throw new LogicException('Not implemented yet: Task 7');
    }
}
