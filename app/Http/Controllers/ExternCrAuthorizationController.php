<?php

namespace App\Http\Controllers;

use App\Models\ExternCr;
use App\Support\WhatsappCrAuthorizationApplier;
use App\Support\WhatsappCrAuthorizationButtonCodes;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternCrAuthorizationController extends Controller
{
    /**
     * Route utama template Meta: base `…/otorisasi/cr/setuju/` + suffix {{5}} atau {{6}}.
     */
    public function fromSetujuButton(Request $request, string $actionSuffix): View
    {
        $parsed = WhatsappCrAuthorizationButtonCodes::decisionFromSetujuUrlSuffix($actionSuffix);
        if ($parsed === null) {
            abort(404);
        }

        $auditReference = $parsed['decision'] === ExternCr::WA_AUTH_APPROVED
            ? 'link-approve'
            : 'link-reject';

        return $this->handle(
            $parsed['interaction_token'],
            $parsed['decision'],
            $auditReference,
        );
    }

    /** Alternatif bila template Meta memakai base `…/otorisasi/cr/setuju/{{5}}` terpisah dari tolak. */
    public function approve(Request $request, string $interactionToken): View
    {
        return $this->handle($interactionToken, ExternCr::WA_AUTH_APPROVED, 'link-approve');
    }

    /** Alternatif bila template Meta memakai base `…/otorisasi/cr/tolak/{{6}}`. */
    public function reject(Request $request, string $interactionToken): View
    {
        return $this->handle($interactionToken, ExternCr::WA_AUTH_REJECTED, 'link-reject');
    }

    private function handle(string $interactionToken, string $decision, string $auditReference): View
    {
        $outcome = app(WhatsappCrAuthorizationApplier::class)->applyByInteractionToken(
            $interactionToken,
            $decision,
            $auditReference,
        );

        return view('pages.extern-cr-authorization-result', [
            'outcome' => $outcome,
            'requestedDecision' => $decision,
        ]);
    }
}
