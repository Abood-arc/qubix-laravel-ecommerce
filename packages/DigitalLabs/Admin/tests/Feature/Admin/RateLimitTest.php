<?php

use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\post;

beforeEach(function () {
    RateLimiter::clear('admin-login|127.0.0.1');
});

it('should block admin login after five failed attempts from the same ip', function () {
    foreach (range(1, 5) as $attempt) {
        post(route('admin.session.store'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);
    }

    post(route('admin.session.store'), [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
