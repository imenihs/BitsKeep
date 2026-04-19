<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Altium連携 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => 'Altium連携'])
<div id="app" data-page="altium-link" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'Altium連携', 'current' => true]]])

  <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <div>
      <h1 class="text-2xl font-bold">Altium連携</h1>
      <p class="text-sm opacity-60 mt-1">回路図・PCBライブラリの登録管理</p>
    </div>
    @if ($isAdmin)
    <button @click="openLibAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + ライブラリを追加</button>
    @else
    <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)]">
      <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ ライブラリを追加</span></div>
      <div class="mt-1 text-xs opacity-70">管理者のみ操作できます</div>
    </div>
    @endif
  </header>

  <!-- ライブラリ一覧 -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-4">種別</th>
          <th class="py-2 pr-4">ライブラリ名</th>
          <th class="py-2 pr-4">ファイルパス</th>
          <th class="py-2 pr-4 text-right">部品数</th>
          <th class="py-2 pr-4">最終同期</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="l in libraries" :key="l.id"
          :class="l.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)]">
          <td class="py-2 pr-4">
            <span :class="l.type === 'SchLib' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700'"
              class="text-xs font-medium px-2 py-0.5 rounded">@{{ l.type }}</span>
          </td>
          <td class="py-2 pr-4 font-medium">@{{ l.name }}</td>
          <td class="py-2 pr-4 text-xs opacity-60 font-mono truncate max-w-xs">@{{ l.path }}</td>
          <td class="py-2 pr-4 text-right">@{{ l.component_count }}</td>
          <td class="py-2 pr-4 text-xs opacity-60">
            @{{ l.last_synced_at ? new Date(l.last_synced_at).toLocaleDateString('ja-JP') : '未同期' }}
          </td>
          <td class="py-2">
            <div class="flex gap-2">
              <button @click="openLibEdit(l)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
        <button @click="deleteLib(l)" class="px-2 py-1 text-xs border border-red-400 text-red-500 rounded hover:bg-red-50">削除</button>
            </div>
          </td>
        </tr>
        <tr v-if="libraries.length === 0">
          <td colspan="6" class="py-8">
            <div class="flex flex-col items-center gap-3 opacity-60">
              <div class="text-center">
                <p class="font-medium">ライブラリが登録されていません</p>
                <p class="text-xs mt-1 opacity-70">「+ ライブラリを追加」から .SchLib / .PcbLib ファイルを登録します</p>
              </div>
              @if ($isAdmin)
              <button @click="openLibAdd" class="btn-primary px-4 py-1.5 rounded text-sm opacity-100">+ ライブラリを追加</button>
              @endif
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ライブラリ追加/編集モーダル -->
  <div v-if="libModal.open" class="modal-overlay">
    <div class="modal-window modal-lg">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ libModal.isEdit ? 'ライブラリ編集' : 'ライブラリ追加' }}</h2>
        <button @click="closeLibModal" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">ライブラリ名 <span class="text-red-500">*</span></label>
          <input v-model="libModal.form.name" type="text" placeholder="例: MySchematicLib"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">種別</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="libModal.form.type" type="radio" value="SchLib" />
              <span class="text-sm">SchLib（回路図）</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="libModal.form.type" type="radio" value="PcbLib" />
              <span class="text-sm">PcbLib（フットプリント）</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">ファイルパス <span class="text-red-500">*</span></label>
          <input v-model="libModal.form.path" type="text" placeholder="例: C:\Altium\Libraries\MyLib.SchLib"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm font-mono" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">備考</label>
          <input v-model="libModal.form.note" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeLibModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveLib" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">@{{ t.msg }}</div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => 'Altium連携', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
