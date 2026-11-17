<?php

namespace DigitalLabs\Shiprocket\Exceptions;

/**
 * Shiprocket did not answer (timeout, connection) or failed on its side (5xx): the request
 * may or may not have been carried out.
 */
class ShiprocketUnknownOutcomeException extends ShiprocketException {}
