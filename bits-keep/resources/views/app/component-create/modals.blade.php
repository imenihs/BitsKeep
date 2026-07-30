{{--
  部品登録/編集画面の補助入力モーダル群。
  入力は親Vue setupが公開する解析状態・ChatGPT状態・スペック詳細フォームで、出力はフォーム候補への反映操作。
  表示中はスクロールロック、外部helper連携、一時PDF削除、スペック詳細作成などの副作用を伴う。
--}}
  {{-- 解析中の表示は1種類に統一する。どの解析方式で動いているかは利用者の判断材料ではないため、
       表題に解析エンジン名を出さない。画面を占有するのは「待つしかない旧方式」のときだけ --}}
  <div v-if="analyzing" class="modal-overlay" style="z-index:8500" role="alertdialog" aria-modal="true" aria-busy="true">
    <div class="modal-window modal-sm p-6 text-center" @click.stop>
      <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[var(--color-border)] border-t-[var(--color-primary)]"></div>
      <h3 class="mt-4 text-lg font-bold">データシートを解析中</h3>
      <p class="mt-2 text-sm opacity-70">データシートPDFを解析しています。完了までこのままお待ちください。</p>
      <p class="mt-1 text-[11px] opacity-50">この方式は画面を開いたままにする必要があります。</p>
    </div>
  </div>
  <div v-if="isChatGptJobBusy && !showChatGptRunModal" class="modal-overlay" style="z-index:8450" role="alertdialog" aria-modal="true" aria-busy="true">
    <div class="modal-window modal-sm p-6 text-center" @click.stop>
      <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[var(--color-border)] border-t-[var(--color-primary)]"></div>
      <h3 class="mt-4 text-lg font-bold">データシートを解析中</h3>
      <p class="mt-2 text-sm opacity-70">ブラウザのタブでデータシートを解析しています。完了までこの画面は操作できません。</p>
      <p class="mt-2 text-xs opacity-60">@{{ chatGptJob.detail || '処理状況を確認しています。' }}</p>
      <p class="mt-1 text-[11px] opacity-50">解析完了後は候補確認モーダルを表示します。</p>
      <button type="button" @click="hardResetChatGptJob" class="mt-4 inline-flex min-h-11 items-center justify-center rounded border border-[var(--color-tag-eol)] px-4 py-2 text-sm font-medium text-[var(--color-tag-eol)]">
        ジョブを破棄してリセット
      </button>
      <p class="mt-2 text-[11px] opacity-45">止まったときはこのボタンで前回ジョブを破棄して、最初からやり直してください。</p>
    </div>
  </div>

  <div v-if="showDatasheetManagerModal" class="modal-overlay" v-esc="closeDatasheetManager">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold">解析対象のPDFを選択</h3>
          <p class="mt-2 text-sm opacity-70">複数PDFを選択したため、今回解析に使う1件だけを選びます。</p>
        </div>
        <button type="button" @click="closeDatasheetManager" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div v-if="datasheetFiles.length" class="mt-5 space-y-2">
        <div v-for="(file, index) in datasheetFiles" :key="`${file.name}-${index}`" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <p class="text-[11px] opacity-60 break-all">@{{ file.name }}</p>
              <label class="mt-2 inline-flex items-center gap-2 text-[11px] opacity-70">
                <input type="radio" v-model="datasheetTargetIndex" :value="index" />
                <span>このPDFを AI 解析対象にする</span>
              </label>
            </div>
            <span v-if="datasheetTargetIndex === index" class="inline-flex items-center rounded-full bg-[var(--color-primary)]/15 px-2 py-1 text-[10px] font-semibold text-[var(--color-primary)]">解析対象</span>
          </div>
          <p v-if="datasheetLabels[index]" class="mt-2 text-[11px] opacity-60">表示名: @{{ datasheetLabels[index] }}</p>
        </div>
      </div>

      <div v-else class="mt-5 rounded-xl border border-dashed border-[var(--color-border)] px-4 py-6 text-sm opacity-60">
        先に主画面のPDF選択ボックスからデータシートを選択してください。
      </div>

      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" @click="closeDatasheetManager" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
          閉じる
        </button>
        <button type="button" @click="confirmDatasheetTargetSelection" :disabled="!datasheetTargetLabel" class="btn-primary px-4 py-2 rounded text-sm disabled:opacity-50">
          このPDFで続行
        </button>
      </div>
    </div>
  </div>

  <div v-if="showChatGptHelperUpdateModal" class="modal-overlay" v-esc="closeChatGptHelperUpdateModal">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold">@{{ chatGptHelperCheckStatus === 'success' ? 'Tampermonkey helper は最新です' : (chatGptHelperIssue ? chatGptHelperIssue.title : 'Tampermonkey helper を確認しました') }}</h3>
          <p class="mt-2 text-sm opacity-75">
            @{{ chatGptHelperCheckStatus === 'success' ? '更新済み helper を検出しました。ChatGPT自動入力を続行できます。' : (chatGptHelperIssue ? chatGptHelperIssue.body : '更新済み helper を検出しました。ChatGPT自動入力を続行できます。') }}
          </p>
        </div>
        <button type="button" @click="closeChatGptHelperUpdateModal" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div v-if="chatGptHelperCheckStatus !== 'success'" class="mt-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="text-sm font-semibold">更新手順</div>
        <ol class="mt-3 space-y-2 text-sm opacity-80">
          <li>1. 下の <strong>userscript を更新</strong> を押す</li>
          <li>2. Tampermonkey の画面で <strong>再インストール</strong> または <strong>更新</strong> を承認する</li>
          <li>3. 下の <strong>再読込して反映</strong> を押し、この部品登録画面に更新版を反映する</li>
          <li>4. 再読込後に `helper v{{ $chatGptHelperMinVersion }}` 以上の成功トーストが出れば完了。旧版のままならこのダイアログが再表示される</li>
        </ol>
        <p class="mt-3 text-xs opacity-60">Tampermonkey の更新は、いま開いている BitsKeep 画面へ自動反映されません。更新後はこの画面の再読込が必要です。</p>
        <p v-if="chatGptJob.helperVersion" class="mt-3 text-xs opacity-60">現在検出中の helper: v@{{ chatGptJob.helperVersion }}</p>
        <p v-else class="mt-3 text-xs opacity-60">現在検出中の helper: 不明</p>
      </div>

      <div v-if="chatGptHelperCheckMessage" class="mt-4 rounded-2xl border p-4"
        :class="chatGptHelperCheckStatus === 'success'
          ? 'border-[var(--color-tag-ok)] bg-[var(--color-tag-ok)]/10'
          : 'border-[var(--color-tag-warning)] bg-[var(--color-tag-warning)]/10'">
        <div class="text-sm font-semibold">
          @{{ chatGptHelperCheckStatus === 'success' ? '確認結果' : '再確認結果' }}
        </div>
        <p class="mt-2 text-sm opacity-80">@{{ chatGptHelperCheckMessage }}</p>
      </div>

      <div v-if="chatGptHelperCheckStatus === 'success'" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" @click="closeChatGptHelperUpdateModal"
          class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-5 py-2 text-sm font-medium leading-none">
          閉じる
        </button>
        <div class="flex justify-end">
          <button type="button" @click="openChatGptRun"
            class="btn-primary inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded px-5 py-2 text-sm font-medium leading-none">
            ChatGPT自動解析を開く
          </button>
        </div>
      </div>

      <div v-else class="mt-6 flex flex-col gap-3">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <a href="{{ $chatGptHelperUrl }}" target="_blank" rel="noreferrer"
            class="btn-primary inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded px-5 py-2 text-sm font-medium leading-none no-underline text-inherit">
            userscript を更新
          </a>
          <button type="button" @click="reloadForChatGptHelperUpdate"
            class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded border border-[var(--color-primary)] px-5 py-2 text-sm font-medium leading-none text-[var(--color-primary)]">
            再読込して反映
          </button>
        </div>
        <div class="flex justify-start sm:justify-end">
          <button type="button" @click="closeChatGptHelperUpdateModal"
            class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-5 py-2 text-sm font-medium leading-none">
          あとで
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showChatGptRunModal" class="modal-overlay" v-esc="closeChatGptRun">
    <div class="modal-window modal-lg p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-bold">ChatGPT自動解析</h3>
          <p class="mt-2 text-sm opacity-70">実行条件の確認、進行状況の把握、fallback への切り替えをこのモーダルに集約しています。</p>
          <p v-if="isChatGptJobBusy" class="mt-2 text-xs text-[var(--color-tag-warning)]">解析中はこのモーダルを閉じず、部品登録画面の操作をロックしています。</p>
        </div>
        <button type="button" @click="closeChatGptRun" :disabled="!canDismissChatGptRun" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-20">✕</button>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="text-[11px] opacity-55">解析対象PDF</div>
          <div class="mt-1 text-sm font-semibold break-words">@{{ datasheetTargetLabel || '未選択' }}</div>
          <p class="mt-2 text-[11px] opacity-60">複数PDFを選んだときだけ、解析に使う1件を選び直せます。</p>
        </div>
        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="text-[11px] opacity-55">実行前チェック</div>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="chip in chatGptStatusChips" :key="chip.label"
              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
              :class="{
                'bg-[var(--color-tag-ok)]/15 text-[var(--color-tag-ok)]': chip.tone === 'ok',
                'bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]': chip.tone === 'warning',
                'bg-[var(--color-tag-eol)]/15 text-[var(--color-tag-eol)]': chip.tone === 'danger',
                'bg-[var(--color-primary)]/10 text-[var(--color-primary)]': chip.tone === 'neutral'
              }">
              @{{ chip.label }}
            </span>
          </div>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="text-sm font-semibold">次にやること</div>
        <p class="mt-2 text-sm opacity-75">@{{ chatGptGuideReason || showChatGptRunHint }}</p>
        <p v-if="chatGptJob.connected && chatGptJob.helperVersion && !isChatGptHelperVersionCompatible()" class="mt-2 text-xs text-[var(--color-tag-warning)]">
          userscript が旧版です。下の「userscript を更新」を押し、Tampermonkey の更新画面で置き換えてください。
        </p>
        <p v-if="chatGptJob.detail" class="mt-2 text-xs opacity-60">@{{ chatGptJob.detail }}</p>
        <p v-if="chatGptJob.error" class="mt-2 text-xs text-[var(--color-tag-eol)]">@{{ chatGptJob.error }}</p>
      </div>

      <div class="mt-4 grid gap-2 md:grid-cols-4">
        <div v-for="step in chatGptStepStates" :key="step.label"
          class="rounded-xl border px-3 py-3 text-sm"
          :class="{
            'border-[var(--color-tag-ok)] bg-[var(--color-tag-ok)]/10': step.status === 'done',
            'border-[var(--color-primary)] bg-[var(--color-primary)]/10': step.status === 'current',
            'border-[var(--color-border)] opacity-70': step.status === 'pending'
          }">
          <div class="text-[11px] opacity-60">ステップ</div>
          <div class="mt-1 font-semibold">@{{ step.label }}</div>
        </div>
      </div>

      <div class="mt-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
          <button v-if="datasheetFiles.length > 1" type="button" @click="openDatasheetManager" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            解析対象を選び直す
          </button>
          <a href="{{ $chatGptHelperUrl }}" target="_blank" rel="noreferrer"
            :class="isChatGptJobBusy ? 'pointer-events-none opacity-40' : ''"
            class="px-4 py-2 rounded border border-[var(--color-border)] text-sm no-underline text-inherit">
            userscript を更新
          </a>
          <button type="button" @click="openChatGptHelperUpdateModal" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            更新手順
          </button>
          <button type="button" @click="openPasteFallbackFromGuide" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            手動貼り付けへ
          </button>
          <button type="button" @click="hardResetChatGptJob" class="px-4 py-2 rounded border border-[var(--color-tag-eol)] text-sm text-[var(--color-tag-eol)]">
            ジョブを破棄してリセット
          </button>
          <button type="button" v-if="chatGPTPasteText.trim()" @click="copyChatGptFallbackText" :disabled="isChatGptJobBusy" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm disabled:cursor-not-allowed disabled:opacity-40">
            応答テキストをコピー
          </button>
        </div>
        <button type="button" @click="startChatGPTAutoFill"
          :disabled="!canStartChatGptAutoFill || isChatGptJobBusy"
          class="btn-primary px-5 py-2 rounded text-sm disabled:opacity-50">
          @{{ chatGptJob.state === 'idle' ? '解析を開始' : '再実行する' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ChatGPT 貼り付けモーダル -->
  <div v-if="showHelperResultModal && helperResult" class="modal-overlay" v-esc="closeHelperResultModal">
    <div class="modal-window modal-helper-review p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-center justify-between gap-4 mb-4">
        <div>
          <h3 class="text-lg font-bold">解析候補を確認</h3>
          <p class="mt-1 text-xs opacity-60">適用前に内容を修正できます。未マッチ項目はここで既存マスタへ紐付けてください。</p>
        </div>
        <button type="button" @click="closeHelperResultModal" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>

      <div class="space-y-6">
        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="font-semibold">基本情報</h4>
          </div>
          <div class="space-y-3">
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-center text-sm">
              <input type="checkbox" v-model="helperResult.part_number.apply" class="rounded" />
              <span class="opacity-60">型番</span>
              <input v-model="helperResult.part_number.value" type="text" class="input-text w-full" placeholder="型番" />
            </label>
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-center text-sm">
              <input type="checkbox" v-model="helperResult.manufacturer.apply" class="rounded" />
              <span class="opacity-60">メーカー</span>
              <input v-model="helperResult.manufacturer.value" type="text" class="input-text w-full" placeholder="メーカー" />
            </label>
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-center text-sm">
              <input type="checkbox" v-model="helperResult.common_name.apply" class="rounded" />
              <span class="opacity-60">通称</span>
              <input v-model="helperResult.common_name.value" type="text" class="input-text w-full" placeholder="通称・シリーズ名" />
            </label>
            <label class="grid grid-cols-[auto_88px_1fr] gap-3 items-start text-sm">
              <input type="checkbox" v-model="helperResult.description.apply" class="rounded mt-3" />
              <span class="opacity-60 mt-2">説明</span>
              <textarea v-model="helperResult.description.value" class="input-text w-full min-h-24" placeholder="説明"></textarea>
            </label>
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-semibold">部品種別 / 部品分類候補</h4>
              <p class="text-[11px] opacity-60 mt-1">候補名を修正したり、既存部品分類へ手動で紐付けできます。</p>
            </div>
            <button type="button" @click="addHelperCategory" class="text-xs link-text">+ 部品分類候補を追加</button>
          </div>
          <div v-if="helperResult.categories.length" class="space-y-2">
            <div v-for="(category, index) in helperResult.categories" :key="`helper-category-${index}`"
              class="grid grid-cols-1 md:grid-cols-[auto_1.1fr_1fr_auto] gap-2 items-center rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="category.apply" class="rounded" />
                <span class="opacity-70">適用</span>
              </label>
              <input v-model="category.name" type="text" class="input-text w-full" placeholder="部品分類候補名" />
              <select v-model="category.category_id" @change="handleHelperCategorySelection(category)" class="input-text w-full">
                <option value="">既存部品分類に未紐付け</option>
                <option v-for="masterCategory in categories" :key="masterCategory.id" :value="masterCategory.id">@{{ masterCategory.name }}</option>
              </select>
              <div class="flex items-center justify-end gap-2">
                <span v-if="category.matched" class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)]">一致</span>
                <span v-else class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未一致</span>
                <button type="button" @click="removeHelperCategory(index)" class="text-[var(--color-tag-eol)] text-sm px-2">✕</button>
              </div>
            </div>
          </div>
          <p v-else class="text-xs opacity-40">部品分類候補はまだありません</p>
        </section>

        <section v-if="helperSuggestionLoading || helperSpecGroups.length || helperSpecTemplates.length" class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h4 class="font-semibold">部品分類 / 入力テンプレート</h4>
              <p class="text-[11px] opacity-60 mt-1">適用対象の部品分類候補から推奨を表示します。</p>
            </div>
            <span v-if="helperSuggestionLoading" class="text-xs opacity-50">読込中...</span>
          </div>
          <div v-if="helperSpecGroups.length" class="flex flex-wrap gap-2">
            <span v-for="group in helperSpecGroups" :key="`helper-spec-group-${group.id}`"
              class="rounded-md border border-[var(--color-border)] bg-[var(--color-card-even)] px-3 py-2 text-xs">
              <span class="font-semibold">@{{ group.name }}</span>
              <span class="ml-2 opacity-60">@{{ group.spec_types?.length ?? 0 }}項目</span>
            </span>
          </div>
          <div v-if="helperSpecTemplates.length" class="flex flex-wrap gap-2">
            <button v-for="template in helperSpecTemplates" :key="`helper-template-${template.id}`" type="button"
              @click="applyHelperTemplate(template)"
              class="rounded-md border border-[var(--color-border)] bg-[var(--color-card-even)] px-3 py-2 text-left text-xs hover:border-[var(--color-primary)]">
              <span class="block font-semibold">@{{ template.name }}</span>
              <span class="block opacity-60">@{{ specTemplateLabel(template) }} / @{{ template.items?.length ?? 0 }}行</span>
            </button>
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h4 class="font-semibold">パッケージ候補</h4>
              <p class="text-[11px] opacity-60 mt-1">複数候補の中から、この部品として登録するパッケージを 1 つ選びます。</p>
            </div>
            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="helperResult.package_apply" class="rounded" />
                <span class="opacity-70">選択した候補を適用</span>
              </label>
              <button type="button" @click="addHelperPackage" class="text-xs link-text">+ パッケージ候補を追加</button>
            </div>
          </div>
          <div v-if="helperResult.packages.length" class="space-y-2">
            <div v-for="(packageCandidate, index) in helperResult.packages" :key="`helper-package-${index}`"
              class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4 space-y-3">
              <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="radio"
                    v-model="helperResult.selected_package_index"
                    :value="index"
                    :disabled="!helperResult.package_apply" />
                  <span class="opacity-70">この候補で登録</span>
                </label>
                <div class="flex items-center gap-2">
                  <span v-if="packageCandidate.matched" class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)]">一致</span>
                  <span v-else class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未一致</span>
                  <button type="button" @click="removeHelperPackage(index)" class="text-[var(--color-tag-eol)] text-sm px-2">✕</button>
                </div>
              </div>

              <label class="grid grid-cols-[88px_1fr] gap-3 items-center text-sm">
                <span class="opacity-60">候補名</span>
                <input v-model="packageCandidate.name" type="text" class="input-text w-full" placeholder="例: SOT-23 / SOP-8 / TO-220 / 0603" />
              </label>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] font-semibold mb-1 opacity-70">パッケージ分類</label>
                  <select v-model="packageCandidate.package_group_id" @change="handleHelperPackageGroupChange(packageCandidate)" class="input-text w-full">
                    <option value="">未選択</option>
                    <option v-for="group in packageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[11px] font-semibold mb-1 opacity-70">パッケージを絞り込み</label>
                  <input v-model="packageCandidate.package_query" type="text" class="input-text w-full" :disabled="!packageCandidate.package_group_id" placeholder="例: SOT / 0603 / SOP" />
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-semibold mb-1 opacity-70">パッケージ</label>
                <select v-model="packageCandidate.package_id" @change="handleHelperPackageSelection(packageCandidate)" class="input-text w-full">
                  <option value="">未選択</option>
                  <option v-for="pkg in helperFilteredPackages(packageCandidate)" :key="pkg.id" :value="pkg.id">@{{ pkg.name }}</option>
                </select>
                <p class="text-[11px] opacity-50 mt-2" v-if="!packageCandidate.package_group_id">先にパッケージ分類を選択してください。</p>
                <p class="text-[11px] opacity-50 mt-2" v-else-if="!helperFilteredPackages(packageCandidate).length">選択中のパッケージ分類に該当するパッケージがありません。</p>
              </div>
            </div>
          </div>
          <p v-else class="text-xs opacity-40">パッケージ候補はまだありません</p>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-semibold">スペック候補</h4>
              <p class="text-[11px] opacity-60 mt-1">値・単位・スペック詳細を修正できます。不要な候補は削除してください。</p>
            </div>
            <button type="button" @click="addHelperSpec" class="text-xs link-text">+ スペック候補を追加</button>
          </div>
          <div v-if="helperResult.specs.length" class="space-y-2">
            <div v-for="(spec, index) in helperResult.specs" :key="`helper-spec-${index}`"
              class="spec-card bg-[var(--color-card-odd)] space-y-3">
              <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="spec.apply" class="rounded" />
                  <span class="opacity-70">この候補を適用</span>
                </label>
                <div class="flex items-center gap-2">
                  <span v-if="spec.matched" class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)]">一致</span>
                  <span v-else class="text-[10px] px-2 py-1 rounded-full bg-[var(--color-tag-warning)]/15 text-[var(--color-tag-warning)]">未一致</span>
                  <button type="button" @click="removeHelperSpec(index)" class="text-[var(--color-tag-eol)] text-sm px-2">✕</button>
                </div>
              </div>
              <div class="spec-card-grid spec-card-grid--helper">
                <div class="spec-card-field">
                  <label class="spec-card-label">スペック詳細</label>
                  <div class="spec-type-picker">
                    <select v-model="spec.spec_type_id" @change="handleHelperSpecTypeSelection(spec)" class="input-text spec-card-control w-full">
                      <option value="">スペック詳細を選択</option>
                      <option v-for="st in specTypes" :key="`helper-type-${index}-${st.id}`" :value="st.id">@{{ specTypeOptionLabel(st) }}</option>
                    </select>
                  </div>
                  <div class="space-y-1">
                    <p v-if="spec.name" class="spec-card-help">データシート表記: @{{ spec.name }}</p>
                    <p v-if="spec.name_ja || spec.name_en || spec.symbol" class="spec-card-help">
                      候補: @{{ [spec.name_ja, spec.name_en].filter(Boolean).join(' / ') }}<span v-if="spec.symbol" class="font-mono ml-1 opacity-70" v-html="renderSymbol(spec.symbol)"></span>
                    </p>
                  </div>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">値種別</label>
                  <div class="spec-card-profile-select-wrap">
                    <select :value="spec.value_profile" @change="changeSpecProfile(spec, $event.target.value)"
                      class="input-text spec-card-control spec-card-profile-select w-full"
                      :title="specProfileHelpText(spec.value_profile)"
                      :aria-label="`値種別: ${specProfileControlLabel(spec.value_profile)}`">
                      <option v-for="option in specProfileOptions" :key="`helper-profile-${index}-${option.value}`" :value="option.value">
                        @{{ specProfileControlLabel(option.value) }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">値</label>
                  <label v-if="spec.value_profile === 'typ'" class="spec-card-subfield">
                    <span class="spec-card-subfield-label">TYP</span>
                    <input v-model="spec.value_typ" type="text" class="input-text spec-card-control w-full" placeholder="TYP" />
                  </label>
                  <label v-else-if="spec.value_profile === 'max_only'" class="spec-card-subfield">
                    <span class="spec-card-subfield-label">MAX</span>
                    <input v-model="spec.value_max" type="text" class="input-text spec-card-control w-full" placeholder="MAX" />
                  </label>
                  <label v-else-if="spec.value_profile === 'min_only'" class="spec-card-subfield">
                    <span class="spec-card-subfield-label">MIN</span>
                    <input v-model="spec.value_min" type="text" class="input-text spec-card-control w-full" placeholder="MIN" />
                  </label>
                  <div v-else-if="spec.value_profile === 'range'" class="spec-card-values--range">
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">MIN</span>
                      <input v-model="spec.value_min" type="text" class="input-text spec-card-control w-full" placeholder="MIN" />
                    </label>
                    <span class="text-xs opacity-50 pb-3">〜</span>
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">MAX</span>
                      <input v-model="spec.value_max" type="text" class="input-text spec-card-control w-full" placeholder="MAX" />
                    </label>
                  </div>
                  <div v-else class="spec-card-values--triple">
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">MIN</span>
                      <input v-model="spec.value_min" type="text" class="input-text spec-card-control w-full" placeholder="MIN" />
                    </label>
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">TYP</span>
                      <input v-model="spec.value_typ" type="text" class="input-text spec-card-control w-full" placeholder="TYP" />
                    </label>
                    <label class="spec-card-subfield">
                      <span class="spec-card-subfield-label">MAX</span>
                      <input v-model="spec.value_max" type="text" class="input-text spec-card-control w-full" placeholder="MAX" />
                    </label>
                  </div>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">単位</label>
                  <input v-model="spec.unit" type="text" class="input-text spec-card-control w-full"
                    :readonly="hasSpecBaseUnit(spec)"
                    :placeholder="hasSpecBaseUnit(spec) ? '' : '単位'"
                    :list="hasSpecBaseUnit(spec) ? null : `helper-spec-unit-${index}`" />
                  <datalist v-if="!hasSpecBaseUnit(spec)" :id="`helper-spec-unit-${index}`">
                    <option v-for="unitOption in getUnitSuggestions(spec.spec_type_id)" :key="`helper-${index}-${unitOption}`" :value="unitOption">@{{ unitOption }}</option>
                  </datalist>
                </div>
                <div class="spec-card-field">
                  <label class="spec-card-label">確認</label>
                  <div class="spec-card-preview spec-card-preview-panel text-[11px]">
                    <p class="text-sm font-semibold leading-tight break-words">
                      @{{ specDisplayName(spec) || 'スペック詳細を選択' }}
                      <span v-if="specProfileBadge(spec)" class="tag ml-1 text-[10px] align-middle">@{{ specProfileBadge(spec) }}</span>
                    </p>
                    <template v-if="specPreview(spec).hasNumeric">
                      <p class="opacity-75 break-words">入力値: @{{ specPreview(spec).recommendedText }}</p>
                      <p class="opacity-55 break-words">標準単位換算: @{{ specPreview(spec).canonicalText }}</p>
                    </template>
                    <p v-else class="opacity-50 break-words">数値として扱える場合は標準単位換算を表示します。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-xs opacity-40">スペック候補はまだありません</p>
        </section>
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between mt-6">
        <button type="button" @click="discardHelperResult" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
          候補を破棄
        </button>
        <div class="flex flex-col-reverse gap-2 sm:flex-row">
          <button type="button" @click="closeHelperResultModal" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
            後で確認する
          </button>
          <button type="button" @click="applyHelperResult" class="btn-primary px-4 py-2 rounded text-sm">
            選択した候補を適用
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- スペック詳細追加モーダル -->
  <div v-if="inlineSpecTypeModal.open" class="modal-overlay" style="z-index: 70" v-esc="closeInlineSpecTypeModal">
    <div class="modal-window modal-md p-6 max-h-[85vh] overflow-y-auto" @click.stop>
      <div class="flex items-center justify-between gap-4 mb-4">
        <h3 class="text-lg font-bold">スペック詳細を追加</h3>
        <button type="button" @click="closeInlineSpecTypeModal()" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100">✕</button>
      </div>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs font-semibold mb-1">日本語名 <span class="text-[var(--color-tag-eol)]">*</span></label>
          <input v-model="inlineSpecTypeModal.form.name_ja" type="text" class="input-text w-full" placeholder="例: コレクタ-ベース間電圧" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">英語名</label>
          <input v-model="inlineSpecTypeModal.form.name_en" type="text" class="input-text w-full" placeholder="例: Collector-Base Voltage" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">記号</label>
          <input v-model="inlineSpecTypeModal.form.symbol" type="text" class="input-text w-full font-mono" placeholder="例: V_CBO, h_FE, V_CE-(sat)" />
          <p class="text-xs opacity-50 mt-1">`_` は下付き、`~` は上付き、`-` は通常表示へ戻す区切りです。例: `V_CE-(sat)`。HTMLは入力しません。</p>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">別名・表記ゆれ</label>
          <textarea v-model="inlineSpecTypeModal.form.aliases_text" rows="3" class="input-text w-full" placeholder="1行に1つ。例: VCBO&#10;Collector Base Breakdown Voltage"></textarea>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">説明</label>
          <input v-model="inlineSpecTypeModal.form.description" type="text" class="input-text w-full" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">値の型</label>
          <select v-model="inlineSpecTypeModal.form.value_type" class="input-text w-full">
            <option value="numeric">数値</option>
            <option value="text">テキスト</option>
            <option value="boolean">真偽値</option>
          </select>
        </div>
        <div v-if="inlineSpecTypeModal.form.value_type === 'numeric'">
          <label class="block text-xs font-semibold mb-1">基準単位</label>
          <input v-model="inlineSpecTypeModal.form.unit" type="text" class="input-text w-full" placeholder="例: F / Ω / A" />
          <p class="text-xs opacity-50 mt-1">接頭語は下の入力候補接頭辞で選びます。不要なら空欄のまま保存します。</p>
        </div>
        <template v-if="inlineSpecTypeModal.form.value_type === 'numeric'">
          <div>
            <label class="block text-xs font-semibold mb-1">入力候補接頭辞</label>
            <p class="text-xs opacity-50 mb-2">@{{ inlinePrefixPolicyHelp }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="option in inlinePrefixOptionsFor()" :key="`inline-sp-${option.value || 'blank'}`"
                class="flex items-center gap-1 text-sm"
                :class="option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'">
                <input type="checkbox" :value="option.value" v-model="inlineSpecTypeModal.form.suggest_prefixes" :disabled="option.disabled" @change="syncInlinePrefixList('suggest_prefixes', option.value)" class="rounded" />
                <span class="font-mono">@{{ option.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">表示接頭辞</label>
            <p class="text-xs opacity-50 mb-2">値を人間向け表記へ逆変換するとき使う接頭辞。未選択なら大きさに応じて自動選択します。</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="option in inlinePrefixOptionsFor()" :key="`inline-dp-${option.value || 'blank'}`"
                class="flex items-center gap-1 text-sm"
                :class="option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'">
                <input type="checkbox" :value="option.value" v-model="inlineSpecTypeModal.form.display_prefixes" :disabled="option.disabled" @change="syncInlinePrefixList('display_prefixes', option.value)" class="rounded" />
                <span class="font-mono">@{{ option.label }}</span>
              </label>
            </div>
          </div>
        </template>
      </div>
      <div class="flex justify-end gap-3 mt-5">
        <button type="button" @click="closeInlineSpecTypeModal()" class="btn text-sm px-4 py-3 rounded border border-[var(--color-border)]">キャンセル</button>
        <button type="button" @click="saveInlineSpecType" :disabled="inlineSpecTypeModal.saving" class="btn btn-primary text-sm px-5 py-3 rounded disabled:opacity-40">
          @{{ inlineSpecTypeModal.saving ? '保存中...' : '保存して追加' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ChatGPT 貼り付けモーダル -->
  <div v-if="showChatGPTPaste"
    class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-6"
    v-esc="dismissChatGPTPaste"
    role="dialog"
    aria-modal="true"
    aria-labelledby="chatgpt-paste-title">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-3xl overflow-hidden rounded-3xl border border-[var(--color-border)] bg-[var(--color-bg)] shadow-2xl" @click.stop>
      <div class="flex items-start justify-between gap-4 border-b border-[var(--color-border)] px-5 py-4">
        <div>
          <h2 id="chatgpt-paste-title" class="text-lg font-bold">ChatGPT の出力を貼り付け</h2>
          <p class="mt-1 text-xs opacity-60">ChatGPT にデータシート PDF を添付し、`プロンプト/データシート解析プロンプト.md` の指示で返ってきた JSON をここに貼り付けてください。</p>
        </div>
        <button type="button" @click="dismissChatGPTPaste" aria-label="閉じる" title="閉じる" class="text-xl opacity-50 hover:opacity-100 leading-none">✕</button>
      </div>
      <div class="space-y-4 px-5 py-5">
        <textarea
          ref="chatGPTPasteTextarea"
          v-model="chatGPTPasteText"
          rows="12"
          class="input-text w-full resize-y text-xs font-mono min-h-72"
          placeholder='{"part_number": "...", "manufacturer": "...", "specs": [...]}'>
        </textarea>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
          <button type="button" @click="dismissChatGPTPaste" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
            キャンセル
          </button>
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
            <button type="button" @click="chatGPTPasteText = ''" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
              内容をクリア
            </button>
            <button type="button" @click="parseChatGPTResult" :disabled="!chatGPTPasteText.trim()" class="btn-primary px-4 py-2 rounded text-sm disabled:opacity-50">
              解析して適用候補に表示
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
