<?php

namespace DigitalLabs\Core\Helpers;

use InvalidArgumentException;

/**
 * Generates the one-time first-login password for a newly provisioned client.
 *
 * It is emailed as plain text and typed by a person, so it optimises for being read and typed
 * correctly, not for length: lowercase letters and digits only (no case to get wrong) and none of
 * the glyphs people confuse (l, i, o, 0, 1). The original Str::random(20) mixed l/I/1 and O/0, and
 * the first real client could not log in because a capital I was read as a lowercase l.
 *
 * 31 symbols ^ 8 is ~8.5e11 (~40 bits), which is fine for a temporary password behind the admin
 * login's 5-attempts-per-minute-per-IP throttle; the client is expected to change it after logging
 * in. Randomness comes from random_int (CSPRNG).
 */
class FriendlyPassword
{
    const LETTERS = 'abcdefghjkmnpqrstuvwxyz'; // a-z without i, l, o

    const DIGITS = '23456789'; // without 0, 1

    const DEFAULT_LENGTH = 8;

    /**
     * @throws InvalidArgumentException when the length cannot hold at least one letter and one digit
     */
    public static function generate(int $length = self::DEFAULT_LENGTH): string
    {
        if ($length < 2) {
            throw new InvalidArgumentException('A friendly password needs at least 2 characters (one letter, one digit).');
        }

        $alphabet = static::LETTERS.static::DIGITS;
        $max = strlen($alphabet) - 1;

        // Rejection-sample until it contains both a letter and a digit. For 8 characters the chance
        // of an all-letter draw is ~15%, so this loops rarely and does not skew the distribution
        // within the accepted set.
        do {
            $password = '';

            for ($i = 0; $i < $length; $i++) {
                $password .= $alphabet[random_int(0, $max)];
            }
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[0-9]/', $password));

        return $password;
    }
}
