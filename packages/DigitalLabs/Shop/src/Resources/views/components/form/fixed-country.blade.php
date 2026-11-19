@props(['code'])

{{--
    An address's country when the store delivers to one country only (DigitalLabs\Shop\DeliveryCountry):
    shown, not chosen. The form sends the code in a hidden field next to this.
--}}
<input
    type="text"
    value="{{ core()->country_name($code) }}"
    readonly
    {{ $attributes->merge(['class' => 'mb-1.5 w-full cursor-not-allowed rounded-lg border bg-gray-100 px-5 py-3 text-base font-normal text-gray-600 max-sm:px-4 max-md:py-2']) }}
>

<p class="text-xs text-zinc-500">
    {{ \DigitalLabs\Shop\DeliveryCountry::message($code) }}
</p>
