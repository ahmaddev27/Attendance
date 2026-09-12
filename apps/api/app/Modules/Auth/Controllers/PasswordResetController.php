<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

/**
 * Public entry points for the self-service password reset flow.
 *
 * Kept intentionally thin: no branching, no user lookup here. Every
 * response is a fixed message so the endpoints leak nothing about
 * whether the submitted identifier resolves to a real user — that
 * uniformity is the whole point of the flow's security posture, and
 * pushing it into a controller-level `if` would eventually drift.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $service,
    ) {}

    /**
     * Step 1: request an OTP for the identifier. Always returns 200
     * with the same message regardless of whether the identifier
     * matches a real account.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->service->requestReset($request->identifier());

        return response()->json([
            'message' => 'إن كان الحساب موجوداً، أرسلنا لك رمزاً عبر SMS.',
        ]);
    }

    /**
     * Step 2: verify OTP + rotate password. A wrong/expired code and
     * an unknown identifier both surface as the same 422 with a
     * generic Arabic message from PasswordResetService.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->service->resetPassword(
            identifier: $request->identifier(),
            code: $request->code(),
            password: $request->password(),
        );

        return response()->json([
            'message' => 'تم تحديث كلمة السر بنجاح.',
        ]);
    }
}
