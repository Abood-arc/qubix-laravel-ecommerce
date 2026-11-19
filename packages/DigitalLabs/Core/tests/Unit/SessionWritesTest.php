<?php

use DigitalLabs\Core\Session\SkipUnchangedWrites;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\FileSessionHandler;

/*
 * A request that only read the session must not save it back: a page's requests run at once, and an older
 * copy saved last would undo another request's change (a guest's new cart, for one).
 */

/**
 * A handler that keeps sessions in memory and counts the writes that reach it.
 */
function countingSessionHandler(array $sessions = []): SessionHandlerInterface
{
    return new class($sessions) extends ArraySessionHandler
    {
        public int $writes = 0;

        public function __construct(array $sessions)
        {
            parent::__construct(120);

            foreach ($sessions as $id => $data) {
                parent::write($id, $data);
            }
        }

        public function write($sessionId, $data): bool
        {
            $this->writes++;

            return parent::write($sessionId, $data);
        }
    };
}

it('does not save a session the request read and left as it was', function () {
    $inner = countingSessionHandler(['abc' => 'cart|i:7;']);
    $inner->writes = 0;

    $handler = new SkipUnchangedWrites($inner);

    expect($handler->read('abc'))->toBe('cart|i:7;');
    expect($handler->write('abc', 'cart|i:7;'))->toBeTrue();

    expect($inner->writes)->toBe(0);
});

it('saves a session the request changed', function () {
    $inner = countingSessionHandler(['abc' => '']);
    $inner->writes = 0;

    $handler = new SkipUnchangedWrites($inner);

    $handler->read('abc');
    $handler->write('abc', 'cart|i:7;');

    expect($inner->writes)->toBe(1);
    expect($inner->read('abc'))->toBe('cart|i:7;');
});

it('saves a new session it never read, and a session written again after a change', function () {
    $inner = countingSessionHandler();

    $handler = new SkipUnchangedWrites($inner);

    $handler->write('new', 'a|i:1;');
    $handler->write('new', 'a|i:2;');
    $handler->write('new', 'a|i:2;');

    expect($inner->writes)->toBe(2);
});

it('wraps the file and cache drivers, and leaves the others alone', function () {
    $handlerOf = fn (string $driver) => app('session')->driver($driver)->getHandler();

    expect($handlerOf('file'))->toBeInstanceOf(SkipUnchangedWrites::class)
        ->and($handlerOf('file')->getHandler())->toBeInstanceOf(FileSessionHandler::class);

    config(['session.store' => 'array']);

    expect($handlerOf('redis'))->toBeInstanceOf(SkipUnchangedWrites::class)
        ->and($handlerOf('redis')->getHandler())->toBeInstanceOf(CacheBasedSessionHandler::class);

    expect($handlerOf('array'))->toBeInstanceOf(ArraySessionHandler::class);
});
