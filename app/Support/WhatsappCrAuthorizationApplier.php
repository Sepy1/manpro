<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\User;
use App\Models\WhatsappCrAuthorizationDispatch;
use Illuminate\Support\Facades\DB;

final class WhatsappCrAuthorizationApplier
{
    public const RESULT_APPLIED = 'applied';

    public const RESULT_ALREADY_DECIDED = 'already_decided';

    public const RESULT_DISPATCH_NOT_FOUND = 'dispatch_not_found';

    public const RESULT_USER_UNAUTHORIZED = 'user_unauthorized';

    public const RESULT_EXPIRED = 'expired';

    /**
     * @return array{
     *     result: string,
     *     extern_cr: ExternCr|null,
     *     user: User|null,
     *     existing_decision: string|null
     * }
     */
    public function applyByInteractionToken(
        string $interactionToken,
        string $decision,
        string $auditReference,
        ?string $rejectReason = null,
    ): array {
        $token = strtolower(trim($interactionToken));
        if ($token === '' || ! preg_match('/^[a-z0-9]{32}$/', $token)) {
            return $this->emptyResult(self::RESULT_DISPATCH_NOT_FOUND);
        }

        $dispatch = WhatsappCrAuthorizationDispatch::query()
            ->where('interaction_token', $token)
            ->first();

        if ($dispatch === null) {
            return $this->emptyResult(self::RESULT_DISPATCH_NOT_FOUND);
        }

        $user = User::query()->find($dispatch->user_id);
        if ($user === null || ! $user->can_authorize_extern_cr) {
            return $this->emptyResult(self::RESULT_USER_UNAUTHORIZED);
        }

        if (WhatsappCrAuthorizationExpiry::isExpired($dispatch)) {
            return $this->emptyResult(self::RESULT_EXPIRED);
        }

        return $this->applyDecision($dispatch, $user, $decision, $auditReference, $rejectReason);
    }

    /**
     * @return array{
     *     result: string,
     *     extern_cr: ExternCr|null,
     *     user: User|null,
     *     existing_decision: string|null
     * }
     */
    public function applyDecision(
        WhatsappCrAuthorizationDispatch $dispatch,
        User $user,
        string $decision,
        string $auditReference,
        ?string $rejectReason = null,
    ): array {
        $applied = false;
        $existingDecision = null;
        /** @var ExternCr|null $cr */
        $cr = null;

        DB::transaction(function () use ($dispatch, $user, $decision, $auditReference, $rejectReason, &$applied, &$existingDecision, &$cr): void {
            /** @var ExternCr|null $locked */
            $locked = ExternCr::query()
                ->whereKey($dispatch->extern_cr_id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            $cr = $locked;

            if ($locked->wa_authorization_decision !== null && $locked->wa_authorization_decision !== '') {
                $existingDecision = (string) $locked->wa_authorization_decision;

                return;
            }

            $fill = [
                'wa_authorization_decision' => $decision,
                'wa_authorization_at' => now(),
                'wa_authorization_by_user_id' => $user->id,
                'wa_authorization_reject_reason' => $decision === ExternCr::WA_AUTH_REJECTED
                    ? trim((string) ($rejectReason ?? ''))
                    : null,
            ];

            if ($fill['wa_authorization_reject_reason'] === '') {
                $fill['wa_authorization_reject_reason'] = null;
            }

            $locked->forceFill($fill);
            $locked->save();

            ExternCrHistoryRecorder::whatsappAuthorization(
                $locked,
                $user->id,
                $decision,
                $auditReference,
                $decision === ExternCr::WA_AUTH_REJECTED ? $rejectReason : null,
            );
            $applied = true;
        });

        if ($cr === null) {
            return $this->emptyResult(self::RESULT_DISPATCH_NOT_FOUND);
        }

        $cr->load(['creator', 'division', 'authorizationResponder']);

        if ($applied) {
            app(MahadataWhatsappAuthorizationConfirmationSender::class)->sendAfterDecision($cr, $user, $decision);

            return [
                'result' => self::RESULT_APPLIED,
                'extern_cr' => $cr,
                'user' => $user,
                'existing_decision' => null,
            ];
        }

        return [
            'result' => self::RESULT_ALREADY_DECIDED,
            'extern_cr' => $cr,
            'user' => $user,
            'existing_decision' => $existingDecision,
        ];
    }

    /**
     * @return array{result: string, extern_cr: null, user: null, existing_decision: null}
     */
    private function emptyResult(string $result): array
    {
        return [
            'result' => $result,
            'extern_cr' => null,
            'user' => null,
            'existing_decision' => null,
        ];
    }
}
