@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <span style="font-size: 22px;font-weight: 600;color: #121A26">
            @lang('razorpay::app.emails.refunded.title')
        </span>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">{{ $explanation }}</p>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            @lang('razorpay::app.emails.refunded.amount', ['amount' => $formattedAmount])
        </p>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            @lang('razorpay::app.emails.refunded.timing')
        </p>
    </div>
@endcomponent
