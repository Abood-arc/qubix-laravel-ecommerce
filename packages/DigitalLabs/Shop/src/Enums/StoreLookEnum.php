<?php

namespace DigitalLabs\Shop\Enums;

/**
 * The store look: Admin > Configuration > General > Design > Store look. Colours, fonts and corners
 * only, through data-look on <html> and the tokens in app.css.
 */
enum StoreLookEnum: string
{
    case FOREST_GREEN = 'forest-green';

    case MARIGOLD_INDIGO = 'marigold-indigo';

    case SAND_CHARCOAL = 'sand-charcoal';

    case MINIMAL_WHITE = 'minimal-white';

    public const CONFIG_KEY = 'general.design.store_look.look';

    /**
     * The admin's live preview (shop.card-preview): the query parameter that names the look, and the
     * height of the preview window, the store header above two cards at a phone's width.
     */
    public const PREVIEW_PARAMETER = 'look';

    public const PREVIEW_HEIGHT = 620;

    /**
     * Its previews show the store header too, where a look shows most.
     */
    public const PREVIEW_HEADER = true;

    /**
     * The look saved for the current channel (or `$channelCode`). Forest Green when nothing (or an
     * unknown value) is saved.
     */
    public static function saved(?string $channelCode = null): self
    {
        return self::tryFrom((string) core()->getConfigData(self::CONFIG_KEY, $channelCode)) ?? self::FOREST_GREEN;
    }

    public static function current(): self
    {
        return self::saved();
    }

    /**
     * This look's Google fonts, added to the storefront's one font request. Forest Green uses Karla,
     * which every page already loads.
     */
    public function fontFamilies(): string
    {
        return match ($this) {
            self::FOREST_GREEN => '',
            self::MARIGOLD_INDIGO => '&family=Baloo+2:wght@500;600;700;800&family=Mukta:wght@400;500;600;700;800',
            self::SAND_CHARCOAL => '&family=Archivo:wght@400;500;600;700;800',
            self::MINIMAL_WHITE => '&family=Manrope:wght@400;500;600;700;800',
        };
    }

    /**
     * The storefront's font stylesheet with this look's fonts.
     */
    public function fontsUrl(): string
    {
        return 'https://fonts.googleapis.com/css2?family=Karla:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=DM+Serif+Display&family=Noto+Sans+Arabic:wght@400;500;600;700'.$this->fontFamilies().'&display=swap';
    }
}
