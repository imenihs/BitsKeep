<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ユーザー管理 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="user-list" class="p-6 max-w-4xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>管理</span>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">ユーザー管理</span>
  </nav>

  <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <div>
      <h1 class="text-2xl font-bold">ユーザー管理</h1>
      <p class="text-sm opacity-60 mt-1">管理者のみ操作可能</p>
    </div>
    <button @click="openInvite" class="btn-primary px-4 py-2 rounded text-sm font-medium">+ ユーザーを招待</button>
  </header>

  <table class="w-full text-sm border-collapse">
    <thead>
      <tr class="border-b border-[var(--color-border)] text-left opacity-70">
        <th class="py-2 pr-4">名前 / メール</th>
        <th class="py-2 pr-4">ロール</th>
        <th class="py-2 pr-4">状態</th>
        <th class="py-2 pr-4">招待日</th>
        <th class="py-2">操作</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="u in users" :key="u.id"
        :class="u.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
        :style="!u.is_active ? 'opacity: 0.5' : ''"
        class="border-b border-[var(--color-border)]">
        <td class="py-2 pr-4">
          <div class="font-medium">@{{ u.name }}</div>
          <div class="text-xs opacity-60">@{{ u.email }}</div>
        </td>
        <td class="py-2 pr-4">
          <span :class="roleBadgeClass(u.role)" class="px-2 py-0.5 rounded text-xs font-medium">
            @{{ roleLabel(u.role) }}
          </span>
        </td>
        <td class="py-2 pr-4">
          <span :class="u.is_active ? 'text-emerald-600' : 'text-red-500'" class="text-xs font-medium">
            @{{ u.is_active ? '有効' : '無効' }}
          </span>
        </td>
        <td class="py-2 pr-4 text-xs opacity-60">
          @{{ u.invited_at ? new Date(u.invited_at).toLocaleDateString('ja-JP') : '-' }}
        </td>
        <td class="py-2">
          <div class="flex gap-2 items-center">
            <select :value="u.role" @change="changeRole(u, $event.target.value)"
              class="text-xs bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1">
              <option value="admin">管理者</option>
              <option value="editor">編集者</option>
              <option value="viewer">閲覧者</option>
            </select>
            <button @click="toggleActive(u)"
              :class="u.is_active ? 'border-red-400 text-red-500 hover:bg-red-50' : 'border-emerald-500 text-emerald-600 hover:bg-emerald-50'"
              class="px-2 py-1 text-xs border rounded">
              @{{ u.is_active ? '無効化' : '有効化' }}
            </button>
          </div>
        </td>
      </tr>
      <tr v-if="users.length === 0">
        <td colspan="5" class="py-8 text-center opacity-40">ユーザーがいません</td>
      </tr>
    </tbody>
  </table>

  <!-- 招待モーダル -->
  <div v-if="inviteModal.open" class="modal-overlay">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">ユーザー招待</h2>
        <button @click="inviteModal.open = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>

      <!-- 招待完了: 仮パスワード表示 -->
      <div v-if="inviteModal.result" class="p-6">
        <p class="text-emerald-600 font-medium mb-3">✓ 招待ユーザーを作成しました</p>
        <p class="text-sm mb-2">以下の仮パスワードを本人に伝えてください（この画面を閉じると確認できません）:</p>
        <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded p-3 font-mono text-lg text-center tracking-widest">
          @{{ inviteModal.result }}
        </div>
        <div class="flex justify-end mt-4">
          <button @click="inviteModal.open = false" class="btn-primary px-4 py-2 rounded">閉じる</button>
        </div>
      </div>

      <!-- 招待フォーム -->
      <div v-else class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="inviteModal.form.name" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">メールアドレス <span class="text-red-500">*</span></label>
          <input v-model="inviteModal.form.email" type="email"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">ロール</label>
          <select v-model="inviteModal.form.role"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="admin">管理者</option>
            <option value="editor">編集者</option>
            <option value="viewer">閲覧者</option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button @click="inviteModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
          <button @click="invite" class="btn-primary px-4 py-2 rounded font-medium">招待する</button>
        </div>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">@{{ t.message }}</div>
  </div>

</div>
</body>
</html>
