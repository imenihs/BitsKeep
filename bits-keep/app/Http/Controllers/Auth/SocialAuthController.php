<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Services\BootstrapAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, BootstrapAdminService $bootstrapAdmin): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        $socialUser = Socialite::driver($provider)->user();
        $email = strtolower((string) ($socialUser->getEmail() ?? ''));

        if ($email === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'SNSログインからメールアドレスを取得できませんでした。メールアドレス公開設定または別のログイン方法を確認してください。']);
        }

        $providerLink = UserAuthProvider::where('provider', $provider)
            ->where('provider_user_id', (string) $socialUser->getId())
            ->first();

        $user = $providerLink?->user ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ]);
        }

        UserAuthProvider::updateOrCreate(
            ['provider' => $provider, 'provider_user_id' => (string) $socialUser->getId()],
            [
                'user_id' => $user->id,
                'provider_email' => $email,
                'provider_payload' => [
                    'name' => $socialUser->getName(),
                    'nickname' => $socialUser->getNickname(),
                    'avatar' => $socialUser->getAvatar(),
                ],
                'linked_at' => now(),
                'last_used_at' => now(),
            ]
        );

        $bootstrapAdmin->ensureForUser($user);

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function ensureProviderIsSupported(string $provider): void
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 404);
    }

    private function ensureProviderIsConfigured(string $provider): void
    {
        $config = Config::get("services.{$provider}");

        abort_unless(
            filled($config['client_id'] ?? null) &&
            filled($config['client_secret'] ?? null) &&
            filled($config['redirect'] ?? null),
            503,
            "{$provider} login is not configured."
        );
    }
}
