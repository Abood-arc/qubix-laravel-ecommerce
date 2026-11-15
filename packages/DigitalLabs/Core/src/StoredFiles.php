<?php

namespace DigitalLabs\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an uploaded image only once nothing points at it any more.
 *
 * The store logo, the admin logo, the favicons and the locale flags are separate settings,
 * but two of them can hold the same file path (jjbags.in's admin logo was set to the
 * channel's logo file). Replacing one used to delete the shared file and break the other,
 * so callers update their own reference first and then hand the old path to this.
 */
class StoredFiles
{
    public static function deleteIfUnused(?string $path): void
    {
        if (blank($path) || static::isInUse($path)) {
            return;
        }

        Storage::delete($path);
    }

    public static function isInUse(string $path): bool
    {
        return DB::table('channels')->where('logo', $path)->orWhere('favicon', $path)->exists()
            || DB::table('core_config')->where('value', $path)->exists()
            || DB::table('locales')->where('logo_path', $path)->exists();
    }
}
