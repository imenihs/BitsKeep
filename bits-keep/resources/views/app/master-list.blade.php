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

  <!-- ════════════════════════ パッケージ分類タブ ═══════════════════════════ -->
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
            index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
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
          <tr v-for="(group, index) in archivedPackageGroups" :key="`archived-group-${group.id}`"
            :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
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

  <div v-if="activeTab === 'packages'" class="grid grid-cols-1 lg:grid-cols-[264px_minmax(0,1fr)] gap-4">
    <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
      <div class="px-4 py-3 border-b border-[var(--color-border)]">
        <div class="font-semibold text-sm">パッケージ分類</div>
        <div class="text-xs opacity-60 mt-1">パッケージ分類を選ぶと、その中だけを並び替えます</div>
      </div>
      <div class="master-scroll max-h-[60vh] overflow-y-auto">
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

    <section class="min-w-0">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <h2 class="font-bold">@{{ currentPackageGroup?.name || 'パッケージ詳細' }}</h2>
          <p class="text-xs opacity-60 mt-1">@{{ currentPackageGroup?.description || '左のパッケージ分類を選択してください' }}</p>
        </div>
        @if ($canEdit)
        <button @click="openPkgAdd" class="btn-primary inline-flex items-center gap-2 whitespace-nowrap px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span><span>+ パッケージ詳細を追加</span></button>
        @else
        <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
          <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">編</span><span>+ パッケージ詳細を追加</span></div>
          <div class="mt-1 text-xs opacity-70">閲覧者のため追加できません</div>
        </div>
        @endif
      </div>

      <div class="overflow-x-auto">
      <table class="w-full min-w-[820px] text-sm border-collapse">
        <colgroup>
          <col class="w-7">
          <col class="w-[22%]">
          <col class="w-[18%]">
          <col>
          <col class="w-48">
        </colgroup>
        <thead>
          <tr class="border-b border-[var(--color-border)] text-left opacity-70">
            <th class="py-2 pr-2 w-6"></th>
            <th class="py-2 pr-4">名前</th>
            <th class="py-2 pr-4">寸法 / 資料</th>
            <th class="py-2 pr-4">説明</th>
            <th class="py-2 text-right">操作</th>
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
              index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
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
              <div class="flex flex-nowrap justify-end gap-2 whitespace-nowrap">
                <button @click="openPkgEdit(p)" class="shrink-0 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                <button @click="openPkgDuplicate(p)" class="shrink-0 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                <button @click="archivePackage(p)" class="shrink-0 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              </div>
              @else
              <span class="text-xs opacity-40">-</span>
              @endif
            </td>
          </tr>
          <tr v-if="currentPackageGroup && activePackages.length === 0">
            <td colspan="5" class="py-8 text-center opacity-40">このパッケージ分類にはパッケージ詳細が登録されていません</td>
          </tr>
          <tr v-if="!currentPackageGroup">
            <td colspan="5" class="py-8 text-center opacity-40">左のパッケージ分類を選択してください</td>
          </tr>
        </tbody>
      </table>
      </div>

      <section v-if="archivedPackages.length" class="mt-6">
        <h2 class="font-bold mb-3">アーカイブ</h2>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm border-collapse">
          <colgroup>
            <col class="w-7">
            <col class="w-[22%]">
            <col class="w-[18%]">
            <col>
            <col class="w-32">
          </colgroup>
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 pr-2 w-6"></th>
              <th class="py-2 pr-4">名前</th>
              <th class="py-2 pr-4">寸法 / 資料</th>
              <th class="py-2 pr-4">説明</th>
              <th class="py-2 text-right">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, index) in archivedPackages" :key="`archived-package-${p.id}`"
              :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
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
              <td class="py-2 text-right">
                @if ($canEdit)
                <button @click="restorePackage(p)" class="shrink-0 px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </section>
    </section>
  </div>

  <!-- ══════════════════════════ 部品分類タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'part-categories'">
    <div class="flex justify-end mb-4">
      @if ($isAdmin)
      <button @click="openSgAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 部品分類を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 部品分類を追加</span></div>
        <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
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
        <tr v-for="(group, index) in activeSpecGroups" :key="group.id"
          :draggable="isAdmin && !group.deleted_at ? 'true' : 'false'"
          @dragstart="sgDnD.start(index)"
          @dragover="sgDnD.over($event, index)"
          @dragend="sgDnD.end()"
          @drop.prevent="sgDnD.drop(index)"
          :class="[
            index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="isAdmin && !group.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ group.name }}</span>
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ group.description || '-' }}</div>
            <div class="mt-1 opacity-60">入力候補: @{{ group.usage_count ?? 0 }}件 / テンプレート: @{{ group.template_count ?? 0 }}件 / 部品シリーズ: @{{ group.series_count ?? 0 }}件</div>
            <div v-if="specGroupSeriesModeLabel(group)" class="mt-1">
              <span class="tag text-[10px]"
                :class="group.series_management_mode === 'series_recommended' ? 'tag-warning' : ''">
                @{{ specGroupSeriesModeLabel(group) }}
              </span>
            </div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openSgEdit(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="openSgDuplicate(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
              <button @click="archiveSpecGroup(group)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
            </div>
          </td>
        </tr>
        <tr v-if="activeSpecGroups.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">部品分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
    <section v-if="archivedSpecGroups.length" class="mt-6">
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
          <tr v-for="(group, index) in archivedSpecGroups" :key="`archived-spec-group-${group.id}`"
            :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2"></td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <span>@{{ group.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ group.description || '-' }}</div>
              <div class="mt-1 opacity-60">入力候補: @{{ group.usage_count ?? 0 }}件 / テンプレート: @{{ group.template_count ?? 0 }}件 / 部品シリーズ: @{{ group.series_count ?? 0 }}件</div>
            </td>
            <td class="py-2">
              <button @click="restoreSpecGroup(group)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>

  <!-- ══════════════════════════ スペック詳細タブ ═══════════════════════════ -->
  <div v-if="['spec-types','spec-candidates','spec-templates','common-spec-types','tolerance-spec-types'].includes(activeTab)" class="space-y-6">
    <section v-if="['spec-types','spec-candidates','spec-templates'].includes(activeTab)" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)]">
          <div class="font-semibold text-sm">部品分類</div>
          <div class="text-xs opacity-60 mt-1">@{{ activeTab === 'spec-types' ? 'スペック詳細を持たせる部品分類を選択' : (activeTab === 'spec-templates' ? '入力テンプレートを管理する部品分類を選択' : '候補スペック詳細を整理する部品分類を選択') }}</div>
        </div>
        <div class="max-h-[55vh] overflow-y-auto">
          <button v-for="group in activeSpecGroups" :key="`spec-type-group-select-${group.id}`"
            @click="selectSpecGroup(group)"
            :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
            class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
            <span class="flex items-baseline justify-between gap-2">
              <span class="min-w-0 truncate font-medium">@{{ group.name }}</span>
              <span class="shrink-0 text-[11px] font-normal opacity-60">@{{ specGroupSidebarMeta(group) }}</span>
            </span>
          </button>
          <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">部品分類がありません</div>
        </div>
      </aside>

      <section v-if="currentSpecGroup" class="space-y-6">
        <div v-if="activeTab === 'spec-types'" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="font-semibold text-sm">@{{ currentSpecGroup.name }} のスペック詳細</div>
              <div class="text-xs opacity-60 mt-1">左で選んだ部品分類に持たせるスペック詳細を追加・編集・アーカイブします。</div>
              <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                <span class="tag border border-[var(--color-border)]">個別 @{{ activeSpecTypes.length }} 件</span>
                <span v-if="archivedSpecTypes.length" class="tag border border-[var(--color-border)] opacity-70">アーカイブ @{{ archivedSpecTypes.length }} 件</span>
              </div>
            </div>
            @if ($isAdmin)
            <button @click="openLocalSpecTypeAdd" :disabled="specGroupDetailLoading" class="btn-primary px-3 py-2 rounded text-xs font-medium disabled:opacity-40"><span class="feature-lock">管</span> + スペック詳細を追加</button>
            @endif
          </div>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 px-3">名前</th>
                <th class="py-2 pr-4">記号</th>
                <th class="py-2 pr-4">単位</th>
                <th class="py-2 pr-4">使用</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in activeSpecTypes" :key="`spec-type-row-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 px-3 font-medium">@{{ s.name_ja || s.name }}</td>
                <td class="py-2 pr-4 text-xs font-mono">
                  <span v-if="s.symbol" v-html="renderSymbol(s.symbol)"></span>
                  <span v-else class="opacity-40">-</span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ s.units?.[0]?.unit || s.base_unit || '-' }}</td>
                <td class="py-2 pr-4 text-xs opacity-70">使用件数: @{{ s.usage_count ?? 0 }}</td>
                <td class="py-2">
                  @if ($isAdmin)
                  <div class="flex gap-2 flex-wrap">
                    <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                    <button @click="openStDuplicate(s, { spec_scope: 'group_local', owner_spec_group_id: currentSpecGroup.id })" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                    <button @click="archiveSpecType(s)" class="w-20 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">アーカイブ</button>
                  </div>
                  @else
                  <span class="text-xs opacity-40">-</span>
                  @endif
                </td>
              </tr>
              <tr v-if="activeSpecTypes.length === 0">
                <td colspan="5" class="py-8 text-center opacity-40">この部品分類に持たせるスペック詳細はまだありません</td>
              </tr>
            </tbody>
          </table>
          <section v-if="archivedSpecTypes.length" class="px-4 py-4 border-t border-[var(--color-border)]">
            <h2 class="font-bold mb-3">アーカイブ</h2>
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                  <th class="py-2 pr-4">名前</th>
                  <th class="py-2 pr-4">単位</th>
                  <th class="py-2">操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(s, index) in archivedSpecTypes" :key="`archived-local-spec-${s.id}`"
                  :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                  class="border-b border-[var(--color-border)] transition-colors">
                  <td class="py-2 pr-4 font-medium">
                    <span>@{{ s.name_ja || s.name }}</span>
                    <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                  </td>
                  <td class="py-2 pr-4 text-xs">@{{ s.units?.[0]?.unit || s.base_unit || '-' }}</td>
                  <td class="py-2">
                    <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>

        <div v-if="activeTab === 'spec-candidates'" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="font-semibold text-sm">@{{ currentSpecGroup.name }} のスペック候補設定</div>
              <div class="text-xs opacity-60 mt-1">この部品分類を選んだときに候補へ出すスペック詳細と、表示順・扱い・既定値を管理します</div>
              <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                <span class="tag border border-[var(--color-border)]">入力候補 @{{ currentSpecGroupCounts.total }} 件</span>
                <span class="tag border border-[var(--color-border)]">個別 @{{ currentSpecGroupCounts.local }} 件</span>
                <span class="tag border border-[var(--color-border)]">共通 @{{ currentSpecGroupCounts.common }} 件</span>
                <span class="tag border border-[var(--color-border)]">許容差 @{{ currentSpecGroupCounts.tolerance }} 件</span>
              </div>
            </div>
            @if ($isAdmin)
            <div class="flex items-center gap-2 flex-wrap">
              <button @click="openCandidateAddModal('local')" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)] disabled:opacity-40">スペック詳細から追加</button>
              <button @click="openCandidateAddModal('common')" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)] disabled:opacity-40">共通スペック詳細から追加</button>
              <button @click="openCandidateAddModal('tolerance')" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)] disabled:opacity-40">許容差スペック詳細から追加</button>
              <span v-if="specGroupMemberSaving" class="text-xs px-2 py-1 rounded border border-[var(--color-border)] opacity-70">保存中...</span>
            </div>
            @endif
          </div>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 pr-2 w-6"></th>
                <th class="py-2 pr-4">スペック詳細</th>
                <th class="py-2 pr-4">範囲</th>
                <th class="py-2 pr-4">扱い</th>
                <th class="py-2 pr-4">既定</th>
                <th class="py-2 pr-4">メモ</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(member, index) in currentSpecGroup.spec_types" :key="`sg-member-${member.id}`"
                :draggable="isAdmin && !specGroupMemberSaving ? 'true' : 'false'"
                @dragstart="candidateMemberDnD.start(index)"
                @dragover="candidateMemberDnD.over($event, index)"
                @dragend="candidateMemberDnD.end()"
                @drop.prevent="candidateMemberDnD.drop(index)"
                :class="[
                  index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
                  dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
                ]"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 pr-2 text-center">
                  @if ($isAdmin)
                  <span v-if="!specGroupMemberSaving" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
                  @endif
                </td>
                <td class="py-2 pr-4 font-medium">
                  <span>@{{ member.name_ja || member.name }}</span>
                  <span v-if="member.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(member.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">
                  <span class="tag border border-[var(--color-border)]" :title="candidateMemberTypeTitle(member)">@{{ candidateMemberTypeLabel(member) }}</span>
                </td>
                <td class="py-2 pr-4 text-xs">
                  @{{ memberStateLabel(member) }}
                </td>
                <td class="py-2 pr-4 text-xs">
                  @{{ memberDefaultLabel(member) }}
                </td>
                <td class="py-2 pr-4 text-xs">
                  <span v-if="member.pivot?.note">@{{ member.pivot.note }}</span>
                  <span v-else class="opacity-40">-</span>
                </td>
                <td class="py-2">
                  @if ($isAdmin)
                  <div class="flex gap-2 flex-wrap">
                    <button @click="openCandidateSettingEdit(member, index)" :disabled="specGroupMemberSaving" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40">編集</button>
                    <button @click="confirmRemoveSpecGroupMember(member)" :disabled="specGroupMemberSaving" class="w-28 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center disabled:opacity-40">候補から外す</button>
                  </div>
                  @else
                  <span class="text-xs opacity-40">-</span>
                  @endif
                </td>
              </tr>
              <tr v-if="!currentSpecGroup.spec_types?.length">
                <td colspan="7" class="py-8 text-center opacity-40">候補スペック詳細がありません</td>
              </tr>
            </tbody>
          </table>
        </div>

        <section v-if="activeTab === 'spec-templates'" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="font-semibold text-sm">入力テンプレート</div>
              <div class="text-xs opacity-60 mt-1">@{{ currentSpecGroup.name }} の部品登録時に、スペック行をまとめて追加する初期行セット</div>
              <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                <span class="tag border border-[var(--color-border)]">テンプレート @{{ currentSpecGroupCounts.templates }} 件</span>
                <span class="tag border border-[var(--color-border)]">行 @{{ currentSpecGroupCounts.templateItems }} 件</span>
              </div>
            </div>
            @if ($isAdmin)
            <button @click="openTemplateAdd" :disabled="specGroupDetailLoading" class="btn-primary px-3 py-2 rounded text-xs font-medium disabled:opacity-40">入力テンプレート追加</button>
            @endif
          </div>
          <div class="divide-y divide-[var(--color-border)]">
            <article v-for="template in currentSpecGroup.templates" :key="`spec-template-${template.id}`" class="px-4 py-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold text-sm">@{{ template.name }}</h3>
                  <p class="text-xs opacity-60 mt-1">@{{ template.description || '説明なし' }}</p>
                  <div class="mt-2 flex flex-wrap gap-1">
                    <span v-for="item in template.items" :key="`template-item-chip-${template.id}-${item.id}`" class="inline-flex flex-wrap items-center gap-1">
                      <span class="tag border border-[var(--color-border)]">
                        <span>@{{ item.spec_type?.name_ja || item.spec_type?.name || 'スペック' }}</span>
                        <span v-if="item.spec_type?.symbol" class="ml-1 font-mono opacity-70" v-html="renderSymbol(item.spec_type.symbol)"></span>
                      </span>
                      <span v-if="isToleranceSpecType(item.spec_type)" class="tag border border-[var(--color-border)] text-[10px]">許容差</span>
                      <span v-if="item.is_required" class="tag border border-[var(--color-border)] text-[10px]">必須</span>
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
            <div v-if="!currentSpecGroup.templates?.length" class="px-4 py-8 text-center text-sm opacity-40">入力テンプレートが登録されていません</div>
          </div>
        </section>
      </section>
      <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
        部品分類を選択してください
      </section>
    </section>

    <section v-if="activeTab === 'common-spec-types'" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)]">
          <div class="font-semibold text-sm">部品分類</div>
          <div class="text-xs opacity-60 mt-1">共通スペック詳細を候補に入れる部品分類を選択</div>
        </div>
        <div class="max-h-[55vh] overflow-y-auto">
          <button v-for="group in activeSpecGroups" :key="`common-spec-group-select-${group.id}`"
            @click="selectSpecGroup(group)"
            :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
            class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
            <span class="flex items-baseline justify-between gap-2">
              <span class="min-w-0 truncate font-medium">@{{ group.name }}</span>
              <span class="shrink-0 text-[11px] font-normal opacity-60">@{{ specGroupSidebarMeta(group) }}</span>
            </span>
          </button>
          <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">部品分類がありません</div>
        </div>
      </aside>

      <section v-if="currentSpecGroup" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-bold text-sm">共通スペック詳細</h2>
            <p class="text-xs opacity-60 mt-1">登録済みの共通スペック詳細から、@{{ currentSpecGroup.name }} の入力候補に入れるものを選びます。</p>
            <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
              <span class="tag border border-[var(--color-border)]">共通 @{{ currentSpecGroupCounts.common }} 件</span>
              <span class="tag border border-[var(--color-border)]">候補 @{{ currentSpecGroupCounts.total }} 件</span>
            </div>
          </div>
          @if ($isAdmin)
          <button @click="openCommonSpecTypeAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 共通スペック詳細を追加</button>
          @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
            <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 共通スペック詳細を追加</span></div>
            <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
          </div>
          @endif
        </div>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 px-3">名前</th>
              <th class="py-2 pr-4">入力候補</th>
              <th class="py-2 pr-4">単位</th>
              <th class="py-2 pr-4">使用</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, index) in activeCommonSpecTypes" :key="`common-spec-${s.id}`"
              :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)] transition-colors">
              <td class="py-2 px-3 font-medium">
                <div class="flex items-center gap-2">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </div>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span v-if="isCommonSpecLinked(s)" class="tag tag-ok">採用中</span>
                <span v-else class="tag border border-[var(--color-border)] opacity-60">未追加</span>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span v-if="s.units?.[0] || s.base_unit" class="inline-block bg-[var(--color-card-even)] border border-[var(--color-border)] rounded px-1.5 py-0.5">
                  @{{ s.units?.[0]?.unit || s.base_unit }}
                </span>
                <span v-else class="opacity-40">-</span>
              </td>
              <td class="py-2 pr-4 text-xs opacity-70">使用件数: @{{ s.usage_count ?? 0 }}</td>
              <td class="py-2">
                @if ($isAdmin)
                <div class="flex gap-2 flex-wrap">
                  <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                  <button @click="openCommonSpecTypeDuplicate(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                  <button @click="toggleCommonSpecForCurrentGroup(s)" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="w-28 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40 text-center">
                    @{{ isCommonSpecLinked(s) ? '候補から外す' : '候補に入れる' }}
                  </button>
                  <button @click="archiveSpecType(s)" class="w-20 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">アーカイブ</button>
                </div>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
            <tr v-if="activeCommonSpecTypes.length === 0">
              <td colspan="5" class="py-8 text-center opacity-40">共通スペック詳細が登録されていません</td>
            </tr>
          </tbody>
        </table>
        <section v-if="archivedCommonSpecTypes.length" class="px-4 py-4 border-t border-[var(--color-border)]">
          <h2 class="font-bold mb-3">アーカイブ</h2>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 pr-4">名前</th>
                <th class="py-2 pr-4">単位</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in archivedCommonSpecTypes" :key="`archived-common-spec-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 pr-4 font-medium">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ s.units?.[0]?.unit || s.base_unit || '-' }}</td>
                <td class="py-2">
                  <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>
      <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
        部品分類を選択してください
      </section>
    </section>

    <section v-if="activeTab === 'tolerance-spec-types'" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)]">
          <div class="font-semibold text-sm">部品分類</div>
          <div class="text-xs opacity-60 mt-1">許容差スペック詳細を候補に入れる部品分類を選択</div>
        </div>
        <div class="max-h-[55vh] overflow-y-auto">
          <button v-for="group in activeSpecGroups" :key="`tolerance-spec-group-select-${group.id}`"
            @click="selectSpecGroup(group)"
            :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
            class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
            <span class="flex items-baseline justify-between gap-2">
              <span class="min-w-0 truncate font-medium">@{{ group.name }}</span>
              <span class="shrink-0 text-[11px] font-normal opacity-60">@{{ specGroupSidebarMeta(group) }}</span>
            </span>
          </button>
          <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">部品分類がありません</div>
        </div>
      </aside>

      <section v-if="currentSpecGroup" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-bold text-sm">許容差スペック詳細</h2>
            <p class="text-xs opacity-60 mt-1">登録済みの許容差スペック詳細から、@{{ currentSpecGroup.name }} の入力候補に入れるものを選びます。</p>
            <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
              <span class="tag border border-[var(--color-border)]">許容差 @{{ currentSpecGroupCounts.tolerance }} 件</span>
              <span class="tag border border-[var(--color-border)]">候補 @{{ currentSpecGroupCounts.total }} 件</span>
            </div>
          </div>
          @if ($isAdmin)
          <button @click="openToleranceSpecTypeAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 許容差スペック詳細を追加</button>
          @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
            <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 許容差スペック詳細を追加</span></div>
            <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
          </div>
          @endif
        </div>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 px-3">名前</th>
              <th class="py-2 pr-4">入力候補</th>
              <th class="py-2 pr-4">許容差の単位</th>
              <th class="py-2 pr-4">設定</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, index) in activeToleranceSpecTypes" :key="`tolerance-spec-${s.id}`"
              :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)] transition-colors">
              <td class="py-2 px-3 font-medium">
                <div class="flex items-center gap-2">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </div>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span v-if="isCommonSpecLinked(s)" class="tag tag-ok">採用中</span>
                <span v-else class="tag border border-[var(--color-border)] opacity-60">未追加</span>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span class="inline-block bg-[var(--color-card-even)] border border-[var(--color-border)] rounded px-1.5 py-0.5">
                  @{{ toleranceUnit(s) }}
                </span>
              </td>
              <td class="py-2 pr-4 text-xs opacity-70">
                @{{ toleranceInputFormat(s) }} / @{{ toleranceAllowedUnits(s) }}
              </td>
              <td class="py-2">
                @if ($isAdmin)
                <div class="flex gap-2 flex-wrap">
                  <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                  <button @click="openCommonSpecTypeDuplicate(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                  <button @click="toggleCommonSpecForCurrentGroup(s)" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="w-28 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40 text-center">
                    @{{ isCommonSpecLinked(s) ? '候補から外す' : '候補に入れる' }}
                  </button>
                  <button @click="archiveSpecType(s)" class="w-20 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">アーカイブ</button>
                </div>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
            <tr v-if="activeToleranceSpecTypes.length === 0">
              <td colspan="5" class="py-8 text-center opacity-40">許容差スペック詳細が登録されていません</td>
            </tr>
          </tbody>
        </table>
        <section v-if="archivedToleranceSpecTypes.length" class="px-4 py-4 border-t border-[var(--color-border)]">
          <h2 class="font-bold mb-3">アーカイブ</h2>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 pr-4">名前</th>
                <th class="py-2 pr-4">許容差の単位</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in archivedToleranceSpecTypes" :key="`archived-tolerance-spec-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 pr-4 font-medium">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ toleranceUnit(s) }}</td>
                <td class="py-2">
                  <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>
      <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
        部品分類を選択してください
      </section>
    </section>
  </div>

  <!-- ═══════════════ パッケージ詳細モーダル ════════════════ -->
  <div v-if="pkgModal.open" class="modal-overlay" v-esc="closePkgModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgModal.isEdit ? 'パッケージ詳細編集' : 'パッケージ詳細追加' }}</h2>
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
          <label class="block text-sm font-medium mb-1">外形寸法（mm）</label>
          <p class="text-xs opacity-60 mb-2">部品本体の寸法です。X は縦または長手方向、Y は横または幅、Z は実装高さを入力します。</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="block">
              <span class="block text-xs font-medium mb-1 opacity-70">X: 縦・長手方向</span>
              <input v-model="pkgModal.form.size_x" type="number" min="0" step="0.0001" placeholder="例: 1.6"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <span class="block text-xs font-medium mb-1 opacity-70">Y: 横・幅</span>
              <input v-model="pkgModal.form.size_y" type="number" min="0" step="0.0001" placeholder="例: 0.8"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <span class="block text-xs font-medium mb-1 opacity-70">Z: 高さ</span>
              <input v-model="pkgModal.form.size_z" type="number" min="0" step="0.0001" placeholder="例: 0.55"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            </label>
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
            <a v-if="pkgModal.form.pdf_url" :href="pkgModal.form.pdf_url" target="_blank" rel="noopener" title="登録済みPDF" class="inline-flex mt-2 text-xs tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">PDF</a>
          </label>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closePkgModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackage" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <div v-if="pkgGroupModal.open" class="modal-overlay" v-esc="closePkgGroupModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
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
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closePkgGroupModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackageGroup" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ 部品分類モーダル（互換） ════════════════ -->
  <div v-if="specGroupModal.open" class="modal-overlay" v-esc="closeSpecGroupModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ specGroupModal.isEdit ? '部品分類編集' : '部品分類追加' }}</h2>
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
          <label class="block text-sm font-medium mb-1">部品シリーズの扱い</label>
          <select v-model="specGroupModal.form.series_management_mode" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="single">シリーズを使わない</option>
            <option value="series_optional">シリーズ登録も使う</option>
            <option value="series_recommended">シリーズ登録を推奨</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeSpecGroupModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveSpecGroup" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ 入力テンプレートモーダル ════════════════ -->
  <div v-if="templateModal.open" class="modal-overlay modal-top" v-esc="closeTemplateModal">
    <div class="modal-window modal-master max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ templateModal.isEdit ? '入力テンプレート編集' : '入力テンプレート追加' }}</h2>
        <button type="button" @click="closeTemplateModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">関連する部品分類</label>
          <select v-model="templateModal.form.spec_group_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="">関連する部品分類なし</option>
            <option v-for="group in activeSpecGroups" :key="`template-group-${group.id}`" :value="group.id">@{{ group.name }}</option>
          </select>
          <p class="mt-1 text-xs opacity-60">スペック詳細は、ここで選んだ部品分類の候補スペック詳細だけを表示します。</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="templateModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="templateModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex items-center justify-between">
            <div class="font-semibold text-sm">作成する入力行</div>
            <button @click="addTemplateItem" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)]">行追加</button>
          </div>
          <div class="divide-y divide-[var(--color-border)]">
            <div v-for="(item, index) in templateModal.form.items" :key="`template-modal-item-${index}`"
              draggable="true"
              @dragstart="templateItemDnD.start(index)"
              @dragover="templateItemDnD.over($event, index)"
              @dragend="templateItemDnD.end()"
              @drop.prevent="templateItemDnD.drop(index)"
              :class="dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''"
              class="grid grid-cols-1 lg:grid-cols-[3rem_minmax(18rem,2fr)_9rem_8rem_6rem_minmax(12rem,1fr)_5.5rem] gap-2 px-4 py-3 items-end transition-colors">
              <div class="pb-2 text-center">
                <span class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none" title="ドラッグして並び替え">⠿</span>
              </div>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">スペック詳細</span>
                <select v-model="item.spec_type_id" :disabled="!templateModal.form.spec_group_id || templateSpecGroupLoading" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm disabled:opacity-50">
                  <option value="">@{{ templateModal.form.spec_group_id ? (templateSpecGroupLoading ? '候補を読込中...' : '選択してください') : '先に関連する部品分類を選択' }}</option>
                  <option v-for="st in templateSpecTypeOptionsForItem(item)" :key="`template-spec-type-${index}-${st.id}`" :value="st.id">@{{ templateSpecTypeOptionLabel(st) }}</option>
                </select>
                <span v-if="templateModal.form.spec_group_id && !templateSpecGroupLoading && templateSpecTypeOptions.length === 0" class="block mt-1 text-[11px] opacity-50">この部品分類には候補スペック詳細がありません</span>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">入力形式</span>
                <select v-model="item.default_profile" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
                  <option value="typ">TYP</option>
                  <option value="range">MIN..MAX</option>
                  <option value="max_only">MAX</option>
                  <option value="min_only">MIN</option>
                  <option value="triple">MIN/TYP/MAX</option>
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
              <button @click="removeTemplateItem(index)" class="w-20 px-2 py-2 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">行を外す</button>
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

  <!-- ═══════════════ 候補スペック詳細モーダル ════════════════ -->
  <div v-if="candidateSettingModal.open" class="modal-overlay" v-esc="closeCandidateSettingModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">候補設定編集</h2>
        <button type="button" @click="closeCandidateSettingModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div v-if="candidateSettingMember" class="rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
          <div class="text-sm font-semibold">
            <span>@{{ candidateSettingMember.name_ja || candidateSettingMember.name }}</span>
            <span v-if="candidateSettingMember.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(candidateSettingMember.symbol)"></span>
          </div>
          <div class="mt-2 text-xs opacity-70">範囲: @{{ candidateMemberTypeLabel(candidateSettingMember) }}</div>
        </div>
        <label class="block">
          <span class="block text-sm font-medium mb-1">扱い</span>
          <select v-model="candidateSettingModal.form.state" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="required">必須</option>
            <option value="recommended">推奨</option>
            <option value="optional">任意</option>
          </select>
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-sm font-medium mb-1">既定の入力形式</span>
            <select v-model="candidateSettingModal.form.default_profile" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
              <option value="">未指定</option>
              <option value="typ">TYP</option>
              <option value="range">MIN..MAX</option>
              <option value="max_only">MAX</option>
              <option value="min_only">MIN</option>
              <option value="triple">MIN/TYP/MAX</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-sm font-medium mb-1">既定単位</span>
            <input v-model="candidateSettingModal.form.default_unit" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </label>
        </div>
        <label class="block">
          <span class="block text-sm font-medium mb-1">メモ</span>
          <input v-model="candidateSettingModal.form.note" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </label>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeCandidateSettingModal" :disabled="specGroupMemberSaving" class="px-4 py-2 border border-[var(--color-border)] rounded disabled:opacity-40">キャンセル</button>
        <button @click="saveCandidateSetting" :disabled="specGroupMemberSaving" class="btn-primary px-4 py-2 rounded font-medium disabled:opacity-40">保存</button>
      </div>
    </div>
  </div>

  <div v-if="candidateAddModal.open" class="modal-overlay" v-esc="closeCandidateAddModal">
    <div class="modal-window modal-master max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ candidateAddTitle }}</h2>
        <button type="button" @click="closeCandidateAddModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6">
        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 px-3">名前</th>
                <th class="py-2 pr-4">範囲</th>
                <th class="py-2 pr-4">単位</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in candidateAddOptions" :key="`candidate-add-${candidateAddModal.mode}-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)]">
                <td class="py-2 px-3 font-medium">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">
                  <span class="tag border border-[var(--color-border)]" :title="candidateMemberTypeTitle(s)">@{{ candidateMemberTypeLabel(s) }}</span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ isToleranceSpecType(s) ? toleranceUnit(s) : (s.units?.[0]?.unit || s.base_unit || '-') }}</td>
                <td class="py-2">
                  <button @click="addCandidateFromOption(s)" :disabled="specGroupMemberSaving" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40">追加</button>
                </td>
              </tr>
              <tr v-if="candidateAddOptions.length === 0">
                <td colspan="4" class="py-8 text-center opacity-40">@{{ candidateAddEmptyMessage }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeCandidateAddModal" class="px-4 py-2 border border-[var(--color-border)] rounded">閉じる</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ スペック詳細モーダル ════════════════ -->
  <div v-if="stModal.open" class="modal-overlay modal-top" v-esc="closeStModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">
          @{{ stModalTitle }}
        </h2>
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
          <input v-model="stModal.form.symbol" type="text" placeholder="例: V_CBO, h_FE, V_CE-(sat)"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm font-mono" />
          <p class="text-xs opacity-50 mt-1">`_` は下付き、`~` は上付き、`-` は通常表示へ戻す区切りです。例: `V_CE-(sat)`。HTMLは入力しません。</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">別名・表記ゆれ</label>
          <textarea v-model="stModal.form.aliases_text" rows="3" placeholder="1行に1つ。例: VCBO&#10;Collector Base Breakdown Voltage"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="stModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div v-if="stModal.form.spec_kind !== 'tolerance'">
          <label class="block text-sm font-medium mb-1">値の型</label>
          <select v-model="stModal.form.value_type"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="numeric">数値</option>
            <option value="text">テキスト</option>
            <option value="boolean">真偽値</option>
          </select>
        </div>

        <!-- 単位（数値型のみ） -->
        <div v-if="stModal.form.spec_kind !== 'tolerance' && stModal.form.value_type === 'numeric'">
          <label class="text-sm font-medium block mb-2">単位</label>
          <input v-model="stModal.form.unit" type="text" placeholder="例: μF"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          <p class="text-xs opacity-50 mt-1">不要なら空欄のまま保存します。</p>
        </div>

        <!-- 接頭辞ポリシー（数値型かつ単位あり） -->
        <template v-if="stModal.form.spec_kind !== 'tolerance' && stModal.form.value_type === 'numeric' && stModal.form.unit">
          <div>
            <label class="text-sm font-medium block mb-1">入力候補接頭辞</label>
            <p class="text-xs opacity-50 mb-2">@{{ prefixPolicyHelp }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="option in prefixOptionsFor()" :key="`sp-${option.value || 'blank'}`"
                class="flex items-center gap-1 text-sm"
                :class="option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'">
                <input type="checkbox" :value="option.value" v-model="stModal.form.suggest_prefixes" :disabled="option.disabled" @change="syncPrefixList('suggest_prefixes', option.value)" class="rounded" />
                <span class="font-mono">@{{ option.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1">表示接頭辞</label>
            <p class="text-xs opacity-50 mb-2">値を人間向け表記へ逆変換するとき使う接頭辞。未選択なら大きさに応じて自動選択します。</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="option in prefixOptionsFor()" :key="`dp-${option.value || 'blank'}`"
                class="flex items-center gap-1 text-sm"
                :class="option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'">
                <input type="checkbox" :value="option.value" v-model="stModal.form.display_prefixes" :disabled="option.disabled" @change="syncPrefixList('display_prefixes', option.value)" class="rounded" />
                <span class="font-mono">@{{ option.label }}</span>
              </label>
            </div>
          </div>
        </template>

        <template v-if="stModal.form.spec_kind === 'tolerance'">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">許容差の単位</label>
              <select v-model="stModal.form.tolerance_settings.default_unit"
                @change="stModal.form.unit = stModal.form.tolerance_settings.default_unit"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
                <option v-for="unit in toleranceUnitOptions" :key="`tol-default-${unit.value}`" :value="unit.value">@{{ unit.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">既定入力形式</label>
              <select v-model="stModal.form.tolerance_settings.default_mode"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
                <option value="symmetric">± 対称</option>
                <option value="asymmetric">+/- 非対称</option>
                <option value="grade">ランク</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1">使用する単位</label>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="unit in toleranceUnitOptions" :key="`tol-unit-${unit.value}`" class="flex items-center gap-1 text-sm cursor-pointer">
                <input type="checkbox" :value="unit.value" v-model="stModal.form.tolerance_settings.allowed_units" class="rounded" />
                <span class="font-mono">@{{ unit.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ランク定義</label>
            <textarea v-model="stModal.form.tolerance_settings.grade_options_text" rows="4" placeholder="例: B: ±0.1pF&#10;F: ±1%&#10;X7R: -55〜125℃ / ±15%"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm"></textarea>
            <p class="text-xs opacity-50 mt-1">保存時にランクコードと許容差値の配列へ変換します。対称値は `F: ±1%` や `B: ±0.1pF`、非対称値は `Z: +80/-20%`、コード値は `X7R: -55〜125℃ / ±15%` の形式で入力します。</p>
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
