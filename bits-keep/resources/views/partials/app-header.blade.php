@php
    // 目的: 共通ヘッダで現在画面名、ログインユーザー、権限バッジの表示情報を組み立てる。
    // 入力: 呼び出し元の $current と認証ユーザー。出力: ヘッダ表示用の $current/$user/$roleMeta。
    // 動作条件: 認証済み画面または閲覧者扱いで表示できる画面。副作用: なし。
    $current = $current ?? 'BitsKeep';
    $user = auth()->user();
    $roleMeta = match($user?->role) {
        'admin' => ['icon' => '管', 'label' => '管理', 'desc' => '管理者', 'class' => 'role-admin'],
        'editor' => ['icon' => '編', 'label' => '編集', 'desc' => '編集者', 'class' => 'role-editor'],
        default => ['icon' => '閲', 'label' => '閲覧', 'desc' => '閲覧者', 'class' => 'role-viewer'],
    };
@endphp

<header class="app-shell-header">
  <div class="app-shell-header__inner">
    <a href="{{ route('dashboard') }}" class="app-shell-brand no-underline text-inherit">
      <img src="{{ asset('brand/bitskeep-logo-mark.png') }}" alt="BitsKeep" class="app-shell-brand__logo" />
      <div class="min-w-0">
        <div class="app-shell-brand__eyebrow">BitsKeep</div>
        <div class="app-shell-brand__current">{{ $current }}</div>
      </div>
    </a>

    <div class="app-shell-user">
      <a href="{{ route('help.index') }}" class="app-shell-link">使い方</a>
      <a href="{{ route('functions.index') }}" class="app-shell-link">全機能一覧</a>
      <span class="app-shell-user__text">ログイン: {{ $user?->name }}</span>
      <span class="role-pill {{ $roleMeta['class'] }}" title="{{ $roleMeta['desc'] }}">
        {{ $roleMeta['label'] }}
      </span>
    </div>
  </div>
</header>
