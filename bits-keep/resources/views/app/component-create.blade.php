<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品登録/編集 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="component-create" data-id="{{ $id ?? '' }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="{{ route('components.index') }}">部品管理</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">{{ isset($id) ? '部品編集' : '部品登録' }}</span>
  </nav>

  <header class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">@{{ isEdit ? '部品編集' : '部品登録' }}</h1>
    <button @click="submit" :disabled="saving" class="btn btn-primary px-5 py-2 rounded text-sm">
      @{{ saving ? '保存中...' : (isEdit ? '更新' : '登録') }}
    </button>
  </header>

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
          <label class="block text-xs font-semibold mb-1">データシート（PDF）</label>
          <input type="file" accept=".pdf,application/pdf" class="input-text w-full text-xs" @change="onDatasheetChange" />
          <p v-if="datasheetFile" class="text-[11px] mt-1">@{{ datasheetFile.name }}</p>
          <p v-else-if="currentDatasheetUrl" class="text-[11px] mt-1">
            現在のファイル:
            <a :href="currentDatasheetUrl" target="_blank" rel="noreferrer" class="link-text">@{{ currentDatasheetName || 'データシートを開く' }}</a>
          </p>
          <p class="text-[11px] opacity-50 mt-1">差し替え時のみ選択してください</p>
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
                placeholder="入力して絞り込み。候補がなければ新規で使う" />
              <div v-if="manufacturerQuery.trim()" class="absolute left-0 right-0 top-full z-10 mt-1 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-2 shadow-lg">
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
      </div>
    </div>
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
          <input v-model="packageQuery" type="text" class="input-text w-full"
            placeholder="パッケージ名で絞り込み。なければ追加" />
          <div v-if="form.package_ids.length" class="mt-2 flex flex-wrap gap-2">
            <span v-for="id in form.package_ids" :key="`selected-pkg-${id}`"
              class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[var(--color-card-even)] border border-[var(--color-border)]">
              @{{ packages.find((item) => item.id === id)?.name }}
              <button type="button" @click="togglePackage(id)">✕</button>
            </span>
          </div>
          <div class="mt-2 max-h-36 overflow-y-auto space-y-1">
            <button v-for="pkg in filteredPackages" :key="pkg.id" type="button" @click="togglePackage(pkg.id)"
              class="w-full flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-[var(--color-card-odd)]"
              :class="form.package_ids.includes(pkg.id) ? 'bg-[var(--color-card-even)] border border-[var(--color-primary)]' : ''">
              <span>@{{ pkg.name }}</span>
              <span class="text-xs opacity-60">@{{ form.package_ids.includes(pkg.id) ? '選択中' : '追加' }}</span>
            </button>
            <button v-if="canCreatePackage" type="button" @click="addPackageFromQuery"
              class="w-full rounded px-2 py-1 text-left text-sm border border-dashed border-[var(--color-primary)] text-[var(--color-primary)]">
              「@{{ packageQuery.trim() }}」を新規追加
            </button>
            <p v-if="!filteredPackages.length && !canCreatePackage" class="text-xs opacity-40 p-1">パッケージがありません</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- スペック -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-bold">スペック</h2>
      <button @click="addSpec" class="text-xs link-text">+ 追加</button>
    </div>
    <div v-for="(spec, i) in form.specs" :key="i" class="flex gap-2 mb-2 items-center">
      <select v-model="spec.spec_type_id" class="input-text text-sm py-1 flex-1">
        <option value="">-- 種別 --</option>
        <option v-for="st in specTypes" :key="st.id" :value="st.id">@{{ st.name }}</option>
      </select>
      <input v-model="spec.value" type="text" class="input-text text-sm py-1 w-28" placeholder="値" />
      <select v-model="spec.unit" class="input-text text-sm py-1 w-24">
        <option value="">単位</option>
        <option v-for="u in getUnits(spec.spec_type_id)" :key="u.id" :value="u.unit">@{{ u.unit }}</option>
      </select>
      <button @click="removeSpec(i)" class="text-[var(--color-tag-eol)] text-xs px-2">✕</button>
    </div>
    <p v-if="!form.specs.length" class="text-xs opacity-40">スペックを追加してください</p>
  </section>

  <!-- 仕入先 -->
  <section class="card mb-4 p-5 flex-col items-start block bg-[var(--color-card-even)]">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-bold">仕入先</h2>
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
          </div>
        </div>
        <label class="flex items-center gap-1 text-xs cursor-pointer">
          <input type="checkbox" v-model="row.is_preferred" />優先
        </label>
        <button @click="removeSupplier(i)" class="text-[var(--color-tag-eol)] text-xs px-2">✕</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2">
        <input v-model="row.supplier_part_number" type="text" class="input-text text-xs py-1" placeholder="商社型番" />
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

  <!-- トースト -->
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">@{{ t.msg }}</div>
  </div>
</div>
</body>
</html>
