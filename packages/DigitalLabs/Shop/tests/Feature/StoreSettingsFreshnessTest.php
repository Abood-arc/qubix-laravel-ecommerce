<?php

use DigitalLabs\Core\Repositories\CoreConfigRepository;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

/**
 * A setting saved in admin must show on every page straight away.
 *
 * Before 2026-11-16 settings were read through the repository cache, which kept one copy per
 * page URL for 7 days and forgot most of them on save: on jjbags.in the Store Look changed on
 * the home page while three category pages kept the old one.
 */
const STORE_LOOK = 'general.design.store_look.look';

function saveStoreLookBehindTheAppsBack(string $look): void
{
    // Like a save made by another web worker: no model events reach this process.
    DB::table('core_config')->where('code', STORE_LOOK)->delete();

    foreach (DB::table('channels')->pluck('code') as $channel) {
        DB::table('core_config')->insert([
            'code' => STORE_LOOK,
            'value' => $look,
            'channel_code' => $channel,
            'locale_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function lookOn(string $uri): ?string
{
    preg_match('/data-look="([^"]+)"/', get($uri)->assertOk()->getContent(), $match);

    return $match[1] ?? null;
}

beforeEach(function () {
    config(['responsecache.enabled' => false]);
});

it('keeps the per-URL repository cache switched off', function () {
    expect(config('repository.cache.enabled'))->toBeFalse();

    foreach (config('repository.cache.repositories') as $repository => $settings) {
        expect($settings['enabled'] ?? null)->toBeFalse("{$repository} must not use the per-URL repository cache");
    }
});

it('shows a setting changed by another worker on the next page, whichever page cached it first', function () {
    saveStoreLookBehindTheAppsBack('sand-charcoal');

    expect(lookOn('/'))->toBe('sand-charcoal');

    saveStoreLookBehindTheAppsBack('minimal-white');

    expect(lookOn('/'))->toBe('minimal-white')
        ->and(lookOn('/?page=2'))->toBe('minimal-white');
});

it('shows a setting saved in admin on the very next read in the same process', function () {
    $channel = core()->getDefaultChannelCode();

    core()->getConfigData(STORE_LOOK, $channel);

    app(CoreConfigRepository::class)->create([
        'general' => ['design' => ['store_look' => ['look' => 'marigold-indigo']]],
        'channel' => $channel,
        'locale' => 'en',
    ]);

    expect(core()->getConfigData(STORE_LOOK, $channel))->toBe('marigold-indigo');
});

it('re-reads settings when a queued job starts', function () {
    $channel = core()->getDefaultChannelCode();

    saveStoreLookBehindTheAppsBack('sand-charcoal');

    expect(core()->getConfigData(STORE_LOOK, $channel))->toBe('sand-charcoal');

    saveStoreLookBehindTheAppsBack('minimal-white');

    event(new JobProcessing('redis', Mockery::mock(Job::class, ['payload' => []])));

    expect(core()->getConfigData(STORE_LOOK, $channel))->toBe('minimal-white');
});
