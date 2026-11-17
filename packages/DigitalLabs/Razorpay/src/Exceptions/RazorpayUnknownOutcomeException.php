<?php

namespace DigitalLabs\Razorpay\Exceptions;

/**
 * Razorpay did not answer (timeout, connection failure) or failed on its side (5xx),
 * so the request may or may not have been carried out.
 */
class RazorpayUnknownOutcomeException extends RazorpayException {}
