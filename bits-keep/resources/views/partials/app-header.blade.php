@php
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
      <a href="{{ route('functions.index') }}" class="app-shell-link">全機能一覧</a>
      <span class="app-shell-user__text">ログイン: {{ $user?->name }}</span>
      <span class="role-pill {{ $roleMeta['class'] }}" title="{{ $roleMeta['desc'] }}">
        <span class="role-pill__icon">{{ $roleMeta['icon'] }}</span>
      </span>
    </div>
  </div>
</header>
