<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront chatbot widget (Zanderio)
    |--------------------------------------------------------------------------
    |
    | Empty (the default) loads NO third-party script. A widget id belongs to one
    | client's chatbot account, so it must never be shared by default: this used to
    | be hardcoded in the layout, which showed the first client's (JJ Bags) chatbot
    | on every store built from this code and sent each visitor's browser to a
    | third party.
    |
    | Set per deployment in .env: SHOP_CHATBOT_WIDGET_ID=wdg_...
    |
    | The two legacy stacks (jjbags.in, jj-bags.com) are frozen on the `abood`
    | branch, which still hardcodes the original id. If they are ever migrated onto
    | `fleet`, set their existing id here or their widget disappears.
    |
    */

    'widget_id' => env('SHOP_CHATBOT_WIDGET_ID', ''),

    'loader_url' => env('SHOP_CHATBOT_LOADER_URL', 'https://cdn.zanderio.ai/widget/loader.js'),

];
