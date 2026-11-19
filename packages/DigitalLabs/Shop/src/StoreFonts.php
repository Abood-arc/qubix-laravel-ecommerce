<?php

namespace DigitalLabs\Shop;

use DigitalLabs\Shop\Enums\StoreLookEnum;

/**
 * The self-hosted fonts a storefront page declares (config shop.fonts): the store look's families,
 * the always families and, on a right-to-left page, the rtl families. Written into the page's <head>
 * by layouts/fonts.
 */
final class StoreFonts
{
    public function __construct(
        private readonly StoreLookEnum $look,
        private readonly bool $rtl,
    ) {}

    /**
     * The fonts of a page in `$look`, in the current locale's direction.
     */
    public static function forPage(StoreLookEnum $look): self
    {
        return new self($look, core()->getCurrentLocale()?->direction === 'rtl');
    }

    /**
     * Every face the page declares: family name, weight (a range for a variable font), file URL and
     * unicode range.
     *
     * @return list<array{family: string, weight: string, url: string, unicode_range: string}>
     */
    public function faces(): array
    {
        $faces = [];

        foreach ($this->families() as $key) {
            $family = config("shop.fonts.families.$key");

            foreach ($family['subsets'] as $subset) {
                foreach ($this->files($key, $subset) as $weight => $url) {
                    $faces[] = [
                        'family' => $family['name'],
                        'weight' => (string) $weight,
                        'url' => $url,
                        'unicode_range' => config("shop.fonts.subsets.$subset"),
                    ];
                }
            }
        }

        return $faces;
    }

    /**
     * The faces worth fetching before the stylesheet asks for them: the main face of each family the
     * page is mostly set in (the look's families, or the rtl ones on a right-to-left page, where
     * app.css sets every element in font-arabic). The main face is the family's first subset at its
     * first weight.
     *
     * @return list<string>
     */
    public function preloads(): array
    {
        $main = $this->rtl ? config('shop.fonts.rtl') : $this->look->fontFamilies();

        return array_map(function (string $key) {
            $subset = config("shop.fonts.families.$key.subsets")[0];

            return array_values($this->files($key, $subset))[0];
        }, $main);
    }

    /**
     * @return list<string>
     */
    private function families(): array
    {
        return array_values(array_unique([
            ...$this->look->fontFamilies(),
            ...config('shop.fonts.always'),
            ...($this->rtl ? config('shop.fonts.rtl') : []),
        ]));
    }

    /**
     * A family's files for one subset, keyed by the weight each one covers.
     *
     * @return array<string|int, string>
     */
    private function files(string $key, string $subset): array
    {
        $weights = config("shop.fonts.families.$key.weights");

        $url = fn (string $file) => asset(config('shop.fonts.path')."/$key/$file.woff2");

        if (is_string($weights)) {
            return [$weights => $url("$key-$subset")];
        }

        $files = [];

        foreach ($weights as $weight) {
            $files[$weight] = $url("$key-$subset-$weight");
        }

        return $files;
    }
}
