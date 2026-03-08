<?php

namespace DigitalLabs\Theme\Exceptions;

class ThemeAlreadyExists extends \Exception
{
    /**
     * Create an instance.
     *
     * @param  \DigitalLabs\Theme\Theme  $theme
     * @return void
     */
    public function __construct($theme)
    {
        parent::__construct("Theme {$theme->name} already exists", 1);
    }
}
