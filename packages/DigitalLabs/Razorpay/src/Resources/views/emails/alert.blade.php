@component('admin::emails.layout')
    <div style="margin-bottom: 34px;">
        <span style="font-size: 22px;font-weight: 600;color: #121A26">
            @lang('razorpay::app.emails.alert.title')
        </span>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            @lang('razorpay::app.emails.alert.payment', ['payment' => $paymentReference])
        </p>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            @lang('razorpay::app.emails.alert.amount', ['amount' => $formattedAmount])
        </p>

        <p style="font-size: 16px;color: #5E5E5E;line-height: 24px;">
            @lang('razorpay::app.emails.alert.'.$problem, $details)
        </p>
    </div>
@endcomponent
