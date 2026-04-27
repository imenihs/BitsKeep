<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * ユーザー管理 API（SCR-008）
 * admin ロールのみアクセス可
 */
class UserController extends Controller
{
    // GET /api/users
    public function index(Request $request)
    {
        // 管理者のみ
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $users = User::with('authProviders')->orderBy('created_at')->get()
            ->map(fn ($u) => $this->format($u));

        return ApiResponse::success($users);
    }

    // PATCH /api/users/{user}/role
    public function updateRole(Request $request, User $user)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
        ]);

        // 最後の管理者を降格させようとする場合は拒否
        if ($user->role === 'admin' && $validated['role'] !== 'admin') {
            $adminCount = User::where('role', 'admin')->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return ApiResponse::error('管理者が他にいないため、この変更はできません');
            }
        }

        $user->update($validated);

        return ApiResponse::success($this->format($user));
    }

    // PATCH /api/users/{user}/active
    public function updateActive(Request $request, User $user)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        // 自分自身を無効化しようとする場合は拒否
        if ($user->id === $request->user()->id) {
            return ApiResponse::error('自分自身を無効化することはできません');
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update($validated);

        return ApiResponse::success($this->format($user));
    }

    // PATCH /api/users/{user}/name
    public function updateName(Request $request, User $user)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return ApiResponse::success($this->format($user));
    }

    // PATCH /api/users/{user}/email
    public function updateEmail(Request $request, User $user)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return ApiResponse::success($this->format($user));
    }

    // PATCH /api/users/{user}/password
    public function updatePassword(Request $request, User $user)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return ApiResponse::success($this->format($user), 'パスワードをリセットしました');
    }

    // POST /api/users/invite
    public function invite(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
        ]);

        // 仮パスワード生成（初回ログイン時に変更）
        $tempPassword = \Illuminate\Support\Str::random(12);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($tempPassword),
            'is_active' => true,
            'invited_at' => now(),
        ]);

        Mail::to($user->email)->send(new UserInvitationMail($user, $tempPassword));

        return ApiResponse::created([
            'user' => $this->format($user),
            'temp_password' => $tempPassword,
            'mail_sent' => true,
        ], 'ユーザーを招待しました');
    }

    private function format(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'is_active' => $u->is_active,
            'auth_providers' => $u->authProviders
                ->map(fn ($provider) => [
                    'provider' => $provider->provider,
                    'email' => $provider->provider_email,
                    'linked_at' => $provider->linked_at?->toIso8601String(),
                    'last_used_at' => $provider->last_used_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'invited_at' => $u->invited_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
