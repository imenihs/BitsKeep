<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>発注画面 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '発注画面'])
<div id="app" data-page="stock-orders" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '在庫警告', 'url' => route('stock.alert')],
    ['label' => '発注画面', 'current' => true],
  ]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)] flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold">部品発注</h1>
      <p class="text-sm opacity-60 mt-1">発注候補に対して、購入商社・購入単位・数量を決めて商社別にCSV出力します</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('stock.alert') }}" class="btn px-4 py-2 rounded border border-[var(--color-border)] no-underline text-inherit">在庫警告へ戻る</a>
      <button @click="clearAll" class="btn px-4 py-2 rounded border border-[var(--color-border)]">候補をクリア</button>
    </div>
  </header>

  <div v-if="orderDraft.length === 0" class="card p-8 bg-[var(--color-card-even)] text-center opacity-70">
    <div class="text-lg font-semibold">発注候補はまだありません</div>
    <div class="text-sm mt-2">在庫警告で発注対象を選び、発注リストへ追加してください。</div>
  </div>

  <div v-else class="space-y-6">
    <section class="card p-5 bg-[var(--color-card-even)] block">
      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 pr-4">部品</th>
              <th class="py-2 pr-4">購入単位</th>
              <th class="py-2 pr-4">購入数量</th>
              <th class="py-2 pr-4">新品 / 中古数量</th>
              <th class="py-2 pr-4">購入商社</th>
              <th class="py-2 pr-4">単価</th>
              <th class="py-2 pr-4">小計</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in orderDraft" :key="item.id" class="border-b border-[var(--color-border)]">
              <td class="py-2 pr-4">
                <div class="font-medium">@{{ item.name }}</div>
                <div class="text-xs opacity-60">@{{ item.partNumber }}</div>
              </td>
              <td class="py-2 pr-4">
                <select v-model="item.purchaseUnit" @change="saveDraft" class="input-text min-w-[120px] text-sm py-2">
                  <option value="">選択してください</option>
                  <option v-for="option in purchaseUnitOptions" :key="option.value" :value="option.value">@{{ option.label }}</option>
                </select>
              </td>
              <td class="py-2 pr-4">
                <input v-model.number="item.orderQty" @change="saveDraft" type="number" min="1" class="input-text w-24 text-right" />
              </td>
              <td class="py-2 pr-4 font-mono text-xs">
                新品 @{{ item.quantityNew }} / 中古 @{{ item.quantityUsed }}
              </td>
              <td class="py-2 pr-4">
                <select v-model="item.supplierId" @change="selectSupplier(item)" class="input-text min-w-[180px] text-sm py-2">
                  <option value="">選択してください</option>
                  <option v-for="supplier in item.supplierOptions" :key="supplier.supplier_id" :value="supplier.supplier_id">@{{ supplier.name }}</option>
                </select>
                <div class="text-xs opacity-50 mt-1">@{{ item.supplierPartNumber || '商社型番未設定' }}</div>
              </td>
              <td class="py-2 pr-4 font-mono">@{{ formatCurrency(item.price || 0, {decimals:2}) }}</td>
              <td class="py-2 pr-4 font-mono">@{{ formatCurrency((item.price || 0) * (item.orderQty || 0)) }}</td>
              <td class="py-2">
                <button @click="removeItem(item.id)" class="px-3 py-2 rounded border border-[var(--color-border)] text-xs">除外</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card p-5 bg-[var(--color-card-even)] block">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="text-lg font-bold">商社別CSV出力</div>
        <div class="text-sm opacity-60">商社が選ばれている行ごとに出力</div>
      </div>
      <div class="flex flex-wrap gap-3">
        <button v-for="(group, supplierName) in exportGroups" :key="supplierName"
          @click="exportSupplierCsv(supplierName, group)"
          :disabled="supplierName === '未選択'"
          class="btn btn-primary px-4 py-2 rounded text-sm disabled:opacity-50">
          @{{ supplierName }} をCSV出力
        </button>
      </div>
    </section>

    <section class="card p-5 bg-[var(--color-card-odd)] flex items-center justify-between">
      <div class="text-lg font-bold">合計</div>
      <div class="text-xl font-bold">@{{ formatCurrency(grandTotal) }}</div>
    </section>

    <!-- 発注記録へ保存ボタン -->
    <section class="card p-4 bg-[var(--color-card-even)] flex items-center justify-between gap-4">
      <div class="text-sm opacity-70">商社・数量を確定したら発注記録へ保存し、受取管理できます</div>
      <button @click="commitToTracking" class="btn-primary px-4 py-2 rounded text-sm font-medium">発注記録へ保存</button>
    </section>
  </div>

  <!-- 発注追跡セクション -->
  <section class="mt-8">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold">発注追跡（未受取）</h2>
      <button @click="fetchTrackedOrders" class="text-xs opacity-60 hover:opacity-100 px-3 py-1.5 border border-[var(--color-border)] rounded">更新</button>
    </div>

    <div v-if="trackError" class="card p-4 bg-[var(--color-card-even)] border border-[var(--color-tag-eol)] flex items-start gap-3 text-sm mb-4">
      <span class="text-[var(--color-tag-eol)]">⚠</span>
      <div class="flex-1">
        <div class="font-semibold text-[var(--color-tag-eol)]">発注追跡の取得に失敗しました</div>
        <div class="opacity-70 mt-0.5">@{{ trackError }}</div>
      </div>
      <button @click="fetchTrackedOrders" class="px-3 py-1.5 rounded border border-[var(--color-border)] text-xs">再試行</button>
    </div>

    <div v-if="trackLoading" class="text-center py-6 opacity-50 text-sm">読み込み中...</div>

    <div v-else-if="!trackError && trackedOrders.length === 0" class="card p-6 bg-[var(--color-card-even)] text-center opacity-50">
      <p class="text-sm">発注済み（未受取）の記録はありません</p>
    </div>

    <div v-else-if="trackedOrders.length > 0" class="card p-0 overflow-hidden">
      <table class="w-full text-sm border-collapse">
        <thead>
          <tr class="border-b border-[var(--color-border)] text-left opacity-70 bg-[var(--color-card-even)]">
            <th class="py-2 px-4">部品</th>
            <th class="py-2 px-4">商社</th>
            <th class="py-2 px-4 text-right">数量</th>
            <th class="py-2 px-4">発注日</th>
            <th class="py-2 px-4">予定日</th>
            <th class="py-2 px-4">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(order, i) in trackedOrders" :key="order.id"
            :class="i % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)]">
            <td class="py-2 px-4">
              <div class="font-medium">@{{ order.component?.common_name || order.component?.part_number || '-' }}</div>
              <div class="text-xs opacity-60">@{{ order.component?.part_number }}</div>
            </td>
            <td class="py-2 px-4 text-xs">@{{ order.supplier?.name || '-' }}</td>
            <td class="py-2 px-4 font-mono text-right">@{{ order.quantity }}</td>
            <td class="py-2 px-4 text-xs opacity-70">@{{ order.order_date ? formatDate(order.order_date) : '-' }}</td>
            <td class="py-2 px-4 text-xs opacity-70">@{{ order.expected_date ? formatDate(order.expected_date) : '-' }}</td>
            <td class="py-2 px-4">
              <div class="flex gap-2">
                <button @click="updateOrderStatus(order, 'received')"
                  class="px-2 py-1.5 text-xs border border-emerald-500 text-emerald-600 rounded hover:bg-emerald-50">受取済み</button>
                <button @click="updateOrderStatus(order, 'cancelled')"
                  class="px-2 py-1.5 text-xs border border-[var(--color-border)] text-red-500 rounded hover:bg-red-50">キャンセル</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm transition-all">
      @{{ t.msg }}
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '在庫警告', 'url' => route('stock.alert')],
    ['label' => '発注画面', 'current' => true],
  ], 'class' => 'mt-6'])
</div>
</body>
</html>
