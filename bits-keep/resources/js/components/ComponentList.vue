<template>
  <div class="p-4">
    <!-- 絞り込み -->
    <aside class="mb-4 bg-[var(--color-bg-alt)] border border-[var(--color-card-border)] p-4 rounded">
      <label class="block mb-2 font-semibold">キーワード絞り込み</label>
      <input type="text" v-model="filter" class="input-text" placeholder="部品名や型番で検索…" />
    </aside>

    <!-- 一覧カード -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="(part, index) in filteredParts"
        :key="index"
        class="card"
        :class="index % 2 === 0 ? 'bg-[var(--color-bg-alt)]' : 'bg-[var(--color-highlight)]/10'"
      >
        <h2 class="text-lg font-bold mb-1">{{ part.name }}</h2>
        <p class="text-sm text-[var(--color-text)]">型番: {{ part.model }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const filter = ref('');
const parts = ref([
  { name: 'コンデンサ 100μF', model: 'C100UF-16V' },
  { name: '抵抗 10kΩ', model: 'R10K-1/4W' },
  { name: 'トランジスタ 2N2222', model: '2N2222' },
  { name: 'ダイオード 1N4001', model: '1N4001' },
  { name: 'LED 赤色', model: 'LED-RED-5MM' },
]);

const filteredParts = computed(() => {
  return parts.value.filter(p =>
    `${p.name} ${p.model}`.toLowerCase().includes(filter.value.toLowerCase())
  );
});
</script>
