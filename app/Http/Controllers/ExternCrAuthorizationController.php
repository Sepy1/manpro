<?php

namespace App\Http\Controllers;

use App\Models\ExternCr;
use App\Models\WhatsappCrAuthorizationDispatch;
use App\Support\ExternCrPdfQr;
use App\Support\WhatsappCrAuthorizationApplier;
use App\Support\WhatsappCrAuthorizationButtonCodes;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternCrAuthorizationController extends Controller
{
    /** Halaman tindak lanjut dari tombol WA «Tindak Lanjut» (`/approval/{token}`). */
    public function approvalLanding(Request $request, string $interactionToken): View
    {
        $dispatch = $this->findDispatchByToken($interactionToken);
        if ($dispatch === null) {
            abort(404);
        }

        $cr = $dispatch->externCr;
        if ($cr === null) {
            abort(404);
        }

        $cr->load(['creator', 'division', 'authorizationResponder']);

        $token = WhatsappCrAuthorizationButtonCodes::approvalLandingUrlSuffix(
            (string) ($dispatch->interaction_token ?? '')
        );

        return view('pages.extern-cr-approval-landing', [
            'cr' => $cr,
            'dispatch' => $dispatch,
            'pdfUrl' => ExternCrPdfQr::temporarySignedPdfBundleUrl($cr),
            'approveUrl' => route('extern-cr.authorize.approval.approve', ['interactionToken' => $token]),
            'rejectUrl' => route('extern-cr.authorize.approval.reject', ['interactionToken' => $token]),
        ]);
    }

    public function approvalApprove(Request $request, string $interactionToken): View
    {
        return $this->handle(
            $this->normalizeToken($interactionToken),
            ExternCr::WA_AUTH_APPROVED,
            'approval-link-approve',
        );
    }

    public function approvalReject(Request $request, string $interactionToken): View
    {
        return $this->handle(
            $this->normalizeToken($interactionToken),
            ExternCr::WA_AUTH_REJECTED,
            'approval-link-reject',
        );
    }

    /**
     * Route legacy template dua tombol URL: `…/otorisasi/cr/setuju/` + suffix.
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

    public function approve(Request $request, string $interactionToken): View
    {
        return $this->handle($interactionToken, ExternCr::WA_AUTH_APPROVED, 'link-approve');
    }

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

    private function normalizeToken(string $interactionToken): string
    {
        $token = strtolower(trim($interactionToken));
        if ($token === '' || ! preg_match('/^[a-z0-9]{32}$/', $token)) {
            abort(404);
        }

        return $token;
    }

    private function findDispatchByToken(string $interactionToken): ?WhatsappCrAuthorizationDispatch
    {
        $token = $this->normalizeToken($interactionToken);

        return WhatsappCrAuthorizationDispatch::query()
            ->where('interaction_token', $token)
            ->first();
    }
}
