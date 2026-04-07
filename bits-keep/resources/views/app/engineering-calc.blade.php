<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>エンジニア電卓 - BitsKeep</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="engineering-calc" class="p-6 max-w-3xl mx-auto">

  <nav class="breadcrumb mb-4">
    <a href="{{ route('dashboard') }}">🏠 BitsKeep</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>ツール</span>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">エンジニア電卓</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">エンジニア電卓</h1>
    <p class="text-sm opacity-60 mt-1">式入力・多進数表示・物理定数プリセット</p>
  </header>

  <div class="flex gap-6">
    <!-- 電卓メイン -->
    <div class="flex-1">
      <!-- 式入力 -->
      <div class="mb-4">
        <textarea v-model="expr" @keydown.enter.prevent="evaluate" rows="3"
          placeholder="例: 1/(2*pi*10e3*100e-9)"
          class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg px-4 py-3 font-mono text-sm resize-none focus:ring-1 focus:ring-[var(--color-primary)]"></textarea>
        <div class="flex gap-2 mt-2">
          <button @click="evaluate" class="btn-primary px-4 py-2 rounded font-medium text-sm flex-1">= 計算</button>
          <button @click="clearCalc" class="px-4 py-2 border border-[var(--color-border)] rounded text-sm">クリア</button>
        </div>
      </div>

      <!-- 結果 -->
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 mb-4">
        <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
        <div v-else-if="result !== null">
          <div class="text-3xl font-mono font-bold mb-2">{{ formatNum(result) }}</div>
          <div v-if="hexResult" class="grid grid-cols-3 gap-2 text-xs font-mono">
            <div class="bg-[var(--color-bg)] border border-[var(--color-border)] rounded p-2">
              <div class="opacity-60 mb-1">HEX</div>
              <div>{{ hexResult }}</div>
            </div>
            <div class="bg-[var(--color-bg)] border border-[var(--color-border)] rounded p-2">
              <div class="opacity-60 mb-1">OCT</div>
              <div>{{ octResult }}</div>
            </div>
            <div class="bg-[var(--color-bg)] border border-[var(--color-border)] rounded p-2 overflow-hidden">
              <div class="opacity-60 mb-1">BIN</div>
              <div class="break-all">{{ binResult }}</div>
            </div>
          </div>
        </div>
        <div v-else class="text-center opacity-30 py-2 text-sm">結果がここに表示されます</div>
      </div>

      <!-- 定数プリセット -->
      <div>
        <p class="text-xs font-medium opacity-60 mb-2">物理定数・定数プリセット</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="c in PRESET_CONSTANTS" :key="c.key"
            @click="insertConst(c.key)"
            class="text-xs px-2 py-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded hover:bg-[var(--color-primary)] hover:text-white transition-colors"
            :title="c.key + ' = ' + c.value">
            {{ c.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- 履歴 -->
    <div class="w-56 flex-shrink-0">
      <div class="flex justify-between items-center mb-2">
        <p class="text-sm font-medium">履歴</p>
        <button @click="clearHistory" class="text-xs opacity-50 hover:opacity-100">クリア</button>
      </div>
      <div class="space-y-1 max-h-[60vh] overflow-y-auto">
        <div v-for="h in history" :key="h.ts"
          @click="useHistory(h)"
          class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded p-2 cursor-pointer hover:opacity-90 text-xs">
          <div class="font-mono opacity-70 truncate">{{ h.expr }}</div>
          <div class="font-mono font-bold">= {{ formatNum(h.result) }}</div>
        </div>
        <div v-if="history.length === 0" class="text-center py-4 opacity-30 text-xs">履歴なし</div>
      </div>
    </div>
  </div>

</div>
</body>
</html>
