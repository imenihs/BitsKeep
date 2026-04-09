<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>連携設定 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="integration-settings" data-can-edit="{{ auth()->user()->isEditor() ? '1' : '0' }}" class="p-6 max-w-4xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>設定</span>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">連携設定</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">連携設定</h1>
    <p class="text-sm opacity-60 mt-1">外部サービス連携の設定状態を確認します</p>
    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
      <span class="tag">{{ auth()->user()->role === 'admin' ? '管理者' : (auth()->user()->role === 'editor' ? '編集者' : '閲覧者') }}</span>
      <span class="opacity-60">現在の権限で可能な操作が表示されます</span>
    </div>
    <p v-if="!canEdit" class="text-sm text-[var(--color-tag-warning)] font-semibold mt-2">
      このアカウントは閲覧専用です。連携設定の変更には editor 以上の権限が必要です。
    </p>
  </header>

  <section class="rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-card-odd)] shadow-sm">
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <p class="text-xs uppercase tracking-[0.2em] opacity-50">Notion</p>
        <h2 class="text-xl font-bold mt-1">案件同期</h2>
      </div>
      <button @click="fetchStatus" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
        再確認
      </button>
    </div>

    <div v-if="loading" class="text-sm opacity-50">確認中...</div>

    <div v-else class="space-y-4">
      <div v-if="statusError" class="rounded-2xl border border-[var(--color-tag-eol)] p-4 bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-eol)]">Notion設定状態を取得できませんでした</div>
        <div class="mt-1 text-sm opacity-80">@{{ statusError }}</div>
        <div class="mt-3">
          <button @click="fetchStatus" class="px-3 py-2 rounded-xl border border-[var(--color-tag-eol)] text-sm">再試行</button>
        </div>
      </div>

      <div class="rounded-2xl border p-4"
        :class="notion.configured ? 'border-[var(--color-tag-ok)] bg-[color-mix(in_srgb,var(--color-tag-ok)_10%,var(--color-bg))]' : 'border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]'">
        <div class="font-semibold" :class="notion.configured ? 'text-[var(--color-tag-ok)]' : 'text-[var(--color-tag-warning)]'">
          @{{ notion.configured ? 'Notion同期は利用可能です' : 'Notion同期は未設定です' }}
        </div>
        <div class="mt-1 text-sm opacity-80" v-if="!notion.configured">
          不足設定: @{{ notion.missing.join(', ') }}
        </div>
        <div class="mt-1 text-sm opacity-70" v-else>
          検索モード: @{{ notion.discovery_mode === 'root-page' ? '指定ルートページ配下を探索' : 'アクセス可能なページ全体を検索' }}
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)]">
          <div class="text-xs uppercase tracking-[0.2em] opacity-50">NOTION_API_TOKEN</div>
          <div class="mt-2 font-semibold">@{{ notion.token_configured ? '設定済み' : '未設定' }}</div>
          <div class="mt-1 text-sm opacity-60">画面設定を優先し、未設定時のみ .env を初期値として参照</div>
        </div>
        <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)]">
          <div class="text-xs uppercase tracking-[0.2em] opacity-50">NOTION_ROOT_PAGE_URL</div>
          <div class="mt-2 font-semibold">@{{ notion.root_page_configured ? '設定済み' : '未設定（任意）' }}</div>
          <div class="mt-1 text-sm opacity-60">入力された URL からページIDを自動抽出。未設定なら共有済みNotion全体を検索</div>
        </div>
      </div>

      <div class="rounded-2xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
        <div class="font-semibold mb-4">Notion連携を設定</div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Notion API トークン</label>
            <input v-model="form.api_token" type="password" class="input-text w-full px-3 py-2 text-sm"
              placeholder="新しいトークンを入力。空欄なら既存設定を維持" />
            <p class="mt-1 text-xs opacity-60">新しい値を入力したときだけ更新します。クリアしたい場合は空欄保存ではなく、別途削除操作が必要です。</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ルートページ URL（任意）</label>
            <input v-model="form.root_page_url" type="url" class="input-text w-full px-3 py-2 text-sm"
              placeholder="https://www.notion.so/..." />
            <p class="mt-1 text-xs opacity-60">指定するとその配下だけを探索します。空欄なら、インテグレーションがアクセスできるページ全体から `01_案件管理` を検索します。</p>
          </div>
          <div v-if="saveMessage" class="text-sm text-[var(--color-tag-ok)] font-semibold">@{{ saveMessage }}</div>
          <div v-if="saveError" class="text-sm text-[var(--color-tag-eol)] font-semibold">@{{ saveError }}</div>
          <div v-if="!canEdit" class="text-sm text-[var(--color-tag-warning)] font-semibold">
            現在のログイン権限では保存できません。viewer は設定閲覧のみ可能です。
          </div>
          <div class="flex gap-3">
            <button @click="save" :disabled="saving || !canEdit" class="btn-primary px-4 py-2 rounded text-sm font-medium disabled:opacity-50">
              @{{ !canEdit ? '保存権限なし' : (saving ? '保存中...' : '設定を保存') }}
            </button>
            <button @click="fetchStatus" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm">
              再読込
            </button>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
        <div class="font-semibold mb-2">設定手順</div>
        <ol class="list-decimal list-inside text-sm opacity-80 space-y-1">
          <li>Notionインテグレーションを作成し、対象ワークスペースに接続する</li>
          <li>同期対象の `01_案件管理` DB またはその親ページをインテグレーションへ共有する</li>
          <li>この画面で `Notion API トークン` を保存する。必要なら `ルートページ URL` も保存する</li>
          <li>必要なら設定反映後にこの画面で再確認し、案件管理から同期を実行する</li>
        </ol>
      </div>

      <div class="rounded-2xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
        <div class="font-semibold mb-2">権限と変更導線</div>
        @if (auth()->user()->role === 'admin')
          <p class="text-sm opacity-80">連携設定を変更できる権限があります。必要に応じて、ユーザー管理から他ユーザーへ `editor` / `viewer` を割り当ててください。</p>
          <div class="mt-3">
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-[var(--color-border)] no-underline hover:border-[var(--color-primary)] transition-colors">
              ユーザー管理を開く
            </a>
          </div>
        @elseif (auth()->user()->role === 'editor')
          <p class="text-sm opacity-80">このアカウントは連携設定を変更できます。ほかのユーザーの権限変更は管理者が `ユーザー管理` で行います。</p>
        @else
          <p class="text-sm opacity-80">このアカウントは設定の閲覧のみ可能です。権限変更は管理者が `ユーザー管理` 画面で行うため、editor 以上への変更を依頼してください。</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-[var(--color-border)] no-underline hover:border-[var(--color-primary)] transition-colors">
              プロフィールを開く
            </a>
            <a href="{{ route('functions.index') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-[var(--color-border)] no-underline hover:border-[var(--color-primary)] transition-colors">
              全機能一覧へ戻る
            </a>
          </div>
        @endif
      </div>

      <div class="flex gap-3">
        <a href="{{ route('projects.index') }}" class="btn-primary px-4 py-2 rounded text-sm font-medium no-underline">案件管理へ戻る</a>
      </div>
    </div>
  </section>

</div>
</body>
</html>
