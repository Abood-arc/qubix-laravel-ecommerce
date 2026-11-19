{{--
    Shiprocket panel on the admin order page (hook qubix.admin.sales.order.right_component.before).
    Only classes already in the Admin build: Admin's Tailwind does not scan this package.
--}}
@use('DigitalLabs\Shiprocket\Http\Controllers\Admin\ShipmentController')
@use('DigitalLabs\Shiprocket\Models\ShiprocketShipment')
@use('DigitalLabs\Shiprocket\Services\ParcelBuilder')
@use('DigitalLabs\Shiprocket\Support\Settings')

@if (Settings::enabled())
    @php
        $shiprocket = ShiprocketShipment::query()->where('order_id', $order->id)->first();

        $shiprocketStatus = $shiprocket?->status;

        $shiprocketBadge = match ($shiprocketStatus) {
            ShiprocketShipment::STATUS_DELIVERED => 'label-completed',
            ShiprocketShipment::STATUS_FAILED, ShiprocketShipment::STATUS_RTO => 'label-canceled',
            ShiprocketShipment::STATUS_CANCELLED => 'label-closed',
            ShiprocketShipment::STATUS_PENDING => 'label-pending',
            default => 'label-processing',
        };

        $canCreate = bouncer()->hasPermission('sales.shipments.create');

        $canBook = $canCreate && ShipmentController::canBook($order, $shiprocket);

        $canCancel = $canCreate && ShipmentController::canCancel($shiprocket);

        $bookConfirm = null;

        // Only a first booking is measured: booking again after a cancel sends the parcel
        // the store shipment already holds (its items count as shipped, so the order's
        // weight left to ship says nothing about it).
        if ($canBook && ! $shiprocket?->store_shipment_id) {
            $weight = app(ParcelBuilder::class)->weightKg($order);

            if ($weight > Settings::maxWeightKg() || $weight < 0.01) {
                $bookConfirm = trans('shiprocket::app.admin.confirm-heavy', ['weight' => $weight]);
            }
        }
    @endphp

    <x-admin::accordion>
        <x-slot:header>
            <p class="p-2.5 text-base font-semibold text-gray-600 dark:text-gray-300">
                @lang('shiprocket::app.admin.panel-title')
            </p>
        </x-slot>

        <x-slot:content>
            <div
                id="shiprocket-panel"
                class="flex flex-col gap-2.5"
                v-pre
            >
                @if ($shiprocket && $shiprocketStatus !== ShiprocketShipment::STATUS_PENDING)
                    <p class="{{ $shiprocketBadge }}">
                        {{ trans('shiprocket::app.admin.status.'.$shiprocketStatus) }}
                    </p>
                @else
                    <p class="text-gray-600 dark:text-gray-300">
                        @lang('shiprocket::app.admin.not-sent')
                    </p>
                @endif

                @if ($shiprocketStatus === ShiprocketShipment::STATUS_FAILED && filled($shiprocket->failure_reason))
                    <p class="break-all text-red-600">
                        {{ trans('shiprocket::app.admin.reason', ['reason' => $shiprocket->failure_reason]) }}
                    </p>
                @endif

                @if (filled($shiprocket?->courier_name))
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white">
                            {{ $shiprocket->courier_name }}
                        </p>

                        <p class="text-gray-600 dark:text-gray-300">
                            @lang('shiprocket::app.admin.courier')
                        </p>
                    </div>
                @endif

                @if ($shiprocket?->requested_courier_id && filled($shiprocket->courier_name))
                    <p class="text-red-600">
                        {{ trans('shiprocket::app.admin.courier-swapped', ['courier' => $shiprocket->courier_name]) }}
                    </p>
                @endif

                @if (filled($shiprocket?->awb_code))
                    <div>
                        <p class="break-all font-semibold text-gray-800 dark:text-white">
                            {{ $shiprocket->awb_code }}

                            <a
                                href="{{ Settings::trackingUrl($shiprocket->awb_code) }}"
                                class="text-sm font-normal text-blue-600 hover:underline"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                @lang('shiprocket::app.admin.track')
                            </a>
                        </p>

                        <p class="text-gray-600 dark:text-gray-300">
                            @lang('shiprocket::app.admin.awb')
                        </p>
                    </div>
                @endif

                @if (ShipmentController::hasLabel($shiprocket) && bouncer()->hasPermission('sales.shipments.view'))
                    <a
                        href="{{ route('admin.shiprocket.label', $order->id) }}"
                        class="text-blue-600 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        @lang('shiprocket::app.admin.label')
                    </a>
                @endif

                @if ($canBook || $canCancel)
                    <div class="flex flex-wrap items-center gap-2.5">
                        @if ($canBook)
                            @php
                                $retry = $shiprocketStatus === ShiprocketShipment::STATUS_FAILED;
                            @endphp

                            <form
                                method="POST"
                                action="{{ route($retry ? 'admin.shiprocket.retry' : 'admin.shiprocket.ship', $order->id) }}"
                                @if ($bookConfirm)
                                    data-confirm="{{ $bookConfirm }}"
                                    onsubmit="return confirm(this.dataset.confirm)"
                                @endif
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="primary-button"
                                >
                                    @lang($retry ? 'shiprocket::app.admin.retry' : 'shiprocket::app.admin.ship')
                                </button>
                            </form>
                        @endif

                        @if ($canCancel)
                            <form
                                method="POST"
                                action="{{ route('admin.shiprocket.cancel', $order->id) }}"
                                data-confirm="{{ trans('shiprocket::app.admin.confirm-cancel') }}"
                                onsubmit="return confirm(this.dataset.confirm)"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="secondary-button"
                                >
                                    @lang('shiprocket::app.admin.cancel')
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </x-slot>
    </x-admin::accordion>
@endif
