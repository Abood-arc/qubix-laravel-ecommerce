{{--
    Store Look preview (Configuration > General > Design > Store Look): a small sample of each
    look's header, menu row, button and type, so the owner can compare before saving. Inline
    styles, because the admin's own stylesheet does not carry the storefront's look colours;
    the values mirror the tokens in the Shop package's app.css.
--}}
@php
    $looks = [
        'forest-green'    => ['head' => '#2f5d48', 'ink' => '#ffffff', 'nav' => '#2f5d48', 'navInk' => '#ffffff', 'field' => '#ffffff', 'brand' => '#2f5d48', 'tile' => '#f4efe6', 'accent' => '#b5432f', 'font' => 'Karla', 'radius' => '999px', 'case' => 'none'],
        'marigold-indigo' => ['head' => '#f2ac1a', 'ink' => '#1c2a6b', 'nav' => '#f2ac1a', 'navInk' => '#1c2a6b', 'field' => '#fff8e6', 'brand' => '#1c2a6b', 'tile' => '#fbf3df', 'accent' => '#c7302a', 'font' => 'Baloo 2', 'radius' => '999px', 'case' => 'none'],
        'sand-charcoal'   => ['head' => '#c9a46a', 'ink' => '#1d1a15', 'nav' => '#1d1a15', 'navInk' => '#f1e2c2', 'field' => '#efe0bf', 'brand' => '#1d1a15', 'tile' => '#f1e6cf', 'accent' => '#a8322a', 'font' => 'Archivo', 'radius' => '4px', 'case' => 'uppercase'],
        'minimal-white'   => ['head' => '#ffffff', 'ink' => '#111111', 'nav' => '#ffffff', 'navInk' => '#111111', 'field' => '#f3f3f1', 'brand' => '#111111', 'tile' => '#f4f4f2', 'accent' => '#111111', 'font' => 'Manrope', 'radius' => '10px', 'case' => 'none'],
    ];
@endphp

<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Karla:wght@700;800&family=Baloo+2:wght@700;800&family=Archivo:wght@700;800&family=Manrope:wght@700;800&display=swap"
>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-top: 8px;">
    @foreach ($looks as $code => $look)
        <figure style="margin: 0; overflow: hidden; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff;">
            <div style="background: {{ $look['head'] }}; padding: 10px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(0,0,0,.08);">
                <span style="width: 22px; height: 22px; border-radius: 999px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.15); flex: none;"></span>
                <span style="flex: 1; height: 18px; border-radius: {{ $look['radius'] }}; background: {{ $look['field'] }};"></span>
                <span style="width: 14px; height: 14px; border-radius: 3px; border: 2px solid {{ $look['ink'] }}; flex: none;"></span>
            </div>

            <div style="background: {{ $look['nav'] }}; color: {{ $look['navInk'] }}; padding: 6px 10px; font: 700 11px/1.4 '{{ $look['font'] }}', sans-serif; text-transform: {{ $look['case'] }}; letter-spacing: {{ $look['case'] === 'uppercase' ? '.06em' : '0' }};">
                New in &nbsp; Shop all &nbsp; Offers
            </div>

            <div style="padding: 12px; display: grid; gap: 10px;">
                <div style="display: flex; gap: 8px; align-items: end;">
                    <span style="position: relative; width: 56px; height: 56px; border-radius: 8px; background: {{ $look['tile'] }}; flex: none;">
                        <span style="position: absolute; top: 4px; left: 4px; padding: 1px 5px; border-radius: 999px; background: {{ $look['accent'] }}; color: #fff; font: 700 8px/1.4 sans-serif;">Sale</span>
                    </span>

                    <span style="font: 800 20px/1.1 '{{ $look['font'] }}', sans-serif; color: {{ $look['brand'] }};">Aa</span>
                </div>

                <span style="justify-self: start; padding: 6px 14px; border-radius: {{ $look['radius'] }}; background: {{ $look['brand'] }}; color: #fff; font: 700 12px/1.4 '{{ $look['font'] }}', sans-serif;">
                    Add to cart
                </span>
            </div>

            <figcaption style="padding: 8px 12px; border-top: 1px solid #f1f1f1; font-size: 13px; font-weight: 600; color: #374151;">
                @lang('admin::app.configuration.index.general.design.store-look.'.$code)
            </figcaption>
        </figure>
    @endforeach
</div>
