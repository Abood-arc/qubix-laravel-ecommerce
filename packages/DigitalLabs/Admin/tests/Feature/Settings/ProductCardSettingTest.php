<?php

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Configuration > General > Design > Product card: the card style for grids, chosen by clicking a live
 * preview of each style (v-design-picker: a radio per style, no dropdown), and the unit of the size
 * line. The Store look is chosen the same way. The pickers are Vue components: their radios and preview
 * windows are rendered in the browser (Playwright: card-styles/previews.spec.ts), from the props checked here.
 */

function designPage(): string
{
    return get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'design']))
        ->assertOk()
        ->getContent();
}

/**
 * The opening tag of the picker that chooses `$parameter` ("style" or "look").
 */
function designPicker(string $html, string $parameter): string
{
    preg_match('/<v-design-picker\b[^>]*\sparameter="'.$parameter.'"[^>]*>/s', $html, $tag);

    expect($tag)->not->toBeEmpty("No {$parameter} picker on the page");

    return $tag[0];
}

/**
 * A JSON prop of a picker tag (:options="JSON.parse('…')", as Js::from() writes it).
 */
function designPickerProp(string $tag, string $prop): mixed
{
    preg_match('/:'.$prop.'="JSON\.parse\(\'(.*?)\'\)"/s', $tag, $value);

    expect($value)->not->toBeEmpty("The picker has no :{$prop}");

    return json_decode(json_decode('"'.$value[1].'"'), true);
}

it('offers the three card styles as a radio picker with live previews, after Store Look, and no dropdown', function () {
    $this->loginAsAdmin();

    $html = designPage();

    $picker = designPicker($html, 'style');

    expect(strpos($html, trans('admin::app.configuration.index.general.design.store-look.title')))
        ->toBeLessThan(strpos($html, trans('admin::app.configuration.index.general.design.product-card.title')))
        ->and($picker)
        ->toContain('name="general[design][product_card][style]"')
        ->toContain('preview-url="/card-preview"')
        ->toContain(':with-header="false"')
        ->toContain(':height="400"')
        ->and(array_column(designPickerProp($picker, 'options'), 'value'))->toBe(['classic', 'swipe', 'deck'])
        ->and(designPickerProp($picker, 'choices'))->toBe(['style' => 'classic', 'look' => 'forest-green'])
        ->and($html)->not->toContain('<select name="general[design][product_card][style]"')
        ->toContain('name="general[design][product_card][size_unit]"');
});

it('offers the four store looks the same way, previewed with the store header and the saved card style', function () {
    $this->loginAsAdmin();

    $picker = designPicker(designPage(), 'look');

    expect($picker)
        ->toContain('name="general[design][store_look][look]"')
        ->toContain(':with-header="true"')
        ->toContain(':height="620"')
        ->and(array_column(designPickerProp($picker, 'options'), 'value'))
        ->toBe(['forest-green', 'marigold-indigo', 'sand-charcoal', 'minimal-white'])
        ->and(designPickerProp($picker, 'choices'))->toBe(['style' => 'classic', 'look' => 'forest-green']);
});

it('hands the channel name to the picker as a prop, never as template text', function () {
    $this->loginAsAdmin();

    DB::table('channel_translations')->update(['name' => 'Shop {{ 7 * 7 }}']);

    preg_match_all('#<v-design-picker\b.*?</v-design-picker>#s', designPage(), $pickers);

    expect($pickers[0])->toHaveCount(2);

    foreach ($pickers[0] as $picker) {
        // Once, as an attribute value (Vue binds it as text); nowhere as a text node it would compile.
        expect(substr_count($picker, '{{ 7 * 7 }}'))->toBe(1)
            ->and($picker)->toContain('channel="Shop {{ 7 * 7 }}"');
    }
});

it('saves the chosen style and size unit for the channel', function () {
    $this->loginAsAdmin();

    $channel = core()->getDefaultChannelCode();

    post(route('admin.configuration.store', ['slug' => 'general', 'slug2' => 'design']), [
        'channel' => $channel,
        'locale' => 'en',
        'general' => [
            'design' => [
                'product_card' => [
                    'style' => 'swipe',
                    'size_unit' => 'cm',
                ],
            ],
        ],
    ])->assertRedirect();

    $saved = fn (string $code) => DB::table('core_config')
        ->where('code', $code)
        ->where('channel_code', $channel)
        ->value('value');

    expect($saved('general.design.product_card.style'))->toBe('swipe')
        ->and($saved('general.design.product_card.size_unit'))->toBe('cm');
});

it('names and describes each option in the admin language', function () {
    $this->loginAsAdmin();

    app()->setLocale('ar');

    $html = designPage();

    $deck = collect(designPickerProp(designPicker($html, 'style'), 'options'))->firstWhere('value', 'deck');

    $marigold = collect(designPickerProp(designPicker($html, 'look'), 'options'))->firstWhere('value', 'marigold-indigo');

    expect($deck)->toBe([
        'value' => 'deck',
        'title' => 'رزمة صور',
        'info' => 'الصور في كومة صغيرة، مع زر دائري للإضافة إلى السلة تحتها.',
        'previewTitle' => 'معاينة حية: رزمة صور',
    ])
        ->and($marigold['info'])->toBe('رأس أصفر ساطع ونص نيلي.')
        ->and(designPicker($html, 'style'))->toContain('title="شكل البطاقة"');
});
