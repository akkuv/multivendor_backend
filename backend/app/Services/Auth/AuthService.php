<?php

namespace App\Services\Auth;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VerificationCode;
use App\Models\Wallet;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SendVerificationCodeNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public const TOKEN_NAME = 'auth-token';

    public function __construct(
        protected VerificationService $verification,
        protected AuditLogService $audit,
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function register(array $data): array
    {
        $role = Role::tryFrom($data['role'] ?? Role::Customer->value) ?? Role::Customer;

        $user = $this->createUser($data, $role);

        if ($role === Role::Vendor) {
            $this->createVendorProfile($user, $data);
        }

        $token = $user->createToken(self::TOKEN_NAME)->plainTextToken;

        $this->audit->log($user->id, 'auth.register', __('User registered.'), request());

        $this->dispatcher->send(
            $user,
            NotificationType::CustomerRegistered,
            __('Welcome to :app!', ['app' => config('app.name')]),
            __('Your account has been created successfully.'),
            [],
            false,
        );

        if ($role === Role::Vendor) {
            $this->dispatcher->notifyAdmins(
                NotificationType::NewVendorRegistered,
                __('New vendor registration'),
                __('A new vendor ":store" has registered and is awaiting review.', ['store' => $data['store_name'] ?? $user->name]),
                ['user_id' => $user->id],
            );
        }

        return ['user' => $user, 'token' => $token];
    }

    public function registerVendor(array $data): array
    {
        $role = Role::Vendor;

        $user = $this->createUser($data, $role);
        $vendor = $this->createVendorProfile($user, $data);

        $token = $user->createToken(self::TOKEN_NAME)->plainTextToken;

        $this->audit->log($user->id, 'vendor.register', __('Vendor registration submitted.'), request());

        $this->dispatcher->send(
            $user,
            NotificationType::CustomerRegistered,
            __('Welcome to :app!', ['app' => config('app.name')]),
            __('Your vendor account has been created successfully.'),
            [],
            false,
        );

        $this->dispatcher->notifyAdmins(
            NotificationType::NewVendorRegistered,
            __('New vendor registration'),
            __('Vendor ":store" registered and is awaiting review.', ['store' => $vendor->store_name]),
            ['vendor_id' => $vendor->id],
        );

        return ['user' => $user, 'vendor' => $vendor, 'token' => $token];
    }

    public function login(array $credentials, Request $request): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->log(null, 'auth.login.failed', __('Failed login attempt.'), $request, [
                'email' => $credentials['email'],
            ]);

            throw ValidationException::withMessages([
                'email' => [__('Invalid credentials.')],
            ]);
        }

        if (! $user->is_active) {
            $this->audit->log($user->id, 'auth.login.blocked', __('Login blocked: inactive account.'), $request);

            throw ValidationException::withMessages([
                'email' => [__('Your account has been deactivated.')],
            ]);
        }

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken(self::TOKEN_NAME, $this->abilitiesForRole($user->role))->plainTextToken;

        $this->audit->log($user->id, 'auth.login', __('User logged in.'), $request);

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();

        $this->audit->log($user->id, 'auth.logout', __('User logged out.'), request());
    }

    public function sendEmailVerification(User $user, Request $request): void
    {
        if ($user->email_verified_at) {
            return;
        }

        $verification = $this->verification->generate(
            $user,
            VerificationCode::PURPOSE_EMAIL,
            VerificationCode::CHANNEL_EMAIL,
        );

        $user->notify(new SendVerificationCodeNotification($verification->code, 'email'));

        $this->audit->log($user->id, 'auth.email.verification.sent', __('Email verification code sent.'), $request);
    }

    public function verifyEmail(User $user, string $code, Request $request): User
    {
        if ($user->email_verified_at) {
            return $user;
        }

        $this->verification->verify($user, VerificationCode::PURPOSE_EMAIL, VerificationCode::CHANNEL_EMAIL, $code);

        $this->verification->markEmailVerified($user);

        $this->audit->log($user->id, 'auth.email.verified', __('Email address verified.'), $request);

        return $user;
    }

    public function sendOtp(User $user, Request $request): void
    {
        if (! $user->phone) {
            throw ValidationException::withMessages([
                'phone' => [__('No phone number on file.')],
            ]);
        }

        $verification = $this->verification->generate(
            $user,
            VerificationCode::PURPOSE_PHONE,
            VerificationCode::CHANNEL_SMS,
        );

        // In production this would dispatch an SMS. For development it is logged.
        logger()->info('OTP sent', ['user_id' => $user->id, 'code' => $verification->code]);

        $this->audit->log($user->id, 'auth.phone.otp.sent', __('Phone OTP sent.'), $request);
    }

    public function verifyOtp(User $user, string $code, Request $request): User
    {
        $this->verification->verify($user, VerificationCode::PURPOSE_PHONE, VerificationCode::CHANNEL_SMS, $code);

        $this->verification->markPhoneVerified($user);

        $this->audit->log($user->id, 'auth.phone.verified', __('Phone number verified.'), $request);

        return $user;
    }

    public function forgotPassword(string $email, Request $request): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->audit->log(null, 'auth.password.forgot', __('Password reset requested for unknown email.'), $request, [
                'email' => $email,
            ]);

            return;
        }

        Password::broker()->sendResetLink(['email' => $email], function ($notifiable, string $token): void {
            $notifiable->notify(new ResetPasswordNotification($token));
        });

        $this->audit->log($user->id, 'auth.password.forgot', __('Password reset link sent.'), $request);
    }

    public function resetPassword(array $data, Request $request): void
    {
        $status = Password::broker()->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $user = User::where('email', $data['email'])->first();
        $this->audit->log($user?->id, 'auth.password.reset', __('Password reset successfully.'), $request);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword, Request $request): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('Your current password is incorrect.')],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'remember_token' => Str::random(60),
        ])->save();

        $this->audit->log($user->id, 'auth.password.changed', __('Password changed.'), $request);
    }

    /**
     * @return array<int, array{id: int, name: string, ip_address: mixed, user_agent: mixed, last_used_at: mixed}>
     */
    public function sessions(User $user): array
    {
        $currentTokenId = $user->currentAccessToken()?->id;

        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $currentTokenId,
            ])
            ->all();
    }

    public function revokeSession(User $user, int $tokenId, Request $request): bool
    {
        $removed = $user->tokens()->where('id', $tokenId)->delete();

        $this->audit->log($user->id, 'auth.session.revoked', __('Session revoked.'), $request, [
            'token_id' => $tokenId,
        ]);

        return (bool) $removed;
    }

    public function createUser(array $data, Role $role): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role->value,
            'phone' => $data['phone'] ?? null,
        ]);
    }

    public function createWallet(User $user): Wallet
    {
        return $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0],
        );
    }

    public function createVendorProfile(User $user, array $data): VendorProfile
    {
        $documents = $this->storeBusinessDocuments($data['business_documents'] ?? null);

        return $user->vendorProfile()->create([
            'store_name' => $data['store_name'],
            'slug' => Str::slug($data['store_name']).'-'.Str::random(6),
            'description' => $data['description'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'gstin' => $data['gstin'] ?? null,
            'pan' => $data['pan'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_ifsc' => $data['bank_ifsc'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'upi_id' => $data['upi_id'] ?? null,
            'business_documents' => $documents,
            'status' => VendorStatus::Pending->value,
            'is_approved' => false,
            'commission_rate' => $data['commission_rate'] ?? config('commission.default_rate', 10),
        ]);
    }

    protected function storeBusinessDocuments(mixed $documents): ?array
    {
        if (empty($documents) || ! is_array($documents)) {
            return null;
        }

        return collect($documents)
            ->filter(fn ($doc) => $doc instanceof UploadedFile)
            ->map(fn ($doc) => $doc->store('vendor-documents', 'public'))
            ->values()
            ->all();
    }

    public function createAdmin(array $data): User
    {
        return $this->createUser($data, Role::SuperAdmin);
    }

    protected function abilitiesForRole(string $role): array
    {
        return match ($role) {
            Role::SuperAdmin->value => ['*'],
            Role::Vendor->value => ['vendor:*'],
            default => ['customer:*'],
        };
    }
}
