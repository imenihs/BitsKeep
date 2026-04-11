<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品詳細 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '部品詳細'])
<div id="app" data-page="component-detail" data-id="{{ $id }}" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">

  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '部品一覧', 'url' => route('components.index')],
    ['label' => '部品詳細', 'current' => true],
  ]])

  <div v-if="loading" class="text-center py-20 opacity-40">読み込み中...</div>

  <template v-else-if="part">
    <!-- ヘッダ -->
    <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
      <div>
        <h1 class="text-2xl font-bold">@{{ part.common_name || part.part_number }}</h1>
        <p class="text-sm opacity-60 font-mono mt-0.5">@{{ part.part_number }}@{{ part.manufacturer ? ' / ' + part.manufacturer : '' }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 justify-end">
        <button @click="(() => { try { navigator.clipboard.writeText(location.href); toastSuccess('URLをコピーしました'); } catch(e) { const el = document.createElement('textarea'); el.value = location.href; document.body.appendChild(el); el.select(); document.execCommand('copy'); document.body.removeChild(el); toastSuccess('URLをコピーしました'); } })()"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          🔗 リンクコピー
        </button>
        <button @click="stockOutModal.open = true"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          出庫する
        </button>
        <a :href="'/component-compare?ids=' + componentId"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          類似部品を探す
        </a>
        <a :href="'/components/create?duplicate_from=' + componentId"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          パッケージ違いで複製
        </a>
        <button @click="stockInModal.form.location_id = part.primary_location_id || ''; stockInModal.open = true"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          入庫
        </button>
        <button @click="deletePart"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-tag-eol)] text-[var(--color-tag-eol)] text-sm hover:bg-red-50 transition-colors">
          削除
        </button>
      </div>
    </header>

    <div class="flex flex-col gap-4">
    <section class="bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.basic = !sections.basic" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.basic ? '▾' : '▸' }}</span>
          <span>基本情報</span>
        </button>
        <div class="flex items-center gap-3">
          <button @click="openEdit('specs')" class="text-xs link-text">スペック編集</button>
          <button @click="openEdit('basic')" class="text-xs link-text">編集</button>
        </div>
      </div>
      <div v-show="sections.basic" class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
        <!-- 左カラム: 画像 + データシート + 在庫内訳 -->
        <div class="space-y-3">
          <div class="component-image-frame component-image-frame-lg">
            <img v-if="part.image_url" :src="part.image_url" alt="部品画像" class="component-image-preview" />
            <div v-else class="component-image-empty">
              <span class="text-3xl opacity-30">□</span>
              <span>画像未登録</span>
            </div>
          </div>
          <!-- データシート -->
          <div>
            <div v-if="part.datasheets?.length" class="flex flex-wrap gap-2">
              <a v-for="(sheet, i) in part.datasheets" :key="sheet.id" :href="sheet.url" target="_blank" rel="noreferrer"
                class="btn inline-flex items-center gap-2 px-3 py-2 rounded border border-[var(--color-border)] text-sm">
                📄 @{{ part.datasheets.length > 1 ? 'データシート ' + (i + 1) : 'データシートを開く' }}
              </a>
            </div>
            <p v-else class="text-xs opacity-50">データシート未登録</p>
          </div>
          <!-- 棚別在庫内訳（モック準拠: 各ブロックを行表示） -->
          <div>
            <p class="text-xs font-semibold opacity-60 mb-1">在庫内訳</p>
            <div v-if="part.inventory_blocks?.length" class="space-y-1 text-xs">
              <div v-for="b in part.inventory_blocks" :key="b.id"
                class="px-2 py-1.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span v-if="b.location" class="font-medium">@{{ b.location.code }}</span>
                    <span class="opacity-60">@{{ b.condition === 'new' ? '新品' : '中古' }}</span>
                    <span class="px-1.5 py-0 rounded border border-[var(--color-border)] opacity-70">@{{ stockTypeLabel[b.stock_type] }}</span>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <strong class="font-mono">@{{ b.quantity }}個</strong>
                    <button @click="openStockOut(b)" class="btn text-[10px] px-1.5 py-0.5 rounded border border-[var(--color-border)]">出庫</button>
                  </div>
                </div>
                <div v-if="b.lot_number || b.reel_code" class="flex gap-3 pl-1 mt-0.5 text-[10px] opacity-50">
                  <span v-if="b.lot_number">ロット: @{{ b.lot_number }}</span>
                  <span v-if="b.reel_code">リール: @{{ b.reel_code }}</span>
                </div>
              </div>
              <div class="flex justify-end text-xs font-bold pt-1 border-t border-[var(--color-border)] opacity-70">
                合計: 新品 @{{ stockSummary.new }}個 / 中古 @{{ stockSummary.used }}個
              </div>
            </div>
            <p v-else class="text-xs opacity-40">在庫なし</p>
          </div>
        </div>
        <!-- 右カラム: ラベル:値 ×2組の横並びグリッド（スマホ1組、sm以上2組） -->
        <div class="space-y-3">
          <div class="grid grid-cols-[auto_1fr] sm:grid-cols-[auto_1fr_auto_1fr] gap-x-5 gap-y-2 text-sm items-baseline">
            <span class="list-label">型番</span>
            <span class="list-value font-mono">@{{ part.part_number }}</span>
            <span class="list-label">通称</span>
            <span class="list-value">@{{ part.common_name || '—' }}</span>
            <span class="list-label">メーカー</span>
            <span class="list-value">@{{ part.manufacturer || '—' }}</span>
            <span class="list-label">分類</span>
            <span>
              <span v-for="c in part.categories" :key="c.id" class="tag mr-1 text-xs">@{{ c.name }}</span>
              <span v-if="!part.categories.length" class="opacity-40">—</span>
            </span>
            <span class="list-label">パッケージ</span>
            <span>
              <span v-for="p in part.packages" :key="p.id" class="tag mr-1 text-xs">@{{ p.name }}</span>
              <span v-if="!part.packages.length" class="opacity-40">—</span>
            </span>
            <span class="list-label">入手可否</span>
            <span :class="'tag w-fit ' + (part.procurement_status === 'active' ? 'tag-ok' : part.procurement_status === 'eol' ? 'tag-eol' : 'tag-warning')">
              @{{ {active:'量産中',eol:'EOL',last_time:'在庫限り',nrnd:'新規非推奨'}[part.procurement_status] }}
            </span>
            <span class="list-label">発注点</span>
            <span class="list-value">新品 @{{ part.threshold_new }}個 / 中古 @{{ part.threshold_used }}個</span>
            <span class="list-label">代表保管棚</span>
            <span class="list-value">@{{ part.primary_location ? `${part.primary_location.code} / ${part.primary_location.name}` : '—' }}</span>
            <span class="list-label">最優先仕入先</span>
            <span class="list-value">
              @{{ preferredSupplier?.supplier?.name || '—' }}
              <span v-if="preferredSupplier?.unit_price != null" class="text-xs opacity-60 ml-1">¥@{{ preferredSupplier.unit_price.toLocaleString() }}</span>
            </span>
            <!-- 登録スペック（分類によって内容が変わる） -->
            <template v-for="s in part.specs" :key="s.id">
              <span class="list-label">@{{ s.spec_type?.name }}</span>
              <span class="list-value">@{{ s.value }}@{{ s.unit ? ' ' + s.unit : '' }}</span>
            </template>
          </div>
          <!-- 説明（あれば） -->
          <div v-if="part.description" class="text-sm border-t border-[var(--color-border)] pt-3">
            <span class="list-label">説明</span>
            <p class="mt-1 opacity-70 leading-relaxed">@{{ part.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.detail = !sections.detail" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.detail ? '▾' : '▸' }}</span>
          <span>詳細情報</span>
        </button>
        <button @click="openEdit('suppliers')" class="text-xs link-text">仕入先編集</button>
      </div>
      <div v-show="sections.detail" class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold mb-2 opacity-80">仕入先・価格</h3>
          <div v-if="part.component_suppliers?.length" class="space-y-3">
            <div v-for="cs in part.component_suppliers" :key="cs.id" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
              <div class="flex items-start justify-between text-sm">
                <div>
                  <span class="font-semibold">@{{ cs.supplier?.name }}</span>
                  <span v-if="cs.is_preferred" class="tag tag-ok ml-2 text-xs">優先</span>
                  <p v-if="cs.supplier_part_number" class="text-xs opacity-60 font-mono mt-0.5">@{{ cs.supplier_part_number }}</p>
                </div>
                <div class="text-right">
                  <p class="font-mono">¥@{{ cs.unit_price?.toLocaleString() ?? '—' }}</p>
                  <a v-if="cs.product_url" :href="cs.product_url" target="_blank" class="text-xs link-text">商品ページ</a>
                </div>
              </div>
              <div v-if="cs.price_breaks?.length" class="mt-3 overflow-x-auto">
                <table class="w-full text-xs">
                  <tbody>
                    <tr v-for="pb in cs.price_breaks" :key="pb.id" class="border-t border-[var(--color-border)]">
                      <td class="py-1 pr-3 opacity-60">@{{ pb.min_qty }}個〜</td>
                      <td class="py-1 font-mono">¥@{{ pb.unit_price.toLocaleString() }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <p v-else class="text-sm opacity-40">仕入先未登録</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div>
            <h3 class="text-sm font-semibold mb-2 opacity-80">使用案件・直近入出庫</h3>
            <div class="flex flex-wrap gap-1.5 mb-3">
              <span v-for="project in part.projects" :key="project.id" class="tag text-xs">
                @{{ project.business_code ? `${project.business_code}_${project.business_name} / ` : '' }}@{{ project.name }}
              </span>
              <span v-if="!part.projects?.length" class="text-sm opacity-40">使用案件なし</span>
            </div>
            <div class="space-y-1 text-xs">
              <div v-for="tx in recentTransactions" :key="tx.id" class="flex items-center justify-between rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
                <div class="flex items-center gap-2">
                  <span class="tag text-[10px]" :class="tx.type === 'out' ? 'tag-warning' : 'tag-ok'">@{{ tx.type === 'out' ? '出庫' : '入庫' }}</span>
                  <span class="opacity-70">@{{ tx.created_at?.substring(0, 10) }}</span>
                </div>
                <div class="font-mono">@{{ tx.quantity }}個</div>
              </div>
              <div v-if="recentTransactions.length === 0" class="text-sm opacity-40">直近入出庫なし</div>
            </div>
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold mb-2 opacity-80">類似部品</h3>
          <p v-if="similarLoading" class="text-sm opacity-50">類似部品を検索中...</p>
          <div v-else-if="similarError" class="notice-card notice-card-warning py-4 px-4">
            <div class="font-semibold text-[var(--color-tag-warning)]">類似部品の取得に失敗しました</div>
            <p class="mt-2 text-sm opacity-80">@{{ similarError }}</p>
            <div class="mt-3 flex flex-wrap gap-3">
              <button @click="fetchSimilar" class="btn-primary px-4 py-2 rounded text-sm">再試行</button>
              <a :href="'/component-compare?ids=' + componentId" class="btn px-4 py-2 rounded border border-[var(--color-border)] text-sm">比較画面へ</a>
            </div>
          </div>
          <div v-else-if="similarParts.length" class="space-y-2">
            <div v-for="item in similarParts" :key="item.id"
              class="flex items-center justify-between gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-3">
              <div>
                <div class="font-semibold">@{{ item.common_name || item.part_number }}</div>
                <div class="text-xs opacity-60 font-mono mt-1">@{{ item.part_number }}</div>
                <div v-if="item.manufacturer" class="text-xs opacity-60 mt-0.5">@{{ item.manufacturer }}</div>
              </div>
              <div class="flex flex-col gap-1.5 text-xs shrink-0">
                <a :href="`/component-compare?ids=${componentId},${item.id}`"
                  class="px-3 py-1.5 rounded border border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary)] hover:text-white transition-colors text-center">
                  比較する
                </a>
                <a :href="`/components/${item.id}`"
                  class="px-3 py-1.5 rounded border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors text-center opacity-70">
                  詳細を見る
                </a>
              </div>
            </div>
          </div>
          <p v-else class="text-sm opacity-40">類似部品候補はまだ見つかっていません</p>
        </div>
      </div>
    </section>

    <!-- カスタムフィールド（自由属性のみ） -->
    <section class="bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.custom = !sections.custom" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.custom ? '▾' : '▸' }}</span>
          <span>カスタムフィールド</span>
        </button>
        <button @click="openEdit('attributes')" class="text-xs link-text">編集</button>
      </div>
      <div v-show="sections.custom" class="text-sm">
        <div v-if="part.attributes?.length" class="grid grid-cols-[auto_1fr] sm:grid-cols-[auto_1fr_auto_1fr] gap-x-5 gap-y-2">
          <span v-for="attr in part.attributes" :key="attr.id" class="contents">
            <span class="list-label">@{{ attr.key }}</span>
            <span class="list-value">@{{ attr.value }}</span>
          </span>
        </div>
        <p v-else class="text-sm opacity-40">カスタムフィールド未登録</p>
      </div>
    </section>

    <!-- 連携情報（独立カード） -->
    <section class="bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.integration = !sections.integration" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.integration ? '▾' : '▸' }}</span>
          <span>連携情報</span>
        </button>
        <a href="{{ route('altium.index') }}" class="text-xs link-text">Altium設定</a>
      </div>
      <div v-show="sections.integration" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        <span class="list-label">Altium Designer</span>
        <span class="list-value">@{{ part.altium_link?.symbol_name || '未連携' }}</span>
        <span v-if="part.altium_link?.library_name" class="list-label">ライブラリ</span>
        <span v-if="part.altium_link?.library_name" class="list-value font-mono">@{{ part.altium_link.library_name }}</span>
      </div>
      <div v-show="sections.integration && !part.altium_link" class="text-sm opacity-40 mt-1">
        Altium未連携 — <a href="{{ route('altium.index') }}" class="link-text">Altium連携設定へ</a>
      </div>
    </section>
    </div>
  </template>

  <div v-else class="notice-card notice-card-error py-8 px-6">
    <div class="font-semibold text-[var(--color-tag-eol)]">部品詳細を表示できません</div>
    <p class="mt-2 text-sm opacity-80">@{{ loadError || '部品情報の取得に失敗しました。' }}</p>
    <div class="mt-4 flex flex-wrap gap-3">
      <button @click="fetchPart" class="btn-primary px-4 py-2 rounded">再試行</button>
      <a href="{{ route('components.index') }}" class="btn px-4 py-2 rounded border border-[var(--color-border)]">部品一覧へ戻る</a>
    </div>
  </div>

  <!-- 出庫モーダル -->
  <div v-if="stockOutModal.open" class="modal-overlay" @click.self="stockOutModal.open = false">
    <div class="modal-window modal-sm p-6">
      <h3 class="text-lg font-bold mb-4">出庫</h3>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs font-semibold mb-1">数量（最大 @{{ stockOutModal.maxQty }}個）</label>
          <input v-model.number="stockOutModal.qty" type="number" :max="stockOutModal.maxQty" min="1"
            class="input-text w-full" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">備考</label>
          <input v-model="stockOutModal.note" type="text" class="input-text w-full" placeholder="任意" />
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button @click="stockOutModal.open = false" class="btn text-sm">キャンセル</button>
        <button @click="submitStockOut" class="btn btn-primary text-sm">出庫する</button>
      </div>
    </div>
  </div>

  <!-- 入庫モーダル -->
  <div v-if="stockInModal.open" class="modal-overlay" @click.self="stockInModal.open = false">
    <div class="modal-window modal-sm p-6">
      <h3 class="text-lg font-bold mb-4">入庫</h3>
      <div class="space-y-3 text-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold mb-1">在庫区分</label>
            <select v-model="stockInModal.form.stock_type" class="input-text w-full text-sm">
              <option v-for="(l,v) in stockTypeLabel" :key="v" :value="v">@{{ l }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">新品/中古</label>
            <select v-model="stockInModal.form.condition" class="input-text w-full text-sm">
              <option value="new">新品</option>
              <option value="used">中古</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">数量</label>
          <input v-model.number="stockInModal.form.quantity" type="number" min="1" class="input-text w-full" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">入庫先棚</label>
          <select v-model="stockInModal.form.location_id" class="input-text w-full">
            <option value="">未設定</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">@{{ location.code }} / @{{ location.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">ロット番号</label>
          <input v-model="stockInModal.form.lot_number" type="text" class="input-text w-full" placeholder="任意" />
        </div>
        <div v-if="stockInModal.form.stock_type === 'reel'">
          <label class="block text-xs font-semibold mb-1">リール番号</label>
          <input v-model="stockInModal.form.reel_code" type="text" class="input-text w-full" placeholder="任意" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">備考</label>
          <input v-model="stockInModal.form.note" type="text" class="input-text w-full" placeholder="任意" />
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-5">
        <button @click="stockInModal.open = false" class="btn text-sm px-4 py-3 rounded border border-[var(--color-border)]">キャンセル</button>
        <button @click="submitStockIn" class="btn btn-primary text-sm px-5 py-3 rounded">入庫する</button>
      </div>
    </div>
  </div>

  <div v-if="editModal.open" class="modal-overlay" @click.self="editModal.open = false">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold">@{{ editModal.title }}</h3>
        <button @click="editModal.open = false" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div v-if="editModal.section === 'basic'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input v-model="editModal.form.part_number" type="text" class="input-text w-full" placeholder="型番" />
          <input v-model="editModal.form.manufacturer" type="text" class="input-text w-full" placeholder="メーカー" />
          <input v-model="editModal.form.common_name" type="text" class="input-text w-full" placeholder="通称" />
          <select v-model="editModal.form.procurement_status" class="input-text w-full">
            <option v-for="option in procurementOptions" :key="option.value" :value="option.value">@{{ option.label }}</option>
          </select>
          <input v-model.number="editModal.form.threshold_new" type="number" min="0" class="input-text w-full" placeholder="発注点 新品" />
          <input v-model.number="editModal.form.threshold_used" type="number" min="0" class="input-text w-full" placeholder="発注点 中古" />
        </div>
        <textarea v-model="editModal.form.description" class="input-text w-full h-20" placeholder="説明（任意）"></textarea>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold mb-1">分類</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="category in categories" :key="category.id" class="inline-flex items-center gap-2 text-sm">
                <input v-model="editModal.form.category_ids" :value="category.id" type="checkbox" />
                <span>@{{ category.name }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">パッケージ</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="pkg in packages" :key="pkg.id" class="inline-flex items-center gap-2 text-sm">
                <input v-model="editModal.form.package_ids" :value="pkg.id" type="checkbox" />
                <span>@{{ pkg.name }}</span>
              </label>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">代表保管棚</label>
          <select v-model="editModal.form.primary_location_id" class="input-text w-full">
            <option value="">未設定</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">@{{ location.code }} / @{{ location.name }}</option>
          </select>
        </div>
        <!-- 画像変更 -->
        <div>
          <label class="block text-xs font-semibold mb-1">部品画像（変更する場合のみ選択）</label>
          <div class="flex items-center gap-3">
            <img v-if="part.image_url" :src="part.image_url" alt="現在の画像" class="w-16 h-16 object-contain rounded border border-[var(--color-border)] bg-[var(--color-bg)]" />
            <span v-else class="text-xs opacity-50">画像未登録</span>
            <input type="file" accept="image/jpeg,image/png,image/webp"
              class="text-sm"
              @change="editModal.form._newImage = $event.target.files[0] || null" />
          </div>
        </div>
        <!-- データシート追加 -->
        <div>
          <label class="block text-xs font-semibold mb-1">データシート（複数選択可。ファイルを選択すると既存をすべて置き換えます）</label>
          <div v-if="part.datasheets?.length" class="mb-2 space-y-1">
            <div v-for="(sheet, i) in part.datasheets" :key="sheet.id" class="flex items-center gap-2 text-xs opacity-70">
              <span>📄</span><span>データシート @{{ i + 1 }}</span>
            </div>
          </div>
          <input type="file" accept="application/pdf" multiple
            class="text-sm"
            @change="editModal.form._newDatasheets = Array.from($event.target.files)" />
        </div>
      </div>

      <div v-else-if="editModal.section === 'specs'" class="space-y-3">
        <div v-for="(spec, index) in editModal.form.specs" :key="index" class="grid grid-cols-[1.4fr_1fr_0.8fr_0.8fr_auto] gap-2 items-center">
          <select v-model="spec.spec_type_id" class="input-text w-full">
            <option value="">-- 種別 --</option>
            <option v-for="st in specTypes" :key="st.id" :value="st.id">@{{ st.name }}</option>
          </select>
          <input v-model="spec.value" type="text" class="input-text w-full" placeholder="値" />
          <input v-model="spec.unit" type="text" class="input-text w-full" placeholder="単位" />
          <input v-model="spec.value_numeric" type="number" step="any" class="input-text w-full" placeholder="数値化" />
          <button @click="editModal.form.specs.splice(index, 1)" class="text-[var(--color-tag-eol)] px-2">✕</button>
        </div>
        <button @click="editModal.form.specs.push({ spec_type_id: '', value: '', unit: '', value_numeric: '' })" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm">+ スペック追加</button>
      </div>

      <!-- カスタムフィールド編集 -->
      <div v-else-if="editModal.section === 'attributes'" class="space-y-3">
        <div v-for="(attr, index) in editModal.form.attributes" :key="index"
          class="grid grid-cols-[1fr_1.5fr_auto] gap-2 items-center">
          <input v-model="attr.key" type="text" class="input-text w-full" placeholder="項目名" />
          <input v-model="attr.value" type="text" class="input-text w-full" placeholder="値" />
          <button @click="editModal.form.attributes.splice(index, 1)" class="text-[var(--color-tag-eol)] px-2 text-lg leading-none">✕</button>
        </div>
        <button @click="editModal.form.attributes.push({ key: '', value: '' })"
          class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm">+ 項目を追加</button>
      </div>

      <div v-else-if="editModal.section === 'suppliers'" class="space-y-3">
        <div v-for="(supplier, index) in editModal.form.suppliers" :key="index" class="rounded-xl border border-[var(--color-border)] p-3 bg-[var(--color-card-even)]">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <select v-model="supplier.supplier_id" class="input-text w-full">
              <option value="">-- 商社 --</option>
              <option v-for="item in suppliers" :key="item.id" :value="item.id">@{{ item.name }}</option>
            </select>
            <input v-model="supplier.supplier_part_number" type="text" class="input-text w-full" placeholder="商社型番" />
            <input v-model="supplier.product_url" type="url" class="input-text w-full" placeholder="商品URL" />
            <input v-model="supplier.unit_price" type="number" step="0.01" class="input-text w-full" placeholder="単価" />
          </div>
          <label class="mt-3 inline-flex items-center gap-2 text-sm">
            <input v-model="supplier.is_preferred" type="checkbox" />
            <span>優先商社</span>
          </label>
          <div class="mt-3 space-y-2">
            <div v-for="(pb, pbIndex) in supplier.price_breaks" :key="pbIndex" class="grid grid-cols-[1fr_1fr_auto] gap-2 items-center">
              <input v-model="pb.min_qty" type="number" min="1" class="input-text w-full" placeholder="数量以上" />
              <input v-model="pb.unit_price" type="number" step="0.01" class="input-text w-full" placeholder="単価" />
              <button @click="supplier.price_breaks.splice(pbIndex, 1)" class="text-[var(--color-tag-eol)] px-2">✕</button>
            </div>
            <button @click="supplier.price_breaks.push({ min_qty: 1, unit_price: '' })" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm">+ 価格ブレーク追加</button>
          </div>
          <div class="mt-3">
            <button @click="editModal.form.suppliers.splice(index, 1)" class="text-[var(--color-tag-eol)] text-sm">この商社を削除</button>
          </div>
        </div>
        <button @click="editModal.form.suppliers.push({ supplier_id: '', supplier_part_number: '', product_url: '', unit_price: '', is_preferred: false, price_breaks: [] })" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm">+ 商社追加</button>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button @click="editModal.open = false" class="btn text-sm px-4 py-3 rounded border border-[var(--color-border)]">キャンセル</button>
        <button @click="saveSection()" class="btn btn-primary text-sm px-5 py-3 rounded">保存</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">
      @{{ t.msg }}
    </div>
  </div>
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '部品一覧', 'url' => route('components.index')],
    ['label' => '部品詳細', 'current' => true],
  ], 'class' => 'mt-6'])
</div>
</body>
</html>
