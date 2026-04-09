<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>全機能一覧 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div class="p-6 max-w-6xl mx-auto">
  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">全機能一覧</span>
  </nav>

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
        <a href="{{ route('settings.integrations') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">連携設定</a>
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
        <a href="{{ route('profile.edit') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">プロフィール</a>
        <div class="rounded-xl border border-[var(--color-border)] px-4 py-3 text-sm opacity-70">
          現在の権限: {{ auth()->user()->role === 'admin' ? '管理者' : (auth()->user()->role === 'editor' ? '編集者' : '閲覧者') }}
        </div>
      </div>
    </section>

    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-odd)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Permissions</div>
      <div class="space-y-2">
        @if (auth()->user()->role === 'admin')
          <a href="{{ route('users.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ユーザー管理</a>
          <div class="rounded-xl border border-[var(--color-border)] px-4 py-3 text-sm opacity-70">
            権限変更は `ユーザー管理` から実施します。
          </div>
        @else
          <div class="rounded-xl border border-[var(--color-border)] px-4 py-3 text-sm opacity-70">
            権限変更は管理者が `ユーザー管理` で実施します。設定保存やユーザー追加が必要なら管理者へ依頼してください。
          </div>
          <a href="{{ route('settings.integrations') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">連携設定</a>
        @endif
      </div>
    </section>

    @if (auth()->user()->role === 'admin')
    <section class="rounded-3xl border border-[var(--color-border)] p-5 bg-[var(--color-card-odd)]">
      <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-3">Admin</div>
      <div class="space-y-2">
        <a href="{{ route('users.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">ユーザー管理</a>
        <a href="{{ route('audit.index') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">操作ログ</a>
        <a href="{{ route('csv.import') }}" class="block rounded-xl border border-[var(--color-border)] px-4 py-3 no-underline hover:border-[var(--color-primary)]">CSVインポート</a>
      </div>
    </section>
    @endif
  </div>
</div>
</body>
</html>
