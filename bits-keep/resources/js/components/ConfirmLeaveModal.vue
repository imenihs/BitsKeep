<template>
  <div v-if="visible" class="modal-overlay" style="z-index:9000" v-esc="cancel">
    <div class="modal-window modal-sm" @click.stop>
      <div class="p-6">
        <p class="text-sm leading-relaxed mb-6">{{ message }}</p>
        <div class="flex justify-end gap-3">
          <button
            @click="cancel"
            class="px-4 py-2 border border-[var(--color-border)] rounded text-sm hover:opacity-70 transition-opacity">
            キャンセル
          </button>
          <button
            @click="confirm"
            class="px-4 py-2 rounded text-sm bg-[var(--color-tag-eol)] text-white hover:opacity-80 transition-opacity">
            閉じる
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useConfirmModal } from '../composables/useConfirmModal.js';
const { visible, message, confirm, cancel } = useConfirmModal();
</script>
