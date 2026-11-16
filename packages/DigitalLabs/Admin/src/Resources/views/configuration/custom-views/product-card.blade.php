{{--
    Product card preview (Configuration > General > Design > Product card): a small sketch of each
    card style, so the owner can compare before saving. Inline styles, like the Store Look preview,
    because the admin stylesheet does not carry the storefront's card styles. Forest Green colours;
    the real cards follow the chosen Store look.
--}}
@php
    $cardStyles = ['classic', 'swipe', 'deck'];

    $colour = [
        'tile'   => '#f4efe6',
        'brand'  => '#2f5d48',
        'accent' => '#b5432f',
        'line'   => '#d9d6cc',
        'text'   => '#c9ccc6',
    ];
@endphp

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px; margin-top: 8px;">
    @foreach ($cardStyles as $code)
        <figure
            data-card-preview="{{ $code }}"
            style="margin: 0; overflow: hidden; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff;"
        >
            <div
                aria-hidden="true"
                style="display: grid; gap: 7px; padding: 12px; justify-items: center;"
            >
                <div style="position: relative; width: 100%; height: 132px;">
                    @if ($code === 'deck')
                        <span style="position: absolute; inset: 10px 14px 2px 4px; border-radius: 10px; background: #e7dfcf; transform: translate(10px, -8px) rotate(4deg);"></span>
                        <span style="position: absolute; inset: 10px 14px 2px 4px; border-radius: 10px; background: #eee6d8; transform: translate(5px, -4px) rotate(2deg);"></span>
                        <span style="position: absolute; inset: 10px 14px 2px 4px; border-radius: 10px; background: {{ $colour['tile'] }}; box-shadow: 0 4px 10px rgba(0, 0, 0, .08);"></span>
                        <span style="position: absolute; left: 12px; bottom: 10px; width: 34px; height: 16px; border-radius: 999px; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12);"></span>
                        <span style="position: absolute; right: 20px; bottom: 8px; width: 26px; height: 26px; border-radius: 999px; background: {{ $colour['brand'] }};"></span>
                    @else
                        <span style="position: absolute; inset: 0; border-radius: 10px; background: {{ $colour['tile'] }};"></span>

                        @if ($code === 'swipe')
                            <span style="position: absolute; top: 8px; right: 8px; width: 22px; height: 22px; border-radius: 999px; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12);"></span>
                            <span style="position: absolute; left: 8px; bottom: 16px; width: 54px; height: 14px; border-radius: 999px; background: #fff;"></span>
                            <span style="position: absolute; left: 8px; right: 8px; bottom: 7px; display: flex; gap: 3px;">
                                @foreach (range(1, 4) as $tick)
                                    <span style="flex: 1; height: 3px; border-radius: 2px; background: {{ $tick === 2 ? $colour['brand'] : 'rgba(0, 0, 0, .14)' }};"></span>
                                @endforeach
                            </span>
                        @endif
                    @endif

                    <span style="position: absolute; top: {{ $code === 'deck' ? '16px' : '8px' }}; left: {{ $code === 'deck' ? '12px' : '8px' }}; width: 30px; height: 14px; border-radius: 999px; background: {{ $colour['accent'] }};"></span>
                </div>

                <span style="width: 85%; height: 8px; border-radius: 4px; background: {{ $colour['text'] }};"></span>
                <span style="width: 55%; height: 8px; border-radius: 4px; background: {{ $colour['text'] }};"></span>

                @if ($code === 'swipe')
                    <span style="width: 45%; height: 6px; border-radius: 3px; background: {{ $colour['line'] }};"></span>
                @endif

                @if ($code === 'deck')
                    <span style="display: flex; width: 100%; align-items: center; gap: 4px;">
                        <span style="width: 34px; height: 10px; margin-inline-end: auto; border-radius: 4px; background: {{ $colour['brand'] }};"></span>
                        <span style="width: 16px; height: 16px; border-radius: 999px; border: 1px solid {{ $colour['line'] }};"></span>
                        <span style="width: 16px; height: 16px; border-radius: 999px; border: 1px solid {{ $colour['line'] }};"></span>
                    </span>
                @else
                    <span style="width: 34px; height: 10px; border-radius: 4px; background: {{ $colour['brand'] }};"></span>
                    <span style="box-sizing: border-box; width: 100%; height: 24px; border-radius: 999px; border: 1.5px solid {{ $colour['brand'] }};"></span>
                @endif

                @if ($code === 'swipe')
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <span style="width: 11px; height: 11px; border-radius: 3px; border: 1.5px solid #9b9e98;"></span>
                        <span style="width: 40px; height: 6px; border-radius: 3px; background: {{ $colour['line'] }};"></span>
                    </span>
                @endif
            </div>

            <figcaption style="padding: 8px 12px; border-top: 1px solid #f1f1f1; font-size: 13px; font-weight: 600; color: #374151;">
                @lang('admin::app.configuration.index.general.design.product-card.'.$code)
            </figcaption>
        </figure>
    @endforeach
</div>
