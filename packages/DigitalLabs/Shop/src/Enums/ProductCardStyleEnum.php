<?php

namespace DigitalLabs\Shop\Enums;

/**
 * How product cards look in grids: Admin > Configuration > General > Design > Product card.
 * List view is the same for every style.
 */
enum ProductCardStyleEnum: string
{
    /**
     * Today's card. The default.
     */
    case CLASSIC = 'classic';

    /**
     * "Swipe, named and sized" (design concept G).
     */
    case SWIPE = 'swipe';

    /**
     * "Photo deck" (design concept E).
     */
    case DECK = 'deck';

    public const CONFIG_KEY = 'general.design.product_card.style';

    /**
     * Pages that always show the Classic card (owner decision, 2026-11-16): the cart page, whose
     * cross-sell row uses the card, and the compare page.
     */
    public const CLASSIC_ONLY_ROUTES = ['shop.checkout.cart.index', 'shop.compare.index'];

    /**
     * The request attribute that carries the style an admin's live preview shows (CardPreviewController),
     * for that one response only.
     */
    public const PREVIEW_ATTRIBUTE = 'product_card_preview_style';

    /**
     * The admin's live preview (shop.card-preview): the query parameter that names the style, and the
     * height of the preview window, two cards at a phone's width.
     */
    public const PREVIEW_PARAMETER = 'style';

    public const PREVIEW_HEIGHT = 400;

    /**
     * Its previews show the cards only, without the store header.
     */
    public const PREVIEW_HEADER = false;

    /**
     * The style saved for the current channel (or `$channelCode`). Classic when nothing (or an
     * unknown value) is saved.
     */
    public static function saved(?string $channelCode = null): self
    {
        return self::tryFrom((string) core()->getConfigData(self::CONFIG_KEY, $channelCode)) ?? self::CLASSIC;
    }

    public static function current(): self
    {
        return self::saved();
    }

    /**
     * The style for the page being rendered. The card template is pushed once per page, so this is
     * a page-level choice: the previewed style on an admin's live preview, Classic on the Classic-only
     * pages, otherwise the saved style.
     */
    public static function forPage(): self
    {
        $preview = request()->attributes->get(self::PREVIEW_ATTRIBUTE);

        if ($preview instanceof self) {
            return $preview;
        }

        return request()->routeIs(...self::CLASSIC_ONLY_ROUTES) ? self::CLASSIC : self::current();
    }

    public function isClassic(): bool
    {
        return $this === self::CLASSIC;
    }
}
