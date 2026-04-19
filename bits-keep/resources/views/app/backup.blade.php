<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>DBバックアップ - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => 'DBバックアップ'])
<div id="app" data-page="backup" class="px-4 py-4 sm:px-6 sm:py-6 max-w-3xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'DBバックアップ', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">DBバックアップ</h1>
    <p class="text-sm opacity-60 mt-1">PostgreSQL のダンプ取得・書き戻し（管理者専用）</p>
  </header>

  <!-- ダウンロード -->
  <section class="card p-6 bg-[var(--color-card-even)] mb-6 block">
    <h2 class="font-bold mb-1">バックアップをダウンロード</h2>
    <p class="text-sm opacity-60 mb-4">現時点の DB 全体を <code>.sql.gz</code> 形式でエクスポートします。</p>
    <button @click="downloadBackup" :disabled="downloading"
      class="btn btn-primary px-5 py-2 rounded text-sm disabled:opacity-50">
      @{{ downloading ? 'ダウンロード中...' : '📥 ダウンロード' }}
    </button>
    <div v-if="downloadError" class="mt-3 text-sm text-[var(--color-tag-eol)]">@{{ downloadError }}</div>
  </section>

  <!-- リストア -->
  <section class="card p-6 bg-[var(--color-card-even)] mb-6 block">
    <h2 class="font-bold mb-1">バックアップから書き戻す</h2>
    <p class="text-sm opacity-60 mb-1"><strong class="text-[var(--color-tag-eol)]">⚠ 現在のDBデータが上書きされます。</strong></p>
    <p class="text-sm opacity-60 mb-4"><code>.sql</code> または <code>.sql.gz</code> ファイルをアップロードしてください。</p>

    <div class="space-y-3">
      <input type="file" accept=".sql,.gz" @change="onFileChange"
        class="input-text w-full text-sm" />
      <div v-if="selectedFile" class="text-xs opacity-60">選択: @{{ selectedFile.name }} (@{{ formatSize(selectedFile.size) }})</div>

      <button @click="startRestore" :disabled="!selectedFile || restoring"
        class="btn px-5 py-2 rounded text-sm border border-[var(--color-tag-eol)] text-[var(--color-tag-eol)] disabled:opacity-40">
        @{{ restoring ? '書き戻し中...' : '⚠ この内容で書き戻す' }}
      </button>
    </div>

    <div v-if="restoreResult" :class="restoreResult.ok ? 'text-[var(--color-tag-ok)]' : 'text-[var(--color-tag-eol)]'"
      class="mt-3 text-sm font-medium">
      @{{ restoreResult.message }}
    </div>
  </section>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">
      @{{ t.msg }}
    </div>
  </div>
</div>
</body>
</html>
