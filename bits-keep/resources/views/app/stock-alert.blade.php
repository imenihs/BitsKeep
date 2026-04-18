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
      <p class="text-sm opacity-60 mt-1">不足部品を確認し、発注対象を選んで発注リストへ送ります</p>
    </div>
    <div class="flex gap-2">
      <button
        @click="addCheckedToOrder"
        :disabled="checkedCount === 0"
        class="btn px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:opacity-50">
        発注リストに入れる <span v-if="checkedCount > 0">(@{{ checkedCount }})</span>
      </button>
      <a href="{{ route('stock.orders') }}"
        class="btn-primary px-4 py-2 rounded text-sm font-medium no-underline text-white inline-flex items-center"
        :class="orderCount === 0 ? 'opacity-50 pointer-events-none' : ''">
        部品発注へ <span v-if="orderCount > 0" class="ml-1 bg-white/30 rounded-full px-1.5">@{{ orderCount }}</span>
      </a>
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
          <th class="py-2 pr-4">発注対象</th>
          <th class="py-2 pr-4">部品名 / 型番</th>
          <th class="py-2 pr-4">パッケージ</th>
          <th class="py-2 pr-4 text-right">在庫(新品)</th>
          <th class="py-2 pr-4 text-right">在庫(中古)</th>
          <th class="py-2 pr-4 text-right">発注点</th>
          <th class="py-2 pr-4 text-right">充足率(新品基準)</th>
          <th class="py-2">状態</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="alert in alerts" :key="alert.id"
          :class="alert.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)] hover:opacity-90 transition-opacity">
          <td class="py-2 pr-4">
            <template v-if="isPending(alert)">
              <span class="tag tag-warning text-xs">発注待ち</span>
            </template>
            <template v-else-if="orderDraft.some(o => o.id === alert.id)">
              <span class="tag tag-ok text-xs">追加済み</span>
            </template>
            <input
              v-else
              :checked="checkedIds.includes(alert.id)"
              @change="toggleChecked(alert.id)"
              type="checkbox"
              class="h-4 w-4" />
          </td>
          <td class="py-2 pr-4">
            <a :href="`/components/${alert.id}`" class="font-medium hover:underline text-[var(--color-primary)]">
              @{{ alert.common_name || alert.part_number }}
            </a>
            <div class="text-xs opacity-60">@{{ alert.part_number }}</div>
          </td>
          <td class="py-2 pr-4 text-sm">@{{ alert.package_name || '—' }}</td>
          <td class="py-2 pr-4 text-right font-mono">@{{ alert.quantity_new }}</td>
          <td class="py-2 pr-4 text-right font-mono">@{{ alert.quantity_used }}</td>
          <td class="py-2 pr-4 text-right font-mono">@{{ alert.threshold_new }}</td>
          <td class="py-2 pr-4 text-right">
            <span :class="urgencyClass(alert.quantity_new / alert.threshold_new)"
              class="tag px-2 py-0.5 rounded text-xs font-medium">
              @{{ alert.threshold_new > 0 ? Math.round(alert.quantity_new / alert.threshold_new * 100) : 0 }}%
            </span>
          </td>
          <td class="py-2">
            <span v-if="isPending(alert)" class="text-xs opacity-60">発注済み（納品待ち）</span>
            <span v-else-if="orderDraft.some(o => o.id === alert.id)" class="text-xs opacity-60">発注候補へ追加済み</span>
            <span v-else class="text-xs opacity-50">選択して追加</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ローディング -->
  <div v-if="loading" class="text-center py-8 opacity-50">読み込み中...</div>

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
