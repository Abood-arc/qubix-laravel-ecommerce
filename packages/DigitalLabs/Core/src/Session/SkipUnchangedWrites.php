<?php

namespace DigitalLabs\Core\Session;

use Closure;
use SessionHandlerInterface;

/**
 * Wraps a session handler so a request that changed nothing in the session doesn't save it back.
 *
 * Laravel saves the whole session at the end of every request. A page fires several requests at once
 * (product lists, categories, the cart), and each of them saves the copy it read when it started. If a
 * shopper adds to the cart meanwhile, a request that started earlier can finish later and save its older
 * copy over the session that now holds the new cart, and the cart is gone. Requests that only read the
 * session stop writing it, so they can't undo another request's change.
 *
 * An unchanged session's expiry is still pushed back, through $keepAlive (Redis EXPIRE, or touching the
 * session file), which can't overwrite anything.
 */
class SkipUnchangedWrites implements SessionHandlerInterface
{
    /**
     * The data each session had when this request read it, by session id.
     *
     * @var array<string, string>
     */
    protected array $readData = [];

    /**
     * @param  (Closure(string): void)|null  $keepAlive  pushes back the expiry of the session with the given id
     */
    public function __construct(
        protected SessionHandlerInterface $handler,
        protected ?Closure $keepAlive = null,
    ) {}

    public function open(string $path, string $name): bool
    {
        return $this->handler->open($path, $name);
    }

    public function close(): bool
    {
        return $this->handler->close();
    }

    public function read(string $id): string|false
    {
        $data = $this->handler->read($id);

        $this->readData[$id] = (string) $data;

        return $data;
    }

    public function write(string $id, string $data): bool
    {
        if (array_key_exists($id, $this->readData) && $this->readData[$id] === $data) {
            if ($this->keepAlive) {
                ($this->keepAlive)($id);
            }

            return true;
        }

        $this->readData[$id] = $data;

        return $this->handler->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        unset($this->readData[$id]);

        return $this->handler->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->handler->gc($max_lifetime);
    }

    /**
     * The handler this one wraps.
     */
    public function getHandler(): SessionHandlerInterface
    {
        return $this->handler;
    }
}
