{{-- "Track your parcel" under a Shiprocket AWB in the shipment email. --}}
@if (str_starts_with((string) $shipment->carrier_title, 'Shiprocket') && filled($shipment->track_number))
    <div style="margin-top: 8px;">
        <a
            href="{{ \DigitalLabs\Shiprocket\Support\Settings::trackingUrl($shipment->track_number) }}"
            style="color: #2969FF;"
        >
            @lang('shiprocket::app.emails.track')
        </a>
    </div>
@endif
