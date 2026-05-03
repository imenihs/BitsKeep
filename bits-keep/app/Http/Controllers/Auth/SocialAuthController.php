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
    /**
     * 目的: Social Authのredirectを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        return Socialite::driver($provider)->redirect();
    }
    /**
     * 目的: Social Authのcallbackを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider, $bootstrapAdmin。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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

        if (Auth::check() && session('social_link_provider') === $provider) {
            session()->forget('social_link_provider');

            return $this->linkSocialAccount($provider, $socialUser->getId(), $email, [
                'name' => $socialUser->getName(),
                'nickname' => $socialUser->getNickname(),
                'avatar' => $socialUser->getAvatar(),
            ]);
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
    /**
     * 目的: Social Authのlinkredirectを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function linkRedirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        session(['social_link_provider' => $provider]);

        return Socialite::driver($provider)->redirect();
    }
    /**
     * 目的: Social Authのlinkcallbackを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function linkCallback(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        $socialUser = Socialite::driver($provider)->user();
        $providerUserId = (string) $socialUser->getId();
        $email = strtolower((string) ($socialUser->getEmail() ?? ''));

        return $this->linkSocialAccount($provider, $providerUserId, $email, [
            'name' => $socialUser->getName(),
            'nickname' => $socialUser->getNickname(),
            'avatar' => $socialUser->getAvatar(),
        ]);
    }
    /**
     * 目的: Social Authのunlinkを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function unlink(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        Auth::user()
            ->authProviders()
            ->where('provider', $provider)
            ->delete();

        return redirect()->route('profile.edit')->with('status', 'social-unlinked');
    }
    /**
     * 目的: Social Authのlinksocialaccountを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider, $providerUserId, $email, $payload。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function linkSocialAccount(string $provider, string $providerUserId, string $email, array $payload): RedirectResponse
    {
        $user = Auth::user();

        $linkedToOther = UserAuthProvider::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->where('user_id', '<>', $user->id)
            ->exists();

        if ($linkedToOther) {
            return redirect()->route('profile.edit')
                ->withErrors(['social' => 'このSNSアカウントは別ユーザーに連携済みです。']);
        }

        UserAuthProvider::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            [
                'provider_user_id' => $providerUserId,
                'provider_email' => $email ?: null,
                'provider_payload' => $payload,
                'linked_at' => now(),
                'last_used_at' => now(),
            ]
        );

        return redirect()->route('profile.edit')->with('status', 'social-linked');
    }
    /**
     * 目的: Social Authのensureproviderissupportedを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function ensureProviderIsSupported(string $provider): void
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 404);
    }
    /**
     * 目的: Social Authのensureproviderisconfiguredを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $provider。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
