<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>全機能一覧 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($canEdit = auth()->user()->isEditor())
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => '全機能一覧'])
<div class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '全機能一覧', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">全機能一覧</h1>
    <p class="text-sm opacity-60 mt-1">主要アクションに出していない画面も含めて、全導線をまとめています</p>
  </header>

  <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-odd)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Inventory</div>
      <div class="space-y-2">
        <a href="{{ route('components.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">部品一覧</a>
        <a href="{{ route('components.create') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">部品登録</a>
        <a href="{{ route('stock.alert') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">在庫警告</a>
        <a href="{{ route('locations.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">棚管理</a>
        <a href="{{ route('suppliers.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">商社管理</a>
      </div>
    </section>

    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Projects</div>
      <div class="space-y-2">
        <a href="{{ route('projects.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">案件管理</a>
        <a href="{{ route('components.compare') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">部品比較</a>
        <a href="{{ route('master.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">マスタ管理</a>
        @if ($canEdit)
        <a href="{{ route('settings.integrations') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">
          <span class="feature-lock">編</span> 連携設定
        </a>
        @else
        <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-3">
          <div class="flex items-center gap-2 font-semibold"><span class="feature-lock">編</span><span>連携設定</span></div>
          <div class="mt-1 text-xs opacity-70">閲覧者のため変更できません</div>
        </div>
        @endif
      </div>
    </section>

    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-odd)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Tools</div>
      <div class="space-y-2">
        <a href="{{ route('tools.design') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">設計解析ツール</a>
        <a href="{{ route('tools.calc') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">エンジニア電卓</a>
        <a href="{{ route('tools.network') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ネットワーク探索</a>
      </div>
    </section>

    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Account</div>
      <div class="space-y-2">
        <a href="{{ route('dashboard') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ホーム</a>
        <a href="{{ route('settings.home') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ホーム設定</a>
        <a href="{{ route('profile.edit') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">プロフィール</a>
        <div class="rounded-xl border border-[var(--color-border)] px-4 py-3 text-sm opacity-70">
          現在の権限: {{ auth()->user()->role === 'admin' ? '管理者' : (auth()->user()->role === 'editor' ? '編集者' : '閲覧者') }}
        </div>
      </div>
    </section>

    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-odd)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Permissions</div>
      <div class="space-y-2">
        @if ($isAdmin)
          <a href="{{ route('users.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ユーザー管理</a>
          <div class="rounded-xl border border-[var(--color-border)] px-4 py-3 text-sm opacity-70">
            権限変更は `ユーザー管理` から実施します。
          </div>
        @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-3">
            <div class="flex items-center gap-2 font-semibold"><span class="feature-lock">管</span><span>ユーザー管理</span></div>
            <div class="mt-1 text-xs opacity-70">管理者のみ操作できます</div>
          </div>
          @if ($canEdit)
          <a href="{{ route('settings.integrations') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]"><span class="feature-lock">編</span> 連携設定</a>
          @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-3">
            <div class="flex items-center gap-2 font-semibold"><span class="feature-lock">編</span><span>連携設定</span></div>
            <div class="mt-1 text-xs opacity-70">閲覧者は変更できません</div>
          </div>
          @endif
        @endif
      </div>
    </section>

    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-odd)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Admin</div>
      <div class="space-y-2">
        @if ($isAdmin)
        <a href="{{ route('users.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ユーザー管理</a>
        <a href="{{ route('audit.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">操作ログ</a>
        <a href="{{ route('csv.import') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">CSVインポート</a>
        <a href="{{ route('backup.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">DBバックアップ</a>
        @else
        <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-3">
          <div class="flex items-center gap-2 font-semibold"><span class="feature-lock">管</span><span>ユーザー管理</span></div>
          <div class="mt-1 text-xs opacity-70">管理者のみ操作できます</div>
        </div>
        <a href="{{ route('audit.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">操作ログ</a>
        <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-3">
          <div class="flex items-center gap-2 font-semibold"><span class="feature-lock">管</span><span>CSVインポート</span></div>
          <div class="mt-1 text-xs opacity-70">管理者のみ実行できます</div>
        </div>
        <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-3">
          <div class="flex items-center gap-2 font-semibold"><span class="feature-lock">管</span><span>DBバックアップ</span></div>
          <div class="mt-1 text-xs opacity-70">管理者のみ操作できます</div>
        </div>
        @endif
      </div>
    </section>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '全機能一覧', 'current' => true]], 'class' => 'mt-6'])
</div>
</body>
</html>
