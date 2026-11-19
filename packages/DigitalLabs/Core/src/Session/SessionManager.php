<?php

namespace DigitalLabs\Core\Session;

use Closure;
use Illuminate\Cache\RedisStore;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\SessionManager as BaseSessionManager;
use SessionHandlerInterface;

/**
 * Laravel's session manager, with the file and cache (Redis) drivers wrapped in SkipUnchangedWrites.
 *
 * Other drivers stay as they are: the cookie driver needs every response to carry the session, and the
 * database driver tracks whether its row exists through the handler itself.
 */
class SessionManager extends BaseSessionManager
{
    protected function buildSession($handler)
    {
        if ($handler instanceof FileSessionHandler || $handler instanceof CacheBasedSessionHandler) {
            $handler = new SkipUnchangedWrites($handler, $this->keepAlive($handler));
        }

        return parent::buildSession($handler);
    }

    /**
     * How to push back a session's expiry without writing its data: touch its file, or reset its Redis TTL.
     * Other cache stores get none (their unchanged sessions simply aren't rewritten).
     */
    protected function keepAlive(SessionHandlerInterface $handler): ?Closure
    {
        $lifetime = (int) $this->config->get('session.lifetime') * 60;

        if ($handler instanceof FileSessionHandler) {
            $directory = $this->config->get('session.files');

            return static function (string $id) use ($directory): void {
                $path = $directory.'/'.$id;

                if (is_file($path)) {
                    touch($path);
                }
            };
        }

        $store = $handler->getCache()->getStore();

        if ($store instanceof RedisStore) {
            return static fn (string $id) => $store->connection()->expire($store->getPrefix().$id, $lifetime);
        }

        return null;
    }
}
