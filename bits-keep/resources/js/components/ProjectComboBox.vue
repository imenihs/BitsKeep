<template>
  <div class="relative" ref="wrapRef">
    <!-- 入力フィールド -->
    <div class="flex items-center border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] px-3 py-2 gap-2 focus-within:border-[var(--color-primary)] transition-colors">
      <span class="opacity-40 text-sm flex-shrink-0">📋</span>
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        :placeholder="placeholder || '事業名・案件名・案件番号で絞り込み'"
        class="flex-1 bg-transparent text-sm outline-none min-w-0"
        @input="onInput"
        @keydown="onKeyDown"
        @focus="onFocus"
        autocomplete="off"
      />
      <!-- 選択済み表示 -->
      <button v-if="selected" @click.stop="clear"
        class="opacity-40 hover:opacity-70 text-xs flex-shrink-0 transition-opacity">✕</button>
      <span v-else-if="loading" class="opacity-40 text-xs flex-shrink-0">...</span>
    </div>

    <!-- ドロップダウン候補 -->
    <div v-if="open"
      class="absolute z-50 left-0 right-0 mt-1 bg-[var(--color-bg)] border border-[var(--color-border)] rounded-xl shadow-lg max-h-72 overflow-y-auto">

      <!-- 候補リスト -->
      <div v-if="candidates.length > 0">
        <div
          v-for="(item, idx) in candidates"
          :key="item.id"
          @click="selectItem(item)"
          :class="[
            'flex items-center gap-2 px-4 py-2.5 cursor-pointer transition-colors border-b border-[var(--color-border)] last:border-0',
            idx === activeIdx
              ? 'bg-[var(--color-primary)] text-white'
              : 'hover:bg-[var(--color-card-odd)]',
          ]">
          <!-- 事業コード -->
          <span v-if="item.business_code"
            class="text-xs font-mono px-1.5 py-0.5 rounded flex-shrink-0"
            :class="idx === activeIdx ? 'bg-white/20' : 'bg-[var(--color-card-even)]'">
            {{ item.business_code }}
          </span>
          <!-- 案件名 -->
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate">
              {{ item.external_code ? item.external_code + '_' + item.name : item.name }}
            </div>
            <div v-if="item.business_name" class="text-xs opacity-50 truncate">{{ item.business_name }}</div>
          </div>
          <!-- ソース種別バッジ -->
          <span
            class="text-xs px-1.5 py-0.5 rounded flex-shrink-0"
            :class="idx === activeIdx
              ? 'bg-white/20'
              : item.source_type === 'notion' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'">
            {{ item.source_type === 'notion' ? 'Notion' : 'Local' }}
          </span>
        </div>
      </div>

      <!-- 候補なし -->
      <div v-else-if="!loading && query" class="px-4 py-3 text-sm opacity-40">
        「{{ query }}」に一致する案件がありません
      </div>
      <div v-else-if="loading" class="px-4 py-3 text-sm opacity-40">検索中...</div>

      <!-- 新規追加導線（allowNew かつ完全一致なし） -->
      <div v-if="allowNew && query && !exactMatch"
        @click="openNewModal"
        :class="[
          'flex items-center gap-2 px-4 py-2.5 cursor-pointer border-t border-[var(--color-border)] transition-colors',
          activeIdx === candidates.length
            ? 'bg-[var(--color-primary)] text-white'
            : 'hover:bg-[var(--color-card-odd)] text-[var(--color-primary)]',
        ]">
        <span class="text-base">➕</span>
        <span class="text-sm font-medium">「{{ query }}」を新規案件として追加</span>
      </div>
    </div>

    <!-- 新規案件追加確認モーダル -->
    <teleport to="body">
      <div v-if="newModal.open"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="newModal.open = false">
        <div class="bg-[var(--color-bg)] rounded-2xl shadow-2xl w-full max-w-md">
          <div class="p-5 border-b border-[var(--color-border)]">
            <h3 class="font-bold text-lg">新規案件を追加</h3>
            <p class="text-sm opacity-60 mt-1">BitsKeep独自案件として登録します。Notion側には反映されません。</p>
          </div>
          <div class="p-5 space-y-4">
            <!-- 案件名 -->
            <div>
              <label class="text-xs font-semibold opacity-60 uppercase tracking-wide block mb-1">案件名 *</label>
              <input v-model="newModal.name" type="text"
                class="w-full border border-[var(--color-border)] rounded-lg px-3 py-2 text-sm bg-[var(--color-card-odd)] focus:border-[var(--color-primary)] outline-none" />
            </div>
            <!-- 事業選択 -->
            <div>
              <label class="text-xs font-semibold opacity-60 uppercase tracking-wide block mb-1">事業（任意）</label>
              <select v-model="newModal.businessCode"
                class="w-full border border-[var(--color-border)] rounded-lg px-3 py-2 text-sm bg-[var(--color-card-odd)] focus:border-[var(--color-primary)] outline-none">
                <option value="">事業を選択しない</option>
                <option v-for="b in businesses" :key="b.business_code" :value="b.business_code">
                  [{{ b.business_code }}] {{ b.business_name }}
                </option>
              </select>
            </div>
            <!-- 説明 -->
            <div>
              <label class="text-xs font-semibold opacity-60 uppercase tracking-wide block mb-1">説明（任意）</label>
              <textarea v-model="newModal.description" rows="2"
                class="w-full border border-[var(--color-border)] rounded-lg px-3 py-2 text-sm bg-[var(--color-card-odd)] focus:border-[var(--color-primary)] outline-none resize-none" />
            </div>
          </div>
          <div class="p-5 border-t border-[var(--color-border)] flex justify-end gap-3">
            <button @click="newModal.open = false"
              class="px-4 py-2 text-sm border border-[var(--color-border)] rounded-lg hover:opacity-70 transition-opacity">
              キャンセル
            </button>
            <button @click="createNewProject" :disabled="!newModal.name || newModal.saving"
              class="px-4 py-2 text-sm bg-[var(--color-primary)] text-white rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity">
              {{ newModal.saving ? '作成中...' : '作成して選択' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { api } from '../api.js';

export default {
  name: 'ProjectComboBox',

  props: {
    modelValue: { type: [Object, null], default: null },
    placeholder: { type: String, default: '' },
    allowNew:    { type: Boolean, default: true },
  },

  emits: ['update:modelValue', 'new-project-created'],

  setup(props, { emit }) {
    const wrapRef  = ref(null);
    const inputRef = ref(null);
    const query     = ref('');
    const open      = ref(false);
    const loading   = ref(false);
    const candidates = ref([]);
    const activeIdx  = ref(-1);
    const selected   = ref(props.modelValue);
    const businesses = ref([]);

    let debounceTimer = null;

    // 選択済みなら表示ラベルをクエリに反映
    watch(() => props.modelValue, (val) => {
      selected.value = val;
      if (val) query.value = formatLabel(val);
    }, { immediate: true });

    const formatLabel = (item) => {
      if (!item) return '';
      const code = item.external_code ? item.external_code + '_' : '';
      return code + item.name;
    };

    // 完全一致（名前が同じ候補が存在するか）
    const exactMatch = computed(() =>
      candidates.value.some(c => c.name === query.value)
    );

    const search = async () => {
      if (!query.value.trim()) { candidates.value = []; return; }
      loading.value = true;
      try {
        const res = await api.get(`/projects/options?q=${encodeURIComponent(query.value)}&limit=20`);
        candidates.value = res.data?.data ?? res.data ?? [];
      } catch { candidates.value = []; }
      finally { loading.value = false; }
    };

    const onInput = () => {
      selected.value = null;
      emit('update:modelValue', null);
      open.value = true;
      activeIdx.value = -1;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(search, 300);
    };

    const onFocus = async () => {
      open.value = true;
      if (!query.value && candidates.value.length === 0) {
        await search();
      }
    };

    const onKeyDown = (e) => {
      const total = candidates.value.length + (props.allowNew && query.value && !exactMatch.value ? 1 : 0);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx.value = Math.min(activeIdx.value + 1, total - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx.value = Math.max(activeIdx.value - 1, -1);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIdx.value >= 0 && activeIdx.value < candidates.value.length) {
          selectItem(candidates.value[activeIdx.value]);
        } else if (activeIdx.value === candidates.value.length) {
          openNewModal();
        }
      } else if (e.key === 'Escape') {
        open.value = false;
      }
    };

    const selectItem = (item) => {
      selected.value = item;
      query.value    = formatLabel(item);
      open.value     = false;
      emit('update:modelValue', item);
    };

    const clear = () => {
      selected.value = null;
      query.value    = '';
      candidates.value = [];
      emit('update:modelValue', null);
      inputRef.value?.focus();
    };

    // ── 新規案件モーダル ───────────────────────────
    const newModal = ref({ open: false, name: '', businessCode: '', description: '', saving: false });

    const openNewModal = async () => {
      newModal.value = { open: true, name: query.value, businessCode: '', description: '', saving: false };
      open.value = false;
      // 事業一覧を遅延ロード
      if (businesses.value.length === 0) {
        const res = await api.get('/project-businesses').catch(() => null);
        businesses.value = res?.data?.data ?? res?.data ?? [];
      }
    };

    const createNewProject = async () => {
      if (!newModal.value.name) return;
      newModal.value.saving = true;
      try {
        const payload = { name: newModal.value.name, description: newModal.value.description };
        if (newModal.value.businessCode) {
          const biz = businesses.value.find(b => b.business_code === newModal.value.businessCode);
          payload.business_code = biz?.business_code;
          payload.business_name = biz?.business_name;
        }
        const res     = await api.post('/projects', payload);
        const project = res.data?.data ?? res.data;
        newModal.value.open = false;
        selectItem(project);
        emit('new-project-created', project);
      } catch { /* エラー無視（将来的にトースト表示） */ }
      finally { newModal.value.saving = false; }
    };

    // クリック外で閉じる
    const onClickOutside = (e) => {
      if (wrapRef.value && !wrapRef.value.contains(e.target)) {
        open.value = false;
      }
    };

    onMounted(() => document.addEventListener('mousedown', onClickOutside));
    onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));

    return {
      wrapRef, inputRef, query, open, loading, candidates, activeIdx, selected,
      exactMatch, businesses, newModal,
      onInput, onFocus, onKeyDown, selectItem, clear, openNewModal, createNewProject,
    };
  },
};
</script>
