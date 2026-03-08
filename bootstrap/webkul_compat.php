<?php

declare(strict_types=1);

if (! function_exists('registerWebkulCompatibilityAutoloader')) {
    /**
     * Bridge legacy Webkul namespaces used by external packages
     * to renamed DigitalLabs classes.
     */
    function registerWebkulCompatibilityAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (! str_starts_with($class, 'Webkul\\')) {
                return;
            }

            if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                return;
            }

            $digitalLabsClass = 'DigitalLabs\\' . substr($class, strlen('Webkul\\'));

            if (! (
                class_exists($digitalLabsClass)
                || interface_exists($digitalLabsClass)
                || trait_exists($digitalLabsClass)
            )) {
                return;
            }

            class_alias($digitalLabsClass, $class);
        }, true, true);
    }
}

registerWebkulCompatibilityAutoloader();
