<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>マスタ管理 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($canEdit = auth()->user()->isEditor())
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => 'マスタ管理'])
<div id="app" data-page="master-list" data-tab="package-groups" data-can-edit="{{ $canEdit ? '1' : '0' }}" data-is-admin="{{ $isAdmin ? '1' : '0' }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'マスタ管理', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">マスタ管理</h1>
    <p class="text-sm opacity-60 mt-1">パッケージ分類・パッケージ詳細・部品分類・スペック詳細の管理</p>
  </header>

  <!-- タブ切り替え -->
  <div class="flex flex-wrap gap-1 mb-6 border-b border-[var(--color-border)]">
    <button v-for="tab in [
        {id:'package-groups',label:'パッケージ分類'},
        {id:'packages',label:'パッケージ詳細'},
        {id:'part-categories',label:'部品分類'},
        {id:'spec-types',label:'スペック詳細'},
        {id:'spec-candidates',label:'スペック候補設定'},
        {id:'common-spec-types',label:'共通スペック詳細'},
        {id:'tolerance-spec-types',label:'許容差スペック詳細'},
        {id:'spec-templates',label:'入力テンプレート'}
      ]"
      :key="tab.id" @click="switchTab(tab.id)"
      :class="activeTab === tab.id
        ? 'border-b-2 border-[var(--color-primary)] text-[var(--color-primary)] font-medium'
        : 'opacity-60 hover:opacity-90'"
      class="px-4 py-2 text-sm transition-colors -mb-px">
      @{{ tab.label }}
    </button>
  </div>

  <!-- エラーカード -->
  <div v-if="fetchError" class="card p-5 bg-[var(--color-card-even)] mb-4 flex items-start gap-3 text-sm border border-[var(--color-tag-eol)]">
    <span class="text-[var(--color-tag-eol)] text-lg leading-none">⚠</span>
    <div class="flex-1">
      <div class="font-semibold text-[var(--color-tag-eol)]">データの取得に失敗しました</div>
      <div class="opacity-70 mt-0.5">@{{ fetchError }}</div>
    </div>
    <button @click="retryActiveTab"
      class="px-3 py-1.5 rounded border border-[var(--color-border)] text-xs">再試行</button>
  </div>

  @include('app.master-list.tabs')

  @include('app.master-list.modals')
  <!-- 汎用確認モーダル -->
  <div v-if="confirmModal.open" class="modal-overlay" v-esc="() => confirmModal.open = false">
    <div class="modal-window modal-sm p-6">
      <h3 class="text-lg font-bold mb-3">@{{ confirmModal.title }}</h3>
      <p class="text-sm opacity-80 mb-5 whitespace-pre-line">@{{ confirmModal.message }}</p>
      <div class="flex justify-end gap-3">
        <button @click="confirmModal.open = false" class="btn text-sm px-4 py-2 rounded border border-[var(--color-border)]">キャンセル</button>
        <button @click="doConfirm" class="text-sm px-5 py-2 rounded border font-semibold transition-colors" :class="confirmModal.actionClass">@{{ confirmModal.actionLabel }}</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">
      @{{ t.msg }}
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => 'マスタ管理', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
