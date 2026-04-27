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
    <p class="text-sm opacity-60 mt-1">分類・パッケージ分類/パッケージ・スペック分類/スペック項目の管理</p>
  </header>

  <!-- タブ切り替え -->
  <div class="flex gap-1 mb-6 border-b border-[var(--color-border)]">
    <button v-for="tab in [{id:'categories',label:'分類'},{id:'package-groups',label:'パッケージ分類'},{id:'packages',label:'パッケージ'},{id:'spec-groups',label:'スペック分類'},{id:'spec-types',label:'スペック項目'}]"
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
        <tr v-for="(c, index) in activeCategories" :key="c.id"
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
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ c.description || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ c.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openCatEdit(c)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="openCatDuplicate(c)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
              <button @click="archiveCategory(c)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
            </div>
          </td>
        </tr>
        <tr v-if="activeCategories.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
    <section v-if="archivedCategories.length" class="mt-6">
      <h2 class="font-bold mb-3">アーカイブ</h2>
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
          <tr v-for="c in archivedCategories" :key="`archived-cat-${c.id}`"
            :class="c.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2"></td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <span>@{{ c.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ c.description || '-' }}</div>
              <div class="mt-1 opacity-60">使用件数: @{{ c.usage_count ?? 0 }}</div>
            </td>
            <td class="py-2">
              <button @click="restoreCategory(c)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
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
        <tr v-for="(group, index) in activePackageGroups" :key="group.id"
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
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ group.description || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ group.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openPkgGroupEdit(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="openPkgGroupDuplicate(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
              <button @click="archivePackageGroup(group)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
            </div>
          </td>
        </tr>
        <tr v-if="activePackageGroups.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">パッケージ分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
    <section v-if="archivedPackageGroups.length" class="mt-6">
      <h2 class="font-bold mb-3">アーカイブ</h2>
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
          <tr v-for="group in archivedPackageGroups" :key="`archived-group-${group.id}`"
            :class="group.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2"></td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <span>@{{ group.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ group.description || '-' }}</div>
              <div class="mt-1 opacity-60">使用件数: @{{ group.usage_count ?? 0 }}</div>
            </td>
            <td class="py-2">
              <button @click="restorePackageGroup(group)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>

  <div v-if="activeTab === 'packages'" class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-4">
    <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
      <div class="px-4 py-3 border-b border-[var(--color-border)]">
        <div class="font-semibold text-sm">パッケージ分類</div>
        <div class="text-xs opacity-60 mt-1">分類を選ぶと、その中だけを並び替えます</div>
      </div>
      <div class="max-h-[60vh] overflow-y-auto">
        <button v-for="group in activePackageGroups" :key="`pkg-group-select-${group.id}`"
          @click="selectPackageGroup(group)"
          :class="Number(selectedPackageGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
          class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
          <span class="block font-medium">@{{ group.name }}</span>
          <span class="block text-xs opacity-70 mt-1">@{{ group.usage_count ?? 0 }} 件</span>
        </button>
        <div v-if="activePackageGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">パッケージ分類がありません</div>
      </div>
    </aside>

    <section>
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <h2 class="font-bold">@{{ currentPackageGroup?.name || 'パッケージ' }}</h2>
          <p class="text-xs opacity-60 mt-1">@{{ currentPackageGroup?.description || '左のパッケージ分類を選択してください' }}</p>
        </div>
        @if ($canEdit)
        <button @click="openPkgAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span> + パッケージを追加</button>
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
            <th class="py-2 pr-4">名前</th>
            <th class="py-2 pr-4">寸法 / 資料</th>
            <th class="py-2 pr-4">説明</th>
            <th class="py-2">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(p, index) in activePackages" :key="p.id"
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
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <img v-if="p.image_url" :src="p.image_url" alt="" class="w-10 h-10 rounded border border-[var(--color-border)] bg-[var(--color-bg)] object-contain" />
                <span>@{{ p.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 text-xs opacity-70">
              <div>@{{ packageDimensions(p) }}</div>
              <div class="mt-1 flex flex-wrap gap-1">
                <span v-if="p.image_url" class="tag tag-ok">画像</span>
                <a v-if="p.pdf_url" :href="p.pdf_url" target="_blank" rel="noopener" class="tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">PDF</a>
                <span v-if="!p.image_url && !p.pdf_url" class="opacity-40">資料なし</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ p.description || '-' }}</div>
              <div class="mt-1 opacity-60">使用件数: @{{ p.usage_count ?? 0 }}</div>
            </td>
            <td class="py-2">
              @if ($canEdit)
              <div class="flex gap-2 flex-wrap">
                <button @click="openPkgEdit(p)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                <button @click="openPkgDuplicate(p)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                <button @click="archivePackage(p)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              </div>
              @else
              <span class="text-xs opacity-40">-</span>
              @endif
            </td>
          </tr>
          <tr v-if="currentPackageGroup && activePackages.length === 0">
            <td colspan="5" class="py-8 text-center opacity-40">この分類にはパッケージが登録されていません</td>
          </tr>
          <tr v-if="!currentPackageGroup">
            <td colspan="5" class="py-8 text-center opacity-40">左のパッケージ分類を選択してください</td>
          </tr>
        </tbody>
      </table>

      <section v-if="archivedPackages.length" class="mt-6">
        <h2 class="font-bold mb-3">アーカイブ</h2>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 pr-2 w-6"></th>
              <th class="py-2 pr-4">名前</th>
              <th class="py-2 pr-4">寸法 / 資料</th>
              <th class="py-2 pr-4">説明</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in archivedPackages" :key="`archived-package-${p.id}`"
              :class="p.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)] transition-colors">
              <td class="py-2 pr-2"></td>
              <td class="py-2 pr-4 font-medium">
                <div class="flex items-center gap-2">
                  <img v-if="p.image_url" :src="p.image_url" alt="" class="w-10 h-10 rounded border border-[var(--color-border)] bg-[var(--color-bg)] object-contain" />
                  <span>@{{ p.name }}</span>
                </div>
              </td>
              <td class="py-2 pr-4 text-xs opacity-70">
                <div>@{{ packageDimensions(p) }}</div>
                <div class="mt-1 flex flex-wrap gap-1">
                  <span v-if="p.image_url" class="tag tag-ok">画像</span>
                  <a v-if="p.pdf_url" :href="p.pdf_url" target="_blank" rel="noopener" class="tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">PDF</a>
                  <span v-if="!p.image_url && !p.pdf_url" class="opacity-40">資料なし</span>
                </div>
              </td>
              <td class="py-2 pr-4 opacity-70 text-xs">
                <div>@{{ p.description || '-' }}</div>
                <div class="mt-1 opacity-60">使用件数: @{{ p.usage_count ?? 0 }}</div>
              </td>
              <td class="py-2">
                @if ($canEdit)
                <button @click="restorePackage(p)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </section>
  </div>

  <!-- ══════════════════════════ スペック分類タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'spec-groups'" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
    <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
      <div class="px-4 py-3 border-b border-[var(--color-border)]">
        <div class="flex items-center justify-between gap-2">
          <div class="font-semibold text-sm">スペック分類</div>
          @if ($isAdmin)
          <button @click="openSgAdd" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)]">追加</button>
          @endif
        </div>
      </div>
      <div class="max-h-[55vh] overflow-y-auto">
        <button v-for="group in activeSpecGroups" :key="`spec-group-select-${group.id}`"
          @click="selectSpecGroup(group)"
          :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
          class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
          <span class="block font-medium">@{{ group.name }}</span>
          <span class="block text-xs opacity-70 mt-1">@{{ group.usage_count ?? 0 }} 件 / テンプレート @{{ group.template_count ?? 0 }} 件</span>
        </button>
        <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">スペック分類がありません</div>
      </div>
      <div v-if="archivedSpecGroups.length" class="border-t border-[var(--color-border)] px-4 py-3">
        <div class="text-xs font-semibold opacity-60 mb-2">アーカイブ</div>
        <div class="space-y-2">
          <div v-for="group in archivedSpecGroups" :key="`archived-spec-group-${group.id}`" class="flex items-center justify-between gap-2 text-xs">
            <span class="truncate">@{{ group.name }}</span>
            <button @click="restoreSpecGroup(group)" class="shrink-0 px-2 py-1 border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
          </div>
        </div>
      </div>
    </aside>

    <section v-if="currentSpecGroup">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <h2 class="font-bold">@{{ currentSpecGroup.name }}</h2>
          <p class="text-xs opacity-60 mt-1">@{{ currentSpecGroup.description || '説明なし' }}</p>
          <div class="mt-2 flex flex-wrap gap-1">
            <span v-for="category in currentSpecGroup.categories" :key="`sg-cat-${category.id}`" class="tag border border-[var(--color-border)]">
              @{{ category.name }}@{{ category.pivot?.is_primary ? ' / 主' : '' }}
            </span>
            <span v-if="!currentSpecGroup.categories?.length" class="text-xs opacity-40">カテゴリ推奨なし</span>
          </div>
        </div>
        @if ($isAdmin)
        <div class="flex gap-2 flex-wrap">
          <button @click="openSgEdit(currentSpecGroup)" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
          <button @click="openSgDuplicate(currentSpecGroup)" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
          <button @click="archiveSpecGroup(currentSpecGroup)" class="px-3 py-2 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
        </div>
        @endif
      </div>

      <div class="border border-[var(--color-border)] rounded-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="font-semibold text-sm">所属スペック項目</div>
            <div class="text-xs opacity-60 mt-1">分類内の表示順、必須/推奨、既定値を管理します</div>
          </div>
          @if ($isAdmin)
          <div class="flex items-center gap-2">
            <span v-if="inlineDirty" class="text-xs px-2 py-1 rounded border border-[var(--color-tag-warning)] text-[var(--color-tag-warning)]">未保存</span>
            <button @click="syncSpecGroupMembers" class="btn-primary px-3 py-2 rounded text-xs font-medium">所属を保存</button>
          </div>
          @endif
        </div>
        @if ($isAdmin)
        <div class="px-4 py-3 border-b border-[var(--color-border)] grid grid-cols-1 md:grid-cols-[1.5fr_120px_120px_100px_1fr_auto] gap-2 items-end">
          <label class="block">
            <span class="block text-xs opacity-60 mb-1">スペック項目</span>
            <select v-model="memberEditor.spec_type_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
              <option value="">未分類スペックから選択</option>
              <option v-for="st in unassignedSpecTypes" :key="`add-member-${st.id}`" :value="st.id">@{{ st.name_ja || st.name }}</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-xs opacity-60 mb-1">扱い</span>
            <select v-model="memberEditor.state" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
              <option value="required">必須</option>
              <option value="recommended">推奨</option>
              <option value="optional">任意</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-xs opacity-60 mb-1">profile</span>
            <select v-model="memberEditor.default_profile" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
              <option value="typ">typ</option>
              <option value="range">range</option>
              <option value="max_only">max</option>
              <option value="min_only">min</option>
              <option value="triple">min/typ/max</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-xs opacity-60 mb-1">単位</span>
            <input v-model="memberEditor.default_unit" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm" />
          </label>
          <label class="block">
            <span class="block text-xs opacity-60 mb-1">メモ</span>
            <input v-model="memberEditor.note" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm" />
          </label>
          <button @click="addSpecGroupMember" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)]">追加</button>
        </div>
        @endif
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 px-3 w-20">順序</th>
              <th class="py-2 pr-4">スペック項目</th>
              <th class="py-2 pr-4">扱い</th>
              <th class="py-2 pr-4">既定</th>
              <th class="py-2 pr-4">メモ</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(member, index) in currentSpecGroup.spec_types" :key="`sg-member-${member.id}`"
              :class="member.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)]">
              <td class="py-2 px-3">
                @if ($isAdmin)
                <div class="flex gap-1">
                  <button @click="moveSpecGroupMember(index, -1)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded">↑</button>
                  <button @click="moveSpecGroupMember(index, 1)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded">↓</button>
                </div>
                @else
                <span class="text-xs opacity-50">@{{ index + 1 }}</span>
                @endif
              </td>
              <td class="py-2 pr-4 font-medium">
                <span>@{{ member.name_ja || member.name }}</span>
                <span v-if="member.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(member.symbol)"></span>
              </td>
              <td class="py-2 pr-4">
                <select :value="memberState(member)" :disabled="!isAdmin" @change="setMemberState(member, $event.target.value)" class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1 text-xs">
                  <option value="required">必須</option>
                  <option value="recommended">推奨</option>
                  <option value="optional">任意</option>
                </select>
              </td>
              <td class="py-2 pr-4 text-xs">
                <div class="flex flex-wrap gap-2">
                  <select v-model="member.pivot.default_profile" :disabled="!isAdmin" class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1 text-xs">
                    <option value="">未指定</option>
                    <option value="typ">typ</option>
                    <option value="range">range</option>
                    <option value="max_only">max</option>
                    <option value="min_only">min</option>
                    <option value="triple">min/typ/max</option>
                  </select>
                  <input v-model="member.pivot.default_unit" :disabled="!isAdmin" type="text" placeholder="単位" class="w-20 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1 text-xs" />
                </div>
              </td>
              <td class="py-2 pr-4">
                <input v-model="member.pivot.note" :disabled="!isAdmin" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1 text-xs" />
              </td>
              <td class="py-2">
                @if ($isAdmin)
                <button @click="removeSpecGroupMember(index)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">外す</button>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
            <tr v-if="!currentSpecGroup.spec_types?.length">
              <td colspan="6" class="py-8 text-center opacity-40">所属スペック項目がありません</td>
            </tr>
          </tbody>
        </table>
      </div>

      <section class="border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="font-semibold text-sm">スペックテンプレート</div>
            <div class="text-xs opacity-60 mt-1">部品登録時に初期行として使う代表スペックセット</div>
          </div>
          @if ($isAdmin)
          <button @click="openTemplateAdd" class="btn-primary px-3 py-2 rounded text-xs font-medium">テンプレート追加</button>
          @endif
        </div>
        <div class="divide-y divide-[var(--color-border)]">
          <article v-for="template in currentSpecGroup.templates" :key="`spec-template-${template.id}`" class="px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="font-semibold text-sm">@{{ template.name }}</h3>
                <p class="text-xs opacity-60 mt-1">@{{ template.description || '説明なし' }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                  <span v-for="item in template.items" :key="`template-item-chip-${template.id}-${item.id}`" class="tag border border-[var(--color-border)]">
                    @{{ item.spec_type?.name_ja || item.spec_type?.name || 'スペック' }}@{{ item.is_required ? ' / 必須' : '' }}
                  </span>
                </div>
              </div>
              @if ($isAdmin)
              <div class="flex gap-2">
                <button @click="openTemplateEdit(template)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                <button @click="openTemplateDuplicate(template)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                <button @click="archiveTemplate(template)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              </div>
              @endif
            </div>
          </article>
          <div v-if="!currentSpecGroup.templates?.length" class="px-4 py-8 text-center text-sm opacity-40">テンプレートが登録されていません</div>
        </div>
      </section>
    </section>

    <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
      スペック分類を選択してください
    </section>
  </div>

  <!-- ══════════════════════════ スペック項目タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'spec-types'">
    <div class="flex justify-end mb-4">
      @if ($isAdmin)
      <button @click="openStAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + スペック項目を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ スペック項目を追加</span></div>
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
        <tr v-for="(s, index) in activeSpecTypes" :key="s.id"
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
            <span>@{{ s.name_ja || s.name }}</span>
            <span v-if="s.symbol" class="text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
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
              <button @click="openStDuplicate(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
              <button @click="archiveSpecType(s)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
            </div>
          </td>
        </tr>
        <tr v-if="activeSpecTypes.length === 0">
          <td colspan="5" class="py-8 text-center opacity-40">スペック項目が登録されていません</td>
        </tr>
      </tbody>
    </table>
    <section v-if="archivedSpecTypes.length" class="mt-6">
      <h2 class="font-bold mb-3">アーカイブ</h2>
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
          <tr v-for="s in archivedSpecTypes" :key="`archived-spec-${s.id}`"
            :class="s.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2"></td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <span>@{{ s.name_ja || s.name }}</span>
                <span v-if="s.symbol" class="text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
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
              <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>

  <!-- ═══════════════ 分類モーダル ════════════════ -->
  <div v-if="catModal.open" class="modal-overlay" v-esc="closeCatModal">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ catModal.isEdit ? '分類編集' : '分類追加' }}</h2>
        <button type="button" @click="closeCatModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
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
    <div class="modal-window modal-lg max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgModal.isEdit ? 'パッケージ編集' : 'パッケージ追加' }}</h2>
        <button type="button" @click="closePkgModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">パッケージ分類 <span class="text-red-500">*</span></label>
          <select v-model="pkgModal.form.package_group_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="">選択してください</option>
            <option v-for="group in activePackageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
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
          <label class="block text-sm font-medium mb-1">寸法 mm</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input v-model="pkgModal.form.size_x" type="number" min="0" step="0.0001" placeholder="X"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            <input v-model="pkgModal.form.size_y" type="number" min="0" step="0.0001" placeholder="Y"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            <input v-model="pkgModal.form.size_z" type="number" min="0" step="0.0001" placeholder="Z"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-sm font-medium mb-1">外観画像</span>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="onPackageFileChange('image', $event)"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            <span v-if="pkgModal.form.image_url" class="mt-2 flex items-center gap-2 text-xs opacity-70">
              <img :src="pkgModal.form.image_url" alt="" class="w-12 h-12 rounded border border-[var(--color-border)] bg-[var(--color-bg)] object-contain" />
              <span>登録済み画像</span>
            </span>
          </label>
          <label class="block">
            <span class="block text-sm font-medium mb-1">寸法図PDF</span>
            <input type="file" accept="application/pdf" @change="onPackageFileChange('pdf', $event)"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            <a v-if="pkgModal.form.pdf_url" :href="pkgModal.form.pdf_url" target="_blank" rel="noopener" class="inline-flex mt-2 text-xs tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">登録済みPDF</a>
          </label>
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
        <button type="button" @click="closePkgGroupModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
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

  <!-- ═══════════════ スペック分類モーダル ════════════════ -->
  <div v-if="specGroupModal.open" class="modal-overlay" v-esc="closeSpecGroupModal">
    <div class="modal-window modal-lg max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ specGroupModal.isEdit ? 'スペック分類編集' : 'スペック分類追加' }}</h2>
        <button type="button" @click="closeSpecGroupModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="specGroupModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="specGroupModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="specGroupModal.form.sort_order" type="number" class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <div class="text-sm font-medium mb-2">推奨カテゴリ</div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div v-for="category in activeCategories" :key="`sg-modal-cat-${category.id}`" class="flex items-center justify-between gap-3 rounded border border-[var(--color-border)] px-3 py-2">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" :checked="isSpecGroupCategoryLinked(category.id)" @change="toggleSpecGroupCategory(category.id)" />
                <span>@{{ category.name }}</span>
              </label>
              <label class="flex items-center gap-1 text-xs opacity-70 cursor-pointer">
                <input type="checkbox" :disabled="!isSpecGroupCategoryLinked(category.id)" :checked="isSpecGroupPrimaryCategory(category.id)" @change="toggleSpecGroupPrimaryCategory(category.id)" />
                <span>主</span>
              </label>
            </div>
            <div v-if="activeCategories.length === 0" class="text-sm opacity-40">分類が登録されていません</div>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeSpecGroupModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveSpecGroup" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ スペックテンプレートモーダル ════════════════ -->
  <div v-if="templateModal.open" class="modal-overlay modal-top" v-esc="closeTemplateModal">
    <div class="modal-window modal-lg max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ templateModal.isEdit ? 'スペックテンプレート編集' : 'スペックテンプレート追加' }}</h2>
        <button type="button" @click="closeTemplateModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">スペック分類</label>
          <select v-model="templateModal.form.spec_group_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="">分類なし</option>
            <option v-for="group in activeSpecGroups" :key="`template-group-${group.id}`" :value="group.id">@{{ group.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="templateModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="templateModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="templateModal.form.sort_order" type="number" class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex items-center justify-between">
            <div class="font-semibold text-sm">テンプレート項目</div>
            <button @click="addTemplateItem" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)]">行追加</button>
          </div>
          <div class="divide-y divide-[var(--color-border)]">
            <div v-for="(item, index) in templateModal.form.items" :key="`template-modal-item-${index}`" class="grid grid-cols-1 md:grid-cols-[70px_1.5fr_120px_100px_90px_1fr_70px] gap-2 px-4 py-3 items-end">
              <div class="flex gap-1">
                <button @click="moveTemplateItem(index, -1)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded">↑</button>
                <button @click="moveTemplateItem(index, 1)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded">↓</button>
              </div>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">スペック項目</span>
                <select v-model="item.spec_type_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
                  <option value="">選択してください</option>
                  <option v-for="st in activeSpecTypes" :key="`template-spec-type-${index}-${st.id}`" :value="st.id">@{{ st.name_ja || st.name }}</option>
                </select>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">profile</span>
                <select v-model="item.default_profile" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
                  <option value="typ">typ</option>
                  <option value="range">range</option>
                  <option value="max_only">max</option>
                  <option value="min_only">min</option>
                  <option value="triple">min/typ/max</option>
                </select>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">単位</span>
                <input v-model="item.default_unit" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm" />
              </label>
              <label class="flex items-center gap-2 text-sm pb-2">
                <input v-model="item.is_required" type="checkbox" />
                <span>必須</span>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">メモ</span>
                <input v-model="item.note" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm" />
              </label>
              <button @click="removeTemplateItem(index)" class="px-2 py-2 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">削除</button>
            </div>
            <div v-if="templateModal.form.items.length === 0" class="px-4 py-8 text-center text-sm opacity-40">項目がありません</div>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeTemplateModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveTemplate" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ スペック項目モーダル ════════════════ -->
  <div v-if="stModal.open" class="modal-overlay modal-top" v-esc="closeStModal">
    <div class="modal-window modal-lg max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ stModal.isEdit ? 'スペック項目編集' : 'スペック項目追加' }}</h2>
        <button type="button" @click="closeStModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">日本語名 <span class="text-red-500">*</span></label>
          <input v-model="stModal.form.name_ja" type="text" placeholder="例: コレクタ-ベース間電圧"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">英語名</label>
          <input v-model="stModal.form.name_en" type="text" placeholder="例: Collector-Base Voltage"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">記号</label>
          <input v-model="stModal.form.symbol" type="text" placeholder="例: -V_CBO, h_FE"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm font-mono" />
          <p class="text-xs opacity-50 mt-1">`-` は通常、`_` は下付き、`~` は上付きに切り替えます。例: `-V_CEO`。HTMLは入力しません。</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">alias（別名・表記ゆれ）</label>
          <textarea v-model="stModal.form.aliases_text" rows="3" placeholder="1行に1つ。例: VCBO&#10;Collector Base Breakdown Voltage"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm"></textarea>
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

        <!-- 接頭辞ポリシー（数値型かつ単位あり） -->
        <template v-if="stModal.form.value_type === 'numeric' && stModal.form.unit">
          <div>
            <label class="text-sm font-medium block mb-1">入力候補接頭辞</label>
            <p class="text-xs opacity-50 mb-2">単位入力時のドロップダウンに表示する接頭辞。未選択なら汎用候補（G M k 無印 m u n p）を使います。</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="p in ['G','M','k','','m','u','n','p','f']" :key="`sp-${p}`" class="flex items-center gap-1 text-sm cursor-pointer">
                <input type="checkbox" :value="p" v-model="stModal.form.suggest_prefixes" class="rounded" />
                <span class="font-mono">@{{ p === '' ? '（無印）' : p }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1">表示接頭辞</label>
            <p class="text-xs opacity-50 mb-2">値を人間向け表記へ逆変換するとき使う接頭辞。未選択なら大きさに応じて自動選択します。</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="p in ['G','M','k','','m','u','n','p','f']" :key="`dp-${p}`" class="flex items-center gap-1 text-sm cursor-pointer">
                <input type="checkbox" :value="p" v-model="stModal.form.display_prefixes" class="rounded" />
                <span class="font-mono">@{{ p === '' ? '（無印）' : p }}</span>
              </label>
            </div>
          </div>
        </template>
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
