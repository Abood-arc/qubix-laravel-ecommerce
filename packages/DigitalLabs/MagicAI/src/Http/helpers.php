<?php

use DigitalLabs\MagicAI\Facades\MagicAI;

if (! function_exists('magic_ai')) {
    /**
     * MagicAI helper.
     *
     * @return \DigitalLabs\MagicAI\MagicAI
     */
    function magic_ai()
    {
        return MagicAI::getFacadeRoot();
    }
}
