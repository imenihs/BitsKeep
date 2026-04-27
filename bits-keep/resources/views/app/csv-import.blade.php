<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>CSVインポート - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => 'CSVインポート'])
<div id="app" data-page="csv-import" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'CSVインポート', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">CSVインポート</h1>
    @unless ($isAdmin)
    <div class="mt-3 inline-flex flex-col rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] px-4 py-3 feature-disabled">
      <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>CSV取込</span></div>
      <div class="mt-1 text-xs opacity-70">管理者のみ実行できます</div>
    </div>
    @endunless
  </header>

  <div class="grid gap-3 mb-6 md:grid-cols-4">
    <div v-for="card in stepCards" :key="card.key"
      class="rounded-2xl border px-4 py-3 bg-[var(--color-card-even)]"
      :class="{
        'border-[var(--color-tag-ok)]': card.state === 'ok',
        'border-[var(--color-tag-warning)]': card.state === 'warning',
        'border-[var(--color-tag-eol)]': card.state === 'danger',
        'border-[var(--color-border)]': card.state === 'pending'
      }">
      <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">@{{ card.label }}</div>
      <div class="mt-1 text-sm font-semibold break-words">@{{ card.value }}</div>
    </div>
  </div>

  <!-- ステップインジケータ -->
  <div class="flex items-center gap-2 mb-8">
    <template v-for="(label, i) in ['アップロード', 'プレビュー', '確認', '完了']" :key="i">
      <div class="flex items-center gap-2">
        <div :class="step > i + 1 ? 'bg-emerald-500 text-white' : step === i + 1 ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] opacity-50'"
          class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">
          @{{ step > i + 1 ? '✓' : i + 1 }}
        </div>
        <span :class="step === i + 1 ? 'font-medium' : 'opacity-50'" class="text-sm">@{{ label }}</span>
      </div>
      <div v-if="i < 3" class="flex-1 h-px bg-[var(--color-border)] mx-1"></div>
    </template>
  </div>

  <!-- ═══════ Step 1: ファイル選択 ═══════ -->
  <div v-if="step === 1">
    <div class="border-2 border-dashed border-[var(--color-border)] rounded-xl p-10 text-center">
      <p class="text-4xl mb-3">📁</p>
      <p class="font-medium mb-1">CSVファイルを選択</p>
      <p class="text-xs opacity-60 mb-4">UTF-8 または Shift-JIS（BOMあり）、最大5MB</p>
      <input ref="fileInput" type="file" accept=".csv,.txt" @change="onFileChange" class="hidden" />
      <button @click="fileInput.click()" class="border border-[var(--color-border)] px-4 py-2 rounded hover:bg-[var(--color-card-odd)]">
        ファイルを選ぶ
      </button>
      <p v-if="selectedFile" class="mt-3 text-sm text-emerald-600 font-medium">@{{ selectedFile.name }}</p>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-3">
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3 text-xs">
        <div class="font-semibold">必須列</div>
        <code class="mt-1 block break-all opacity-70">part_number, common_name</code>
      </div>
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3 text-xs">
        <div class="font-semibold">候補列</div>
        <code class="mt-1 block break-all opacity-70">procurement_status, quantity_new, category_names, package_name</code>
      </div>
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3 text-xs">
        <div class="font-semibold">完了条件</div>
        <div class="mt-1 opacity-70">登録可能行が1件以上 / 重複型番はスキップ</div>
      </div>
    </div>

    <div class="flex justify-end mt-4">
      <button @click="uploadPreview" :disabled="!selectedFile || uploading"
        class="btn-primary px-6 py-2 rounded font-medium disabled:opacity-40">
        @{{ uploading ? '処理中...' : '次へ → プレビュー' }}
      </button>
    </div>
  </div>

  <!-- ═══════ Step 2: プレビュー ═══════ -->
  <div v-if="step === 2">
    <div class="flex items-center gap-4 mb-4">
      <div class="text-sm">
        <span class="font-medium">@{{ preview.total }}</span> 行を解析
        <span v-if="preview.errors.length > 0" class="text-red-500 ml-2">/ @{{ preview.errors.length }} 件エラー</span>
      </div>
      <button @click="step = 1" class="text-sm opacity-60 hover:opacity-100 underline">← 戻る</button>
    </div>

    <!-- エラー一覧 -->
    <div v-if="preview.errors.length > 0" class="mb-4 border border-[var(--color-tag-eol)] rounded p-3 text-xs bg-[color-mix(in_srgb,var(--color-tag-eol)_6%,var(--color-bg))]">
      <p class="font-medium text-[var(--color-tag-eol)] mb-2">エラー行（インポートからスキップされます）:</p>
      <div v-for="e in preview.errors" :key="e.row" class="flex items-start gap-2 mb-1">
        <span class="bg-[var(--color-tag-eol)] text-white text-[10px] px-1 rounded flex-shrink-0">行@{{ e.row }}</span>
        <span class="text-[var(--color-tag-eol)]">@{{ e.message }}</span>
      </div>
    </div>

    <!-- プレビューテーブル -->
    <div class="overflow-x-auto max-h-96">
      <table class="w-full text-xs border-collapse">
        <thead class="sticky top-0 bg-[var(--color-bg)]">
          <tr class="border-b border-[var(--color-border)] text-left opacity-70">
            <th v-for="h in preview.headers" :key="h" class="py-1.5 pr-3 whitespace-nowrap">@{{ h }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, i) in preview.rows" :key="i"
            :class="i % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)]">
            <td v-for="h in preview.headers" :key="h" class="py-1.5 pr-3 truncate max-w-xs">
              @{{ row[h] || '-' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex justify-end mt-4">
      <button @click="goConfirm" class="btn-primary px-6 py-2 rounded font-medium">
        次へ → 確認
      </button>
    </div>
  </div>

  <!-- ═══════ Step 3: 確認 ═══════ -->
  <div v-if="step === 3">
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-xl p-4 text-center">
        <p class="text-3xl font-bold text-[var(--color-primary)]">@{{ preview.rows.length }}</p>
        <p class="text-xs mt-1 font-medium">登録予定</p>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-xl p-4 text-center">
        <p class="text-3xl font-bold text-amber-500">@{{ preview.total - preview.rows.length - (preview.errors?.length ?? 0) }}</p>
        <p class="text-xs mt-1 font-medium">重複スキップ予定</p>
        <p class="text-[10px] opacity-50 mt-0.5">型番が既存と重複</p>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-xl p-4 text-center">
        <p class="text-3xl font-bold text-[var(--color-tag-eol)]">@{{ preview.errors?.length ?? 0 }}</p>
        <p class="text-xs mt-1 font-medium">エラースキップ</p>
        <p class="text-[10px] opacity-50 mt-0.5">フォーマット不正など</p>
      </div>
    </div>
    <div class="flex justify-between">
      <button @click="step = 2" class="px-4 py-2 border border-[var(--color-border)] rounded">← 戻る</button>
      <button @click="commitImport" :disabled="committing"
        class="btn-primary px-6 py-2 rounded font-medium disabled:opacity-40">
        @{{ committing ? 'インポート中...' : '✓ インポート実行' }}
      </button>
    </div>
  </div>

  <!-- ═══════ Step 4: 完了 ═══════ -->
  <div v-if="step === 4" class="text-center py-10">
    <p class="text-5xl mb-4">✅</p>
    <p class="text-xl font-bold mb-2">インポート完了</p>
    <p class="mb-1"><span class="text-2xl font-bold text-emerald-600">@{{ result.created }}</span> 件を登録しました</p>
    <div v-if="result.skipped.length > 0" class="mt-4 text-sm opacity-70">
      <p class="mb-1">スキップ: @{{ result.skipped.length }} 件</p>
      <div v-for="s in result.skipped" :key="s.row" class="text-xs">行@{{ s.row }} - @{{ s.reason }}</div>
    </div>
    <div class="flex justify-center gap-3 mt-6">
      <a href="{{ route('components.index') }}" class="btn-primary px-4 py-2 rounded">部品一覧へ</a>
      <button @click="reset" class="px-4 py-2 border border-[var(--color-border)] rounded">続けてインポート</button>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">@{{ t.msg }}</div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => 'CSVインポート', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
