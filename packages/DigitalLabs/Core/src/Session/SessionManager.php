<?php

namespace DigitalLabs\Core\Session;

use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\SessionManager as BaseSessionManager;

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
            $handler = new SkipUnchangedWrites($handler);
        }

        return parent::buildSession($handler);
    }
}
