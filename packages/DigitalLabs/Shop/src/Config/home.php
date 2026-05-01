<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront home layout
    |--------------------------------------------------------------------------
    |
    | legacy — current behavior: full theme customization loop (carousel, static
    | HTML, category/product carousels, etc.).
    |
    | v2 — hero image carousels still render from theme customizations; blocks
    | below the hero that are normally driven by static/category/product
    | customizations are skipped so the v2 shell can be customized in code/DB
    | later without removing existing admin data.
    |
    | Set in .env: SHOP_HOME_LAYOUT=legacy|v2
    |
    */

    'layout_mode' => env('SHOP_HOME_LAYOUT', 'legacy'),

];
