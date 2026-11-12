{!! view_render_event('qubix.shop.layout.footer.before') !!}

<!--
    The category repository is injected directly here because there is no way
    to retrieve it from the view composer, as this is an anonymous component.
-->
@inject('themeCustomizationRepository', 'DigitalLabs\Theme\Repositories\ThemeCustomizationRepository')

<!--
    This code needs to be refactored to reduce the amount of PHP in the Blade
    template as much as possible.
-->
@php
    $channel = core()->getCurrentChannel();

    $customization = $themeCustomizationRepository->findOneWhere([
        'type'       => 'footer_links',
        'status'     => 1,
        'theme_code' => $channel->theme,
        'channel_id' => $channel->id,
    ]);

    // Footer link groups (admin: Themes > Footer Links), each sorted by its sort order.
    $footerLinkSections = collect($customization?->options ?? [])->map(function ($section) {
        usort($section, fn ($a, $b) => $a['sort_order'] - $b['sort_order']);

        return $section;
    });

    // Admin: Configuration > General > Content > Store Contact. wa.me links need digits only.
    $whatsappDisplay = core()->getConfigData('general.content.store_contact.whatsapp_number');
    $whatsappDigits = preg_replace('/\D+/', '', (string) $whatsappDisplay);
    $contactEmail = core()->getConfigData('general.content.store_contact.email');
@endphp

{{--
    Deep green footer: logo, the admin's link groups as columns, then contact and newsletter.
    On phones the contact block comes first, the links fold under "Help & Policies", and the
    newsletter follows.
--}}
<footer class="mt-16 bg-brandDeep text-[#e8efe9] max-md:mt-12">
    <div class="grid grid-cols-[minmax(0,1.1fr)_minmax(0,2fr)_minmax(0,1.4fr)] gap-10 px-10 pb-10 pt-12 max-1180:px-8 max-1060:grid-cols-1 max-1060:gap-0 max-md:px-4 max-md:pb-4 max-md:pt-8">
        <!-- Logo -->
        <div class="max-1060:hidden">
            <a
                href="{{ route('shop.home.index') }}"
                class="inline-flex"
                aria-label="{{ config('app.name') }}"
            >
                <img
                    src="{{ $channel->logo_url ?? qubix_asset('images/logo.svg') }}"
                    alt="{{ config('app.name') }}"
                    class="h-14 w-auto rounded-full bg-white object-contain"
                >
            </a>
        </div>

        <!-- Links: columns on desktop -->
        <div
            class="flex flex-wrap gap-x-16 gap-y-8 max-1060:hidden"
            v-pre
        >
            @foreach ($footerLinkSections as $footerLinkSection)
                <ul class="grid content-start gap-1">
                    @foreach ($footerLinkSection as $link)
                        <li>
                            <a
                                href="{{ $link['url'] }}"
                                class="inline-block py-1 text-[15px] text-[#c7d6cb] underline-offset-4 transition-colors hover:text-white hover:underline"
                            >
                                {{ $link['title'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>

        <!-- Contact and newsletter (first on phones) -->
        <div class="grid content-start gap-8 max-1060:gap-6 max-1060:pb-2">
            @if ($whatsappDigits || $contactEmail)
                <div
                    class="grid content-start gap-1"
                    v-pre
                >
                    <p
                        class="mb-1 text-base font-bold text-white"
                        role="heading"
                        aria-level="2"
                    >
                        @lang('shop::app.components.layouts.footer.talk-to-us')
                    </p>

                    @if ($whatsappDigits)
                        <a
                            href="https://wa.me/{{ $whatsappDigits }}"
                            class="inline-flex min-h-11 items-center text-[15px] font-semibold text-white underline-offset-4 hover:underline"
                            target="_blank"
                            rel="noopener"
                        >
                            @lang('shop::app.components.layouts.footer.whatsapp'): {{ $whatsappDisplay }}
                        </a>
                    @endif

                    @if ($contactEmail)
                        <a
                            href="mailto:{{ $contactEmail }}"
                            class="inline-flex min-h-11 items-center text-[15px] font-semibold text-white underline-offset-4 hover:underline"
                        >
                            {{ $contactEmail }}
                        </a>
                    @endif
                </div>
            @endif

            <!-- Links: one folded group on phones -->
            @if ($footerLinkSections->isNotEmpty())
                <x-shop::accordion
                    :is-active="false"
                    class="hidden !w-full !rounded-none !border-0 !border-y !border-white/15 !bg-transparent max-1060:block"
                >
                    <x-slot:header class="!bg-transparent !px-0 !py-3.5 text-base font-bold text-white [&_span]:text-white">
                        @lang('shop::app.components.layouts.footer.footer-content')
                    </x-slot>

                    <x-slot:content class="grid !bg-transparent !px-0 !pb-4 !pt-0">
                        @foreach ($footerLinkSections as $footerLinkSection)
                            <ul
                                class="grid gap-0"
                                v-pre
                            >
                                @foreach ($footerLinkSection as $link)
                                    <li>
                                        <a
                                            href="{{ $link['url'] }}"
                                            class="flex min-h-11 items-center text-[15px] text-[#c7d6cb]"
                                        >
                                            {{ $link['title'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </x-slot>
                </x-shop::accordion>
            @endif

            {!! view_render_event('qubix.shop.layout.footer.newsletter_subscription.before') !!}

            <!-- News Letter subscription -->
            @if (core()->getConfigData('customer.settings.newsletter.subscription'))
                <div class="grid gap-2">
                    <p
                        class="text-base font-bold text-white"
                        role="heading"
                        aria-level="2"
                    >
                        @lang('shop::app.components.layouts.footer.newsletter-text')
                    </p>

                    <p class="text-sm text-[#c7d6cb]">
                        @lang('shop::app.components.layouts.footer.subscribe-stay-touch')
                    </p>

                    <x-shop::form
                        :action="route('shop.subscription.store')"
                        class="mt-1"
                    >
                        <div class="relative w-full max-w-[420px] max-1060:max-w-none">
                            <x-shop::form.control-group.control
                                type="email"
                                id="footer-newsletter-email"
                                class="block h-12 w-full !rounded-full !border-0 bg-white/10 px-5 text-base text-white placeholder:text-[#b9c9bd] focus:bg-white/15 ltr:pr-32 rtl:pl-32"
                                name="email"
                                autocomplete="email"
                                rules="required|email"
                                label="Email"
                                :aria-label="trans('shop::app.components.layouts.footer.email')"
                                placeholder="email@example.com"
                            />

                            <x-shop::form.control-group.error control-name="email" />

                            <button
                                type="submit"
                                class="absolute top-1 flex h-10 items-center rounded-full bg-white px-5 text-[15px] font-bold text-brandDeep transition-transform duration-150 hover:bg-sage active:scale-[0.97] ltr:right-1 rtl:left-1"
                            >
                                @lang('shop::app.components.layouts.footer.subscribe')
                            </button>
                        </div>
                    </x-shop::form>
                </div>
            @endif

            {!! view_render_event('qubix.shop.layout.footer.newsletter_subscription.after') !!}
        </div>
    </div>

    <div class="flex justify-between border-t border-white/10 px-10 py-4 max-1180:px-8 max-md:justify-center max-md:px-4">
        {!! view_render_event('qubix.shop.layout.footer.footer_text.before') !!}

        <p class="text-sm text-[#b9c9bd] max-md:text-center">
            @if (core()->getConfigData('general.content.footer.copyright_content'))
                {!! core()->getConfigData('general.content.footer.copyright_content') !!}
            @else
                @lang('shop::app.components.layouts.footer.footer-text', ['current_year'=> date('Y'), 'app_name' => config('app.name') ])
            @endif
        </p>

        {!! view_render_event('qubix.shop.layout.footer.footer_text.after') !!}
    </div>
</footer>

{!! view_render_event('qubix.shop.layout.footer.after') !!}
