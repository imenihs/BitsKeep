<template>
  <div class="flex min-h-screen">
    <!-- Sidebar: Filters -->
    <aside class="w-64 bg-[var(--color-bg-alt)] border-r border-[var(--color-card-border)] p-4 overflow-y-auto">
      <div class="mb-4">
        <label class="block text-sm font-semibold mb-1">分類</label>
        <select class="input-text">
          <option>すべて</option>
          <option>抵抗</option>
          <option>コンデンサ</option>
          <option>IC</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-semibold mb-1">商社</label>
        <select class="input-text">
          <option>すべて</option>
          <option>秋月</option>
          <option>RS</option>
          <option>Digikey</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-semibold mb-1">在庫タイプ</label>
        <select class="input-text">
          <option>すべて</option>
          <option>新品</option>
          <option>中古</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">キーワード</label>
        <input type="text" v-model="filter" class="input-text" placeholder="型番・備考など">
      </div>
    </aside>

    <!-- Main content: Component list -->
    <main class="flex-1 p-6 bg-[var(--color-bg)] overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <input type="text" v-model="filter" class="input-text w-2/3" placeholder="部品名・型番で検索..." />
        <button class="toggle-button">+ 新規登録</button>
      </div>

      <div class="space-y-4">
        <div
          v-for="(part, index) in filteredParts"
          :key="index"
          class="card flex justify-between items-center"
          :class="index % 2 === 0 ? 'bg-[var(--color-card-a)]' : 'bg-[var(--color-card-b)]'"
        >
          <div>
            <strong class="text-base">{{ part.name }}</strong><br>
            <span class="text-sm text-[var(--color-text)]">{{ part.model }} ｜ {{ part.supplier }} ｜ {{ part.price }}円</span>
          </div>
          <div class="space-x-2">
            <button class="toggle-button bg-[var(--color-primary)]">詳細</button>
            <button class="toggle-button bg-[var(--color-highlight)]">編集</button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const filter = ref('');
const parts = ref([
  { name: 'SLG46826V DIP化モジュール', model: 'AE-SLG46826V-DIP20', supplier: '秋月電子通商', price: 350 },
  { name: 'Tang Nano 9K', model: 'Tang Nano 9K', supplier: '秋月電子通商', price: 2980 },
  { name: 'LED 赤色', model: 'LED-RED-5MM', supplier: 'RS', price: 25 },
]);

const filteredParts = computed(() => {
  return parts.value.filter(p =>
    `${p.name} ${p.model} ${p.supplier}`.toLowerCase().includes(filter.value.toLowerCase())
  );
});
</script>

<style scoped>
@import '../../css/components/component-list.css';
</style>
