<?php

/*
 * The new cards' flying photo lands on the header cart icon: the element the mini cart draws as the
 * cart in the header, in both of its forms (the drawer toggle and the plain link to checkout), is
 * marked data-cart-target. Nothing else on the page is.
 */

/**
 * The elements marked data-cart-target in the mini cart's template (a text/x-template script, so its
 * markup is not part of the page's own DOM), and how many the page's own DOM holds.
 *
 * @return array{0: DOMNodeList, 1: int}
 */
function cartTargets(string $html): array
{
    libxml_use_internal_errors(true);

    $page = new DOMDocument;
    $page->loadHTML($html);

    $template = (new DOMXPath($page))->query('//script[@id="v-mini-cart-template"]')->item(0);

    expect($template)->not->toBeNull();

    $markup = new DOMDocument;
    $markup->loadHTML('<body>'.$template->textContent.'</body>');

    return [
        (new DOMXPath($markup))->query('//*[@data-cart-target]'),
        (new DOMXPath($page))->query('//*[@data-cart-target]')->length,
    ];
}

it('marks the header cart toggle of the drawer as where the photo flies to', function () {
    config(['responsecache.enabled' => false]);

    $this->pinStoreConfig('sales.checkout.mini_cart.display_mini_cart', '1');

    [$targets, $outside] = cartTargets($this->get(route('shop.home.index'))->assertOk()->getContent());

    expect($outside)->toBe(0)
        ->and($targets)->toHaveCount(1);

    $toggle = $targets->item(0);

    expect($toggle->nodeName)->toBe('span')
        ->and($toggle->getAttribute('role'))->toBe('button')
        ->and($toggle->getAttribute('class'))->toContain('header-action')
        ->and($toggle->getAttribute('aria-label'))->toBe(trans('shop::app.checkout.cart.mini-cart.shopping-cart'));
});

it('marks the header cart link to checkout as where the photo flies to', function () {
    config(['responsecache.enabled' => false]);

    $this->pinStoreConfig('sales.checkout.mini_cart.display_mini_cart', '0');

    [$targets, $outside] = cartTargets($this->get(route('shop.home.index'))->assertOk()->getContent());

    expect($outside)->toBe(0)
        ->and($targets)->toHaveCount(1);

    $link = $targets->item(0);

    expect($link->nodeName)->toBe('a')
        ->and($link->getAttribute('href'))->toBe(route('shop.checkout.onepage.index'))
        ->and($link->getAttribute('class'))->toContain('header-action');
});
