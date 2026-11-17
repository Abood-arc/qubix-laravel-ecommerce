<?php

namespace DigitalLabs\Shiprocket\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Receives Shiprocket tracking updates (Task 7).
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort(501);
    }
}
