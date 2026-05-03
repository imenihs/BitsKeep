  <!-- ═══════════════ パッケージ詳細モーダル ════════════════ -->
  <div v-if="pkgModal.open" class="modal-overlay" v-esc="closePkgModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgModal.isEdit ? 'パッケージ詳細編集' : 'パッケージ詳細追加' }}</h2>
        <button type="button" @click="closePkgModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">パッケージ分類 <span class="text-red-500">*</span></label>
          <select v-model="pkgModal.form.package_group_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="">選択してください</option>
            <option v-for="group in activePackageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="pkgModal.form.name" type="text" placeholder="例: 0402, SOT-23"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="pkgModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">外形寸法（mm）</label>
          <p class="text-xs opacity-60 mb-2">部品本体の寸法です。X は縦または長手方向、Y は横または幅、Z は実装高さを入力します。</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="block">
              <span class="block text-xs font-medium mb-1 opacity-70">X: 縦・長手方向</span>
              <input v-model="pkgModal.form.size_x" type="number" min="0" step="0.0001" placeholder="例: 1.6"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <span class="block text-xs font-medium mb-1 opacity-70">Y: 横・幅</span>
              <input v-model="pkgModal.form.size_y" type="number" min="0" step="0.0001" placeholder="例: 0.8"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <span class="block text-xs font-medium mb-1 opacity-70">Z: 高さ</span>
              <input v-model="pkgModal.form.size_z" type="number" min="0" step="0.0001" placeholder="例: 0.55"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            </label>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-sm font-medium mb-1">外観画像</span>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="onPackageFileChange('image', $event)"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            <span v-if="pkgModal.form.image_url" class="mt-2 flex items-center gap-2 text-xs opacity-70">
              <img :src="pkgModal.form.image_url" alt="" class="w-12 h-12 rounded border border-[var(--color-border)] bg-[var(--color-bg)] object-contain" />
              <span>登録済み画像</span>
            </span>
          </label>
          <label class="block">
            <span class="block text-sm font-medium mb-1">寸法図PDF</span>
            <input type="file" accept="application/pdf" @change="onPackageFileChange('pdf', $event)"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
            <a v-if="pkgModal.form.pdf_url" :href="pkgModal.form.pdf_url" target="_blank" rel="noopener" title="登録済みPDF" class="inline-flex mt-2 text-xs tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">PDF</a>
          </label>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closePkgModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackage" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>
  <div v-if="pkgGroupModal.open" class="modal-overlay" v-esc="closePkgGroupModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgGroupModal.isEdit ? 'パッケージ分類編集' : 'パッケージ分類追加' }}</h2>
        <button type="button" @click="closePkgGroupModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="pkgGroupModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="pkgGroupModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closePkgGroupModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackageGroup" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ 部品分類モーダル（互換） ════════════════ -->
  <div v-if="specGroupModal.open" class="modal-overlay" v-esc="closeSpecGroupModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ specGroupModal.isEdit ? '部品分類編集' : '部品分類追加' }}</h2>
        <button type="button" @click="closeSpecGroupModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="specGroupModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="specGroupModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">部品シリーズの扱い</label>
          <select v-model="specGroupModal.form.series_management_mode" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="single">シリーズを使わない</option>
            <option value="series_optional">シリーズ登録も使う</option>
            <option value="series_recommended">シリーズ登録を推奨</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeSpecGroupModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveSpecGroup" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ 入力テンプレートモーダル ════════════════ -->
  <div v-if="templateModal.open" class="modal-overlay modal-top" v-esc="closeTemplateModal">
    <div class="modal-window modal-master max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ templateModal.isEdit ? '入力テンプレート編集' : '入力テンプレート追加' }}</h2>
        <button type="button" @click="closeTemplateModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">関連する部品分類</label>
          <select v-model="templateModal.form.spec_group_id" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="">関連する部品分類なし</option>
            <option v-for="group in activeSpecGroups" :key="`template-group-${group.id}`" :value="group.id">@{{ group.name }}</option>
          </select>
          <p class="mt-1 text-xs opacity-60">スペック詳細は、ここで選んだ部品分類の候補スペック詳細だけを表示します。</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="templateModal.form.name" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="templateModal.form.description" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex items-center justify-between">
            <div class="font-semibold text-sm">作成する入力行</div>
            <button @click="addTemplateItem" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)]">行追加</button>
          </div>
          <div class="divide-y divide-[var(--color-border)]">
            <div v-for="(item, index) in templateModal.form.items" :key="`template-modal-item-${index}`"
              draggable="true"
              @dragstart="templateItemDnD.start(index)"
              @dragover="templateItemDnD.over($event, index)"
              @dragend="templateItemDnD.end()"
              @drop.prevent="templateItemDnD.drop(index)"
              :class="dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''"
              class="grid grid-cols-1 lg:grid-cols-[3rem_minmax(18rem,2fr)_9rem_8rem_6rem_minmax(12rem,1fr)_5.5rem] gap-2 px-4 py-3 items-end transition-colors">
              <div class="pb-2 text-center">
                <span class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none" title="ドラッグして並び替え">⠿</span>
              </div>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">スペック詳細</span>
                <select v-model="item.spec_type_id" :disabled="!templateModal.form.spec_group_id || templateSpecGroupLoading" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm disabled:opacity-50">
                  <option value="">@{{ templateModal.form.spec_group_id ? (templateSpecGroupLoading ? '候補を読込中...' : '選択してください') : '先に関連する部品分類を選択' }}</option>
                  <option v-for="st in templateSpecTypeOptionsForItem(item)" :key="`template-spec-type-${index}-${st.id}`" :value="st.id">@{{ templateSpecTypeOptionLabel(st) }}</option>
                </select>
                <span v-if="templateModal.form.spec_group_id && !templateSpecGroupLoading && templateSpecTypeOptions.length === 0" class="block mt-1 text-[11px] opacity-50">この部品分類には候補スペック詳細がありません</span>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">入力形式</span>
                <select v-model="item.default_profile" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm">
                  <option value="typ">TYP</option>
                  <option value="range">MIN..MAX</option>
                  <option value="max_only">MAX</option>
                  <option value="min_only">MIN</option>
                  <option value="triple">MIN/TYP/MAX</option>
                </select>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">単位</span>
                <input v-model="item.default_unit" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm" />
              </label>
              <label class="flex items-center gap-2 text-sm pb-2">
                <input v-model="item.is_required" type="checkbox" />
                <span>必須</span>
              </label>
              <label class="block">
                <span class="block text-xs opacity-60 mb-1">メモ</span>
                <input v-model="item.note" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-2 text-sm" />
              </label>
              <button @click="removeTemplateItem(index)" class="w-20 px-2 py-2 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">行を外す</button>
            </div>
            <div v-if="templateModal.form.items.length === 0" class="px-4 py-8 text-center text-sm opacity-40">項目がありません</div>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeTemplateModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveTemplate" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ 候補スペック詳細モーダル ════════════════ -->
  <div v-if="candidateSettingModal.open" class="modal-overlay" v-esc="closeCandidateSettingModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">候補設定編集</h2>
        <button type="button" @click="closeCandidateSettingModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div v-if="candidateSettingMember" class="rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
          <div class="text-sm font-semibold">
            <span>@{{ candidateSettingMember.name_ja || candidateSettingMember.name }}</span>
            <span v-if="candidateSettingMember.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(candidateSettingMember.symbol)"></span>
          </div>
          <div class="mt-2 text-xs opacity-70">範囲: @{{ candidateMemberTypeLabel(candidateSettingMember) }}</div>
        </div>
        <label class="block">
          <span class="block text-sm font-medium mb-1">扱い</span>
          <select v-model="candidateSettingModal.form.state" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="required">必須</option>
            <option value="recommended">推奨</option>
            <option value="optional">任意</option>
          </select>
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-sm font-medium mb-1">既定の入力形式</span>
            <select v-model="candidateSettingModal.form.default_profile" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
              <option value="">未指定</option>
              <option value="typ">TYP</option>
              <option value="range">MIN..MAX</option>
              <option value="max_only">MAX</option>
              <option value="min_only">MIN</option>
              <option value="triple">MIN/TYP/MAX</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-sm font-medium mb-1">既定単位</span>
            <input v-model="candidateSettingModal.form.default_unit" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </label>
        </div>
        <label class="block">
          <span class="block text-sm font-medium mb-1">メモ</span>
          <input v-model="candidateSettingModal.form.note" type="text" class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </label>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeCandidateSettingModal" :disabled="specGroupMemberSaving" class="px-4 py-2 border border-[var(--color-border)] rounded disabled:opacity-40">キャンセル</button>
        <button @click="saveCandidateSetting" :disabled="specGroupMemberSaving" class="btn-primary px-4 py-2 rounded font-medium disabled:opacity-40">保存</button>
      </div>
    </div>
  </div>

  <div v-if="candidateAddModal.open" class="modal-overlay" v-esc="closeCandidateAddModal">
    <div class="modal-window modal-master max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ candidateAddTitle }}</h2>
        <button type="button" @click="closeCandidateAddModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6">
        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 px-3">名前</th>
                <th class="py-2 pr-4">範囲</th>
                <th class="py-2 pr-4">単位</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in candidateAddOptions" :key="`candidate-add-${candidateAddModal.mode}-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)]">
                <td class="py-2 px-3 font-medium">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">
                  <span class="tag border border-[var(--color-border)]" :title="candidateMemberTypeTitle(s)">@{{ candidateMemberTypeLabel(s) }}</span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ isToleranceSpecType(s) ? toleranceUnit(s) : (s.units?.[0]?.unit || s.base_unit || '-') }}</td>
                <td class="py-2">
                  <button @click="addCandidateFromOption(s)" :disabled="specGroupMemberSaving" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40">追加</button>
                </td>
              </tr>
              <tr v-if="candidateAddOptions.length === 0">
                <td colspan="4" class="py-8 text-center opacity-40">@{{ candidateAddEmptyMessage }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeCandidateAddModal" class="px-4 py-2 border border-[var(--color-border)] rounded">閉じる</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ スペック詳細モーダル ════════════════ -->
  <div v-if="stModal.open" class="modal-overlay modal-top" v-esc="closeStModal">
    <div class="modal-window modal-md max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">
          @{{ stModalTitle }}
        </h2>
        <button type="button" @click="closeStModal" aria-label="閉じる" title="閉じる" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">日本語名 <span class="text-red-500">*</span></label>
          <input v-model="stModal.form.name_ja" type="text" placeholder="例: コレクタ-ベース間電圧"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">英語名</label>
          <input v-model="stModal.form.name_en" type="text" placeholder="例: Collector-Base Voltage"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">記号</label>
          <input v-model="stModal.form.symbol" type="text" placeholder="例: V_CBO, h_FE, V_CE-(sat)"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm font-mono" />
          <p class="text-xs opacity-50 mt-1">`_` は下付き、`~` は上付き、`-` は通常表示へ戻す区切りです。例: `V_CE-(sat)`。HTMLは入力しません。</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">別名・表記ゆれ</label>
          <textarea v-model="stModal.form.aliases_text" rows="3" placeholder="1行に1つ。例: VCBO&#10;Collector Base Breakdown Voltage"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="stModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div v-if="stModal.form.spec_kind !== 'tolerance'">
          <label class="block text-sm font-medium mb-1">値の型</label>
          <select v-model="stModal.form.value_type"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
            <option value="numeric">数値</option>
            <option value="text">テキスト</option>
            <option value="boolean">真偽値</option>
          </select>
        </div>

        <!-- 単位（数値型のみ） -->
        <div v-if="stModal.form.spec_kind !== 'tolerance' && stModal.form.value_type === 'numeric'">
          <label class="text-sm font-medium block mb-2">単位</label>
          <input v-model="stModal.form.unit" type="text" placeholder="例: μF"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          <p class="text-xs opacity-50 mt-1">不要なら空欄のまま保存します。</p>
        </div>

        <!-- 接頭辞ポリシー（数値型かつ単位あり） -->
        <template v-if="stModal.form.spec_kind !== 'tolerance' && stModal.form.value_type === 'numeric' && stModal.form.unit">
          <div>
            <label class="text-sm font-medium block mb-1">入力候補接頭辞</label>
            <p class="text-xs opacity-50 mb-2">@{{ prefixPolicyHelp }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="option in prefixOptionsFor()" :key="`sp-${option.value || 'blank'}`"
                class="flex items-center gap-1 text-sm"
                :class="option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'">
                <input type="checkbox" :value="option.value" v-model="stModal.form.suggest_prefixes" :disabled="option.disabled" @change="syncPrefixList('suggest_prefixes', option.value)" class="rounded" />
                <span class="font-mono">@{{ option.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1">表示接頭辞</label>
            <p class="text-xs opacity-50 mb-2">値を人間向け表記へ逆変換するとき使う接頭辞。未選択なら大きさに応じて自動選択します。</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="option in prefixOptionsFor()" :key="`dp-${option.value || 'blank'}`"
                class="flex items-center gap-1 text-sm"
                :class="option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'">
                <input type="checkbox" :value="option.value" v-model="stModal.form.display_prefixes" :disabled="option.disabled" @change="syncPrefixList('display_prefixes', option.value)" class="rounded" />
                <span class="font-mono">@{{ option.label }}</span>
              </label>
            </div>
          </div>
        </template>

        <template v-if="stModal.form.spec_kind === 'tolerance'">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">許容差の単位</label>
              <select v-model="stModal.form.tolerance_settings.default_unit"
                @change="stModal.form.unit = stModal.form.tolerance_settings.default_unit"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
                <option v-for="unit in toleranceUnitOptions" :key="`tol-default-${unit.value}`" :value="unit.value">@{{ unit.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">既定入力形式</label>
              <select v-model="stModal.form.tolerance_settings.default_mode"
                class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
                <option value="symmetric">± 対称</option>
                <option value="asymmetric">+/- 非対称</option>
                <option value="grade">ランク</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1">使用する単位</label>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <label v-for="unit in toleranceUnitOptions" :key="`tol-unit-${unit.value}`" class="flex items-center gap-1 text-sm cursor-pointer">
                <input type="checkbox" :value="unit.value" v-model="stModal.form.tolerance_settings.allowed_units" class="rounded" />
                <span class="font-mono">@{{ unit.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ランク定義</label>
            <textarea v-model="stModal.form.tolerance_settings.grade_options_text" rows="4" placeholder="例: B: ±0.1pF&#10;F: ±1%&#10;X7R: -55〜125℃ / ±15%"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm"></textarea>
            <p class="text-xs opacity-50 mt-1">保存時にランクコードと許容差値の配列へ変換します。対称値は `F: ±1%` や `B: ±0.1pF`、非対称値は `Z: +80/-20%`、コード値は `X7R: -55〜125℃ / ±15%` の形式で入力します。</p>
          </div>
        </template>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="closeStModal" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveSpecType" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>
