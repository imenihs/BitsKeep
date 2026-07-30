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
  </section>

  <!-- スペック -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <div class="flex justify-between items-center mb-3">
      <div class="flex items-center gap-2">
        <h2 class="font-bold">スペック</h2>
        <span v-if="!form.specs.length" class="text-xs px-1.5 py-0.5 rounded bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未追加</span>
      </div>
    </div>
    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-4 mb-3">
      <div class="grid gap-3 lg:grid-cols-[minmax(180px,240px)_minmax(0,1fr)_auto] lg:items-start">
        <div>
          <label class="block text-xs font-semibold mb-1">部品分類</label>
          <select v-model="selectedSpecGroupId" class="input-text w-full text-sm">
            <option value="all">フィルタしない</option>
            <option v-for="group in specGroupOptions" :key="`create-spec-filter-${group.id}`" :value="String(group.id)">
              @{{ group.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">スペック候補</label>
          <select v-model="selectedSpecCandidateId" class="input-text w-full text-sm" :disabled="filteredSpecTypesForPicker().length === 0">
            <option value="">スペック候補を選択</option>
            <option v-for="st in filteredSpecTypesForPicker()" :key="`create-spec-candidate-${st.id}`" :value="st.id">
              @{{ specTypePickerOptionLabel(st) }}
            </option>
          </select>
        </div>
        <div class="lg:mt-5">
          <button type="button" @click="addSelectedSpecCandidate" :disabled="!selectedSpecCandidateId" class="btn-primary h-10 rounded px-3 py-2 text-xs disabled:opacity-40 disabled:cursor-not-allowed">追加</button>
        </div>
        <div class="hidden lg:block"></div>
        <div class="space-y-2">
          <label class="block text-xs font-semibold mb-1">入力テンプレート</label>
          <select v-model="selectedSpecTemplateId" class="input-text w-full text-sm" :disabled="!visibleSpecTemplates.length">
            <option value="">入力テンプレートを選択</option>
            <option v-for="template in visibleSpecTemplates" :key="`create-template-option-${template.id}`" :value="template.id">
              @{{ specTemplateLabel(template) }}
            </option>
          </select>
          <div class="min-h-8 rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-2 py-1.5">
            <div v-if="selectedSpecTemplateItems.length" class="flex flex-wrap gap-1">
              <span v-for="item in selectedSpecTemplateItems" :key="`create-template-preview-${selectedSpecTemplate?.id}-${item.id ?? item.spec_type_id}`"
                class="inline-flex items-center rounded-full border border-[var(--color-border)] bg-[var(--color-bg)] px-1.5 py-0.5 text-[11px] leading-tight">
                @{{ templateItemPreviewLabel(item) }}
              </span>
            </div>
            <span v-else class="text-[11px] opacity-45">テンプレート未選択</span>
          </div>
        </div>
        <button type="button" @click="applySelectedSpecTemplate" :disabled="!selectedSpecTemplate" class="btn-primary h-10 rounded px-3 py-2 text-xs disabled:opacity-40 disabled:cursor-not-allowed lg:mt-5">一式追加</button>
      </div>
      <div v-if="canCreateSpecType" class="mt-4 border-t border-[var(--color-border)] pt-3">
        <button type="button" @click="openInlineSpecTypeModal()" class="btn h-10 rounded border border-[var(--color-border)] px-3 py-2 text-xs">
          スペックを新規で追加
        </button>
      </div>
      <div v-if="specSuggestionLoading" class="mt-2 text-xs opacity-50">読込中...</div>
    </div>
    <div class="mb-2 text-xs font-semibold">登録済みスペック</div>
    <div v-for="(spec, i) in form.specs" :key="i" class="spec-card mb-3 bg-[var(--color-card-odd)]">
      <div class="spec-card-grid spec-card-grid--editor">
        <div class="spec-card-field">
          <label class="spec-card-label">スペック詳細</label>
          <div class="spec-type-picker">
            <select v-model="spec.spec_type_id" @change="handleSpecTypeSelection(spec)" class="input-text spec-card-control text-sm py-1 w-full">
              <option value="">スペック詳細を選択</option>
              <option v-for="st in filteredSpecTypesForPicker(spec)" :key="`type-${i}-${st.id}`" :value="st.id">@{{ specTypePickerOptionLabel(st) }}</option>
            </select>
          </div>
          <p v-if="spec.name" class="spec-card-help">データシート表記: @{{ spec.name }}</p>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">値種別</label>
          <select v-if="isToleranceSpecRow(spec)" :value="'tolerance'"
            class="input-text spec-card-control spec-card-profile-select w-full"
            aria-label="値種別: 許容差">
            <option value="tolerance">許容差</option>
          </select>
          <div v-else class="spec-card-profile-select-wrap">
            <select :value="spec.value_profile" @change="changeSpecProfile(spec, $event.target.value)"
              class="input-text spec-card-control spec-card-profile-select w-full"
              :title="specProfileHelpText(spec.value_profile)"
              :aria-label="`値種別: ${specProfileControlLabel(spec.value_profile)}`">
              <option v-for="option in specProfileOptions" :key="`create-profile-${i}-${option.value}`" :value="option.value">
                @{{ specProfileControlLabel(option.value) }}
              </option>
            </select>
          </div>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">値</label>
          <template v-if="isToleranceSpecRow(spec)">
            <div class="spec-card-subfield">
              <span class="spec-card-subfield-label">許容値</span>
              <div class="tolerance-combobox" @click.stop>
                <input v-model="spec.value_typ" type="text" class="input-text spec-card-control text-sm py-1 w-full"
                  :class="toleranceGradeOptionsFor(spec).length ? 'tolerance-combobox-input' : ''"
                  :placeholder="toleranceValuePlaceholder(spec)"
                  @keydown.escape.stop="closeToleranceGradeMenu" />
                <button v-if="toleranceGradeOptionsFor(spec).length" type="button"
                  class="tolerance-combobox-toggle"
                  :class="isToleranceGradeMenuOpen('create', i) ? 'is-open' : ''"
                  :aria-expanded="isToleranceGradeMenuOpen('create', i) ? 'true' : 'false'"
                  aria-haspopup="listbox"
                  aria-label="許容差候補を選択"
                  title="許容差候補を選択"
                  @click.stop="toggleToleranceGradeMenu('create', i, spec)">
                  ▾
                </button>
                <div v-if="isToleranceGradeMenuOpen('create', i)" class="tolerance-combobox-menu" role="listbox">
                  <button v-for="option in toleranceGradeOptionsFor(spec)" :key="`create-tolerance-grade-${i}-${option.label || option.rank}`"
                    type="button" class="tolerance-combobox-option" role="option"
                    @click.stop="selectToleranceGradeOption(spec, option)">
                    @{{ toleranceGradeOptionLabel(option) }}
                  </button>
                </div>
              </div>
            </div>
          </template>
          <label v-else-if="spec.value_profile === 'typ'" class="spec-card-subfield">
            <span class="spec-card-subfield-label">TYP</span>
            <input v-model="spec.value_typ" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="例: 1 / 1e-6" />
          </label>
          <label v-else-if="spec.value_profile === 'max_only'" class="spec-card-subfield">
            <span class="spec-card-subfield-label">MAX</span>
            <input v-model="spec.value_max" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="MAX" />
          </label>
          <label v-else-if="spec.value_profile === 'min_only'" class="spec-card-subfield">
            <span class="spec-card-subfield-label">MIN</span>
            <input v-model="spec.value_min" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="MIN" />
          </label>
          <div v-else-if="spec.value_profile === 'range'" class="spec-card-values--range">
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">MIN</span>
              <input v-model="spec.value_min" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="MIN" />
            </label>
            <span class="text-xs opacity-50 pb-3">〜</span>
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">MAX</span>
              <input v-model="spec.value_max" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="MAX" />
            </label>
          </div>
          <div v-else class="spec-card-values--triple">
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">MIN</span>
              <input v-model="spec.value_min" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="MIN" />
            </label>
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">TYP</span>
              <input v-model="spec.value_typ" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="TYP" />
            </label>
            <label class="spec-card-subfield">
              <span class="spec-card-subfield-label">MAX</span>
              <input v-model="spec.value_max" type="text" class="input-text spec-card-control text-sm py-1 w-full" placeholder="MAX" />
            </label>
          </div>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">単位</label>
          <select v-if="isToleranceSpecRow(spec)" v-model="spec.unit" class="input-text spec-card-control text-sm py-1 w-full">
            <option v-for="unitOption in toleranceUnitOptionsFor(spec)" :key="`create-tolerance-unit-${i}-${unitOption}`" :value="unitOption">@{{ unitOption }}</option>
          </select>
          <template v-else>
            <input v-model="spec.unit" type="text" class="input-text spec-card-control text-sm py-1 w-full"
              :readonly="hasSpecBaseUnit(spec)"
              :placeholder="hasSpecBaseUnit(spec) ? '' : '単位'"
              :list="hasSpecBaseUnit(spec) ? null : `spec-unit-create-${i}`" />
            <datalist v-if="!hasSpecBaseUnit(spec)" :id="`spec-unit-create-${i}`">
              <option v-for="unitOption in getUnitSuggestions(spec.spec_type_id)" :key="`${i}-${unitOption}`" :value="unitOption">@{{ unitOption }}</option>
            </datalist>
          </template>
        </div>
        <div class="spec-card-field">
          <label class="spec-card-label">確認</label>
          <div class="spec-card-preview spec-card-preview-panel text-[11px]">
            <p class="text-sm font-semibold leading-tight break-words">
              @{{ specDisplayName(spec) || 'スペック名を選択' }}
              <span v-if="specProfileBadge(spec)" class="tag ml-1 text-[10px] align-middle">@{{ specProfileBadge(spec) }}</span>
            </p>
            <template v-if="specPreview(spec).hasNumeric">
              <p class="opacity-75 break-words">入力値: @{{ specPreview(spec).recommendedText }}</p>
              <p class="opacity-55 break-words">標準単位換算: @{{ specPreview(spec).canonicalText }}</p>
            </template>
            <p v-else class="opacity-50 break-words">数値として扱える場合は標準単位換算を表示します。</p>
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

  <!-- 部品分類・パッケージ -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <h2 class="font-bold mb-3">部品分類 / パッケージ</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <label class="text-xs font-semibold block mb-2">部品分類（複数選択可）</label>
        <div class="border border-[var(--color-border)] rounded p-2 bg-[var(--color-bg)]">
          <input v-model="categoryQuery" type="text" class="input-text w-full"
            placeholder="部品分類名で絞り込み。なければ追加" />
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
            <p v-if="!filteredCategories.length && !canCreateCategory" class="text-xs opacity-40 p-1">部品分類がありません</p>
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
            placeholder="パッケージ名で絞り込み。なければ追加" />
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
            <p v-else-if="!filteredPackages.length && !canCreatePackage" class="text-xs opacity-40 p-1">パッケージがありません</p>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-4 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
      <label class="text-xs font-semibold block mb-2">登録単位</label>
      <div class="flex flex-wrap gap-2">
        <button type="button" @click="componentRegistrationMode = 'single'"
          class="px-3 py-2 rounded border text-sm"
          :class="componentRegistrationMode === 'single' ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)]'">
          単体
        </button>
        <button type="button" @click="componentRegistrationMode = 'series'"
          class="px-3 py-2 rounded border text-sm"
          :class="componentRegistrationMode === 'series' ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)]'">
          シリーズ
        </button>
      </div>
      <div v-if="componentRegistrationMode === 'series' && componentSeriesLoadError" class="mt-3 flex flex-wrap items-center gap-2 rounded border border-[var(--color-tag-warning)]/40 bg-[var(--color-tag-warning)]/10 px-3 py-2 text-xs">
        <span>@{{ componentSeriesLoadError }}</span>
        <button type="button" @click="fetchComponentSeriesOptions" class="px-2 py-1 rounded border border-[var(--color-border)]">再取得</button>
      </div>
      <div v-else-if="componentRegistrationMode === 'series' && !componentSeriesLoading && !componentSeriesOptions.length" class="mt-3 flex flex-wrap items-center gap-2 rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-3 py-2 text-xs">
        <span>部品シリーズ未登録</span>
        <a href="{{ route('component-series.index') }}" class="px-2 py-1 rounded border border-[var(--color-border)] no-underline text-inherit hover:border-[var(--color-primary)]">部品シリーズ管理へ</a>
      </div>
      <div v-else-if="componentRegistrationMode === 'series'" class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold mb-1">部品シリーズ</label>
          <select v-model="form.component_series_id" class="input-text w-full" :disabled="componentSeriesLoading">
            <option value="">@{{ componentSeriesLoading ? '読込中...' : '選択してください' }}</option>
            <option v-for="series in componentSeriesOptions" :key="`component-series-${series.id}`" :value="series.id">
              @{{ componentSeriesOptionLabel(series) }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">シリーズ値</label>
          <select v-model="form.component_series_value_id" class="input-text w-full" :disabled="!form.component_series_id || !componentSeriesValueOptions.length">
            <option value="">未選択</option>
            <option v-for="value in componentSeriesValueOptions" :key="`component-series-value-${value.id}`" :value="value.id">
              @{{ value.value_text }}
            </option>
          </select>
        </div>
      </div>
    </div>
  </section>

  <!-- データシート・画像 -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <h2 class="font-bold mb-3">データシート・画像</h2>
    <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
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
        {{-- 解析の入口は1つに保つ。どの解析方式で動くかは連携設定で決まる運用設定であり、
             利用者に毎回選ばせない --}}
        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
          <button type="button" @click="startServerAnalysis"
            :disabled="!hasDatasheetForAi || isServerAnalyzing"
            :title="hasDatasheetForAi ? 'データシートPDFを読み取って入力候補を作ります' : '先にデータシートPDFを選択してください'"
            class="flex w-full min-w-0 items-center justify-center gap-1 rounded border px-2 py-2 text-[11px] leading-tight transition-colors disabled:cursor-not-allowed disabled:opacity-40"
            :class="hasDatasheetForAi
              ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white hover:opacity-90'
              : 'border-[var(--color-border)] bg-[var(--color-card-even)] text-[var(--color-text)]'">
            <span v-if="isServerAnalyzing">⏳ 解析中...</span>
            <span v-else>✨ データシートから自動入力</span>
          </button>
          <button type="button" @click="openChatGPTPaste"
            class="flex w-full min-w-0 items-center justify-center gap-1 rounded border border-[var(--color-border)] px-2 py-2 text-[11px] leading-tight transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]">
            📋 解析結果を貼り付け
          </button>
        </div>

        {{-- 解析中はページを離れても解析が続く。待つ以外の操作は中止だけに絞る --}}
        <div v-if="isServerAnalyzing" class="mt-3 rounded-2xl border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/5 p-3">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
              <p class="text-xs font-semibold">⏳ @{{ serverAnalysisProgressLabel }}</p>
              <p class="mt-1 text-[11px] opacity-60">この画面を閉じても解析は続きます。戻ると続きから表示します。</p>
            </div>
            <button type="button" @click="cancelServerAnalysis"
              class="shrink-0 rounded border border-[var(--color-border)] px-3 py-1.5 text-[11px] hover:border-[var(--color-tag-eol)] hover:text-[var(--color-tag-eol)]">
              中止する
            </button>
          </div>
        </div>

        {{-- 失敗理由ごとに次の一手を変える。再実行が無意味な失敗で再実行を勧めない --}}
        <div v-else-if="serverAnalysisState === 'failed'" class="mt-3 rounded-2xl border border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))] p-3">
          <p class="text-xs font-semibold text-[var(--color-tag-warning)]">解析できませんでした</p>
          <p class="mt-1 text-[11px] opacity-80">@{{ serverAnalysisFailureMessage }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button v-if="serverAnalysisRetryable" type="button" @click="startServerAnalysis"
              class="rounded border border-[var(--color-primary)] px-3 py-1.5 text-[11px] text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10">
              もう一度実行
            </button>
            <a v-if="serverAnalysisNeedsSetup" href="/settings/integrations"
              class="rounded border border-[var(--color-primary)] px-3 py-1.5 text-[11px] text-[var(--color-primary)] no-underline hover:bg-[var(--color-primary)]/10">
              連携設定を開く
            </a>
            <button v-if="serverAnalysisSuggestPaste" type="button" @click="openChatGPTPaste"
              class="rounded border border-[var(--color-border)] px-3 py-1.5 text-[11px] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]">
              貼り付けで入力
            </button>
            <button type="button" @click="dismissServerAnalysisFailure"
              class="rounded border border-[var(--color-border)] px-3 py-1.5 text-[11px] opacity-70">
              閉じる
            </button>
          </div>
        </div>

        {{-- 旧方式。既定の導線から外し、主導線が使えないときの退避先として残す --}}
        <details class="mt-3 rounded-2xl border border-[var(--color-border)] px-3 py-2">
          <summary class="cursor-pointer text-[11px] opacity-60">別の解析方法を使う（旧方式）</summary>
          <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button type="button" @click="beginAiAction('chatgpt')"
              :disabled="!hasDatasheetForAi"
              :title="hasDatasheetForAi ? 'ブラウザのChatGPTタブを使って解析します' : '先にデータシートPDFを選択してください'"
              class="flex w-full min-w-0 items-center justify-center gap-1 rounded border border-[var(--color-border)] px-2 py-2 text-[11px] leading-tight transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] disabled:cursor-not-allowed disabled:opacity-40">
              🤖 ChatGPTタブで解析
            </button>
            <button type="button" @click="beginAiAction('gemini')"
              :disabled="!hasDatasheetForAi || analyzing"
              :title="hasDatasheetForAi ? 'この画面を開いたまま待つ解析です' : '先にデータシートPDFを選択してください'"
              class="flex w-full min-w-0 items-center justify-center gap-1 rounded border border-[var(--color-border)] px-2 py-2 text-[11px] leading-tight transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] disabled:cursor-not-allowed disabled:opacity-40">
              <span v-if="analyzing">⏳ 解析中...</span>
              <span v-else>✨ この画面で待つ解析</span>
            </button>
          </div>
          <p class="mt-2 text-[11px] opacity-50">旧方式はこの画面またはブラウザのタブを開いたままにする必要があります。</p>
        </details>

        <div v-if="helperResult && helperResultSummary" class="mt-4 rounded-2xl border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/5 p-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold">✨ 解析候補を保持中</p>
              <p class="mt-1 text-[11px] opacity-60">
                基本情報 @{{ helperResultSummary.basicCount }} 件 /
                部品分類 @{{ helperResultSummary.categoryCount }} 件 /
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

  <!-- 在庫 -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <h2 class="font-bold mb-3">在庫</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold mb-1">発注点（新品）</label>
        <input v-model.number="form.threshold_new" type="number" min="0" class="input-text w-full" />
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">発注点（中古）</label>
        <input v-model.number="form.threshold_used" type="number" min="0" class="input-text w-full" />
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">代表保管棚</label>
        <select v-model="form.primary_location_id" class="input-text w-full">
          <option value="">未設定</option>
          <option v-for="location in locations" :key="location.id" :value="location.id">
            @{{ location.code }} / @{{ location.name }}
          </option>
        </select>
      </div>
    </div>
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

  @include('app.component-create.modals')

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
