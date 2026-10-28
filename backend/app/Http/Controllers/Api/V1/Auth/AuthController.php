<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->success(__('Account created successfully.'), [
            'user' => new UserResource($result['user']->load('vendorProfile', 'wallet')),
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), $request);

        return $this->success(__('Logged in successfully.'), [
            'user' => new UserResource($result['user']->load('vendorProfile', 'wallet')),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(__('Logged out successfully.'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('vendorProfile', 'wallet');

        return $this->success(__('User profile.'), new UserResource($user));
    }

    public function sendEmailVerification(Request $request): JsonResponse
    {
        $this->authService->sendEmailVerification($request->user(), $request);

        return $this->success(__('Verification code sent to your email.'));
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = $this->authService->verifyEmail(
            $request->user(),
            $request->validated('code'),
            $request,
        );

        return $this->success(__('Email verified successfully.'), new UserResource($user));
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $this->authService->sendOtp($request->user(), $request);

        return $this->success(__('OTP sent to your phone.'));
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->authService->verifyOtp(
            $request->user(),
            $request->validated('code'),
            $request,
        );

        return $this->success(__('Phone verified successfully.'), new UserResource($user));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'), $request);

        return $this->success(__('If your email exists, a reset link has been sent.'));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated(), $request);

        return $this->success(__('Password reset successfully. Please login again.'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $request,
        );

        return $this->success(__('Password changed successfully.'));
    }

    public function sessions(Request $request): JsonResponse
    {
        return $this->success(__('Active sessions.'), [
            'sessions' => $this->authService->sessions($request->user()),
        ]);
    }

    public function revokeSession(Request $request, int $token): JsonResponse
    {
        $removed = $this->authService->revokeSession($request->user(), $token, $request);

        if (! $removed) {
            return $this->error(__('Session not found.'), null, 404);
        }

        return $this->success(__('Session revoked successfully.'));
    }
}
