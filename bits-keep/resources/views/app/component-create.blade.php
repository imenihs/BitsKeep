<!DOCTYPE html>
<html lang="ja">
<head>
@include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品登録/編集 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($chatGptHelperUrl = url('/tampermonkey/bitskeep-chatgpt-helper.user.js').'?v='.filemtime(public_path('tampermonkey/bitskeep-chatgpt-helper.user.js')))
@php($chatGptHelperMinVersion = config('services.chatgpt_helper.min_version'))
@php($canEdit = auth()->user()->isEditor())
@include('partials.app-header', ['current' => isset($id) ? '部品編集' : '部品登録'])
<div id="app" data-page="component-create" data-id="{{ $id ?? '' }}" data-can-create-supplier="{{ auth()->user()->isAdmin() ? '1' : '0' }}" data-can-create-spec-type="{{ auth()->user()->isAdmin() ? '1' : '0' }}" data-chatgpt-helper-min-version="{{ $chatGptHelperMinVersion }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '部品一覧', 'url' => route('components.index')],
    ['label' => isset($id) ? '部品編集' : '部品登録', 'current' => true],
  ]])

  <header class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <div>
      <h1 class="text-2xl font-bold">@{{ isEdit ? '部品編集' : '部品登録' }}</h1>
      <div v-if="duplicateFromId && !isEdit" class="mt-2 text-xs opacity-70">複製元部品を読み込んでいます。型番と差分だけ修正して登録します。</div>
      @unless ($canEdit)
      <div class="mt-2 text-xs opacity-70">閲覧者のため保存できません。内容確認のみ可能です。</div>
      @endunless
    </div>
    <button @click="submit" :disabled="saving || {{ $canEdit ? 'false' : 'true' }}" class="btn btn-primary px-5 py-2 rounded text-sm disabled:opacity-50" title="{{ $canEdit ? '' : '編集者以上の権限が必要です' }}">
      {{ $canEdit ? '' : '編 ' }}@{{ saving ? '保存中...' : (isEdit ? '更新' : '登録') }}
    </button>
  </header>

  <section v-if="masterLoadError" class="mb-4 rounded-2xl border border-[var(--color-tag-warning)] px-4 py-4 bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]">
    <div class="font-semibold text-[var(--color-tag-warning)]">@{{ masterLoadError }}</div>
    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" onclick="window.location.reload()" class="px-3 py-2 rounded-xl border border-[var(--color-tag-warning)] text-sm">再読込</button>
      <a href="{{ route('master.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">マスタ管理へ</a>
      <a href="{{ route('components.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">部品一覧へ戻る</a>
    </div>
  </section>

  <!-- 基本情報 -->
  <section class="card mb-4 p-5 flex-col items-start gap-4 block bg-[var(--color-card-even)]">
    <h2 class="font-bold mb-3">基本情報</h2>
    <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-semibold mb-1">部品画像</label>
          <div class="component-image-frame">
            <img v-if="imagePreviewUrl" :src="imagePreviewUrl" alt="部品画像プレビュー" class="component-image-preview" />
            <div v-else class="component-image-empty">
              <span class="text-3xl opacity-30">□</span>
              <span>未登録</span>
            </div>
          </div>
          <input type="file" accept="image/*" class="input-text w-full mt-2 text-xs" @change="onImageChange" />
          <p class="text-[11px] opacity-50 mt-1">jpg / png / webp、5MBまで</p>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">データシート（PDF・複数可）</label>
          <input type="file" multiple accept=".pdf,application/pdf" class="input-text w-full text-xs" @change="onDatasheetChange" />
          <div class="mt-3 grid grid-cols-3 gap-2">
            <button type="button" @click="beginAiAction('chatgpt')"
              :disabled="!hasDatasheetForAi"
              :title="hasDatasheetForAi ? '' : '先にデータシートPDFを選択してください'"
              class="flex w-full min-w-0 items-center justify-center gap-1 rounded border px-2 py-2 text-[11px] leading-tight transition-colors disabled:cursor-not-allowed disabled:opacity-40"
              :class="hasDatasheetForAi
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white hover:opacity-90'
                : 'border-[var(--color-border)] bg-[var(--color-card-even)] text-[var(--color-text)]'">
              🤖 ChatGPTで自動入力
            </button>
            <button type="button" @click="openChatGPTPaste"
              class="flex w-full min-w-0 items-center justify-center gap-1 rounded border border-[var(--color-border)] px-2 py-2 text-[11px] leading-tight transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]">
              📋 ChatGPTから貼り付け
            </button>
            <button type="button" @click="beginAiAction('gemini')"
              :disabled="!hasDatasheetForAi || analyzing"
              :title="hasDatasheetForAi ? '' : '先にデータシートPDFを選択してください'"
              class="flex w-full min-w-0 items-center justify-center gap-1 rounded border px-2 py-2 text-[11px] leading-tight transition-colors disabled:cursor-not-allowed disabled:opacity-40"
              :class="hasDatasheetForAi
                ? 'border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10'
                : 'border-[var(--color-border)] text-[var(--color-text)]'">
              <span v-if="analyzing">⏳ 解析中...</span>
              <span v-else>✨ Geminiで自動入力</span>
            </button>
          </div>

          <div v-if="helperResult && helperResultSummary" class="mt-4 rounded-2xl border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/5 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-sm font-semibold">✨ 解析候補を保持中</p>
                <p class="mt-1 text-[11px] opacity-60">
                  基本情報 @{{ helperResultSummary.basicCount }} 件 /
                  分類 @{{ helperResultSummary.categoryCount }} 件 /
                  パッケージ @{{ helperResultSummary.packageCount }} 件 /
                  スペック @{{ helperResultSummary.specCount }} 件
                </p>
              </div>
              <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <button type="button" @click="discardHelperResult"
                  class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
                  候補を破棄
                </button>
                <button type="button" @click="openHelperResultModal"
                  class="btn-primary px-4 py-2 rounded text-sm">
                  候補を確認
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold mb-1">型番 <span class="text-[var(--color-tag-eol)]">*</span></label>
            <input v-model="form.part_number" type="text" class="input-text w-full" placeholder="例: RES-10K-0402" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">メーカー</label>
            <div class="relative">
              <input v-model="manufacturerQuery" @blur="commitManufacturer" type="text" class="input-text w-full"
                @focus="manufacturerSuggestionsOpen = true"
                placeholder="入力して絞り込み。候補がなければ新規で使う" />
              <div v-if="manufacturerSuggestionsOpen && manufacturerQuery.trim()" class="absolute left-0 right-0 top-full z-10 mt-1 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-2 shadow-lg">
                <div v-if="filteredManufacturers.length" class="flex flex-wrap gap-2">
                  <button v-for="name in filteredManufacturers" :key="name" @mousedown.prevent="selectManufacturer(name)"
                    type="button" class="px-2 py-1 rounded border border-[var(--color-border)] text-xs hover:border-[var(--color-primary)]">
                    @{{ name }}
                  </button>
                </div>
                <p v-else-if="!manufacturerExactMatch" class="text-[11px] opacity-60">
                  一致なし。このまま新規メーカー名として保存します。
                </p>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">通称</label>
            <input v-model="form.common_name" type="text" class="input-text w-full" placeholder="例: 抵抗 10kΩ 0402" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">入手可否</label>
            <select v-model="form.procurement_status" class="input-text w-full">
              <option value="active">量産中</option>
              <option value="eol">EOL</option>
              <option value="last_time">在庫限り</option>
              <option value="nrnd">新規非推奨</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="block text-xs font-semibold mb-1">説明</label>
          <textarea v-model="form.description" class="input-text w-full h-20" placeholder="任意の説明"></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
          <div>
            <label class="block text-xs font-semibold mb-1">発注点（新品）</label>
            <input v-model.number="form.threshold_new" type="number" min="0" class="input-text w-full" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">発注点（中古）</label>
            <input v-model.number="form.threshold_used" type="number" min="0" class="input-text w-full" />
          </div>
        </div>
        <div class="mt-3">
          <label class="block text-xs font-semibold mb-1">代表保管棚</label>
          <select v-model="form.primary_location_id" class="input-text w-full">
            <option value="">未設定</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">
              @{{ location.code }} / @{{ location.name }}
            </option>
          </select>
        </div>
      </div>
    </div>
  </section>

  <!-- スペック -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <div class="flex justify-between items-center mb-3">
      <div class="flex items-center gap-2">
        <h2 class="font-bold">スペック</h2>
        <span v-if="!form.specs.length" class="text-xs px-1.5 py-0.5 rounded bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未追加</span>
      </div>
      <button @click="addSpec" class="text-xs link-text">+ 追加</button>
    </div>
    <div v-for="(spec, i) in form.specs" :key="i" class="spec-card mb-3 bg-[var(--color-card-odd)]">
      <div class="spec-card-grid spec-card-grid--editor">
        <div class="spec-card-field">
          <label class="spec-card-label">種別</label>
          <div class="spec-type-picker">
            <select v-model="spec.spec_type_id" @change="handleSpecTypeSelection(spec)" class="input-text spec-card-control text-sm py-1 w-full">
              <option value="">スペック種別を選択</option>
              <option v-for="st in specTypes" :key="`type-${i}-${st.id}`" :value="st.id">@{{ specTypeOptionLabel(st) }}</option>
            </select>
            <button v-if="canCreateSpecType" type="button" @click="openInlineSpecTypeModal(spec)" class="spec-type-add-button" title="スペック種別を追加" aria-label="スペック種別を追加">＋</button>
          </div>
          <p v-if="spec.name" class="spec-card-help">抽出名: @{{ spec.name }}</p>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">値の種類</label>
          <div class="spec-card-profile">
            <button v-for="option in specProfileOptions" :key="`create-profile-${i}-${option.value}`" type="button"
              @click="changeSpecProfile(spec, option.value)"
              class="spec-card-profile-button"
              :class="spec.value_profile === option.value ? 'bg-[var(--color-primary)] text-white' : 'opacity-70 hover:bg-[var(--color-card-even)]'">
              @{{ option.label }}
            </button>
          </div>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">値</label>
          <label v-if="spec.value_profile === 'typ'" class="spec-card-subfield">
            <span class="spec-card-subfield-label">typ</span>
            <input v-model="spec.value_typ" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="例: 1 / 1e-6" />
          </label>
          <label v-else-if="spec.value_profile === 'max_only'" class="spec-card-subfield">
            <span class="spec-card-subfield-label">最大値</span>
            <input v-model="spec.value_max" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="最大値" />
          </label>
          <label v-else-if="spec.value_profile === 'min_only'" class="spec-card-subfield">
            <span class="spec-card-subfield-label">最小値</span>
            <input v-model="spec.value_min" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="最小値" />
          </label>
          <div v-else-if="spec.value_profile === 'range'" class="spec-card-values--range">
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">最小値</span>
              <input v-model="spec.value_min" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="最小値" />
            </label>
            <span class="text-xs opacity-50 pb-3">〜</span>
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">最大値</span>
              <input v-model="spec.value_max" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="最大値" />
            </label>
          </div>
          <div v-else class="spec-card-values--triple">
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">min</span>
              <input v-model="spec.value_min" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="min" />
            </label>
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">typ</span>
              <input v-model="spec.value_typ" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="typ" />
            </label>
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">max</span>
              <input v-model="spec.value_max" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="max" />
            </label>
          </div>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">単位</label>
          <input v-model="spec.unit" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="例: uA / kΩ / ns" :list="`spec-unit-create-${i}`" />
          <datalist :id="`spec-unit-create-${i}`">
            <option v-for="unitOption in getUnitSuggestions(spec.spec_type_id)" :key="`${i}-${unitOption}`" :value="unitOption">@{{ unitOption }}</option>
          </datalist>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">確認</label>
          <div class="spec-card-preview spec-card-preview-panel text-[11px]">
            <p class="text-sm font-semibold leading-tight break-words">@{{ specDisplayName(spec) || 'スペック名を選択' }}</p>
            <template v-if="specPreview(spec).hasNumeric">
              <p class="opacity-75 break-words">表示: @{{ specPreview(spec).recommendedText }}</p>
              <p class="opacity-55 break-words">基底: @{{ specPreview(spec).canonicalText }}</p>
            </template>
            <p v-else class="opacity-50 break-words">数値化できると基底換算を表示します。</p>
          </div>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">操作</label>
          <div class="spec-card-actions">
            <button @click="removeSpec(i)" type="button" title="削除" aria-label="削除" class="spec-card-delete">✕</button>
          </div>
        </div>
      </div>
    </div>
    <p v-if="!form.specs.length" class="text-xs opacity-40">スペックを追加してください</p>
  </section>

  <!-- 分類・パッケージ -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <h2 class="font-bold mb-3">分類 / パッケージ</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <label class="text-xs font-semibold block mb-2">分類（複数選択可）</label>
        <div class="border border-[var(--color-border)] rounded p-2 bg-[var(--color-bg)]">
          <input v-model="categoryQuery" type="text" class="input-text w-full"
            placeholder="分類名で絞り込み。なければ追加" />
          <div v-if="form.category_ids.length" class="mt-2 flex flex-wrap gap-2">
            <span v-for="id in form.category_ids" :key="`selected-cat-${id}`"
              class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[var(--color-card-even)] border border-[var(--color-border)]">
              @{{ categories.find((item) => item.id === id)?.name }}
              <button type="button" @click="toggleCategory(id)">✕</button>
            </span>
          </div>
          <div class="mt-2 max-h-36 overflow-y-auto space-y-1">
            <button v-for="cat in filteredCategories" :key="cat.id" type="button" @click="toggleCategory(cat.id)"
              class="w-full flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-[var(--color-card-odd)]"
              :class="form.category_ids.includes(cat.id) ? 'bg-[var(--color-card-even)] border border-[var(--color-primary)]' : ''">
              <span>@{{ cat.name }}</span>
              <span class="text-xs opacity-60">@{{ form.category_ids.includes(cat.id) ? '選択中' : '追加' }}</span>
            </button>
            <button v-if="canCreateCategory" type="button" @click="addCategoryFromQuery"
              class="w-full rounded px-2 py-1 text-left text-sm border border-dashed border-[var(--color-primary)] text-[var(--color-primary)]">
              「@{{ categoryQuery.trim() }}」を新規追加
            </button>
            <p v-if="!filteredCategories.length && !canCreateCategory" class="text-xs opacity-40 p-1">分類がありません</p>
          </div>
        </div>
      </div>
      <div>
        <label class="text-xs font-semibold block mb-2">パッケージ</label>
        <div class="border border-[var(--color-border)] rounded p-2 bg-[var(--color-bg)]">
          <select v-model="form.package_group_id" class="input-text w-full">
            <option value="">パッケージ分類を選択</option>
            <option v-for="group in packageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
          </select>
          <input v-model="packageQuery" type="text" class="input-text w-full mt-2"
            :disabled="!form.package_group_id"
            placeholder="詳細パッケージ名で絞り込み。なければ追加" />
          <div v-if="form.package_id" class="mt-2">
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[var(--color-card-even)] border border-[var(--color-border)]">
              @{{ packages.find((item) => item.id === form.package_id)?.name }}
            </span>
          </div>
          <div class="mt-2 max-h-36 overflow-y-auto space-y-1">
            <button v-for="pkg in filteredPackages" :key="pkg.id" type="button" @click="selectPackage(pkg.id)"
              class="w-full flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-[var(--color-card-odd)]"
              :class="form.package_id === pkg.id ? 'bg-[var(--color-card-even)] border border-[var(--color-primary)]' : ''">
              <span>@{{ pkg.name }}</span>
              <span class="text-xs opacity-60">@{{ form.package_id === pkg.id ? '選択中' : '使う' }}</span>
            </button>
            <button v-if="canCreatePackage" type="button" @click="addPackageFromQuery"
              class="w-full rounded px-2 py-1 text-left text-sm border border-dashed border-[var(--color-primary)] text-[var(--color-primary)]">
              「@{{ packageQuery.trim() }}」を新規追加
            </button>
            <p v-if="!form.package_group_id" class="text-xs opacity-40 p-1">先にパッケージ分類を選択してください</p>
            <p v-else-if="!filteredPackages.length && !canCreatePackage" class="text-xs opacity-40 p-1">詳細パッケージがありません</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 仕入先 -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <div class="flex justify-between items-center mb-3">
      <div class="flex items-center gap-2">
        <h2 class="font-bold">仕入先</h2>
        <span v-if="!form.supplierRows.length" class="text-xs px-1.5 py-0.5 rounded bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未追加</span>
      </div>
      <button @click="addSupplier" class="text-xs link-text">+ 行追加</button>
    </div>
    <div v-for="(row, i) in form.supplierRows" :key="i" class="mb-4 p-3 rounded bg-[var(--color-card-odd)] border border-[var(--color-border)]">
      <div class="flex gap-2 mb-2 items-center">
        <div class="flex-1">
          <input v-model="row.supplier_name" @blur="commitSupplier(row)" type="text" class="input-text text-sm py-1 w-full"
            placeholder="商社名を入力して絞り込み。なければ追加" />
          <div class="mt-2 space-y-1 max-h-28 overflow-y-auto">
            <button v-for="s in filteredSuppliersForRow(row)" :key="`${i}-${s.id}`" type="button" @mousedown.prevent="selectSupplier(row, s)"
              class="w-full flex items-center justify-between rounded px-2 py-1 text-xs hover:bg-[var(--color-bg)]"
              :class="row.supplier_id === s.id ? 'bg-[var(--color-bg)] border border-[var(--color-primary)]' : ''">
              <span>@{{ s.name }}</span>
              <span class="opacity-50">@{{ row.supplier_id === s.id ? '選択中' : '使う' }}</span>
            </button>
            <button v-if="canCreateSupplierForRow(row)" type="button" @mousedown.prevent="commitSupplier(row)"
              class="w-full rounded px-2 py-1 text-left text-xs border border-dashed border-[var(--color-primary)] text-[var(--color-primary)]">
              「@{{ row.supplier_name.trim() }}」を新規追加
            </button>
            <p v-else-if="row.supplier_name?.trim() && !canCreateSupplier" class="text-[11px] opacity-50 px-1">
              商社の新規追加は管理者のみです。既存商社を選択してください。
            </p>
          </div>
        </div>
        <label class="flex items-center gap-1 text-xs cursor-pointer">
          <input type="checkbox" v-model="row.is_preferred" />優先
        </label>
        <button @click="removeSupplier(i)" class="text-[var(--color-tag-eol)] text-xs px-2">✕</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-2">
        <input v-model="row.supplier_part_number" type="text" class="input-text text-xs py-1" placeholder="商社型番" />
        <select v-model="row.purchase_unit" class="input-text text-xs py-1">
          <option value="">購入単位</option>
          <option value="loose">バラ</option>
          <option value="tape">テープ</option>
          <option value="tray">トレー</option>
          <option value="reel">リール</option>
          <option value="box">箱</option>
        </select>
        <input v-model="row.unit_price" type="number" class="input-text text-xs py-1" placeholder="単価 ¥" />
        <input v-model="row.product_url" type="url" class="input-text text-xs py-1" placeholder="商品URL" />
      </div>
      <!-- 価格ブレーク -->
      <div v-for="(pb, j) in row.price_breaks" :key="j" class="flex gap-2 mb-1 items-center text-xs">
        <span class="opacity-60 w-16">数量</span>
        <input v-model.number="pb.min_qty" type="number" min="1" class="input-text text-xs py-0.5 w-20" />
        <span class="opacity-60">以上で</span>
        <input v-model="pb.unit_price" type="number" class="input-text text-xs py-0.5 w-24" placeholder="単価 ¥" />
        <button @click="removePriceBreak(row, j)" class="text-[var(--color-tag-eol)]">✕</button>
      </div>
      <button @click="addPriceBreak(row)" class="text-xs link-text mt-1">+ 価格ブレーク追加</button>
    </div>
    <p v-if="!form.supplierRows.length" class="text-xs opacity-40">仕入先を追加してください</p>
  </section>

  <!-- カスタムフィールド -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-bold">カスタムフィールド</h2>
      <button @click="addCustomAttribute" class="text-xs link-text">+ 追加</button>
    </div>
    <div v-for="(attr, i) in form.custom_attributes" :key="`attr-${i}`" class="grid grid-cols-1 md:grid-cols-[1fr_1.6fr_auto] gap-2 mb-2 items-center">
      <input v-model="attr.key" type="text" class="input-text text-sm py-1 w-full" placeholder="項目名" />
      <input v-model="attr.value" type="text" class="input-text text-sm py-1 w-full" placeholder="値" />
      <button @click="removeCustomAttribute(i)" class="text-[var(--color-tag-eol)] text-xs px-2">✕</button>
    </div>
    <p v-if="!form.custom_attributes.length" class="text-xs opacity-40">必要に応じて任意の項目を追加してください</p>
  </section>

  <!-- Altium連携 -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <h2 class="font-bold mb-3">Altiumライブラリ連携</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
      <div>
        <label class="block text-xs font-semibold mb-1">回路図ライブラリ</label>
        <select v-model="form.altium.sch_library_id" class="input-text w-full">
          <option value="">未設定</option>
          <option v-for="library in schLibraries" :key="library.id" :value="library.id">@{{ library.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">回路図シンボル名</label>
        <input v-model="form.altium.sch_symbol" type="text" class="input-text w-full" placeholder="例: REG_3V3_SOT23" />
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">PCBライブラリ</label>
        <select v-model="form.altium.pcb_library_id" class="input-text w-full">
          <option value="">未設定</option>
          <option v-for="library in pcbLibraries" :key="library.id" :value="library.id">@{{ library.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">PCBフットプリント名</label>
        <input v-model="form.altium.pcb_footprint" type="text" class="input-text w-full" placeholder="例: SOT23-3" />
      </div>
    </div>
  </section>

  <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] px-4 py-4">
    <a href="{{ route('components.index') }}" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit text-center">部品一覧へ戻る</a>
    <button @click="submit" :disabled="saving || {{ $canEdit ? 'false' : 'true' }}" class="btn btn-primary px-5 py-3 rounded text-sm disabled:opacity-50">
      @{{ saving ? '保存中...' : (isEdit ? '更新する' : '登録する') }}
    </button>
  </div>

  <div v-if="analyzing" class="modal-overlay" style="z-index:8500" role="alertdialog" aria-modal="true" aria-busy="true">
    <div class="modal-window modal-sm p-6 text-center" @click.stop>
      <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[var(--color-border)] border-t-[var(--color-primary)]"></div>
      <h3 class="mt-4 text-lg font-bold">Geminiで解析中</h3>
      <p class="mt-2 text-sm opacity-70">データシートPDFを解析しています。完了までこのままお待ちください。</p>
      <p class="mt-1 text-[11px] opacity-50">処理中は画面操作を受け付けません。</p>
    </div>
  </div>

  <div v-if="isChatGptJobBusy && !showChatGptRunModal" class="modal-overlay" style="z-index:8450" role="alertdialog" aria-modal="true" aria-busy="true">
    <div class="modal-window modal-sm p-6 text-center" @click.stop>
      <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[var(--color-border)] border-t-[var(--color-primary)]"></div>
      <h3 class="mt-4 text-lg font-bold">ChatGPTで解析中</h3>
      <p class="mt-2 text-sm opacity-70">ChatGPT タブでデータシートを解析しています。完了までこの画面は操作できません。</p>
      <p class="mt-2 text-xs opacity-60">@{{ chatGptJob.detail || '処理状況を確認しています。' }}</p>
      <p class="mt-1 text-[11px] opacity-50">解析完了後は候補確認モーダルを表示します。</p>
      <button type="button" @click="hardResetChatGptJob" class="mt-4 inline-flex min-h-11 items-center justify-center rounded border border-[var(--color-tag-eol)] px-4 py-2 text-sm font-medium text-[var(--color-tag-eol)]">
        ジョブを破棄してリセット
      </button>
      <p class="mt-2 text-[11px] opacity-45">止まったときはこのボタンで前回ジョブを破棄して、最初からやり直してください。</p>
    </div>
  </div>

  <div v-if="showDatasheetManagerModal" class="modal-overlay" v-esc="closeDatasheetManager">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold">解析対象のPDFを選択</h3>
          <p class="mt-2 text-sm opacity-70">複数PDFを選択したため、今回解析に使う1件だけを選びます。</p>
        </div>
        <button type="button" @click="closeDatasheetManager" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div v-if="datasheetFiles.length" class="mt-5 space-y-2">
        <div v-for="(file, index) in datasheetFiles" :key="`${file.name}-${index}`" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <p class="text-[11px] opacity-60 break-all">@{{ file.name }}</p>
              <label class="mt-2 inline-flex items-center gap-2 text-[11px] opacity-70">
                <input type="radio" v-model="datasheetTargetIndex" :value="index" />
                <span>このPDFを AI 解析対象にする</span>
              </label>
            </div>
            <span v-if="datasheetTargetIndex === index" class="inline-flex items-center rounded-full bg-[var(--color-primary)]/15 px-2 py-1 text-[10px] font-semibold text-[var(--color-primary)]">解析対象</span>
          </div>
          <p v-if="datasheetLabels[index]" class="mt-2 text-[11px] opacity-60">表示名: @{{ datasheetLabels[index] }}</p>
        </div>
      </div>

      <div v-else class="mt-5 rounded-xl border border-dashed border-[var(--color-border)] px-4 py-6 text-sm opacity-60">
        先に主画面のPDF選択ボックスからデータシートを選択してください。
      </div>

      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" @click="closeDatasheetManager" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
          閉じる
        </button>
        <button type="button" @click="confirmDatasheetTargetSelection" :disabled="!datasheetTargetLabel" class="btn-primary px-4 py-2 rounded text-sm disabled:opacity-50">
          このPDFで続行
        </button>
      </div>
    </div>
  </div>

  <div v-if="showChatGptHelperUpdateModal" class="modal-overlay" v-esc="closeChatGptHelperUpdateModal">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold">@{{ chatGptHelperCheckStatus === 'success' ? 'Tampermonkey helper は最新です' : (chatGptHelperIssue ? chatGptHelperIssue.title : 'Tampermonkey helper を確認しました') }}</h3>
          <p class="mt-2 text-sm opacity-75">
            @{{ chatGptHelperCheckStatus === 'success' ? '更新済み helper を検出しました。ChatGPT自動入力を続行できます。' : (chatGptHelperIssue ? chatGptHelperIssue.body : '更新済み helper を検出しました。ChatGPT自動入力を続行できます。') }}
          </p>
        </div>
        <button type="button" @click="closeChatGptHelperUpdateModal" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div v-if="chatGptHelperCheckStatus !== 'success'" class="mt-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="text-sm font-semibold">更新手順</div>
        <ol class="mt-3 space-y-2 text-sm opacity-80">
          <li>1. 下の <strong>userscript を更新</strong> を押す</li>
          <li>2. Tampermonkey の画面で <strong>再インストール</strong> または <strong>更新</strong> を承認する</li>
          <li>3. 下の <strong>再読込して反映</strong> を押し、この部品登録画面に更新版を反映する</li>
          <li>4. 再読込後に `helper v{{ $chatGptHelperMinVersion }}` 以上の成功トーストが出れば完了。旧版のままならこのダイアログが再表示される</li>
        </ol>
        <p class="mt-3 text-xs opacity-60">Tampermonkey の更新は、いま開いている BitsKeep 画面へ自動反映されません。更新後はこの画面の再読込が必要です。</p>
        <p v-if="chatGptJob.helperVersion" class="mt-3 text-xs opacity-60">現在検出中の helper: v@{{ chatGptJob.helperVersion }}</p>
        <p v-else class="mt-3 text-xs opacity-60">現在検出中の helper: 不明</p>
      </div>

      <div v-if="chatGptHelperCheckMessage" class="mt-4 rounded-2xl border p-4"
        :class="chatGptHelperCheckStatus === 'success'
          ? 'border-[var(--color-tag-ok)] bg-[var(--color-tag-ok)]/10'
          : 'border-[var(--color-tag-warning)] bg-[var(--color-tag-warning)]/10'">
        <div class="text-sm font-semibold">
          @{{ chatGptHelperCheckStatus === 'success' ? '確認結果' : '再確認結果' }}
        </div>
        <p class="mt-2 text-sm opacity-80">@{{ chatGptHelperCheckMessage }}</p>
      </div>

      <div v-if="chatGptHelperCheckStatus === 'success'" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" @click="closeChatGptHelperUpdateModal"
          class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-5 py-2 text-sm font-medium leading-none">
          閉じる
        </button>
        <div class="flex justify-end">
          <button type="button" @click="openChatGptRun"
            class="btn-primary inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded px-5 py-2 text-sm font-medium leading-none">
            ChatGPT自動解析を開く
          </button>
        </div>
      </div>

      <div v-else class="mt-6 flex flex-col gap-3">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <a href="{{ $chatGptHelperUrl }}" target="_blank" rel="noreferrer"
            class="btn-primary inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded px-5 py-2 text-sm font-medium leading-none no-underline text-inherit">
            userscript を更新
          </a>
          <button type="button" @click="reloadForChatGptHelperUpdate"
            class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded border border-[var(--color-primary)] px-5 py-2 text-sm font-medium leading-none text-[var(--color-primary)]">
            再読込して反映
          </button>
        </div>
        <div class="flex justify-start sm:justify-end">
          <button type="button" @click="closeChatGptHelperUpdateModal"
            class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-5 py-2 text-sm font-medium leading-none">
          あとで
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showChatGptRunModal" class="modal-overlay" v-esc="closeChatGptRun">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold">ChatGPT自動解析</h3>
          <p class="mt-2 text-sm opacity-70">実行条件の確認、進行状況の把握、fallback への切り替えをこのモーダルに集約しています。</p>
          <p v-if="isChatGptJobBusy" class="mt-2 text-xs text-[var(--color-tag-warning)]">解析中はこのモーダルを閉じず、部品登録画面の操作をロックしています。</p>
        </div>
        <button type="button" @click="closeChatGptRun" :disabled="!canDismissChatGptRun" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-20">✕</button>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="text-[11px] opacity-55">解析対象PDF</div>
          <div class="mt-1 text-sm font-semibold break-words">@{{ datasheetTargetLabel || '未選択' }}</div>
          <p class="mt-2 text-[11px] opacity-60">複数PDFを選んだときだけ、解析に使う1件を選び直せます。</p>
        </div>
        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="text-[11px] opacity-55">実行前チェック</div>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="chip in chatGptStatusChips" :key="chip.label"
              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
              :class="{
                'bg-[var(--color-tag-ok)]/15 text-[var(--color-tag-ok)]': chip.tone === 'ok',
                'bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]': chip.tone === 'warning',
                'bg-[var(--color-tag-eol)]/15 text-[var(--color-tag-eol)]': chip.tone === 'danger',
                'bg-[var(--color-primary)]/10 text-[var(--color-primary)]': chip.tone === 'neutral'
              }">
              @{{ chip.label }}
            </span>
          </div>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="text-sm font-semibold">次にやること</div>
        <p class="mt-2 text-sm opacity-75">@{{ chatGptGuideReason || showChatGptRunHint }}</p>
        <p v-if="chatGptJob.connected && chatGptJob.helperVersion && !isChatGptHelperVersionCompatible()" class="mt-2 text-xs text-[var(--color-tag-warning)]">
          userscript が旧版です。下の「userscript を更新」を押し、Tampermonkey の更新画面で置き換えてください。
        </p>
        <p v-if="chatGptJob.detail" class="mt-2 text-xs opacity-60">@{{ chatGptJob.detail }}</p>
        <p v-if="chatGptJob.error" class="mt-2 text-xs text-[var(--color-tag-eol)]">@{{ chatGptJob.error }}</p>
      </div>

      <div class="mt-4 grid gap-2 md:grid-cols-4">
        <div v-for="step in chatGptStepStates" :key="step.label"
          class="rounded-xl border px-3 py-3 text-sm"
          :class="{
            'border-[var(--color-tag-ok)] bg-[var(--color-tag-ok)]/10': step.status === 'done',
            'border-[var(--color-primary)] bg-[var(--color-primary)]/10': step.status === 'current',
            'border-[var(--color-border)] opacity-70': step.status === 'pending'
          }">
          <div class="text-[11px] opacity-60">ステップ</div>
          <div class="mt-1 font-semibold">@{{ step.label }}</div>
        </div>
      </div>

      <div class="mt-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
          <button v-if="datasheetFiles.length > 1" type="button" @click="openDatasheetManager" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            解析対象を選び直す
          </button>
          <a href="{{ $chatGptHelperUrl }}" target="_blank" rel="noreferrer"
            :class="isChatGptJobBusy ? 'pointer-events-none opacity-40' : ''"
            class="px-4 py-2 rounded border border-[var(--color-border)] text-sm no-underline text-inherit">
            userscript を更新
          </a>
          <button type="button" @click="openChatGptHelperUpdateModal" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            更新手順
          </button>
          <button type="button" @click="openPasteFallbackFromGuide" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            手動貼り付けへ
          </button>
          <button type="button" @click="hardResetChatGptJob" class="px-4 py-2 rounded border border-[var(--color-tag-eol)] text-sm text-[var(--color-tag-eol)]">
            ジョブを破棄してリセット
          </button>
          <button type="button" v-if="chatGPTPasteText.trim()" @click="copyChatGptFallbackText" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            応答テキストをコピー
          </button>
        </div>
        <button type="button" @click="startChatGPTAutoFill"
          :disabled="!canStartChatGptAutoFill || isChatGptJobBusy"
          class="btn-primary px-5 py-2 rounded text-sm disabled:opacity-50">
          @{{ chatGptJob.state === 'idle' ? '解析を開始' : '再実行する' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ChatGPT 貼り付けモーダル -->
  <div v-if="showHelperResultModal && helperResult" class="modal-overlay" v-esc="closeHelperResultModal">
    <div class="modal-window modal-helper-review p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-center justify-between gap-4 mb-4">
        <div>
          <h3 class="text-lg font-bold">解析候補を確認</h3>
          <p class="mt-1 text-xs opacity-60">適用前に内容を修正できます。未マッチ項目はここで既存マスタへ紐付けてください。</p>
        </div>
        <button type="button" @click="closeHelperResultModal" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div class="space-y-6">
        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="font-semibold">基本情報</h4>
          </div>
          <div class="space-y-3">
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-center text-sm">
              <input type="checkbox" v-model="helperResult.part_number.apply" class="rounded" />
              <span class="opacity-60">型番</span>
              <input v-model="helperResult.part_number.value" type="text" class="input-text w-full" placeholder="型番" />
            </label>
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-center text-sm">
              <input type="checkbox" v-model="helperResult.manufacturer.apply" class="rounded" />
              <span class="opacity-60">メーカー</span>
              <input v-model="helperResult.manufacturer.value" type="text" class="input-text w-full" placeholder="メーカー" />
            </label>
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-center text-sm">
              <input type="checkbox" v-model="helperResult.common_name.apply" class="rounded" />
              <span class="opacity-60">通称</span>
              <input v-model="helperResult.common_name.value" type="text" class="input-text w-full" placeholder="通称・シリーズ名" />
            </label>
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-start text-sm">
              <input type="checkbox" v-model="helperResult.description.apply" class="rounded mt-3" />
              <span class="opacity-60 mt-2">説明</span>
              <textarea v-model="helperResult.description.value" class="input-text w-full min-h-24" placeholder="説明"></textarea>
            </label>
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-semibold">部品種別 / 分類候補</h4>
              <p class="text-[11px] opacity-60 mt-1">候補名を修正したり、既存の分類へ手動で紐付けできます。</p>
            </div>
            <button type="button" @click="addHelperCategory" class="text-xs link-text">+ 分類候補を追加</button>
          </div>
          <div v-if="helperResult.categories.length" class="space-y-2">
            <div v-for="(category, index) in helperResult.categories" :key="`helper-category-${index}`"
              class="grid grid-cols-1 md:grid-cols-[auto_1.1fr_1fr_auto] gap-2 items-center rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="category.apply" class="rounded" />
                <span class="opacity-70">適用</span>
              </label>
              <input v-model="category.name" type="text" class="input-text w-full" placeholder="分類候補名" />
              <select v-model="category.category_id" @change="handleHelperCategorySelection(category)" class="input-text w-full">
                <option value="">既存分類に未紐付け</option>
                <option v-for="masterCategory in categories" :key="masterCategory.id" :value="masterCategory.id">@{{ masterCategory.name }}</option>
              </select>
              <div class="flex items-center justify-end gap-2">
                <span v-if="category.matched" class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)]">一致</span>
                <span v-else class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未一致</span>
                <button type="button" @click="removeHelperCategory(index)" class="text-[var(--color-tag-eol)] text-sm px-2">✕</button>
              </div>
            </div>
          </div>
          <p v-else class="text-xs opacity-40">分類候補はまだありません</p>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h4 class="font-semibold">パッケージ候補</h4>
              <p class="text-[11px] opacity-60 mt-1">複数候補の中から、この部品として登録する詳細パッケージを 1 つ選びます。</p>
            </div>
            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="helperResult.package_apply" class="rounded" />
                <span class="opacity-70">選択した候補を適用</span>
              </label>
              <button type="button" @click="addHelperPackage" class="text-xs link-text">+ パッケージ候補を追加</button>
            </div>
          </div>
          <div v-if="helperResult.packages.length" class="space-y-2">
            <div v-for="(packageCandidate, index) in helperResult.packages" :key="`helper-package-${index}`"
              class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4 space-y-3">
              <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="radio"
                    v-model="helperResult.selected_package_index"
                    :value="index"
                    :disabled="!helperResult.package_apply" />
                  <span class="opacity-70">この候補で登録</span>
                </label>
                <div class="flex items-center gap-2">
                  <span v-if="packageCandidate.matched" class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)]">一致</span>
                  <span v-else class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未一致</span>
                  <button type="button" @click="removeHelperPackage(index)" class="text-[var(--color-tag-eol)] text-sm px-2">✕</button>
                </div>
              </div>

              <label class="grid grid-cols-[88px_1fr] gap-3 items-center text-sm">
                <span class="opacity-60">候補名</span>
                <input v-model="packageCandidate.name" type="text" class="input-text w-full" placeholder="例: SOT-23 / SOP-8 / TO-220 / 0603" />
              </label>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] font-semibold mb-1 opacity-70">パッケージ分類</label>
                  <select v-model="packageCandidate.package_group_id" @change="handleHelperPackageGroupChange(packageCandidate)" class="input-text w-full">
                    <option value="">未選択</option>
                    <option v-for="group in packageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[11px] font-semibold mb-1 opacity-70">詳細パッケージを絞り込み</label>
                  <input v-model="packageCandidate.package_query" type="text" class="input-text w-full" :disabled="!packageCandidate.package_group_id" placeholder="例: SOT / 0603 / SOP" />
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-semibold mb-1 opacity-70">詳細パッケージ</label>
                <select v-model="packageCandidate.package_id" @change="handleHelperPackageSelection(packageCandidate)" class="input-text w-full">
                  <option value="">未選択</option>
                  <option v-for="pkg in helperFilteredPackages(packageCandidate)" :key="pkg.id" :value="pkg.id">@{{ pkg.name }}</option>
                </select>
                <p class="text-[11px] opacity-50 mt-2" v-if="!packageCandidate.package_group_id">先にパッケージ分類を選択してください。</p>
                <p class="text-[11px] opacity-50 mt-2" v-else-if="!helperFilteredPackages(packageCandidate).length">選択中の分類に該当する詳細パッケージがありません。</p>
              </div>
            </div>
          </div>
          <p v-else class="text-xs opacity-40">パッケージ候補はまだありません</p>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-semibold">スペック候補</h4>
              <p class="text-[11px] opacity-60 mt-1">値・単位・スペック種別を修正できます。不要な候補は削除してください。</p>
            </div>
            <button type="button" @click="addHelperSpec" class="text-xs link-text">+ スペック候補を追加</button>
          </div>
          <div v-if="helperResult.specs.length" class="space-y-2">
            <div v-for="(spec, index) in helperResult.specs" :key="`helper-spec-${index}`"
              class="spec-card bg-[var(--color-card-odd)] space-y-3">
              <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="spec.apply" class="rounded" />
                  <span class="opacity-70">この候補を適用</span>
                </label>
                <div class="flex items-center gap-2">
                  <span v-if="spec.matched" class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)]">一致</span>
                  <span v-else class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未一致</span>
                  <button type="button" @click="removeHelperSpec(index)" class="text-[var(--color-tag-eol)] text-sm px-2">✕</button>
                </div>
              </div>
              <div class="spec-card-grid spec-card-grid--helper">
                <div class="spec-card-field">
                  <label class="spec-card-label">種別</label>
                  <div class="spec-type-picker">
                    <select v-model="spec.spec_type_id" @change="handleHelperSpecTypeSelection(spec)" class="input-text spec-card-control w-full">
                      <option value="">スペック種別を選択</option>
                      <option v-for="st in specTypes" :key="`helper-type-${index}-${st.id}`" :value="st.id">@{{ specTypeOptionLabel(st) }}</option>
                    </select>
                    <button v-if="canCreateSpecType" type="button" @click="openInlineSpecTypeModal(spec)" class="spec-type-add-button" title="スペック種別を追加" aria-label="スペック種別を追加">＋</button>
                  </div>
                  <div class="space-y-1">
                    <p v-if="spec.name" class="spec-card-help">抽出名: @{{ spec.name }}</p>
                    <p v-if="spec.name_ja || spec.name_en || spec.symbol" class="spec-card-help">候補: @{{ [spec.name_ja, spec.name_en, spec.symbol].filter(Boolean).join(' / ') }}</p>
                  </div>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">値の種類</label>
                  <div class="spec-card-profile">
                    <button v-for="option in specProfileOptions" :key="`helper-profile-${index}-${option.value}`" type="button"
                      @click="changeSpecProfile(spec, option.value)"
                      class="spec-card-profile-button"
                      :class="spec.value_profile === option.value ? 'bg-[var(--color-primary)] text-white' : 'opacity-70 hover:bg-[var(--color-card-even)]'">
                      @{{ option.label }}
                    </button>
                  </div>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">値</label>
                  <label v-if="spec.value_profile === 'typ'" class="spec-card-subfield">
                    <span class="spec-card-subfield-label">typ</span>
                    <input v-model="spec.value_typ" type="text" class="input-text spec-card-control w-full" placeholder="typ値" />
                  </label>
                  <label v-else-if="spec.value_profile === 'max_only'" class="spec-card-subfield">
                    <span class="spec-card-subfield-label">最大値</span>
                    <input v-model="spec.value_max" type="text" class="input-text spec-card-control w-full" placeholder="最大値" />
                  </label>
                  <label v-else-if="spec.value_profile === 'min_only'" class="spec-card-subfield">
                    <span class="spec-card-subfield-label">最小値</span>
                    <input v-model="spec.value_min" type="text" class="input-text spec-card-control w-full" placeholder="最小値" />
                  </label>
                  <div v-else-if="spec.value_profile === 'range'" class="spec-card-values--range">
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">最小値</span>
                      <input v-model="spec.value_min" type="text" class="input-text spec-card-control w-full" placeholder="最小値" />
                    </label>
                    <span class="text-xs opacity-50 pb-3">〜</span>
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">最大値</span>
                      <input v-model="spec.value_max" type="text" class="input-text spec-card-control w-full" placeholder="最大値" />
                    </label>
                  </div>
                  <div v-else class="spec-card-values--triple">
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">min</span>
                      <input v-model="spec.value_min" type="text" class="input-text spec-card-control w-full" placeholder="min" />
                    </label>
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">typ</span>
                      <input v-model="spec.value_typ" type="text" class="input-text spec-card-control w-full" placeholder="typ" />
                    </label>
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">max</span>
                      <input v-model="spec.value_max" type="text" class="input-text spec-card-control w-full" placeholder="max" />
                    </label>
                  </div>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">単位</label>
                  <input v-model="spec.unit" type="text" class="input-text spec-card-control w-full" placeholder="単位" :list="`helper-spec-unit-${index}`" />
                  <datalist :id="`helper-spec-unit-${index}`">
                    <option v-for="unitOption in getUnitSuggestions(spec.spec_type_id)" :key="`helper-${index}-${unitOption}`" :value="unitOption">@{{ unitOption }}</option>
                  </datalist>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">確認</label>
                  <div class="spec-card-preview spec-card-preview-panel text-[11px]">
                    <p class="text-sm font-semibold leading-tight break-words">@{{ specDisplayName(spec) || 'スペック種別を選択' }}</p>
                    <template v-if="specPreview(spec).hasNumeric">
                      <p class="opacity-75 break-words">表示: @{{ specPreview(spec).recommendedText }}</p>
                      <p class="opacity-55 break-words">基底: @{{ specPreview(spec).canonicalText }}</p>
                    </template>
                    <p v-else class="opacity-50 break-words">数値化できると基底換算を表示します。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-xs opacity-40">スペック候補はまだありません</p>
        </section>
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between mt-6">
        <button type="button" @click="discardHelperResult" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
          候補を破棄
        </button>
        <div class="flex flex-col-reverse gap-2 sm:flex-row">
          <button type="button" @click="closeHelperResultModal" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
            後で確認する
          </button>
          <button type="button" @click="applyHelperResult" class="btn-primary px-4 py-2 rounded text-sm">
            選択した候補を適用
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- スペック種別追加モーダル -->
  <div v-if="inlineSpecTypeModal.open" class="modal-overlay" style="z-index: 70" v-esc="closeInlineSpecTypeModal">
    <div class="modal-window modal-md p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-center justify-between gap-4 mb-4">
        <h3 class="text-lg font-bold">スペック種別を追加</h3>
        <button type="button" @click="closeInlineSpecTypeModal()" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs font-semibold mb-1">日本語名 <span class="text-[var(--color-tag-eol)]">*</span></label>
          <input v-model="inlineSpecTypeModal.form.name_ja" type="text" class="input-text w-full" placeholder="例: コレクタ-ベース間電圧" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">英語名</label>
          <input v-model="inlineSpecTypeModal.form.name_en" type="text" class="input-text w-full" placeholder="例: Collector-Base Voltage" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold mb-1">記号</label>
            <input v-model="inlineSpecTypeModal.form.symbol" type="text" class="input-text w-full" placeholder="例: V_CBO / h_FE" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">基底単位</label>
            <input v-model="inlineSpecTypeModal.form.unit" type="text" class="input-text w-full" placeholder="例: V / A / Ω" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">alias</label>
          <textarea v-model="inlineSpecTypeModal.form.aliases_text" rows="3" class="input-text w-full" placeholder="1行に1つ。データシート上の別表記など"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-5">
        <button type="button" @click="closeInlineSpecTypeModal()" class="btn text-sm px-4 py-3 rounded border border-[var(--color-border)]">キャンセル</button>
        <button type="button" @click="saveInlineSpecType" :disabled="inlineSpecTypeModal.saving" class="btn btn-primary text-sm px-5 py-3 rounded disabled:opacity-40">
          @{{ inlineSpecTypeModal.saving ? '保存中...' : '保存して選択' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ChatGPT 貼り付けモーダル -->
  <div v-if="showChatGPTPaste"
    class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-6"
    v-esc="dismissChatGPTPaste"
    role="dialog"
    aria-modal="true"
    aria-labelledby="chatgpt-paste-title">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-3xl overflow-hidden rounded-3xl border border-[var(--color-border)] bg-[var(--color-bg)] shadow-2xl" @click.stop>
      <div class="flex items-start justify-between gap-4 border-b border-[var(--color-border)] px-5 py-4">
        <div>
          <h2 id="chatgpt-paste-title" class="text-lg font-bold">ChatGPT の出力を貼り付け</h2>
          <p class="mt-1 text-xs opacity-60">ChatGPT にデータシート PDF を添付し、`プロンプト/データシート解析プロンプト.md` の指示で返ってきた JSON をここに貼り付けてください。</p>
        </div>
        <button type="button" @click="dismissChatGPTPaste" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100 leading-none">✕</button>
      </div>
      <div class="space-y-4 px-5 py-5">
        <textarea
          ref="chatGPTPasteTextarea"
          v-model="chatGPTPasteText"
          rows="12"
          class="input-text w-full resize-y text-xs font-mono min-h-72"
          placeholder='{"part_number": "...", "manufacturer": "...", "specs": [...]}'>
        </textarea>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
          <button type="button" @click="dismissChatGPTPaste" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
            キャンセル
          </button>
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
            <button type="button" @click="chatGPTPasteText = ''" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
              内容をクリア
            </button>
            <button type="button" @click="parseChatGPTResult" :disabled="!chatGPTPasteText.trim()" class="btn-primary px-4 py-2 rounded text-sm disabled:opacity-50">
              解析して適用候補に表示
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">@{{ t.msg }}</div>
  </div>
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '部品一覧', 'url' => route('components.index')],
    ['label' => isset($id) ? '部品編集' : '部品登録', 'current' => true],
  ], 'class' => 'mt-6'])
</div>
</body>
</html>
