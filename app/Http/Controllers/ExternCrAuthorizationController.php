<?php

namespace App\Http\Controllers;

use App\Models\ExternCr;
use App\Models\User;
use App\Models\WhatsappCrAuthorizationDispatch;
use App\Support\ExternCrPdfQr;
use App\Support\WhatsappCrAuthorizationApplier;
use App\Support\WhatsappCrAuthorizationButtonCodes;
use App\Support\WhatsappCrAuthorizationExpiry;
use App\Support\WhatsappCrAuthorizationOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ExternCrAuthorizationController extends Controller
{
    public function approvalLanding(Request $request, string $interactionToken): View
    {
        $dispatch = $this->findDispatchByToken($interactionToken);
        if ($dispatch === null) {
            abort(404);
        }

        if (WhatsappCrAuthorizationExpiry::isExpired($dispatch)) {
            return $this->expiredView($dispatch);
        }

        $cr = $dispatch->externCr;
        if ($cr === null) {
            abort(404);
        }

        $cr->load(['creator', 'division', 'authorizationResponder', 'application']);

        $token = WhatsappCrAuthorizationButtonCodes::approvalLandingUrlSuffix(
            (string) ($dispatch->interaction_token ?? '')
        );

        return view('pages.extern-cr-approval-landing', [
            'cr' => $cr,
            'dispatch' => $dispatch,
            'pdfUrl' => ExternCrPdfQr::temporarySignedPdfBundleUrl($cr),
            'approveUrl' => route('extern-cr.authorize.approval.approve', ['interactionToken' => $token]),
            'rejectUrl' => route('extern-cr.authorize.approval.reject', ['interactionToken' => $token]),
            'openRejectModal' => $request->boolean('tolak'),
        ]);
    }

    public function approvalApprove(Request $request, string $interactionToken): View|RedirectResponse
    {
        $token = $this->normalizeToken($interactionToken);
        $dispatch = $this->findDispatchByToken($token);
        if ($dispatch === null) {
            abort(404);
        }

        if (WhatsappCrAuthorizationExpiry::isExpired($dispatch)) {
            return $this->expiredView($dispatch);
        }

        $user = User::query()->find($dispatch->user_id);
        if ($user === null || ! $user->can_authorize_extern_cr) {
            return $this->resultView([
                'result' => WhatsappCrAuthorizationApplier::RESULT_USER_UNAUTHORIZED,
                'extern_cr' => null,
                'user' => null,
                'existing_decision' => null,
            ], ExternCr::WA_AUTH_APPROVED);
        }

        if ($request->isMethod('post')) {
            return $this->verifyApprovalOtp($request, $dispatch, $user, $token);
        }

        if (! $user->two_factor_enabled) {
            return view('pages.extern-cr-authorization-approve-2fa', [
                'cr' => $dispatch->externCr,
                'maskedPhone' => '—',
                'verifyUrl' => route('extern-cr.authorize.approval.approve', ['interactionToken' => $token]),
                'resendUrl' => route('extern-cr.authorize.approval.approve.resend', ['interactionToken' => $token]),
                'backUrl' => route('extern-cr.authorize.approval', ['interactionToken' => $token]),
                'otpUnavailable' => true,
            ]);
        }

        $otp = app(WhatsappCrAuthorizationOtp::class);
        $otpUnavailable = false;

        if (! $otp->hasPending($token)) {
            if (! $otp->dispatchForApproval($user, $token)) {
                $otpUnavailable = true;
            }
        }

        return view('pages.extern-cr-authorization-approve-2fa', [
            'cr' => $dispatch->externCr,
            'maskedPhone' => $otp->maskPhone($user->phone),
            'verifyUrl' => route('extern-cr.authorize.approval.approve', ['interactionToken' => $token]),
            'resendUrl' => route('extern-cr.authorize.approval.approve.resend', ['interactionToken' => $token]),
            'backUrl' => route('extern-cr.authorize.approval', ['interactionToken' => $token]),
            'otpUnavailable' => $otpUnavailable,
        ]);
    }

    public function resendApprovalOtp(Request $request, string $interactionToken): RedirectResponse
    {
        $token = $this->normalizeToken($interactionToken);
        $dispatch = $this->findDispatchByToken($token);
        if ($dispatch === null) {
            abort(404);
        }

        if (WhatsappCrAuthorizationExpiry::isExpired($dispatch)) {
            return redirect()
                ->route('extern-cr.authorize.approval', ['interactionToken' => $token])
                ->with('status', 'Tautan otorisasi sudah kedaluwarsa.');
        }

        $user = User::query()->find($dispatch->user_id);
        if ($user === null || ! $user->can_authorize_extern_cr || ! $user->two_factor_enabled) {
            return redirect()
                ->route('extern-cr.authorize.approval.approve', ['interactionToken' => $token])
                ->withErrors(['otp' => 'OTP tidak dapat dikirim ulang untuk akun ini.']);
        }

        $otp = app(WhatsappCrAuthorizationOtp::class);
        if (! $otp->dispatchForApproval($user, $token)) {
            return redirect()
                ->route('extern-cr.authorize.approval.approve', ['interactionToken' => $token])
                ->withErrors(['otp' => 'Gagal mengirim ulang OTP WhatsApp. Periksa nomor HP dan konfigurasi Mahadata.']);
        }

        return redirect()
            ->route('extern-cr.authorize.approval.approve', ['interactionToken' => $token])
            ->with('status', 'Kode OTP baru telah dikirim ke WhatsApp Anda.');
    }

    public function approvalReject(Request $request, string $interactionToken): View|RedirectResponse
    {
        $token = $this->normalizeToken($interactionToken);

        if ($request->isMethod('get')) {
            return redirect()->route('extern-cr.authorize.approval', [
                'interactionToken' => $token,
                'tolak' => 1,
            ]);
        }

        $dispatch = $this->findDispatchByToken($token);
        if ($dispatch === null) {
            abort(404);
        }

        if (WhatsappCrAuthorizationExpiry::isExpired($dispatch)) {
            return $this->expiredView($dispatch);
        }

        $validator = Validator::make($request->all(), [
            'reject_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('extern-cr.authorize.approval', [
                    'interactionToken' => $token,
                    'tolak' => 1,
                ])
                ->withErrors($validator)
                ->withInput();
        }

        return $this->handle(
            $token,
            ExternCr::WA_AUTH_REJECTED,
            'approval-link-reject',
            trim((string) $validator->validated()['reject_reason']),
        );
    }

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

    public function reject(Request $request, string $interactionToken): RedirectResponse
    {
        $token = $this->normalizeToken($interactionToken);

        return redirect()->route('extern-cr.authorize.approval', [
            'interactionToken' => $token,
            'tolak' => 1,
        ]);
    }

    private function verifyApprovalOtp(
        Request $request,
        WhatsappCrAuthorizationDispatch $dispatch,
        User $user,
        string $token,
    ): View|RedirectResponse {
        $throttleKey = sprintf('cr-auth-2fa:%s|%s', $token, $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return redirect()
                ->route('extern-cr.authorize.approval.approve', ['interactionToken' => $token])
                ->withErrors(['otp' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
        }

        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $otpService = app(WhatsappCrAuthorizationOtp::class);
        if (! $otpService->verify($token, (string) $request->input('otp'), (int) $user->id)) {
            RateLimiter::hit($throttleKey, 300);

            return redirect()
                ->route('extern-cr.authorize.approval.approve', ['interactionToken' => $token])
                ->withErrors(['otp' => 'Kode OTP tidak valid atau sudah kedaluwarsa.']);
        }

        RateLimiter::clear($throttleKey);

        return $this->resultView(
            app(WhatsappCrAuthorizationApplier::class)->applyByInteractionToken(
                $token,
                ExternCr::WA_AUTH_APPROVED,
                'approval-link-approve',
            ),
            ExternCr::WA_AUTH_APPROVED,
        );
    }

    private function handle(string $interactionToken, string $decision, string $auditReference, ?string $rejectReason = null): View
    {
        $outcome = app(WhatsappCrAuthorizationApplier::class)->applyByInteractionToken(
            $interactionToken,
            $decision,
            $auditReference,
            $rejectReason,
        );

        return $this->resultView($outcome, $decision);
    }

    /**
     * @param  array{
     *     result: string,
     *     extern_cr: ExternCr|null,
     *     user: User|null,
     *     existing_decision: string|null
     * }  $outcome
     */
    private function resultView(array $outcome, string $requestedDecision): View
    {
        if (($outcome['result'] ?? '') === WhatsappCrAuthorizationApplier::RESULT_EXPIRED) {
            $cr = $outcome['extern_cr'] ?? null;

            return view('pages.extern-cr-authorization-expired', [
                'cr' => $cr,
                'expiredReason' => $cr?->hasWaAuthorizationDecision() ? 'decided' : 'timeout',
            ]);
        }

        if (($outcome['result'] ?? '') === WhatsappCrAuthorizationApplier::RESULT_ALREADY_DECIDED) {
            return view('pages.extern-cr-authorization-expired', [
                'cr' => $outcome['extern_cr'] ?? null,
                'expiredReason' => 'decided',
            ]);
        }

        return view('pages.extern-cr-authorization-result', [
            'outcome' => $outcome,
            'requestedDecision' => $requestedDecision,
        ]);
    }

    private function expiredView(WhatsappCrAuthorizationDispatch $dispatch): View
    {
        $dispatch->loadMissing('externCr');

        return view('pages.extern-cr-authorization-expired', [
            'cr' => $dispatch->externCr,
            'expiredReason' => WhatsappCrAuthorizationExpiry::expiredReason($dispatch),
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
