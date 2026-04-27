<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ユーザー管理 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => 'ユーザー管理'])
<div id="app" data-page="user-list" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'ユーザー管理', 'current' => true]]])

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
        <th class="py-2 pr-4">SNS連携</th>
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
          <div class="flex flex-wrap gap-1.5">
            <span v-for="provider in u.auth_providers" :key="provider.provider"
              class="rounded-full border border-[var(--color-border)] px-2 py-0.5 text-[11px]">
              @{{ providerLabel(provider.provider) }}
            </span>
            <span v-if="!u.auth_providers?.length" class="text-xs opacity-40">未連携</span>
          </div>
          <div class="mt-1 text-[10px] opacity-45">追加/解除は本人のプロフィールで実行</div>
        </td>
        <td class="py-2 pr-4">
          <span :class="u.is_active ? 'text-emerald-600' : 'text-red-500'" class="text-xs font-medium">
            @{{ u.is_active ? '有効' : '無効' }}
          </span>
        </td>
        <td class="py-2 pr-4 text-xs opacity-60">
          @{{ u.invited_at ? formatDate(u.invited_at) : '-' }}
        </td>
        <td class="py-2">
          <div class="flex gap-2 items-center flex-wrap">
            <button @click="openNameEdit(u)"
              class="px-3 py-1.5 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] font-medium">
              名前編集
            </button>
            <button @click="openEmailEdit(u)"
              class="px-3 py-1.5 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] font-medium">
              メール変更
            </button>
            <button @click="openPasswordReset(u)"
              class="px-3 py-1.5 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] font-medium">
              PW リセット
            </button>
            <button @click="openRoleChange(u)"
              class="px-3 py-1.5 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] font-medium">
              ロール変更
            </button>
            <button @click="toggleActive(u)"
              :class="u.is_active ? 'border-red-400 text-red-500 hover:bg-red-50' : 'border-emerald-500 text-emerald-600 hover:bg-emerald-50'"
              class="px-3 py-1.5 text-xs border rounded font-medium">
              @{{ u.is_active ? '無効化' : '有効化' }}
            </button>
          </div>
        </td>
      </tr>
      <tr v-if="users.length === 0">
        <td colspan="6" class="py-8 text-center opacity-40">ユーザーがいません</td>
      </tr>
    </tbody>
  </table>

  <!-- 招待モーダル -->
  <div v-if="inviteModal.open" class="modal-overlay" v-esc="() => inviteModal.open = false">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">ユーザー招待</h2>
        <button type="button" @click="inviteModal.open = false" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>

      <!-- 招待完了 -->
      <div v-if="inviteModal.result" class="p-6">
        <p class="text-emerald-600 font-medium mb-3">✓ 招待ユーザーを作成しました</p>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-3">
          発行直後の確認状態: 招待メール送信済み / 仮パスワード発行済み
        </div>
        <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded p-3 font-mono text-lg text-center tracking-widest">
          @{{ inviteModal.result.temp_password }}
        </div>
        <p class="text-xs opacity-60 mt-2">初回ログイン後はプロフィール画面でパスワードを変更できます</p>
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

  <!-- ロール変更モーダル -->
  <div v-if="roleModal.open" class="modal-overlay" v-esc="() => roleModal.open = false">
    <div class="modal-window modal-sm">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">ロール変更</h2>
        <button type="button" @click="roleModal.open = false" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <p class="text-sm font-medium mb-2">ユーザー: @{{ roleModal.user?.name }}</p>
          <div class="space-y-2">
            <label v-for="role in ['admin', 'editor', 'viewer']" :key="role"
              class="flex items-start gap-3 cursor-pointer p-2 rounded border transition-colors"
              :class="roleModal.selectedRole === role ? 'border-[var(--color-primary)] bg-[var(--color-card-even)]' : 'border-[var(--color-border)]'">
              <input type="radio" :value="role" v-model="roleModal.selectedRole" class="mt-0.5" />
              <div>
                <div class="text-sm font-medium">@{{ roleLabel(role) }}</div>
                <div class="text-xs opacity-60 mt-0.5">
                  <template v-if="role === 'admin'">全操作・ユーザー管理・設定変更・データ削除が可能</template>
                  <template v-else-if="role === 'editor'">部品・在庫・案件の登録・編集が可能。ユーザー管理・設定変更は不可</template>
                  <template v-else>閲覧・検索のみ。登録・編集・削除は不可</template>
                </div>
              </div>
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
          <button @click="roleModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
          <button @click="confirmRoleChange" class="btn-primary px-4 py-2 rounded font-medium">変更する</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 名前編集モーダル -->
  <div v-if="nameModal.open" class="modal-overlay" v-esc="() => nameModal.open = false">
    <div class="modal-window modal-sm">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">名前編集</h2>
        <button type="button" @click="nameModal.open = false" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">新しい名前</label>
          <input v-model="nameModal.editedName" type="text" placeholder="ユーザーの表示名"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
          <button @click="nameModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
          <button @click="confirmNameChange" class="btn-primary px-4 py-2 rounded font-medium">変更する</button>
        </div>
      </div>
    </div>
  </div>

  <!-- メールアドレス変更モーダル -->
  <div v-if="emailModal.open" class="modal-overlay" v-esc="() => emailModal.open = false">
    <div class="modal-window modal-sm">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">メールアドレス変更</h2>
        <button type="button" @click="emailModal.open = false" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">新しいメールアドレス</label>
          <input v-model="emailModal.editedEmail" type="email" placeholder="new@example.com"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <p class="text-xs opacity-60">変更後のアドレスでログインできるようになります</p>
        <div class="flex justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
          <button @click="emailModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
          <button @click="confirmEmailChange" class="btn-primary px-4 py-2 rounded font-medium">変更する</button>
        </div>
      </div>
    </div>
  </div>

  <!-- パスワードリセットモーダル -->
  <div v-if="passwordModal.open" class="modal-overlay" v-esc="() => passwordModal.open = false">
    <div class="modal-window modal-sm">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">パスワードリセット</h2>
        <button type="button" @click="passwordModal.open = false" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm font-medium">対象: @{{ passwordModal.user?.name }}</p>
        <div>
          <label class="block text-sm font-medium mb-2">新しいパスワード（8文字以上）</label>
          <input v-model="passwordModal.newPassword" type="password" placeholder="新しいパスワード"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">確認</label>
          <input v-model="passwordModal.newPasswordConfirmation" type="password" placeholder="もう一度入力"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <p class="text-xs opacity-60">⚠ ユーザーへ新しいパスワードを別途通知してください</p>
        <div class="flex justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
          <button @click="passwordModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
          <button @click="confirmPasswordReset" class="btn-primary px-4 py-2 rounded font-medium">リセットする</button>
        </div>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">@{{ t.msg }}</div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => 'ユーザー管理', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
