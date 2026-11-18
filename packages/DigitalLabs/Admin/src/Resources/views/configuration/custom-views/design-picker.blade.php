{{--
    Configuration > General > Design: the Product card and Store look pickers. One radio per option,
    under the field's own name, so the configuration save is unchanged. Each option shows the real
    storefront in a small window (the Shop package's card-preview page: the store's own products, in that
    style or look, playing a short demo). The previews are pictures only: they cannot be focused or
    clicked (inert), so keyboard and screen reader users choose with the radios, and a click anywhere
    on an option picks it. A Store look preview shows the card style picked above it, and a Product card
    preview the look picked above, saved or not, so the owner sees the combination they are about to save.
    Styles go to the page head (styles stack), outside the Vue template, which would drop a <style>.
--}}
@php
    $isLook = $field->getName() === 'look';

    $lang = 'admin::app.configuration.index.general.design.'.($isLook ? 'store-look' : 'product-card');

    $saved = fn (string $code, string $default) => system_config()->getConfigData($code, $currentChannel->code, $currentLocale->code) ?: $default;

    $value = $saved($field->getNameKey(), (string) $field->getDefault());

    $savedStyle = $saved('general.design.product_card.style', 'classic');

    $savedLook = $saved('general.design.store_look.look', 'forest-green');

    $previewUrl = fn (string $option) => route('shop.card-preview', $isLook
        ? ['style' => $savedStyle, 'look' => $option, 'header' => 1]
        : ['style' => $option, 'look' => $savedLook]);

    $id = 'design-picker-'.$field->getName();
@endphp

<input
    type="hidden"
    name="keys[]"
    value="{{ json_encode($child) }}"
/>

<fieldset
    class="dp-picker mb-4 last:!mb-0"
    data-design-picker="{{ $isLook ? 'look' : 'style' }}"
    aria-describedby="{{ $id }}-help"
>
    <legend class="mb-1.5 flex items-center gap-1 text-xs font-medium text-gray-800 dark:text-white">
        {{ trans($field->getTitle()) }}

        <span class="rounded border border-gray-200 bg-gray-100 px-1 py-0.5 text-[10px] font-semibold leading-normal text-gray-600">
            {{ $currentChannel->name }}
        </span>
    </legend>

    <div class="dp-options">
        @foreach ($field->getOptions() as $option)
            <label class="dp-option">
                <input
                    type="radio"
                    class="dp-radio"
                    name="{{ $field->getNameField() }}"
                    value="{{ $option['value'] }}"
                    aria-labelledby="{{ $id }}-{{ $option['value'] }}"
                    aria-describedby="{{ $id }}-{{ $option['value'] }}-info"
                    @checked($value === $option['value'])
                >

                <span
                    class="dp-frame"
                    style="--dp-height: {{ $isLook ? 620 : 400 }}"
                >
                    <iframe
                        src="{{ $previewUrl($option['value']) }}"
                        title="{{ trans($lang.'.preview', ['name' => $option['title']]) }}"
                        loading="lazy"
                        sandbox="allow-scripts allow-same-origin"
                        referrerpolicy="same-origin"
                        tabindex="-1"
                        inert
                    ></iframe>
                </span>

                <span class="dp-text">
                    <span
                        class="dp-name"
                        id="{{ $id }}-{{ $option['value'] }}"
                    >
                        {{ $option['title'] }}
                    </span>

                    <span
                        class="dp-check"
                        aria-hidden="true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            width="14"
                            height="14"
                        >
                            <path
                                d="M5 12.5l4.5 4.5L19 7.5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>

                    <span
                        class="dp-info"
                        id="{{ $id }}-{{ $option['value'] }}-info"
                    >
                        @lang($lang.'.'.$option['value'].'-info')
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    <p
        class="dp-help mt-2 text-xs leading-[140%] text-gray-600 dark:text-gray-300"
        id="{{ $id }}-help"
    >
        {{ trans($field->getInfo()) }}
    </p>
</fieldset>

@pushOnce('styles')
    <style>
        /* The previews are the storefront at a phone's width (390px), shrunk to fit (--dp-scale, set below). */
        .dp-picker {
            --dp-accent: #2563eb;
            --dp-line: #e5e7eb;
            --dp-surface: #fff;
            --dp-ink: #1f2937;
            --dp-muted: #4b5563;

            min-width: 0;
            margin-inline: 0;
            padding: 0;
            border: 0;
            container-type: inline-size;
        }

        .dark .dp-picker {
            --dp-accent: #60a5fa;
            --dp-line: #374151;
            --dp-surface: #111827;
            --dp-ink: #f9fafb;
            --dp-muted: #d1d5db;
        }

        .dp-options {
            --dp-columns: 3;

            display: grid;
            grid-template-columns: repeat(var(--dp-columns), minmax(0, 1fr));
            gap: 12px;
        }

        [data-design-picker="look"] .dp-options {
            --dp-columns: 4;
        }

        @container (max-width: 640px) {
            [data-design-picker="look"] .dp-options {
                --dp-columns: 2;
            }
        }

        @container (max-width: 480px) {
            .dp-options,
            [data-design-picker="look"] .dp-options {
                --dp-columns: 1;
            }
        }

        .dp-option {
            position: relative;
            display: grid;
            grid-template-rows: auto 1fr;
            overflow: hidden;
            border: 1px solid var(--dp-line);
            border-radius: 10px;
            background: var(--dp-surface);
            cursor: pointer;
            transition: border-color 150ms, box-shadow 150ms;
        }

        .dp-option:hover {
            border-color: #9ca3af;
        }

        .dp-option:has(.dp-radio:checked) {
            border-color: var(--dp-accent);
            box-shadow: 0 0 0 2px var(--dp-accent);
        }

        .dp-option:has(.dp-radio:focus-visible) {
            outline: 2px solid var(--dp-accent);
            outline-offset: 4px;
        }

        /*
         * Invisible, still the control keyboards and screen readers use. It sits on top of a corner of the
         * preview, big enough to take a click itself (a click anywhere else on the option reaches it through
         * the label).
         */
        .dp-radio {
            position: absolute;
            z-index: 1;
            inset-block-start: 0;
            inset-inline-start: 0;
            width: 24px;
            height: 24px;
            margin: 0;
            opacity: 0;
            cursor: pointer;
        }

        .dp-frame {
            position: relative;
            display: block;
            aspect-ratio: 390 / var(--dp-height);
            overflow: hidden;
            border-bottom: 1px solid var(--dp-line);
            background: #fff;
        }

        .dp-frame iframe {
            position: absolute;
            inset-block-start: 0;
            inset-inline-start: 0;
            width: 390px;
            height: calc(var(--dp-height) * 1px);
            border: 0;
            transform: scale(var(--dp-scale, 0.5));
            transform-origin: top left;
            pointer-events: none;
        }

        [dir="rtl"] .dp-frame iframe {
            transform-origin: top right;
        }

        .dp-text {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 2px 8px;
            padding: 10px 12px;
        }

        .dp-info {
            grid-column: 1 / -1;
        }

        .dp-name {
            color: var(--dp-ink);
            font-size: 14px;
            font-weight: 600;
        }

        .dp-info {
            color: var(--dp-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .dp-check {
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            border: 1.5px solid var(--dp-line);
            border-radius: 50%;
            background: var(--dp-surface);
            color: transparent;
        }

        .dp-option:has(.dp-radio:checked) .dp-check {
            border-color: var(--dp-accent);
            background: var(--dp-accent);
            color: #fff;
        }

        @media (prefers-reduced-motion: reduce) {
            .dp-option {
                transition: none;
            }
        }
    </style>
@endPushOnce

@pushOnce('scripts')
    <script>
        (() => {
            /*
             * Shrink each preview to its window: the storefront at 390px wide, scaled to the window's width.
             */
            const fit = (frame) => frame.style.setProperty('--dp-scale', frame.clientWidth / 390);

            /*
             * The other picker's previews follow the choice made here (saved or not): a Store look preview
             * shows the card style picked, a Product card preview the look picked.
             */
            const follow = (event) => {
                const radio = event.target;

                const picker = radio.closest?.('[data-design-picker]');

                if (! picker || radio.type !== 'radio') {
                    return;
                }

                const choice = picker.dataset.designPicker;

                document.querySelectorAll(`[data-design-picker]:not([data-design-picker="${choice}"]) iframe`).forEach((preview) => {
                    const url = new URL(preview.src);

                    url.searchParams.set(choice, radio.value);

                    preview.src = url.toString();
                });
            };

            // After the admin's Vue app has mounted (on load), which renders the page's markup again.
            window.addEventListener('load', () => setTimeout(() => {
                const resizes = new ResizeObserver((entries) => entries.forEach((entry) => fit(entry.target)));

                document.querySelectorAll('.dp-frame').forEach((frame) => {
                    fit(frame);

                    resizes.observe(frame);
                });

                document.addEventListener('change', follow);
            }));
        })();
    </script>
@endPushOnce
