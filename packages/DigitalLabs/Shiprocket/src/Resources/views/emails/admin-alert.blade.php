@component('admin::emails.layout')
    <div style="margin-bottom: 34px;">
        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            @lang('shiprocket::app.emails.alert.'.$problem, $details)
        </p>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            <a href="{{ route('admin.sales.orders.view', $order->id) }}">#{{ $order->increment_id }}</a>
        </p>
    </div>
@endcomponent
