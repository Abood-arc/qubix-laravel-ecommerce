{{--
    Configuration > General > Design: the Product card and Store look pickers (v-design-picker). One radio
    per option, under the field's own name, so the configuration save is unchanged. Each option shows the
    real storefront in a small window: the Shop package's card-preview page, with the store's own
    products in that style or look, playing a short demo. The windows are pictures only: they cannot be
    focused or clicked (inert), so keyboard and screen reader users choose with the radios, and a click
    anywhere on an option picks it.

    The pickers are a Vue component, so their windows exist once, from the moment the admin app mounts
    (markup the app renders again on mount would load every window twice), and a choice made then stays.
    Each picker previews its options with the other picker's current choice, saved or not, so the owner
    sees the combination they are about to save: the pickers tell each other on the event bus.

    What a picker previews comes from the setting's enum (ProductCardStyleEnum, StoreLookEnum): its
    config key, the preview's query parameter, height and whether it shows the store header. Every text
    reaches the template as a prop and is bound as text, never compiled as template.
--}}
@php
    $settings = collect([
        \DigitalLabs\Shop\Enums\ProductCardStyleEnum::class,
        \DigitalLabs\Shop\Enums\StoreLookEnum::class,
    ]);

    $setting = $settings->first(fn (string $enum) => $enum::CONFIG_KEY === $field->getNameKey());

    // Every picker starts from the saved style and look.
    $choices = $settings->mapWithKeys(fn (string $enum) => [
        $enum::PREVIEW_PARAMETER => $enum::saved($currentChannel->code)->value,
    ]);

    // The option texts sit next to the field's title in the lang file (….product-card.swipe-info).
    $lang = \Illuminate\Support\Str::beforeLast($field->getTitle(), '.');

    $options = collect($field->getOptions())->map(fn (array $option) => [
        'value' => $option['value'],
        'title' => $option['title'],
        'info' => trans($lang.'.'.$option['value'].'-info'),
        'previewTitle' => trans($lang.'.preview', ['name' => $option['title']]),
    ]);
@endphp

<input
    type="hidden"
    name="keys[]"
    value="{{ json_encode($child) }}"
/>

<div class="mb-4 last:!mb-0">
    <v-design-picker
        name="{{ $field->getNameField() }}"
        parameter="{{ $setting::PREVIEW_PARAMETER }}"
        :choices="{{ Js::from($choices) }}"
        :options="{{ Js::from($options) }}"
        preview-url="{{ route('shop.card-preview', [], false) }}"
        :with-header="{{ Js::from($setting::PREVIEW_HEADER) }}"
        :height="{{ Js::from($setting::PREVIEW_HEIGHT) }}"
        title="{{ trans($field->getTitle()) }}"
        channel="{{ $currentChannel->name }}"
        help="{{ trans($field->getInfo()) }}"
    >
        <div class="shimmer mb-1.5 h-4 w-24"></div>

        <div class="shimmer flex h-[42px] w-full rounded-md"></div>
    </v-design-picker>
</div>

@pushOnce('styles')
    <style>
        /* The previews are the storefront at a phone's width (390px), shrunk to fit (--dp-scale, set by the component). */
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
            display: grid;
            grid-template-columns: repeat(var(--dp-columns), minmax(0, 1fr));
            gap: 12px;
        }

        @container (max-width: 640px) {
            .dp-options[data-columns="4"] {
                --dp-columns: 2;
            }
        }

        @container (max-width: 480px) {
            .dp-options[data-columns] {
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

        .dp-name {
            color: var(--dp-ink);
            font-size: 14px;
            font-weight: 600;
        }

        .dp-info {
            grid-column: 1 / -1;
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
    <script
        type="text/x-template"
        id="v-design-picker-template"
    >
        <fieldset
            class="dp-picker"
            :aria-describedby="`${id}-help`"
        >
            <legend class="mb-1.5 flex items-center gap-1 text-xs font-medium text-gray-800 dark:text-white">
                <span v-text="title"></span>

                <span
                    class="rounded border border-gray-200 bg-gray-100 px-1 py-0.5 text-[10px] font-semibold leading-normal text-gray-600"
                    v-text="channel"
                ></span>
            </legend>

            <div
                class="dp-options"
                :data-columns="options.length"
                :style="{ '--dp-columns': options.length }"
            >
                <label
                    class="dp-option"
                    v-for="option in options"
                    :key="option.value"
                >
                    <input
                        type="radio"
                        class="dp-radio"
                        :name="name"
                        :value="option.value"
                        :aria-labelledby="`${id}-${option.value}`"
                        :aria-describedby="`${id}-${option.value}-info`"
                        v-model="chosen"
                    >

                    <span
                        class="dp-frame"
                        :style="{ '--dp-height': height }"
                        ref="frames"
                    >
                        <iframe
                            :src="previewSrc(option.value)"
                            :title="option.previewTitle"
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
                            :id="`${id}-${option.value}`"
                            v-text="option.title"
                        ></span>

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
                            :id="`${id}-${option.value}-info`"
                            v-text="option.info"
                        ></span>
                    </span>
                </label>
            </div>

            <p
                class="dp-help mt-2 text-xs leading-[140%] text-gray-600 dark:text-gray-300"
                :id="`${id}-help`"
                v-text="help"
            ></p>
        </fieldset>
    </script>

    <script type="module">
        /*
         * The pickers' current choices, saved or not, as { style, look }: each picker announces its own on the
         * event bus, and every picker previews with all of them.
         */
        const CHOSEN = 'design-picker-chosen';

        app.component('v-design-picker', {
            template: '#v-design-picker-template',

            props: {
                // The config field's form name: what the radios post.
                name: { type: String, required: true },

                // The preview's query parameter this picker chooses: "style" or "look".
                parameter: { type: String, required: true },

                // The saved choice of every picker, by parameter.
                choices: { type: Object, required: true },

                // [{ value, title, info, previewTitle }]
                options: { type: Array, required: true },

                previewUrl: { type: String, required: true },

                withHeader: { type: Boolean, default: false },

                // The preview's height at 390px wide.
                height: { type: Number, required: true },

                title: { type: String, required: true },

                channel: { type: String, default: '' },

                help: { type: String, default: '' },
            },

            data() {
                return {
                    current: { ...this.choices },
                };
            },

            computed: {
                id() {
                    return `design-picker-${this.parameter}`;
                },

                chosen: {
                    get() {
                        return this.current[this.parameter];
                    },

                    set(value) {
                        this.$emitter.emit(CHOSEN, { parameter: this.parameter, value });
                    },
                },
            },

            created() {
                this.onChosen = ({ parameter, value }) => (this.current[parameter] = value);

                this.$emitter.on(CHOSEN, this.onChosen);
            },

            mounted() {
                // Shrink each preview to its window: the storefront at 390px wide, scaled to the window's width.
                this.resizes = new ResizeObserver((entries) => entries.forEach(({ target }) => {
                    target.style.setProperty('--dp-scale', target.clientWidth / 390);
                }));

                this.$refs.frames.forEach((frame) => this.resizes.observe(frame));
            },

            unmounted() {
                this.resizes.disconnect();

                this.$emitter.off(CHOSEN, this.onChosen);
            },

            methods: {
                /**
                 * The preview of `value`: this picker's option with the other pickers' current choices.
                 */
                previewSrc(value) {
                    const query = new URLSearchParams({ ...this.current, [this.parameter]: value });

                    if (this.withHeader) {
                        query.set('header', 1);
                    }

                    return `${this.previewUrl}?${query}`;
                },
            },
        });
    </script>
@endPushOnce
