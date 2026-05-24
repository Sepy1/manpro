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
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        Log::info('Webhook WhatsApp: request diterima.', [
            'method' => $request->method(),
            'content_length' => strlen($request->getContent()),
            'event_type' => data_get($payload, 'type') ?? data_get($payload, 'event') ?? data_get($payload, 'event_type'),
            'top_level_keys' => array_keys($payload),
            'ip' => $request->ip(),
        ]);

        if ($processor->isSubscriptionVerification($request)) {
            return $processor->verifySubscription($request);
        }

        if ($request->isMethod('GET')) {
            return response('Forbidden', 403);
        }

        try {
            $processor->handleInbound($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            Log::warning('Webhook WhatsApp: request ditolak.', [
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Webhook WhatsApp: pemrosesan berhenti karena kesalahan.', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->noContent();
    }
}
