<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ホーム設定 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="home-settings" data-role="{{ auth()->user()->role }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">ホーム設定</span>
  </nav>

  <header class="flex items-center justify-between mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">ホーム設定</h1>
    <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">ダッシュボードへ</a>
  </header>

  <section class="rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-card-odd)] shadow-sm">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between mb-5">
      <div>
        <p class="text-xs uppercase tracking-[0.2em] opacity-50">Home</p>
        <h2 class="text-xl font-bold">主要アクション設定</h2>
      </div>
      <div class="flex flex-wrap gap-2">
        <button @click="resetQuickActions" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm">既定に戻す</button>
        <button @click="persistQuickActions" :disabled="actionsSaving"
          class="btn-primary px-4 py-2 rounded text-sm font-medium disabled:opacity-50">
          @{{ actionsSaving ? '保存中...' : '保存' }}
        </button>
      </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
      <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)]"
        @dragover="allowDrop" @drop="moveAction('visible')">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-xs uppercase tracking-[0.2em] opacity-50">表示中</div>
            <div class="font-semibold mt-1">ホームに出す主要アクション</div>
          </div>
          <div class="text-xs opacity-50">@{{ visibleActions.length }}件</div>
        </div>
        <div class="space-y-3 min-h-24">
          <div v-for="(action, index) in visibleActions" :key="action.key"
            draggable="true"
            @dragstart="startDrag('visible', index)"
            @dragover="allowDrop"
            @drop.stop="moveAction('visible', index)"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 bg-[var(--color-bg)] cursor-grab">
            <div class="text-xs opacity-35">⠿</div>
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">@{{ action.icon }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-sm">@{{ action.label }}</div>
              <div class="text-xs opacity-50 truncate">@{{ action.desc }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-[var(--color-border)] p-4 bg-[var(--color-card-even)]"
        @dragover="allowDrop" @drop="moveAction('hidden')">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-xs uppercase tracking-[0.2em] opacity-50">候補</div>
            <div class="font-semibold mt-1">追加できるアクション</div>
          </div>
          <div class="text-xs opacity-50">@{{ hiddenActions.length }}件</div>
        </div>
        <div class="space-y-3 min-h-24">
          <div v-for="(action, index) in hiddenActions" :key="action.key"
            draggable="true"
            @dragstart="startDrag('hidden', index)"
            @dragover="allowDrop"
            @drop.stop="moveAction('hidden', index)"
            class="flex items-center gap-3 rounded-2xl border border-dashed border-[var(--color-border)] px-4 py-3 bg-[var(--color-bg)] cursor-grab">
            <div class="text-xs opacity-35">⠿</div>
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">@{{ action.icon }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-sm">@{{ action.label }}</div>
              <div class="text-xs opacity-50 truncate">@{{ action.desc }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="actionsMessage" class="mt-4 text-sm font-semibold text-[var(--color-tag-ok)]">@{{ actionsMessage }}</div>
    <div v-if="actionsError" class="mt-4 text-sm font-semibold text-[var(--color-tag-eol)]">@{{ actionsError }}</div>
  </section>

</div>
</body>
</html>
