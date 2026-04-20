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
<div id="app" data-page="master-list" data-tab="categories" data-can-edit="{{ $canEdit ? '1' : '0' }}" data-is-admin="{{ $isAdmin ? '1' : '0' }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'マスタ管理', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">マスタ管理</h1>
    <p class="text-sm opacity-60 mt-1">分類・パッケージ分類・詳細パッケージ・スペック種別の管理</p>
  </header>

  <!-- タブ切り替え -->
  <div class="flex gap-1 mb-6 border-b border-[var(--color-border)]">
    <button v-for="tab in [{id:'categories',label:'分類'},{id:'package-groups',label:'パッケージ分類'},{id:'packages',label:'詳細パッケージ'},{id:'spec-types',label:'スペック種別'}]"
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
    <button @click="activeTab === 'categories' ? fetchCategories() : activeTab === 'package-groups' ? fetchPackageGroups() : activeTab === 'packages' ? fetchPackages() : fetchSpecTypes()"
      class="px-3 py-1.5 rounded border border-[var(--color-border)] text-xs">再試行</button>
  </div>

  <!-- ═══════════════════════════════ 分類タブ ══════════════════════════════ -->
  <div v-if="activeTab === 'categories'">
    <div class="flex justify-end mb-4">
      @if ($canEdit)
      <button @click="openCatAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span> + 分類を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">編</span><span>+ 分類を追加</span></div>
        <div class="mt-1 text-xs opacity-70">閲覧者のため追加できません</div>
      </div>
      @endif
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-2 w-6"></th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(c, index) in categories" :key="c.id"
          :draggable="canEdit && !c.deleted_at ? 'true' : 'false'"
          @dragstart="catDnD.start(index)"
          @dragover="catDnD.over($event, index)"
          @dragend="catDnD.end()"
          @drop.prevent="catDnD.drop(index)"
          :class="[
            c.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="canEdit && !c.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ c.name }}</span>
              <span v-if="c.deleted_at" class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                アーカイブ済み
              </span>
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ c.description || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ c.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openCatEdit(c)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button v-if="!c.deleted_at" @click="archiveCategory(c)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              <button v-else @click="restoreCategory(c)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
              <button v-if="c.can_force_delete" @click="forceDeleteCategory(c)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">完全削除</button>
            </div>
            <div v-if="c.deleted_at && !c.can_force_delete" class="mt-1 text-[10px] opacity-60">@{{ c.force_delete_reason }}</div>
          </td>
        </tr>
        <tr v-if="categories.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ══════════════════════════ パッケージタブ ══════════════════════════════ -->
  <div v-if="activeTab === 'package-groups'">
    <div class="flex justify-end mb-4">
      @if ($canEdit)
      <button @click="openPkgGroupAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span> + パッケージ分類を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">編</span><span>+ パッケージ分類を追加</span></div>
        <div class="mt-1 text-xs opacity-70">閲覧者のため追加できません</div>
      </div>
      @endif
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-2 w-6"></th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(group, index) in packageGroups" :key="group.id"
          :draggable="canEdit && !group.deleted_at ? 'true' : 'false'"
          @dragstart="pgDnD.start(index)"
          @dragover="pgDnD.over($event, index)"
          @dragend="pgDnD.end()"
          @drop.prevent="pgDnD.drop(index)"
          :class="[
            group.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="canEdit && !group.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ group.name }}</span>
              <span v-if="group.deleted_at" class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">アーカイブ済み</span>
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ group.description || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ group.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openPkgGroupEdit(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button v-if="!group.deleted_at" @click="archivePackageGroup(group)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              <button v-else @click="restorePackageGroup(group)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
              <button v-if="group.can_force_delete" @click="forceDeletePackageGroup(group)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">完全削除</button>
            </div>
            <div v-if="group.deleted_at && !group.can_force_delete" class="mt-1 text-[10px] opacity-60">@{{ group.force_delete_reason }}</div>
          </td>
        </tr>
        <tr v-if="packageGroups.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">パッケージ分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div v-if="activeTab === 'packages'">
    <div class="flex justify-end mb-4">
      @if ($canEdit)
      <button @click="openPkgAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span> + 詳細パッケージを追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">編</span><span>+ パッケージを追加</span></div>
        <div class="mt-1 text-xs opacity-70">閲覧者のため追加できません</div>
      </div>
      @endif
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-2 w-6"></th>
          <th class="py-2 pr-4">パッケージ分類</th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(p, index) in packages" :key="p.id"
          :draggable="canEdit && !p.deleted_at ? 'true' : 'false'"
          @dragstart="pkgDnD.start(index)"
          @dragover="pkgDnD.over($event, index)"
          @dragend="pkgDnD.end()"
          @drop.prevent="pkgDnD.drop(index)"
          :class="[
            p.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="canEdit && !p.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 text-xs opacity-70">@{{ p.package_group?.name || '未分類' }}</td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ p.name }}</span>
              <span v-if="p.deleted_at" class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                アーカイブ済み
              </span>
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ p.description || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ p.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openPkgEdit(p)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button v-if="!p.deleted_at" @click="archivePackage(p)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              <button v-else @click="restorePackage(p)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
              <button v-if="p.can_force_delete" @click="forceDeletePackage(p)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">完全削除</button>
            </div>
            <div v-if="p.deleted_at && !p.can_force_delete" class="mt-1 text-[10px] opacity-60">@{{ p.force_delete_reason }}</div>
          </td>
        </tr>
        <tr v-if="packages.length === 0">
          <td colspan="5" class="py-8 text-center opacity-40">詳細パッケージが登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ══════════════════════════ スペック種別タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'spec-types'">
    <div class="flex justify-end mb-4">
      @if ($isAdmin)
      <button @click="openStAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + スペック種別を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ スペック種別を追加</span></div>
        <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
      </div>
      @endif
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-2 w-6"></th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">型</th>
          <th class="py-2 pr-4">単位候補</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(s, index) in specTypes" :key="s.id"
          :draggable="isAdmin && !s.deleted_at ? 'true' : 'false'"
          @dragstart="stDnD.start(index)"
          @dragover="stDnD.over($event, index)"
          @dragend="stDnD.end()"
          @drop.prevent="stDnD.drop(index)"
          :class="[
            s.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="isAdmin && !s.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ s.name }}</span>
              <span v-if="s.deleted_at" class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                アーカイブ済み
              </span>
            </div>
          </td>
          <td class="py-2 pr-4 text-xs opacity-70">
            <div>@{{ s.value_type }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ s.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2 pr-4 text-xs">
            <span v-if="s.units?.[0]" class="inline-block bg-[var(--color-card-even)] border border-[var(--color-border)] rounded px-1.5 py-0.5 mr-1 mb-1">
              @{{ s.units[0].unit }}
            </span>
            <span v-else class="opacity-40">-</span>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button v-if="!s.deleted_at" @click="archiveSpecType(s)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              <button v-else @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
              <button v-if="s.can_force_delete" @click="forceDeleteSpecType(s)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">完全削除</button>
            </div>
            <div v-if="s.deleted_at && !s.can_force_delete" class="mt-1 text-[10px] opacity-60">@{{ s.force_delete_reason }}</div>
          </td>
        </tr>
        <tr v-if="specTypes.length === 0">
          <td colspan="5" class="py-8 text-center opacity-40">スペック種別が登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ═══════════════ 分類モーダル ════════════════ -->
  <div v-if="catModal.open" class="modal-overlay" v-esc="closeCatModal">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ catModal.isEdit ? '分類編集' : '分類追加' }}</h2>
        <button @click="closeCatModal" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="catModal.form.name" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="catModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="catModal.form.sort_order" type="number"
            class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeCatModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveCategory" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ パッケージモーダル ════════════════ -->
  <div v-if="pkgModal.open" class="modal-overlay" v-esc="closePkgModal">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgModal.isEdit ? '詳細パッケージ編集' : '詳細パッケージ追加' }}</h2>
        <button @click="closePkgModal" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">パッケージ分類 <span class="text-red-500">*</span></label>
          <select v-model="pkgModal.form.package_group_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="">選択してください</option>
            <option v-for="group in packageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="pkgModal.form.name" type="text" placeholder="例: 0402, SOT-23"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="pkgModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="pkgModal.form.sort_order" type="number"
            class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closePkgModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackage" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <div v-if="pkgGroupModal.open" class="modal-overlay" v-esc="closePkgGroupModal">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgGroupModal.isEdit ? 'パッケージ分類編集' : 'パッケージ分類追加' }}</h2>
        <button @click="closePkgGroupModal" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="pkgGroupModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="pkgGroupModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="pkgGroupModal.form.sort_order" type="number" class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closePkgGroupModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackageGroup" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ スペック種別モーダル ════════════════ -->
  <div v-if="stModal.open" class="modal-overlay modal-top" v-esc="closeStModal">
    <div class="modal-window modal-lg max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ stModal.isEdit ? 'スペック種別編集' : 'スペック種別追加' }}</h2>
        <button @click="closeStModal" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="stModal.form.name" type="text" placeholder="例: 静電容量, 耐圧"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="stModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium mb-1">値の型</label>
            <select v-model="stModal.form.value_type"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
              <option value="numeric">数値</option>
              <option value="text">テキスト</option>
              <option value="boolean">真偽値</option>
            </select>
          </div>
          <div class="w-24">
            <label class="block text-sm font-medium mb-1">並び順</label>
            <input v-model.number="stModal.form.sort_order" type="number"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </div>
        </div>

        <!-- 単位（数値型のみ） -->
        <div v-if="stModal.form.value_type === 'numeric'">
          <label class="text-sm font-medium block mb-2">単位</label>
          <input v-model="stModal.form.unit" type="text" placeholder="例: μF"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          <p class="text-xs opacity-50 mt-1">不要なら空欄のまま保存します。</p>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeStModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveSpecType" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

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
