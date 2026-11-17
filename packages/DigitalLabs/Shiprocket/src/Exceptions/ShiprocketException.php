<?php

namespace DigitalLabs\Shiprocket\Exceptions;

use RuntimeException;

/**
 * Shiprocket refused a request, or the order cannot be sent as it is.
 */
class ShiprocketException extends RuntimeException {}
