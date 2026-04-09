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
<div id="app" data-page="component-detail" data-id="{{ $id }}" class="p-6 max-w-4xl mx-auto">

  <!-- パンくず -->
  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="{{ route('components.index') }}">部品管理</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">部品詳細</span>
  </nav>

  <div v-if="loading" class="text-center py-20 opacity-40">読み込み中...</div>

  <template v-else-if="part">
    <!-- ヘッダ -->
    <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
      <div>
        <h1 class="text-2xl font-bold">@{{ part.common_name || part.part_number }}</h1>
        <p class="text-sm opacity-60 font-mono mt-0.5">@{{ part.part_number }}@{{ part.manufacturer ? ' / ' + part.manufacturer : '' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="stockOutModal.open = true"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          出庫する
        </button>
        <a :href="'/component-compare?ids=' + componentId"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          類似部品を探す
        </a>
        <button @click="openEdit('basic'); editModal.section='ALL'"
          class="btn btn-primary flex items-center gap-1 px-4 py-2 rounded text-sm">
          全体編集
        </button>
        <button @click="stockInModal.open = true"
          class="flex items-center gap-1 px-4 py-2 rounded border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          入庫
        </button>
      </div>
    </header>

    <section class="mb-4 bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.basic = !sections.basic" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.basic ? '▾' : '▸' }}</span>
          <span>基本情報</span>
        </button>
        <button @click="openEdit('basic')" class="text-xs link-text">編集</button>
      </div>
      <div v-show="sections.basic" class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div>
          <div class="component-image-frame component-image-frame-lg">
            <img v-if="part.image_url" :src="part.image_url" alt="部品画像" class="component-image-preview" />
            <div v-else class="component-image-empty">
              <span class="text-3xl opacity-30">□</span>
              <span>画像未登録</span>
            </div>
          </div>
          <a v-if="part.datasheet_url" :href="part.datasheet_url" target="_blank" rel="noreferrer"
            class="btn mt-3 inline-flex items-center gap-2 px-3 py-2 rounded border border-[var(--color-border)] text-sm">
            データシートを開く
          </a>
          <div class="mt-4 grid gap-2 text-sm">
            <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
              <div class="text-xs opacity-60">最優先仕入先</div>
              <div class="mt-1 font-medium">@{{ preferredSupplier?.supplier?.name || '未登録' }}</div>
              <div v-if="preferredSupplier?.unit_price != null" class="text-xs opacity-70 mt-1">基準単価: ¥@{{ preferredSupplier.unit_price.toLocaleString() }}</div>
            </div>
            <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
              <div class="text-xs opacity-60">棚別在庫合計</div>
              <div class="mt-1 text-sm">新品 @{{ stockSummary.new }}個 / 中古 @{{ stockSummary.used }}個</div>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
          <div><span class="list-label">型番</span><span class="list-value ml-2 font-mono">@{{ part.part_number }}</span></div>
          <div><span class="list-label">メーカー</span><span class="list-value ml-2">@{{ part.manufacturer || '—' }}</span></div>
          <div><span class="list-label">分類</span>
            <span v-for="c in part.categories" :key="c.id" class="tag ml-1 text-xs">@{{ c.name }}</span>
            <span v-if="!part.categories.length" class="ml-2 opacity-40">—</span>
          </div>
          <div><span class="list-label">パッケージ</span>
            <span v-for="p in part.packages" :key="p.id" class="tag ml-1 text-xs">@{{ p.name }}</span>
            <span v-if="!part.packages.length" class="ml-2 opacity-40">—</span>
          </div>
          <div><span class="list-label">入手可否</span>
            <span :class="'tag ml-2 ' + (part.procurement_status === 'active' ? 'tag-ok' : part.procurement_status === 'eol' ? 'tag-eol' : 'tag-warning')">
              @{{ {active:'量産中',eol:'EOL',last_time:'在庫限り',nrnd:'新規非推奨'}[part.procurement_status] }}
            </span>
          </div>
          <div><span class="list-label">発注点</span><span class="list-value ml-2">新品 @{{ part.threshold_new }}個 / 中古 @{{ part.threshold_used }}個</span></div>
        </div>
      </div>
      <div v-show="sections.basic && part.description" class="mt-3 text-sm opacity-70">@{{ part.description }}</div>
    </section>

    <section class="mb-4 bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.detail = !sections.detail" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.detail ? '▾' : '▸' }}</span>
          <span>詳細情報</span>
        </button>
        <div class="flex items-center gap-3">
          <button @click="openEdit('specs')" class="text-xs link-text">スペック編集</button>
          <button @click="openEdit('suppliers')" class="text-xs link-text">仕入先編集</button>
        </div>
      </div>
      <div v-show="sections.detail" class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold mb-2 opacity-80">スペック</h3>
          <div v-if="part.specs.length" class="grid grid-cols-2 gap-2 text-sm">
            <div v-for="s in part.specs" :key="s.id" class="flex gap-2">
              <span class="list-label">@{{ s.spec_type?.name }}</span>
              <span class="list-value">@{{ s.value }} @{{ s.unit }}</span>
            </div>
          </div>
          <p v-else class="text-sm opacity-40">スペック未登録</p>
        </div>

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
            <h3 class="text-sm font-semibold mb-2 opacity-80">在庫ブロック</h3>
            <div v-if="part.inventory_blocks?.length" class="space-y-2">
              <div v-for="b in part.inventory_blocks" :key="b.id"
                class="flex items-center justify-between text-sm px-3 py-2 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">
                <div>
                  <span class="tag text-xs mr-2">@{{ stockTypeLabel[b.stock_type] }}</span>
                  <span class="tag text-xs mr-2" :class="b.condition === 'new' ? 'tag-ok' : ''">@{{ stockConditionLabel[b.condition] }}</span>
                  <span v-if="b.location">@{{ b.location.code }}</span>
                  <span v-if="b.lot_number" class="ml-2 opacity-60 text-xs">Lot: @{{ b.lot_number }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <span class="font-mono font-bold">@{{ b.quantity }}個</span>
                  <button @click="openStockOut(b)" class="btn text-xs px-2 py-1 rounded border border-[var(--color-border)]">出庫</button>
                </div>
              </div>
            </div>
            <p v-else class="text-sm opacity-40">在庫なし</p>
          </div>

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
            <a v-for="item in similarParts" :key="item.id"
              :href="`/components/${item.id}`"
              class="flex items-center justify-between gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-3 hover:border-[var(--color-primary)] transition-colors">
              <div>
                <div class="font-semibold">@{{ item.common_name || item.part_number }}</div>
                <div class="text-xs opacity-60 font-mono mt-1">@{{ item.part_number }}</div>
              </div>
              <div class="text-right text-xs opacity-70">
                <div v-if="item.manufacturer">@{{ item.manufacturer }}</div>
                <div>詳細を見る</div>
              </div>
            </a>
          </div>
          <p v-else class="text-sm opacity-40">類似部品候補はまだ見つかっていません</p>
        </div>
      </div>
    </section>

    <section class="mb-4 bg-[var(--color-card-even)] rounded-lg border border-[var(--color-border)] p-4">
      <div class="flex justify-between items-center mb-3">
        <button @click="sections.custom = !sections.custom" class="flex items-center gap-2 font-bold">
          <span class="text-lg">@{{ sections.custom ? '▾' : '▸' }}</span>
          <span>カスタムフィールド</span>
        </button>
        <span class="text-xs opacity-50">属性・連携情報</span>
      </div>
      <div v-show="sections.custom" class="grid gap-4 lg:grid-cols-2 text-sm">
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <h3 class="text-sm font-semibold mb-2 opacity-80">自由属性</h3>
          <div v-if="part.attributes?.length" class="space-y-2">
            <div v-for="attr in part.attributes" :key="attr.id" class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
              <span class="list-label">@{{ attr.key }}</span>
              <span class="list-value">@{{ attr.value }}</span>
            </div>
          </div>
          <p v-else class="text-sm opacity-40">カスタムフィールド未登録</p>
        </div>
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <h3 class="text-sm font-semibold mb-2 opacity-80">連携情報</h3>
          <div class="space-y-2">
            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
              <span class="list-label">Altium</span>
              <span class="list-value">@{{ part.altium_link?.symbol_name || '未連携' }}</span>
            </div>
            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
              <span class="list-label">説明</span>
              <span class="list-value">@{{ part.description || 'なし' }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>
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
        <div class="grid grid-cols-2 gap-3">
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
      <div class="flex justify-end gap-2 mt-5">
        <button @click="stockInModal.open = false" class="btn text-sm">キャンセル</button>
        <button @click="submitStockIn" class="btn btn-primary text-sm">入庫する</button>
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
</div>
</body>
</html>
