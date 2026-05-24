<?php

namespace App\Http\Controllers;

use App\Support\WhatsappCrAuthorizationWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request, WhatsappCrAuthorizationWebhookProcessor $processor): Response
    {
        if ($processor->isSubscriptionVerification($request)) {
            return $processor->verifySubscription($request);
        }

        if ($request->isMethod('GET')) {
            return response('Forbidden', 403);
        }

        try {
            $processor->handleInbound($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Webhook WhatsApp: pemrosesan berhenti karena kesalahan (detail pada message log).', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->noContent();
    }
}
