<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@lang('razorpay::app.redirect.title')</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #121A26; margin: 0; padding: 24px;">
    <main style="max-width: 480px; margin: 0 auto;">
        <div id="razorpay-summary">
            <h1 style="font-size: 22px;">@lang('razorpay::app.redirect.title')</h1>

            <ul style="padding: 0; list-style: none;">
                @foreach ($payment->snapshot['items'] as $item)
                    <li style="padding: 8px 0; border-bottom: 1px solid #E9E9E9;">{{ $item['name'] }} &times; {{ (int) $item['qty_ordered'] }}</li>
                @endforeach
            </ul>

            <p style="font-size: 18px; font-weight: 600;">
                @lang('razorpay::app.redirect.total'): {{ core()->formatPrice($payment->amount / 100, $payment->currency) }}
            </p>

            <p>@lang('razorpay::app.redirect.opening')</p>
        </div>

        <div id="razorpay-confirming" hidden>
            <h1 style="font-size: 22px;">@lang('razorpay::app.redirect.confirming-title')</h1>

            <p>@lang('razorpay::app.redirect.confirming')</p>

            <a href="{{ route('shop.home.index') }}">@lang('razorpay::app.redirect.continue')</a>
        </div>
    </main>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <script>
        // A page restored from the browser's memory (back button, reopened tab) belongs to a
        // Razorpay order that may already be paid: reload so the server decides what to show.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        (function () {
            const callbackUrl = @json(route('razorpay.callback'));
            const cancelUrl = @json(route('razorpay.cancel'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const options = @json($checkoutOptions);

            function showConfirming() {
                document.getElementById('razorpay-summary').hidden = true;
                document.getElementById('razorpay-confirming').hidden = false;
            }

            options.handler = function (response) {
                fetch(callbackUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(response),
                })
                    .then(function (result) {
                        if (! result.ok) {
                            throw new Error('Callback failed');
                        }

                        return result.json();
                    })
                    .then(function (data) {
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;

                            return;
                        }

                        showConfirming();
                    })
                    .catch(showConfirming);
            };

            options.modal = {
                ondismiss: function () {
                    window.location.href = cancelUrl;
                },
            };

            if (typeof Razorpay === 'undefined') {
                window.location.href = cancelUrl;

                return;
            }

            new Razorpay(options).open();
        })();
    </script>
</body>
</html>
