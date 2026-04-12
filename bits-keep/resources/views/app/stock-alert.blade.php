<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>在庫警告 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '在庫警告'])
<div id="app" data-page="stock-alert" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '在庫警告', 'current' => true]]])

  <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <div>
      <h1 class="text-2xl font-bold">在庫警告</h1>
      <p class="text-sm opacity-60 mt-1">発注点を下回っている部品の一覧</p>
    </div>
    <div class="flex gap-2">
      <button @click="orderModal = true"
        class="btn-primary px-4 py-2 rounded text-sm font-medium"
        :disabled="orderList.length === 0">
        発注リスト確認 <span v-if="orderList.length > 0" class="ml-1 bg-white/30 rounded-full px-1.5">@{{ orderList.length }}</span>
      </button>
    </div>
  </header>

  <!-- 警告バッジ -->
  <div v-if="!loading && alerts.length === 0" class="text-center py-12 opacity-50">
    <p class="text-lg">⚠️ 発注点を下回っている部品はありません</p>
  </div>

  <!-- テーブル -->
  <div v-else class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-4">部品名 / 型番</th>
          <th class="py-2 pr-4">パッケージ</th>
          <th class="py-2 pr-4">今回の購入単位</th>
          <th class="py-2 pr-4 text-right">在庫(新品)</th>
          <th class="py-2 pr-4 text-right">在庫(中古)</th>
          <th class="py-2 pr-4 text-right">発注点</th>
          <th class="py-2 pr-4 text-right">充足率(新品基準)</th>
          <th class="py-2 pr-4">購入商社</th>
          <th class="py-2 pr-4 text-right">単価</th>
          <th class="py-2">発注選択</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="alert in alerts" :key="alert.id"
          :class="alert.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)] hover:opacity-90 transition-opacity">
          <td class="py-2 pr-4">
            <a :href="`/components/${alert.id}`" class="font-medium hover:underline text-[var(--color-primary)]">
              @{{ alert.common_name || alert.part_number }}
            </a>
            <div class="text-xs opacity-60">@{{ alert.part_number }}</div>
          </td>
          <td class="py-2 pr-4 text-sm">@{{ alert.package_name || '—' }}</td>
          <td class="py-2 pr-4 text-sm">
            <select
              v-model="alert.selected_purchase_unit"
              @change="syncOrderPurchaseUnit(alert)"
              class="input-text min-w-[120px] text-sm py-2">
              <option v-if="!alert.selected_purchase_unit" value="">選択してください</option>
              <option v-for="option in purchaseUnitOptions" :key="option.value" :value="option.value">
                @{{ option.label }}
              </option>
            </select>
          </td>
          <td class="py-2 pr-4 text-right font-mono">@{{ alert.quantity_new }}</td>
          <td class="py-2 pr-4 text-right font-mono">@{{ alert.quantity_used }}</td>
          <td class="py-2 pr-4 text-right font-mono">@{{ alert.threshold_new }}</td>
          <td class="py-2 pr-4 text-right">
            <span :class="urgencyClass(alert.quantity_new / alert.threshold_new)"
              class="tag px-2 py-0.5 rounded text-xs font-medium">
              @{{ alert.threshold_new > 0 ? Math.round(alert.quantity_new / alert.threshold_new * 100) : 0 }}%
            </span>
          </td>
          <td class="py-2 pr-4 text-sm">
            <select
              v-if="(alert.supplier_options?.length ?? 0) > 0"
              v-model="alert.selected_supplier_id"
              @change="syncOrderSelection(alert)"
              class="input-text min-w-[180px] text-sm py-2">
              <option v-if="!alert.selected_supplier_id" value="">選択してください</option>
              <option v-for="supplier in alert.supplier_options" :key="supplier.supplier_id" :value="supplier.supplier_id">
                @{{ supplier.name }}
              </option>
            </select>
            <span v-else class="opacity-50">候補なし</span>
          </td>
          <td class="py-2 pr-4 text-right font-mono">
            @{{ selectedSupplier(alert)?.unit_price != null ? '¥' + Number(selectedSupplier(alert).unit_price).toLocaleString() : '-' }}
          </td>
          <td class="py-2">
            <button @click="toggleOrder(alert)"
              :disabled="!inOrder(alert) && !canOrder(alert)"
              :class="inOrder(alert) ? 'btn-primary' : 'border border-[var(--color-border)] hover:bg-[var(--color-primary)] hover:text-white disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-inherit'"
              class="px-3 py-1 rounded text-xs transition-colors">
              @{{ inOrder(alert) ? '✓ 選択中' : '+ 発注' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ローディング -->
  <div v-if="loading" class="text-center py-8 opacity-50">読み込み中...</div>

  <!-- 発注リストモーダル -->
  <div v-if="orderModal" class="modal-overlay">
    <div class="modal-window modal-xl max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">発注リスト</h2>
        <button @click="orderModal = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6">
        <!-- 商社別グループ -->
        <div v-for="(group, supplierName) in orderBySupplier" :key="supplierName" class="mb-6">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
            <h3 class="font-semibold text-[var(--color-primary)]">📦 @{{ supplierName }}</h3>
            <button @click="exportSupplierCsv(supplierName, group)" class="btn-primary px-3 py-2 rounded text-sm font-medium">
              この商社をCSV出力
            </button>
          </div>
          <table class="w-full text-sm mb-2">
            <thead class="opacity-60 text-left">
              <tr>
                <th class="pb-1 pr-4">部品名</th>
                <th class="pb-1 pr-4">商社型番</th>
                <th class="pb-1 pr-4 text-right">数量</th>
                <th class="pb-1 pr-4 text-right">単価</th>
                <th class="pb-1 text-right">小計</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in group.items" :key="item.id" class="border-t border-[var(--color-border)]">
                <td class="py-1 pr-4">
                  <div>@{{ item.name }}</div>
                  <div class="text-xs opacity-60">@{{ item.partNumber }}</div>
                </td>
                <td class="py-1 pr-4 font-mono text-xs">@{{ item.supplierPartNumber || '—' }}</td>
                <td class="py-1 pr-4 text-right">
                  <input v-model.number="item.orderQty" type="number" min="1"
                    class="w-16 text-right bg-transparent border border-[var(--color-border)] rounded px-1 py-0.5" />
                </td>
                <td class="py-1 pr-4 text-right">¥@{{ item.price.toLocaleString() }}</td>
                <td class="py-1 text-right">¥@{{ (item.price * item.orderQty).toLocaleString() }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="font-medium border-t-2 border-[var(--color-border)]">
                <td colspan="3" class="pt-1 pr-4 text-right opacity-70">小計</td>
                <td class="pt-1 text-right">¥@{{ group.total.toLocaleString() }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- 合計 -->
        <div class="border-t-2 border-[var(--color-border)] pt-4 flex justify-between items-center font-bold text-lg">
          <span>合計</span>
          <span>¥@{{ grandTotal.toLocaleString() }}</span>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="orderModal = false" class="px-4 py-2 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">閉じる</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm transition-all">
      @{{ t.msg }}
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '在庫警告', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
