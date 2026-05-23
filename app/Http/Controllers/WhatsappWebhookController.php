<?php

namespace App\Http\Controllers;

use App\Support\WhatsappCrAuthorizationWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request, WhatsappCrAuthorizationWebhookProcessor $processor): Response
    {
        return $processor->verifySubscription($request);
    }

    public function ingest(Request $request, WhatsappCrAuthorizationWebhookProcessor $processor): Response
    {
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
