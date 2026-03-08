<?php

namespace DigitalLabs\Shop\Http\Controllers\Customer\Account;

use DigitalLabs\Shop\Http\Controllers\Controller;

class WishlistController extends Controller
{
    /**
     * Displays the listing resources if the customer having items in wishlist.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (! core()->getConfigData('customer.settings.wishlist.wishlist_option')) {
            abort(404);
        }

        return view('shop::customers.account.wishlist.index');
    }
}
