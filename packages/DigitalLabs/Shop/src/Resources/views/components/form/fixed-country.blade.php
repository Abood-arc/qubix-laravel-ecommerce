@props(['code'])

{{--
    An address's country when the store delivers to one country only (DigitalLabs\Shop\DeliveryCountry):
    shown, not chosen. Text styled as a field rather than a read-only input, so a tap puts no
    text cursor in it. The form sends the code in a hidden field next to this.
--}}
<div
    role="textbox"
    aria-readonly="true"
    {{ $attributes->merge(['class' => 'mb-1.5 w-full cursor-not-allowed select-none rounded-lg border bg-gray-100 px-5 py-3 text-base font-normal text-gray-600 max-sm:px-4 max-md:py-2']) }}
>{{ core()->country_name($code) }}</div>

<p class="text-xs text-zinc-500">
    {{ \DigitalLabs\Shop\DeliveryCountry::message($code) }}
</p>
