<?php

namespace DigitalLabs\Shiprocket\Payment;

use DigitalLabs\Payment\Payment\CashOnDelivery as CoreCashOnDelivery;

/**
 * Core Cash on Delivery, unavailable (with a reason) where couriers don't collect cash (Task 5).
 * Behaves exactly like core COD while Shiprocket is off.
 */
class CashOnDelivery extends CoreCashOnDelivery {}
