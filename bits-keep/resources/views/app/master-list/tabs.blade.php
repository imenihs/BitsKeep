  <!-- ════════════════════════ パッケージ分類タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'package-groups'">
    <div class="flex justify-end mb-4">
      @if ($canEdit)
      <button @click="openPkgGroupAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span> + パッケージ分類を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">編</span><span>+ パッケージ分類を追加</span></div>
        <div class="mt-1 text-xs opacity-70">閲覧者のため追加できません</div>
      </div>
      @endif
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-2 w-6"></th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(group, index) in activePackageGroups" :key="group.id"
          :draggable="canEdit && !group.deleted_at ? 'true' : 'false'"
          @dragstart="pgDnD.start(index)"
          @dragover="pgDnD.over($event, index)"
          @dragend="pgDnD.end()"
          @drop.prevent="pgDnD.drop(index)"
          :class="[
            index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="canEdit && !group.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ group.name }}</span>
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ group.description || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ group.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openPkgGroupEdit(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="openPkgGroupDuplicate(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
              <button @click="archivePackageGroup(group)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
            </div>
          </td>
        </tr>
        <tr v-if="activePackageGroups.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">パッケージ分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
    <section v-if="archivedPackageGroups.length" class="mt-6">
      <h2 class="font-bold mb-3">アーカイブ</h2>
      <table class="w-full text-sm border-collapse">
        <thead>
          <tr class="border-b border-[var(--color-border)] text-left opacity-70">
            <th class="py-2 pr-2 w-6"></th>
            <th class="py-2 pr-4">名前</th>
            <th class="py-2 pr-4">説明</th>
            <th class="py-2">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(group, index) in archivedPackageGroups" :key="`archived-group-${group.id}`"
            :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2"></td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <span>@{{ group.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ group.description || '-' }}</div>
              <div class="mt-1 opacity-60">使用件数: @{{ group.usage_count ?? 0 }}</div>
            </td>
            <td class="py-2">
              <button @click="restorePackageGroup(group)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
  <div v-if="activeTab === 'packages'" class="grid grid-cols-1 lg:grid-cols-[264px_minmax(0,1fr)] gap-4">
    <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
      <div class="px-4 py-3 border-b border-[var(--color-border)]">
        <div class="font-semibold text-sm">パッケージ分類</div>
        <div class="text-xs opacity-60 mt-1">パッケージ分類を選ぶと、その中だけを並び替えます</div>
      </div>
      <div class="master-scroll max-h-[60vh] overflow-y-auto">
        <button v-for="group in activePackageGroups" :key="`pkg-group-select-${group.id}`"
          @click="selectPackageGroup(group)"
          :class="Number(selectedPackageGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
          class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
          <span class="block font-medium">@{{ group.name }}</span>
          <span class="block text-xs opacity-70 mt-1">@{{ group.usage_count ?? 0 }} 件</span>
        </button>
        <div v-if="activePackageGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">パッケージ分類がありません</div>
      </div>
    </aside>

    <section class="min-w-0">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <h2 class="font-bold">@{{ currentPackageGroup?.name || 'パッケージ詳細' }}</h2>
          <p class="text-xs opacity-60 mt-1">@{{ currentPackageGroup?.description || '左のパッケージ分類を選択してください' }}</p>
        </div>
        @if ($canEdit)
        <button @click="openPkgAdd" class="btn-primary inline-flex items-center gap-2 whitespace-nowrap px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">編</span><span>+ パッケージ詳細を追加</span></button>
        @else
        <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
          <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">編</span><span>+ パッケージ詳細を追加</span></div>
          <div class="mt-1 text-xs opacity-70">閲覧者のため追加できません</div>
        </div>
        @endif
      </div>

      <div class="overflow-x-auto">
      <table class="w-full min-w-[820px] text-sm border-collapse">
        <colgroup>
          <col class="w-7">
          <col class="w-[22%]">
          <col class="w-[18%]">
          <col>
          <col class="w-48">
        </colgroup>
        <thead>
          <tr class="border-b border-[var(--color-border)] text-left opacity-70">
            <th class="py-2 pr-2 w-6"></th>
            <th class="py-2 pr-4">名前</th>
            <th class="py-2 pr-4">寸法 / 資料</th>
            <th class="py-2 pr-4">説明</th>
            <th class="py-2 text-right">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(p, index) in activePackages" :key="p.id"
            :draggable="canEdit && !p.deleted_at ? 'true' : 'false'"
            @dragstart="pkgDnD.start(index)"
            @dragover="pkgDnD.over($event, index)"
            @dragend="pkgDnD.end()"
            @drop.prevent="pkgDnD.drop(index)"
            :class="[
              index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
              dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
            ]"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2 text-center">
              <span v-if="canEdit && !p.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
            </td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <img v-if="p.image_url" :src="p.image_url" alt="" class="w-10 h-10 rounded border border-[var(--color-border)] bg-[var(--color-bg)] object-contain" />
                <span>@{{ p.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 text-xs opacity-70">
              <div>@{{ packageDimensions(p) }}</div>
              <div class="mt-1 flex flex-wrap gap-1">
                <span v-if="p.image_url" class="tag tag-ok">画像</span>
                <a v-if="p.pdf_url" :href="p.pdf_url" target="_blank" rel="noopener" class="tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">PDF</a>
                <span v-if="!p.image_url && !p.pdf_url" class="opacity-40">資料なし</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ p.description || '-' }}</div>
              <div class="mt-1 opacity-60">使用件数: @{{ p.usage_count ?? 0 }}</div>
            </td>
            <td class="py-2">
              @if ($canEdit)
              <div class="flex flex-nowrap justify-end gap-2 whitespace-nowrap">
                <button @click="openPkgEdit(p)" class="shrink-0 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                <button @click="openPkgDuplicate(p)" class="shrink-0 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                <button @click="archivePackage(p)" class="shrink-0 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
              </div>
              @else
              <span class="text-xs opacity-40">-</span>
              @endif
            </td>
          </tr>
          <tr v-if="currentPackageGroup && activePackages.length === 0">
            <td colspan="5" class="py-8 text-center opacity-40">このパッケージ分類にはパッケージ詳細が登録されていません</td>
          </tr>
          <tr v-if="!currentPackageGroup">
            <td colspan="5" class="py-8 text-center opacity-40">左のパッケージ分類を選択してください</td>
          </tr>
        </tbody>
      </table>
      </div>

      <section v-if="archivedPackages.length" class="mt-6">
        <h2 class="font-bold mb-3">アーカイブ</h2>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm border-collapse">
          <colgroup>
            <col class="w-7">
            <col class="w-[22%]">
            <col class="w-[18%]">
            <col>
            <col class="w-32">
          </colgroup>
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 pr-2 w-6"></th>
              <th class="py-2 pr-4">名前</th>
              <th class="py-2 pr-4">寸法 / 資料</th>
              <th class="py-2 pr-4">説明</th>
              <th class="py-2 text-right">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, index) in archivedPackages" :key="`archived-package-${p.id}`"
              :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)] transition-colors">
              <td class="py-2 pr-2"></td>
              <td class="py-2 pr-4 font-medium">
                <div class="flex items-center gap-2">
                  <img v-if="p.image_url" :src="p.image_url" alt="" class="w-10 h-10 rounded border border-[var(--color-border)] bg-[var(--color-bg)] object-contain" />
                  <span>@{{ p.name }}</span>
                </div>
              </td>
              <td class="py-2 pr-4 text-xs opacity-70">
                <div>@{{ packageDimensions(p) }}</div>
                <div class="mt-1 flex flex-wrap gap-1">
                  <span v-if="p.image_url" class="tag tag-ok">画像</span>
                  <a v-if="p.pdf_url" :href="p.pdf_url" target="_blank" rel="noopener" class="tag border border-[var(--color-border)] hover:border-[var(--color-primary)]">PDF</a>
                  <span v-if="!p.image_url && !p.pdf_url" class="opacity-40">資料なし</span>
                </div>
              </td>
              <td class="py-2 pr-4 opacity-70 text-xs">
                <div>@{{ p.description || '-' }}</div>
                <div class="mt-1 opacity-60">使用件数: @{{ p.usage_count ?? 0 }}</div>
              </td>
              <td class="py-2 text-right">
                @if ($canEdit)
                <button @click="restorePackage(p)" class="shrink-0 px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </section>
    </section>
  </div>

  <!-- ══════════════════════════ 部品分類タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'part-categories'">
    <div class="flex justify-end mb-4">
      @if ($isAdmin)
      <button @click="openSgAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 部品分類を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 部品分類を追加</span></div>
        <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
      </div>
      @endif
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-2 w-6"></th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(group, index) in activeSpecGroups" :key="group.id"
          :draggable="isAdmin && !group.deleted_at ? 'true' : 'false'"
          @dragstart="sgDnD.start(index)"
          @dragover="sgDnD.over($event, index)"
          @dragend="sgDnD.end()"
          @drop.prevent="sgDnD.drop(index)"
          :class="[
            index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
            dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
          ]"
          class="border-b border-[var(--color-border)] transition-colors">
          <td class="py-2 pr-2 text-center">
            <span v-if="isAdmin && !group.deleted_at" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
          </td>
          <td class="py-2 pr-4 font-medium">
            <div class="flex items-center gap-2">
              <span>@{{ group.name }}</span>
            </div>
          </td>
          <td class="py-2 pr-4 opacity-70 text-xs">
            <div>@{{ group.description || '-' }}</div>
            <div class="mt-1 opacity-60">入力候補: @{{ group.usage_count ?? 0 }}件 / テンプレート: @{{ group.template_count ?? 0 }}件 / 部品シリーズ: @{{ group.series_count ?? 0 }}件</div>
            <div v-if="specGroupSeriesModeLabel(group)" class="mt-1">
              <span class="tag text-[10px]"
                :class="group.series_management_mode === 'series_recommended' ? 'tag-warning' : ''">
                @{{ specGroupSeriesModeLabel(group) }}
              </span>
            </div>
          </td>
          <td class="py-2">
            <div class="flex gap-2 flex-wrap">
              <button @click="openSgEdit(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="openSgDuplicate(group)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
              <button @click="archiveSpecGroup(group)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
            </div>
          </td>
        </tr>
        <tr v-if="activeSpecGroups.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">部品分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
    <section v-if="archivedSpecGroups.length" class="mt-6">
      <h2 class="font-bold mb-3">アーカイブ</h2>
      <table class="w-full text-sm border-collapse">
        <thead>
          <tr class="border-b border-[var(--color-border)] text-left opacity-70">
            <th class="py-2 pr-2 w-6"></th>
            <th class="py-2 pr-4">名前</th>
            <th class="py-2 pr-4">説明</th>
            <th class="py-2">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(group, index) in archivedSpecGroups" :key="`archived-spec-group-${group.id}`"
            :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] transition-colors">
            <td class="py-2 pr-2"></td>
            <td class="py-2 pr-4 font-medium">
              <div class="flex items-center gap-2">
                <span>@{{ group.name }}</span>
              </div>
            </td>
            <td class="py-2 pr-4 opacity-70 text-xs">
              <div>@{{ group.description || '-' }}</div>
              <div class="mt-1 opacity-60">入力候補: @{{ group.usage_count ?? 0 }}件 / テンプレート: @{{ group.template_count ?? 0 }}件 / 部品シリーズ: @{{ group.series_count ?? 0 }}件</div>
            </td>
            <td class="py-2">
              <button @click="restoreSpecGroup(group)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>

  <!-- ══════════════════════════ スペック詳細タブ ═══════════════════════════ -->
  <div v-if="['spec-types','spec-candidates','spec-templates','common-spec-types','tolerance-spec-types'].includes(activeTab)" class="space-y-6">
    <section v-if="['spec-types','spec-candidates','spec-templates'].includes(activeTab)" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)]">
          <div class="font-semibold text-sm">部品分類</div>
          <div class="text-xs opacity-60 mt-1">@{{ activeTab === 'spec-types' ? 'スペック詳細を持たせる部品分類を選択' : (activeTab === 'spec-templates' ? '入力テンプレートを管理する部品分類を選択' : '候補スペック詳細を整理する部品分類を選択') }}</div>
        </div>
        <div class="max-h-[55vh] overflow-y-auto">
          <button v-for="group in activeSpecGroups" :key="`spec-type-group-select-${group.id}`"
            @click="selectSpecGroup(group)"
            :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
            class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
            <span class="flex items-baseline justify-between gap-2">
              <span class="min-w-0 truncate font-medium">@{{ group.name }}</span>
              <span class="shrink-0 text-[11px] font-normal opacity-60">@{{ specGroupSidebarMeta(group) }}</span>
            </span>
          </button>
          <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">部品分類がありません</div>
        </div>
      </aside>

      <section v-if="currentSpecGroup" class="space-y-6">
        <div v-if="activeTab === 'spec-types'" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="font-semibold text-sm">@{{ currentSpecGroup.name }} のスペック詳細</div>
              <div class="text-xs opacity-60 mt-1">左で選んだ部品分類に持たせるスペック詳細を追加・編集・アーカイブします。</div>
              <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                <span class="tag border border-[var(--color-border)]">個別 @{{ activeSpecTypes.length }} 件</span>
                <span v-if="archivedSpecTypes.length" class="tag border border-[var(--color-border)] opacity-70">アーカイブ @{{ archivedSpecTypes.length }} 件</span>
              </div>
            </div>
            @if ($isAdmin)
            <button @click="openLocalSpecTypeAdd" :disabled="specGroupDetailLoading" class="btn-primary px-3 py-2 rounded text-xs font-medium disabled:opacity-40"><span class="feature-lock">管</span> + スペック詳細を追加</button>
            @endif
          </div>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 px-3">名前</th>
                <th class="py-2 pr-4">記号</th>
                <th class="py-2 pr-4">単位</th>
                <th class="py-2 pr-4">使用</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in activeSpecTypes" :key="`spec-type-row-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 px-3 font-medium">@{{ s.name_ja || s.name }}</td>
                <td class="py-2 pr-4 text-xs font-mono">
                  <span v-if="s.symbol" v-html="renderSymbol(s.symbol)"></span>
                  <span v-else class="opacity-40">-</span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ s.units?.[0]?.unit || s.base_unit || '-' }}</td>
                <td class="py-2 pr-4 text-xs opacity-70">使用件数: @{{ s.usage_count ?? 0 }}</td>
                <td class="py-2">
                  @if ($isAdmin)
                  <div class="flex gap-2 flex-wrap">
                    <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                    <button @click="openStDuplicate(s, { spec_scope: 'group_local', owner_spec_group_id: currentSpecGroup.id })" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                    <button @click="archiveSpecType(s)" class="w-20 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">アーカイブ</button>
                  </div>
                  @else
                  <span class="text-xs opacity-40">-</span>
                  @endif
                </td>
              </tr>
              <tr v-if="activeSpecTypes.length === 0">
                <td colspan="5" class="py-8 text-center opacity-40">この部品分類に持たせるスペック詳細はまだありません</td>
              </tr>
            </tbody>
          </table>
          <section v-if="archivedSpecTypes.length" class="px-4 py-4 border-t border-[var(--color-border)]">
            <h2 class="font-bold mb-3">アーカイブ</h2>
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                  <th class="py-2 pr-4">名前</th>
                  <th class="py-2 pr-4">単位</th>
                  <th class="py-2">操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(s, index) in archivedSpecTypes" :key="`archived-local-spec-${s.id}`"
                  :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                  class="border-b border-[var(--color-border)] transition-colors">
                  <td class="py-2 pr-4 font-medium">
                    <span>@{{ s.name_ja || s.name }}</span>
                    <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                  </td>
                  <td class="py-2 pr-4 text-xs">@{{ s.units?.[0]?.unit || s.base_unit || '-' }}</td>
                  <td class="py-2">
                    <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>

        <div v-if="activeTab === 'spec-candidates'" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="font-semibold text-sm">@{{ currentSpecGroup.name }} のスペック候補設定</div>
              <div class="text-xs opacity-60 mt-1">この部品分類を選んだときに候補へ出すスペック詳細と、表示順・扱い・既定値を管理します</div>
              <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                <span class="tag border border-[var(--color-border)]">入力候補 @{{ currentSpecGroupCounts.total }} 件</span>
                <span class="tag border border-[var(--color-border)]">個別 @{{ currentSpecGroupCounts.local }} 件</span>
                <span class="tag border border-[var(--color-border)]">共通 @{{ currentSpecGroupCounts.common }} 件</span>
                <span class="tag border border-[var(--color-border)]">許容差 @{{ currentSpecGroupCounts.tolerance }} 件</span>
              </div>
            </div>
            @if ($isAdmin)
            <div class="flex items-center gap-2 flex-wrap">
              <button @click="openCandidateAddModal('local')" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)] disabled:opacity-40">スペック詳細から追加</button>
              <button @click="openCandidateAddModal('common')" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)] disabled:opacity-40">共通スペック詳細から追加</button>
              <button @click="openCandidateAddModal('tolerance')" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="px-3 py-2 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-even)] disabled:opacity-40">許容差スペック詳細から追加</button>
              <span v-if="specGroupMemberSaving" class="text-xs px-2 py-1 rounded border border-[var(--color-border)] opacity-70">保存中...</span>
            </div>
            @endif
          </div>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 pr-2 w-6"></th>
                <th class="py-2 pr-4">スペック詳細</th>
                <th class="py-2 pr-4">範囲</th>
                <th class="py-2 pr-4">扱い</th>
                <th class="py-2 pr-4">既定</th>
                <th class="py-2 pr-4">メモ</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(member, index) in currentSpecGroup.spec_types" :key="`sg-member-${member.id}`"
                :draggable="isAdmin && !specGroupMemberSaving ? 'true' : 'false'"
                @dragstart="candidateMemberDnD.start(index)"
                @dragover="candidateMemberDnD.over($event, index)"
                @dragend="candidateMemberDnD.end()"
                @drop.prevent="candidateMemberDnD.drop(index)"
                :class="[
                  index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]',
                  dragTarget === index && dragSrc !== index ? 'outline outline-2 outline-[var(--color-primary)] outline-offset-[-2px]' : ''
                ]"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 pr-2 text-center">
                  @if ($isAdmin)
                  <span v-if="!specGroupMemberSaving" class="cursor-grab text-lg opacity-30 hover:opacity-70 select-none">⠿</span>
                  @endif
                </td>
                <td class="py-2 pr-4 font-medium">
                  <span>@{{ member.name_ja || member.name }}</span>
                  <span v-if="member.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(member.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">
                  <span class="tag border border-[var(--color-border)]" :title="candidateMemberTypeTitle(member)">@{{ candidateMemberTypeLabel(member) }}</span>
                </td>
                <td class="py-2 pr-4 text-xs">
                  @{{ memberStateLabel(member) }}
                </td>
                <td class="py-2 pr-4 text-xs">
                  @{{ memberDefaultLabel(member) }}
                </td>
                <td class="py-2 pr-4 text-xs">
                  <span v-if="member.pivot?.note">@{{ member.pivot.note }}</span>
                  <span v-else class="opacity-40">-</span>
                </td>
                <td class="py-2">
                  @if ($isAdmin)
                  <div class="flex gap-2 flex-wrap">
                    <button @click="openCandidateSettingEdit(member, index)" :disabled="specGroupMemberSaving" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40">編集</button>
                    <button @click="confirmRemoveSpecGroupMember(member)" :disabled="specGroupMemberSaving" class="w-28 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center disabled:opacity-40">候補から外す</button>
                  </div>
                  @else
                  <span class="text-xs opacity-40">-</span>
                  @endif
                </td>
              </tr>
              <tr v-if="!currentSpecGroup.spec_types?.length">
                <td colspan="7" class="py-8 text-center opacity-40">候補スペック詳細がありません</td>
              </tr>
            </tbody>
          </table>
        </div>

        <section v-if="activeTab === 'spec-templates'" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="font-semibold text-sm">入力テンプレート</div>
              <div class="text-xs opacity-60 mt-1">@{{ currentSpecGroup.name }} の部品登録時に、スペック行をまとめて追加する初期行セット</div>
              <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                <span class="tag border border-[var(--color-border)]">テンプレート @{{ currentSpecGroupCounts.templates }} 件</span>
                <span class="tag border border-[var(--color-border)]">行 @{{ currentSpecGroupCounts.templateItems }} 件</span>
              </div>
            </div>
            @if ($isAdmin)
            <button @click="openTemplateAdd" :disabled="specGroupDetailLoading" class="btn-primary px-3 py-2 rounded text-xs font-medium disabled:opacity-40">入力テンプレート追加</button>
            @endif
          </div>
          <div class="divide-y divide-[var(--color-border)]">
            <article v-for="template in currentSpecGroup.templates" :key="`spec-template-${template.id}`" class="px-4 py-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold text-sm">@{{ template.name }}</h3>
                  <p class="text-xs opacity-60 mt-1">@{{ template.description || '説明なし' }}</p>
                  <div class="mt-2 flex flex-wrap gap-1">
                    <span v-for="item in template.items" :key="`template-item-chip-${template.id}-${item.id}`" class="inline-flex flex-wrap items-center gap-1">
                      <span class="tag border border-[var(--color-border)]">
                        <span>@{{ item.spec_type?.name_ja || item.spec_type?.name || 'スペック' }}</span>
                        <span v-if="item.spec_type?.symbol" class="ml-1 font-mono opacity-70" v-html="renderSymbol(item.spec_type.symbol)"></span>
                      </span>
                      <span v-if="isToleranceSpecType(item.spec_type)" class="tag border border-[var(--color-border)] text-[10px]">許容差</span>
                      <span v-if="item.is_required" class="tag border border-[var(--color-border)] text-[10px]">必須</span>
                    </span>
                  </div>
                </div>
                @if ($isAdmin)
                <div class="flex gap-2">
                  <button @click="openTemplateEdit(template)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                  <button @click="openTemplateDuplicate(template)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                  <button @click="archiveTemplate(template)" class="px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50">アーカイブ</button>
                </div>
                @endif
              </div>
            </article>
            <div v-if="!currentSpecGroup.templates?.length" class="px-4 py-8 text-center text-sm opacity-40">入力テンプレートが登録されていません</div>
          </div>
        </section>
      </section>
      <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
        部品分類を選択してください
      </section>
    </section>

    <section v-if="activeTab === 'common-spec-types'" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)]">
          <div class="font-semibold text-sm">部品分類</div>
          <div class="text-xs opacity-60 mt-1">共通スペック詳細を候補に入れる部品分類を選択</div>
        </div>
        <div class="max-h-[55vh] overflow-y-auto">
          <button v-for="group in activeSpecGroups" :key="`common-spec-group-select-${group.id}`"
            @click="selectSpecGroup(group)"
            :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
            class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
            <span class="flex items-baseline justify-between gap-2">
              <span class="min-w-0 truncate font-medium">@{{ group.name }}</span>
              <span class="shrink-0 text-[11px] font-normal opacity-60">@{{ specGroupSidebarMeta(group) }}</span>
            </span>
          </button>
          <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">部品分類がありません</div>
        </div>
      </aside>

      <section v-if="currentSpecGroup" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-bold text-sm">共通スペック詳細</h2>
            <p class="text-xs opacity-60 mt-1">登録済みの共通スペック詳細から、@{{ currentSpecGroup.name }} の入力候補に入れるものを選びます。</p>
            <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
              <span class="tag border border-[var(--color-border)]">共通 @{{ currentSpecGroupCounts.common }} 件</span>
              <span class="tag border border-[var(--color-border)]">候補 @{{ currentSpecGroupCounts.total }} 件</span>
            </div>
          </div>
          @if ($isAdmin)
          <button @click="openCommonSpecTypeAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 共通スペック詳細を追加</button>
          @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
            <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 共通スペック詳細を追加</span></div>
            <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
          </div>
          @endif
        </div>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 px-3">名前</th>
              <th class="py-2 pr-4">入力候補</th>
              <th class="py-2 pr-4">単位</th>
              <th class="py-2 pr-4">使用</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, index) in activeCommonSpecTypes" :key="`common-spec-${s.id}`"
              :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)] transition-colors">
              <td class="py-2 px-3 font-medium">
                <div class="flex items-center gap-2">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </div>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span v-if="isCommonSpecLinked(s)" class="tag tag-ok">採用中</span>
                <span v-else class="tag border border-[var(--color-border)] opacity-60">未追加</span>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span v-if="s.units?.[0] || s.base_unit" class="inline-block bg-[var(--color-card-even)] border border-[var(--color-border)] rounded px-1.5 py-0.5">
                  @{{ s.units?.[0]?.unit || s.base_unit }}
                </span>
                <span v-else class="opacity-40">-</span>
              </td>
              <td class="py-2 pr-4 text-xs opacity-70">使用件数: @{{ s.usage_count ?? 0 }}</td>
              <td class="py-2">
                @if ($isAdmin)
                <div class="flex gap-2 flex-wrap">
                  <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                  <button @click="openCommonSpecTypeDuplicate(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                  <button @click="toggleCommonSpecForCurrentGroup(s)" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="w-28 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40 text-center">
                    @{{ isCommonSpecLinked(s) ? '候補から外す' : '候補に入れる' }}
                  </button>
                  <button @click="archiveSpecType(s)" class="w-20 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">アーカイブ</button>
                </div>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
            <tr v-if="activeCommonSpecTypes.length === 0">
              <td colspan="5" class="py-8 text-center opacity-40">共通スペック詳細が登録されていません</td>
            </tr>
          </tbody>
        </table>
        <section v-if="archivedCommonSpecTypes.length" class="px-4 py-4 border-t border-[var(--color-border)]">
          <h2 class="font-bold mb-3">アーカイブ</h2>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 pr-4">名前</th>
                <th class="py-2 pr-4">単位</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in archivedCommonSpecTypes" :key="`archived-common-spec-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 pr-4 font-medium">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ s.units?.[0]?.unit || s.base_unit || '-' }}</td>
                <td class="py-2">
                  <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>
      <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
        部品分類を選択してください
      </section>
    </section>

    <section v-if="activeTab === 'tolerance-spec-types'" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="border border-[var(--color-border)] rounded-lg bg-[var(--color-card-odd)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)]">
          <div class="font-semibold text-sm">部品分類</div>
          <div class="text-xs opacity-60 mt-1">許容差スペック詳細を候補に入れる部品分類を選択</div>
        </div>
        <div class="max-h-[55vh] overflow-y-auto">
          <button v-for="group in activeSpecGroups" :key="`tolerance-spec-group-select-${group.id}`"
            @click="selectSpecGroup(group)"
            :class="Number(selectedSpecGroupId) === Number(group.id) ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'"
            class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] text-sm transition-colors">
            <span class="flex items-baseline justify-between gap-2">
              <span class="min-w-0 truncate font-medium">@{{ group.name }}</span>
              <span class="shrink-0 text-[11px] font-normal opacity-60">@{{ specGroupSidebarMeta(group) }}</span>
            </span>
          </button>
          <div v-if="activeSpecGroups.length === 0" class="px-4 py-8 text-center text-sm opacity-50">部品分類がありません</div>
        </div>
      </aside>

      <section v-if="currentSpecGroup" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-card-odd)] flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-bold text-sm">許容差スペック詳細</h2>
            <p class="text-xs opacity-60 mt-1">登録済みの許容差スペック詳細から、@{{ currentSpecGroup.name }} の入力候補に入れるものを選びます。</p>
            <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
              <span class="tag border border-[var(--color-border)]">許容差 @{{ currentSpecGroupCounts.tolerance }} 件</span>
              <span class="tag border border-[var(--color-border)]">候補 @{{ currentSpecGroupCounts.total }} 件</span>
            </div>
          </div>
          @if ($isAdmin)
          <button @click="openToleranceSpecTypeAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 許容差スペック詳細を追加</button>
          @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)] text-right">
            <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 許容差スペック詳細を追加</span></div>
            <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
          </div>
          @endif
        </div>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left opacity-70">
              <th class="py-2 px-3">名前</th>
              <th class="py-2 pr-4">入力候補</th>
              <th class="py-2 pr-4">許容差の単位</th>
              <th class="py-2 pr-4">設定</th>
              <th class="py-2">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, index) in activeToleranceSpecTypes" :key="`tolerance-spec-${s.id}`"
              :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
              class="border-b border-[var(--color-border)] transition-colors">
              <td class="py-2 px-3 font-medium">
                <div class="flex items-center gap-2">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </div>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span v-if="isCommonSpecLinked(s)" class="tag tag-ok">採用中</span>
                <span v-else class="tag border border-[var(--color-border)] opacity-60">未追加</span>
              </td>
              <td class="py-2 pr-4 text-xs">
                <span class="inline-block bg-[var(--color-card-even)] border border-[var(--color-border)] rounded px-1.5 py-0.5">
                  @{{ toleranceUnit(s) }}
                </span>
              </td>
              <td class="py-2 pr-4 text-xs opacity-70">
                @{{ toleranceInputFormat(s) }} / @{{ toleranceAllowedUnits(s) }}
              </td>
              <td class="py-2">
                @if ($isAdmin)
                <div class="flex gap-2 flex-wrap">
                  <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
                  <button @click="openCommonSpecTypeDuplicate(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">複製</button>
                  <button @click="toggleCommonSpecForCurrentGroup(s)" :disabled="specGroupDetailLoading || specGroupMemberSaving" class="w-28 px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] disabled:opacity-40 text-center">
                    @{{ isCommonSpecLinked(s) ? '候補から外す' : '候補に入れる' }}
                  </button>
                  <button @click="archiveSpecType(s)" class="w-20 px-2 py-1 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 text-center">アーカイブ</button>
                </div>
                @else
                <span class="text-xs opacity-40">-</span>
                @endif
              </td>
            </tr>
            <tr v-if="activeToleranceSpecTypes.length === 0">
              <td colspan="5" class="py-8 text-center opacity-40">許容差スペック詳細が登録されていません</td>
            </tr>
          </tbody>
        </table>
        <section v-if="archivedToleranceSpecTypes.length" class="px-4 py-4 border-t border-[var(--color-border)]">
          <h2 class="font-bold mb-3">アーカイブ</h2>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left opacity-70">
                <th class="py-2 pr-4">名前</th>
                <th class="py-2 pr-4">許容差の単位</th>
                <th class="py-2">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, index) in archivedToleranceSpecTypes" :key="`archived-tolerance-spec-${s.id}`"
                :class="index % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
                class="border-b border-[var(--color-border)] transition-colors">
                <td class="py-2 pr-4 font-medium">
                  <span>@{{ s.name_ja || s.name }}</span>
                  <span v-if="s.symbol" class="ml-2 text-xs opacity-60 font-mono" v-html="renderSymbol(s.symbol)"></span>
                </td>
                <td class="py-2 pr-4 text-xs">@{{ toleranceUnit(s) }}</td>
                <td class="py-2">
                  <button @click="restoreSpecType(s)" class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">復元</button>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>
      <section v-else class="border border-[var(--color-border)] rounded-lg py-12 text-center opacity-50">
        部品分類を選択してください
      </section>
    </section>
  </div>
