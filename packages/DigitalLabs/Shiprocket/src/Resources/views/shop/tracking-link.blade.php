{{-- "Track your parcel" next to a Shiprocket AWB on the customer's order page. Only classes already in the Shop build. --}}
@if (str_starts_with((string) $shipment->carrier_title, 'Shiprocket') && filled($shipment->track_number))
    <a
        href="{{ \DigitalLabs\Shiprocket\Support\Settings::trackingUrl($shipment->track_number) }}"
        class="text-blue-600 underline ltr:ml-2 rtl:mr-2"
        target="_blank"
        rel="noopener noreferrer"
    >
        @lang('shiprocket::app.emails.track')
    </a>
@endif
