<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品シリーズ - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($canEdit = auth()->user()->isEditor())
@include('partials.app-header', ['current' => '部品シリーズ'])

<div id="app" data-page="component-series" data-can-edit="{{ $canEdit ? '1' : '0' }}" class="min-h-screen">
  <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 space-y-6">
    @include('partials.app-breadcrumbs', ['items' => [['label' => '部品シリーズ', 'current' => true]]])

    <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold">部品シリーズ</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <input v-model="search" @input="fetchSeries" type="search" class="input-text w-64 max-w-full" placeholder="シリーズ名・メーカーで検索" />
        <button type="button" @click="openNew" class="btn-primary px-4 py-2 rounded text-sm font-semibold" :disabled="!canEdit">新規シリーズ</button>
      </div>
    </section>

    <section v-if="error" class="rounded-lg border border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))] px-4 py-3 text-sm text-[var(--color-tag-warning)]">
      @{{ error }}
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(280px,0.85fr)_minmax(0,1.6fr)]">
      <section class="rounded-lg border border-[var(--color-border)] overflow-hidden bg-[var(--color-bg)]">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex items-center justify-between">
          <div class="font-semibold">シリーズ一覧</div>
          <span class="text-xs opacity-60">@{{ seriesList.length }}件</span>
        </div>
        <div class="divide-y divide-[var(--color-border)]">
          <button v-for="item in seriesList" :key="item.id" type="button" @click="selectSeries(item)"
            class="w-full px-4 py-3 text-left hover:bg-[var(--color-card-even)]"
            :class="selected?.id === item.id ? 'bg-[var(--color-card-even)]' : ''">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-semibold truncate">@{{ item.name }}</div>
                <div class="mt-1 text-xs opacity-60 truncate">@{{ [item.manufacturer, item.spec_group?.name, item.package?.name].filter(Boolean).join(' / ') || '分類未設定' }}</div>
              </div>
              <span class="tag text-[10px]">@{{ item.enabled_values_count ?? item.values_count ?? 0 }}値</span>
            </div>
          </button>
          <div v-if="!seriesList.length" class="px-4 py-8 text-center text-sm opacity-40">部品シリーズがありません</div>
        </div>
      </section>

      <section class="space-y-6">
        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 class="font-bold">@{{ form.id ? 'シリーズ編集' : 'シリーズ作成' }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" @click="saveSeries" class="btn-primary px-4 py-2 rounded text-sm font-semibold" :disabled="!canEdit || saving">
                @{{ saving ? '保存中...' : (form.id ? '保存して再生成' : '保存して値生成') }}
              </button>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">シリーズ名</span>
              <input v-model="form.name" type="text" class="input-text w-full" />
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">メーカー</span>
              <input v-model="form.manufacturer" type="text" class="input-text w-full" />
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">部品分類</span>
              <select v-model="form.spec_group_id" @change="handleSpecGroupChange" class="input-text w-full">
                <option value="">未設定</option>
                <option v-for="group in specGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">スペック詳細</span>
              <select v-model="form.value_spec_type_id" @change="handleValueSpecTypeChange" class="input-text w-full">
                <option value="">@{{ form.spec_group_id ? '部品分類内のスペック詳細を選択' : '先に部品分類を選択' }}</option>
                <option v-for="type in filteredSpecTypes" :key="type.id" :value="type.id">@{{ type.name_ja || type.name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">パッケージ分類</span>
              <select v-model="form.package_group_id" @change="handlePackageGroupChange" class="input-text w-full">
                <option value="">未設定</option>
                <option v-for="group in packageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">パッケージ詳細</span>
              <select v-model="form.package_id" class="input-text w-full" :disabled="!form.package_group_id">
                <option value="">@{{ form.package_group_id ? '未設定' : '先にパッケージ分類を選択' }}</option>
                <option v-for="pkg in filteredPackages" :key="pkg.id" :value="pkg.id">@{{ pkg.name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">状態</span>
              <select v-model="form.status" class="input-text w-full">
                <option value="active">有効</option>
                <option value="planning">計画中</option>
                <option value="archived">アーカイブ予定</option>
              </select>
            </label>
            <label class="block md:col-span-2">
              <span class="block text-xs font-semibold opacity-60 mb-1">説明</span>
              <textarea v-model="form.description" rows="2" class="input-text w-full"></textarea>
            </label>
          </div>
        </section>

        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
          <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
              <h2 class="font-bold">値候補の作り方</h2>
              <p class="mt-1 text-xs opacity-60">選んだ方式で必要な欄だけ入力します。</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <span v-if="usesESeries" class="tag text-[10px]">@{{ seriesRangeText }}</span>
              <button type="button" @click="previewValues" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm" :disabled="!canEdit">プレビュー</button>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">方式</span>
              <select v-model="form.policy.value_set_type" class="input-text w-full">
                <option value="e_series">E系列</option>
                <option value="hybrid_series">基準系列 + 追加値</option>
                <option value="custom_list">任意値リスト</option>
                <option value="range_step">範囲/刻み</option>
                <option value="none">値展開なし</option>
              </select>
            </label>
            <label class="block">
              <span class="block text-xs font-semibold opacity-60 mb-1">単位</span>
              <input v-model="form.policy.unit" type="text" class="input-text w-full opacity-70" readonly placeholder="スペック詳細から自動設定" />
            </label>
          </div>

          <div v-if="usesESeries" class="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
              <h3 class="text-sm font-semibold">E系列</h3>
              <span class="text-xs opacity-60">@{{ inputPrefixHelpText }}</span>
            </div>
            <div class="mt-3 grid gap-4 md:grid-cols-4">
              <label class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">基準E系列</span>
                <select v-model="form.policy.primary_series" class="input-text w-full">
                  <option value="">なし</option>
                  <option v-for="name in eSeriesOptions" :key="name" :value="name">@{{ name }}</option>
                </select>
              </label>
              <label v-if="usesHybridSeries" class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">追加E系列</span>
                <input v-model="form.policy.extra_series_text" type="text" class="input-text w-full" placeholder="E24, E48" />
              </label>
              <label class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">開始値</span>
                <input v-model="form.policy.range_min" type="text" class="input-text w-full font-mono" :placeholder="startValuePlaceholder" />
                <span v-if="fieldError('range_min')" class="mt-1 block text-xs text-[var(--color-tag-warning)]">@{{ fieldError('range_min') }}</span>
              </label>
              <label class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">終了値</span>
                <input v-model="form.policy.range_max" type="text" class="input-text w-full font-mono" :placeholder="endValuePlaceholder" />
                <span v-if="fieldError('range_max')" class="mt-1 block text-xs text-[var(--color-tag-warning)]">@{{ fieldError('range_max') }}</span>
              </label>
            </div>
            <label class="mt-3 inline-flex items-center gap-2 text-sm">
              <input v-model="form.policy.include_zero" type="checkbox" class="rounded" />
              <span>0を含む</span>
            </label>
            <p class="mt-2 text-xs opacity-60">単位はスペック詳細から反映します。0はチェックで追加します。</p>
          </div>

          <div v-if="usesRangeStep" class="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
            <h3 class="text-sm font-semibold">範囲/刻み</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
              <label class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">開始値</span>
                <input v-model="form.policy.range_min" type="text" class="input-text w-full font-mono" placeholder="0" />
                <span v-if="fieldError('range_min')" class="mt-1 block text-xs text-[var(--color-tag-warning)]">@{{ fieldError('range_min') }}</span>
              </label>
              <label class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">終了値</span>
                <input v-model="form.policy.range_max" type="text" class="input-text w-full font-mono" :placeholder="endValuePlaceholder" />
                <span v-if="fieldError('range_max')" class="mt-1 block text-xs text-[var(--color-tag-warning)]">@{{ fieldError('range_max') }}</span>
              </label>
              <label class="block">
                <span class="block text-xs font-semibold opacity-60 mb-1">刻み幅</span>
                <input v-model="form.policy.range_step" type="text" class="input-text w-full font-mono" :placeholder="rangeStepPlaceholder" />
                <span v-if="fieldError('range_step')" class="mt-1 block text-xs text-[var(--color-tag-warning)]">@{{ fieldError('range_step') }}</span>
              </label>
            </div>
            <p class="mt-3 text-xs opacity-60">@{{ inputPrefixHelpText }}</p>
          </div>

          <label v-if="usesValueTextList" class="mt-4 block">
            <span class="block text-xs font-semibold opacity-60 mb-1">@{{ valueListLabel }}（CSV貼り付け対応）</span>
            <textarea v-model="form.policy.values_text" rows="3" class="input-text w-full font-mono text-sm" :placeholder="valueListPlaceholder"></textarea>
            <span class="mt-1 block text-xs opacity-60">@{{ valueListHelp }}</span>
          </label>

          <label v-if="usesExclusions" class="mt-4 block">
            <span class="block text-xs font-semibold opacity-60 mb-1">候補から外す値</span>
            <input v-model="form.policy.excluded_values_text" type="text" class="input-text w-full font-mono" placeholder="1.1, 1.3" />
          </label>

          <div v-if="form.policy.value_set_type === 'none'" class="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] px-3 py-2 text-sm opacity-70">
            値候補は生成しません。
          </div>
        </section>

        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <div class="font-bold">シリーズ値一覧</div>
              <div class="text-xs opacity-60">@{{ selected?.name || '未保存シリーズ' }} / @{{ visibleValues.length }}値</div>
            </div>
            <div class="flex flex-col items-start gap-1">
              <button type="button" @click="materializeSelected" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm" :disabled="materializeDisabled" :title="materializeHint">選択値を部品化</button>
              <span class="text-xs opacity-60">@{{ materializeHint }}</span>
            </div>
          </div>
          <div class="max-h-[460px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10 text-left bg-[var(--color-card-even)]">
                <tr>
                  <th class="px-3 py-2 w-10"></th>
                  <th class="px-3 py-2">値</th>
                  <th class="px-3 py-2">由来</th>
                  <th class="px-3 py-2">状態</th>
                  <th class="px-3 py-2">登録部品</th>
                  <th class="px-3 py-2">メモ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--color-border)]">
                <tr v-for="value in visibleValues" :key="value.value_key" :class="value.is_enabled ? '' : 'opacity-45'">
                  <td class="px-3 py-2">
                    <input v-if="value.id && value.is_enabled && !value.materialized_component_id" v-model="selectedValueIds" type="checkbox" :value="value.id" />
                  </td>
                  <td class="px-3 py-2 font-mono font-semibold">@{{ value.value_text }}</td>
                  <td class="px-3 py-2"><span class="tag text-[10px]">@{{ originLabel(value.origin, value.source_series) }}</span></td>
                  <td class="px-3 py-2">
                    <span v-if="!value.is_enabled" class="tag tag-eol text-[10px]">除外</span>
                    <span v-else-if="value.is_stocked" class="tag tag-ok text-[10px]">部品あり</span>
                    <span v-else class="tag text-[10px]">部品なし</span>
                  </td>
                  <td class="px-3 py-2">
                    <a v-if="value.materialized_component_id" :href="`/components/${value.materialized_component_id}`" class="link-text">#@{{ value.materialized_component_id }}</a>
                    <span v-else class="opacity-40">-</span>
                  </td>
                  <td class="px-3 py-2 opacity-70">@{{ value.note || '-' }}</td>
                </tr>
                <tr v-if="!visibleValues.length">
                  <td colspan="6" class="px-3 py-8 text-center opacity-40">値がありません。ポリシーを設定してプレビューしてください。</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </div>
  </main>

  <!-- トースト -->
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">
      @{{ t.msg }}
    </div>
  </div>
</div>
</body>
</html>
