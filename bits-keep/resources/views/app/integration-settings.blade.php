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
@include('partials.app-header', ['current' => '連携設定'])
<div id="app" data-page="integration-settings" data-can-edit="{{ auth()->user()->isEditor() ? '1' : '0' }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-4xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '連携設定', 'current' => true]]])

  <header class="flex items-center justify-between mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">連携設定</h1>
    <div class="flex items-center gap-2">
      <span class="feature-lock">{{ auth()->user()->isEditor() ? '編' : '閲' }}</span>
      <span class="tag">{{ auth()->user()->role === 'admin' ? '管理者' : (auth()->user()->role === 'editor' ? '編集者' : '閲覧者') }}</span>
      <button @click="fetchStatus" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">再確認</button>
    </div>
  </header>

  <section class="rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-card-odd)] shadow-sm">
    <div class="flex items-center gap-3 mb-5">
      <p class="text-xs uppercase tracking-[0.2em] opacity-50">Notion</p>
      <h2 class="text-xl font-bold">案件同期</h2>
    </div>

    <div v-if="loading" class="text-sm opacity-50 py-4">確認中...</div>

    <div v-else class="space-y-4">

      {{-- エラー --}}
      <div v-if="statusError" class="rounded-2xl border border-[var(--color-tag-eol)] p-4 bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-eol)]">設定状態を取得できませんでした</div>
        <div class="mt-1 text-sm opacity-80">@{{ statusError }}</div>
        <button @click="fetchStatus" class="mt-3 px-3 py-2 rounded-xl border border-[var(--color-tag-eol)] text-sm">再試行</button>
      </div>

      {{-- 接続状態 --}}
      <div class="rounded-2xl border p-4"
        :class="notion.configured
          ? 'border-[var(--color-tag-ok)] bg-[color-mix(in_srgb,var(--color-tag-ok)_10%,var(--color-bg))]'
          : 'border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]'">
        <div class="flex items-center gap-3">
          <span class="text-lg">@{{ notion.configured ? '✅' : '⚠️' }}</span>
          <div>
            <div class="font-semibold" :class="notion.configured ? 'text-[var(--color-tag-ok)]' : 'text-[var(--color-tag-warning)]'">
              @{{ notion.configured ? 'Notion同期は利用可能' : 'APIトークンが未設定' }}
            </div>
            <div class="text-sm opacity-70 mt-0.5" v-if="notion.configured">
              @{{ notion.discovery_mode === 'root-page' ? 'ルートページ配下を探索' : 'アクセス可能なページ全体を検索' }}
            </div>
          </div>
        </div>
        <div v-if="notion.health && notion.health.status !== 'ok'" class="mt-3 text-sm opacity-80">
          @{{ notion.health.message }}
        </div>
      </div>

      {{-- トークン / ルートページカード --}}
      <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)]">
          <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-2">API トークン</div>
          <div class="flex items-center gap-2">
            <span class="font-semibold">@{{ notion.token_configured ? '設定済み' : '未設定' }}</span>
            <span v-if="notion.token_configured" class="tag tag-ok">保存済み</span>
          </div>
          <div v-if="notion.token_preview" class="mt-2 font-mono text-sm break-all opacity-60">@{{ notion.token_preview }}</div>
          <button v-if="notion.token_configured && canEdit" @click="clearToken" :disabled="deletingToken"
            class="mt-3 px-3 py-1.5 rounded-xl border border-[var(--color-tag-eol)] text-[var(--color-tag-eol)] text-xs disabled:opacity-50">
            @{{ deletingToken ? '削除中...' : 'トークンを削除' }}
          </button>
        </div>
        <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)]">
          <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-2">ルートページ URL <span class="normal-case">（任意）</span></div>
          <div class="flex items-center gap-2">
            <span class="font-semibold">@{{ notion.root_page_configured ? '設定済み' : '未設定' }}</span>
            <span v-if="notion.root_page_configured" class="tag tag-ok">保存済み</span>
          </div>
          <div v-if="notion.root_page_url" class="mt-2 text-xs break-all opacity-60">@{{ notion.root_page_url }}</div>
          <button v-if="notion.root_page_configured && canEdit" @click="clearRootPage" :disabled="deletingRootPage"
            class="mt-3 px-3 py-1.5 rounded-xl border border-[var(--color-tag-eol)] text-[var(--color-tag-eol)] text-xs disabled:opacity-50">
            @{{ deletingRootPage ? '削除中...' : 'URLを削除' }}
          </button>
        </div>
      </div>

      {{-- 設定フォーム --}}
      <div class="rounded-2xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
        <div class="font-semibold mb-4">設定を変更</div>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium mb-1">Notion API トークン</label>
            <input v-model="form.api_token" type="password" class="input-text w-full px-3 py-2 text-sm"
              placeholder="空欄のままにすると既存設定を維持" :disabled="!canEdit" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ルートページ URL <span class="opacity-50 font-normal">（任意）</span></label>
            <input v-model="form.root_page_url" type="url" class="input-text w-full px-3 py-2 text-sm"
              placeholder="https://www.notion.so/..." :disabled="!canEdit" />
          </div>
          <div v-if="saveMessage" class="text-sm text-[var(--color-tag-ok)] font-semibold">@{{ saveMessage }}</div>
          <div v-if="saveError" class="text-sm text-[var(--color-tag-eol)] font-semibold">@{{ saveError }}</div>
          <div class="flex gap-3 pt-1">
            <button @click="save" :disabled="saving || !canEdit"
              class="btn-primary px-4 py-2 rounded text-sm font-medium disabled:opacity-50"
              :title="!canEdit ? 'editor以上の権限が必要です' : ''">
              @{{ saving ? '保存中...' : '保存' }}
            </button>
            <a href="{{ route('projects.index') }}" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">案件管理へ</a>
            @if (auth()->user()->role === 'admin')
            <a href="{{ route('users.index') }}" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">ユーザー管理</a>
            @endif
          </div>
        </div>
      </div>

    </div>
  </section>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '連携設定', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
