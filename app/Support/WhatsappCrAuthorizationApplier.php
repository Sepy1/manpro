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

    /**
     * @return array{
     *     result: string,
     *     extern_cr: ExternCr|null,
     *     user: User|null,
     *     existing_decision: string|null
     * }
     */
    public function applyByInteractionToken(string $interactionToken, string $decision, string $auditReference): array
    {
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

        return $this->applyDecision($dispatch, $user, $decision, $auditReference);
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
    ): array {
        $applied = false;
        $existingDecision = null;
        /** @var ExternCr|null $cr */
        $cr = null;

        DB::transaction(function () use ($dispatch, $user, $decision, $auditReference, &$applied, &$existingDecision, &$cr): void {
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

            $locked->forceFill([
                'wa_authorization_decision' => $decision,
                'wa_authorization_at' => now(),
                'wa_authorization_by_user_id' => $user->id,
            ]);
            $locked->save();

            ExternCrHistoryRecorder::whatsappAuthorization($locked, $user->id, $decision, $auditReference);
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
