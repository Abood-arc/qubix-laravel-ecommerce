<?php

use DigitalLabs\Theme\Facades\Themes;
use DigitalLabs\Theme\ViewRenderEventManager;

if (! function_exists('themes')) {
    /**
     * Themes.
     *
     * @return \DigitalLabs\Theme\Themes
     */
    function themes()
    {
        return Themes::getFacadeRoot();
    }
}

if (! function_exists('qubix_asset')) {
    /**
     * Qubix asset.
     *
     * @return string
     */
    function qubix_asset(string $path, ?string $namespace = null)
    {
        return themes()->url($path, $namespace);
    }
}

if (! function_exists('view_render_event')) {
    /**
     * View render event.
     *
     * @return mixed
     */
    function view_render_event(string $eventName, mixed $params = null)
    {
        return app(ViewRenderEventManager::class)
            ->handleRenderEvent($eventName, $params)
            ->render();
    }
}
