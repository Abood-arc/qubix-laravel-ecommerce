<?php

use DigitalLabs\Core\Helpers\FriendlyPassword;

/*
 * The generated first-login admin password is emailed as plain text and typed by a human. The old
 * Str::random(20) mixed l/I/1 and O/0, so a client read a capital I as a lowercase l and could not log in.
 */
it('is 8 characters of lowercase letters and digits only', function () {
    expect(FriendlyPassword::generate())->toMatch('/^[a-z0-9]{8}$/');
});

it('never contains a look-alike character (l, i, o, 0, 1), across many samples', function () {
    for ($n = 0; $n < 3000; $n++) {
        expect(FriendlyPassword::generate())->not->toMatch('/[lio01]/');
    }
});

it('always contains at least one letter and at least one digit', function () {
    for ($n = 0; $n < 3000; $n++) {
        $p = FriendlyPassword::generate();

        expect($p)->toMatch('/[a-z]/')->and($p)->toMatch('/[0-9]/');
    }
});

it('is random, not constant', function () {
    $samples = array_map(fn () => FriendlyPassword::generate(), range(1, 500));

    expect(count(array_unique($samples)))->toBeGreaterThan(495);
});

it('honours a custom length', function () {
    expect(strlen(FriendlyPassword::generate(12)))->toBe(12);
});

it('refuses a length too short to hold a letter and a digit', function () {
    FriendlyPassword::generate(1);
})->throws(InvalidArgumentException::class);
