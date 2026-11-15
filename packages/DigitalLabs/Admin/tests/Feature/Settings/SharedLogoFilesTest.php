<?php

use DigitalLabs\Core\Models\Channel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;
use function Pest\Laravel\putJson;

/*
 * jjbags.in, 2026-11-15: the admin logo setting held the same file path as the store logo.
 * Uploading a new admin logo deleted that shared file and the storefront header logo broke.
 * A replaced image must only be deleted once nothing else points at it.
 */

function setAdminLogo(string $path): void
{
    DB::table('core_config')->where('code', 'general.design.admin_logo.logo_image')->delete();

    DB::table('core_config')->insert([
        'code' => 'general.design.admin_logo.logo_image',
        'value' => $path,
        'channel_code' => null,
        'locale_code' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function uploadAdminLogo(): void
{
    post(route('admin.configuration.store', ['slug' => 'general', 'slug2' => 'design']), [
        'channel' => core()->getDefaultChannelCode(),
        'locale' => 'en',
        'general' => [
            'design' => [
                'admin_logo' => [
                    'logo_image' => UploadedFile::fake()->image('new-admin-logo.png'),
                ],
            ],
        ],
    ])->assertRedirect();
}

function adminLogoPath(): ?string
{
    return DB::table('core_config')->where('code', 'general.design.admin_logo.logo_image')->value('value');
}

it('keeps the store logo file when a new admin logo replaces a setting that shared it', function () {
    $channel = Channel::query()->where('code', core()->getDefaultChannelCode())->firstOrFail();

    Storage::put('channel/'.$channel->id.'/shared-logo.png', 'logo bytes');

    DB::table('channels')->where('id', $channel->id)->update(['logo' => 'channel/'.$channel->id.'/shared-logo.png']);

    setAdminLogo('channel/'.$channel->id.'/shared-logo.png');

    $this->loginAsAdmin();

    uploadAdminLogo();

    expect(adminLogoPath())->toStartWith('configuration/')
        ->and(Storage::exists(adminLogoPath()))->toBeTrue()
        ->and(Storage::exists('channel/'.$channel->id.'/shared-logo.png'))->toBeTrue();
});

it('still deletes the old admin logo file when nothing else uses it', function () {
    Storage::put('configuration/old-admin-logo.png', 'old bytes');

    setAdminLogo('configuration/old-admin-logo.png');

    $this->loginAsAdmin();

    uploadAdminLogo();

    expect(adminLogoPath())->not->toBe('configuration/old-admin-logo.png')
        ->and(Storage::exists('configuration/old-admin-logo.png'))->toBeFalse();
});

it('keeps the admin logo file when a new store logo replaces a channel logo that shared it', function () {
    $channel = Channel::factory()->create();

    Storage::put('channel/'.$channel->id.'/shared-logo.png', 'logo bytes');

    DB::table('channels')->where('id', $channel->id)->update(['logo' => 'channel/'.$channel->id.'/shared-logo.png']);

    setAdminLogo('channel/'.$channel->id.'/shared-logo.png');

    $this->loginAsAdmin();

    putJson(route('admin.settings.channels.update', $channel->id), [
        'code' => $channel->code,
        app()->getLocale() => [
            'name' => 'Channel with a shared logo',
            'seo_title' => 'Title',
            'seo_description' => 'Description',
            'seo_keywords' => 'keywords',
            'description' => 'Description',
        ],
        'hostname' => 'http://'.fake()->ipv4(),
        'root_category_id' => 1,
        'default_locale_id' => 1,
        'base_currency_id' => 1,
        'inventory_sources' => [1],
        'locales' => [1],
        'currencies' => [1],
        'logo' => [UploadedFile::fake()->image('new-store-logo.png')],
    ])->assertRedirect(route('admin.settings.channels.index'));

    $newLogo = DB::table('channels')->where('id', $channel->id)->value('logo');

    expect($newLogo)->not->toBe('channel/'.$channel->id.'/shared-logo.png')
        ->and(Storage::exists($newLogo))->toBeTrue()
        ->and(Storage::exists('channel/'.$channel->id.'/shared-logo.png'))->toBeTrue();
});
