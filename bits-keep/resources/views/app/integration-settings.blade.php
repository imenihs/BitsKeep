<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
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
              :title="!canEdit ? '編集者以上の権限が必要です' : ''">
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

  <!-- データシート解析の実行方式 -->
  <section class="rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-card-odd)] shadow-sm mt-6">
    <div class="flex items-center gap-3 mb-5">
      <p class="text-xs uppercase tracking-[0.2em] opacity-50">データシート解析</p>
      <h2 class="text-xl font-bold">解析の実行方式</h2>
      <span class="tag" :class="datasheetEngine.available ? 'tag-ok' : 'tag-warning'">
        @{{ datasheetEngine.available ? '利用可能' : '要設定' }}
      </span>
    </div>

    <p class="text-sm opacity-70 mb-5">
      部品登録画面の「データシートから自動入力」がどこで解析するかを決めます。<br>
      利用者は解析方式を選ばずに使えるため、ここでの選択がそのまま全員の解析方式になります。
    </p>

    <div class="space-y-4">
      {{-- 利用できない場合は理由と対処をここで示す。部品登録画面で待たせた末に失敗させない --}}
      <div v-if="!datasheetEngine.available && datasheetEngine.message"
        class="rounded-2xl border border-[var(--color-tag-warning)] p-4 bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-warning)]">現在の方式は使えません</div>
        <div class="mt-1 text-sm opacity-80">@{{ datasheetEngine.message }}</div>
      </div>

      <div v-if="datasheetEngineError" class="text-sm text-[var(--color-tag-eol)] font-semibold">@{{ datasheetEngineError }}</div>
      <div v-if="datasheetEngineMessage" class="text-sm text-[var(--color-tag-ok)] font-semibold">@{{ datasheetEngineMessage }}</div>

      {{-- 方式ごとの利用可否をその場で見せる。選んだ後に使えないと分かる状態を作らない --}}
      <div class="grid gap-3 md:grid-cols-2">
        <button v-for="option in datasheetEngine.options" :key="option.key" type="button"
          @click="saveDatasheetEngine(option.key)"
          :disabled="!canEdit || datasheetEngineSaving"
          :title="!canEdit ? '編集者以上の権限が必要です' : ''"
          class="rounded-2xl border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60"
          :class="option.active
            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
            : 'border-[var(--color-border)] bg-[var(--color-card-even)] hover:border-[var(--color-primary)]'">
          <div class="flex items-center gap-2">
            <span class="font-semibold">@{{ option.label }}</span>
            <span v-if="option.active" class="tag tag-ok">採用中</span>
            <span v-if="!option.available" class="tag tag-warning">要設定</span>
          </div>
          <div v-if="option.message" class="mt-2 text-xs opacity-70">@{{ option.message }}</div>
        </button>
      </div>

      <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)] text-xs opacity-70">
        <p class="font-semibold opacity-80">サーバ内解析について</p>
        <p class="mt-1">解析はサーバ側で動くため、部品登録画面を閉じても解析は続きます。戻ると続きから表示します。</p>
        <p class="mt-1">ログインが切れた場合はこの画面に理由が出ます。サーバ上で再ログインしてください。</p>
      </div>
    </div>
  </section>

  <!-- Gemini AI 設定 -->
  <section class="rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-card-odd)] shadow-sm mt-6">
    <div class="flex items-center gap-3 mb-5">
      <p class="text-xs uppercase tracking-[0.2em] opacity-50">Google AI</p>
      <h2 class="text-xl font-bold">Gemini APIキー</h2>
      <span class="tag" :class="gemini.configured ? 'tag-ok' : ''">
        @{{ gemini.configured ? '設定済み' : '未設定' }}
      </span>
    </div>

    <p class="text-sm opacity-70 mb-5">
      PDFデータシートをアップロードすると、Gemini AI が部品情報・スペック（定格・電気的特性など）を自動抽出し、部品登録フォームに入力します。<br>
      APIキーは <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noreferrer" class="link-text">Google AI Studio</a> から取得できます。
    </p>

    <div class="space-y-4">
      {{-- 設定状態カード --}}
      <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)] flex items-center gap-4">
        <div class="flex-1">
          <div class="text-xs uppercase tracking-[0.2em] opacity-50 mb-1">API キー</div>
          <div class="flex items-center gap-2">
            <span class="font-semibold">@{{ gemini.configured ? '設定済み' : '未設定' }}</span>
            <span v-if="gemini.configured" class="tag tag-ok">保存済み</span>
          </div>
          <div v-if="gemini.key_preview" class="mt-1 font-mono text-sm break-all opacity-60">@{{ gemini.key_preview }}</div>
        </div>
        <button v-if="gemini.configured && canEdit" @click="clearGeminiKey" :disabled="geminiDeleting"
          class="px-3 py-1.5 rounded-xl border border-[var(--color-tag-eol)] text-[var(--color-tag-eol)] text-xs disabled:opacity-50 shrink-0"
          v-esc="() => {}">
          @{{ geminiDeleting ? '削除中...' : 'キーを削除' }}
        </button>
      </div>

      {{-- 設定フォーム --}}
      <div class="rounded-2xl border border-[var(--color-border)] p-5 bg-[var(--color-card-even)]">
        <div class="font-semibold mb-3">APIキーを設定</div>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium mb-1">Gemini API キー</label>
            <input v-model="geminiForm.api_key" type="password" class="input-text w-full px-3 py-2 text-sm"
              placeholder="AIza... （空欄のままにすると既存設定を維持）" :disabled="!canEdit" />
          </div>
          <div v-if="geminiMessage" class="text-sm text-[var(--color-tag-ok)] font-semibold">@{{ geminiMessage }}</div>
          <div v-if="geminiError" class="text-sm text-[var(--color-tag-eol)] font-semibold">@{{ geminiError }}</div>
          <div class="pt-1">
            <button @click="saveGemini" :disabled="geminiSaving || !canEdit || !geminiForm.api_key.trim()"
              class="btn-primary px-4 py-2 rounded text-sm font-medium disabled:opacity-50"
              :title="!canEdit ? '編集者以上の権限が必要です' : ''">
              @{{ geminiSaving ? '保存中...' : 'APIキーを保存' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '連携設定', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
