<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ダッシュボード - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($canEdit = auth()->user()->isEditor())
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => 'ダッシュボード'])
<div id="app"
  data-page="dashboard"
  data-user-name="{{ auth()->user()->name }}"
  data-role="{{ auth()->user()->role }}"
  class="min-h-screen">

  <nav class="sticky top-[73px] z-20 border-b border-[var(--color-border)] bg-[var(--color-bg)]/96 backdrop-blur">
    <div class="max-w-7xl mx-auto px-4 py-2 flex flex-wrap gap-2">
      <a v-for="link in sectionLinks" :key="link.href" :href="link.href"
        class="px-3 py-1.5 rounded-full border border-[var(--color-border)] text-sm no-underline hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors">
        @{{ link.label }}
      </a>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-6 py-8 space-y-8">
    @include('partials.app-breadcrumbs', ['items' => [['label' => 'ダッシュボード', 'current' => true]]])

    <section id="launcher-section" class="scroll-mt-28 grid gap-6 xl:grid-cols-[1.7fr_1fr]">
      <div class="rounded-3xl border border-[var(--color-border)] p-6 shadow-sm"
        style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 14%, var(--color-bg)) 0%, var(--color-bg) 58%, color-mix(in srgb, var(--color-highlight) 12%, var(--color-bg)) 100%);">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.2em] opacity-50 mb-2">Global Launcher</p>
            <h2 class="text-3xl font-bold">検索</h2>
          </div>
          <div class="flex flex-wrap gap-2">
            <button v-for="mode in focusModes" :key="mode" type="button" @click="setFocus(mode)"
              class="px-3 py-1.5 rounded-full text-sm font-semibold border transition-colors"
              :class="activeFocus === mode
                ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                : 'border-[var(--color-border)] hover:border-[var(--color-primary)]'">
              @{{ mode }}
            </button>
          </div>
        </div>

        <div class="mt-6">
          <label for="launcher" class="text-sm font-semibold">グローバル検索</label>
          <div class="relative mt-2">
            <input id="launcher" v-model="searchQuery" @input="onSearchInput" @keydown.enter="openFirstResult"
              class="w-full rounded-2xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-4 text-base outline-none focus:border-[var(--color-primary)]"
              placeholder="型番・部品名・案件・機能名を検索" />
            <span class="absolute right-4 top-4 text-xs opacity-40">Enter</span>
          </div>
          <div class="mt-4 rounded-2xl border border-[var(--color-border)] overflow-hidden bg-[var(--color-bg)]">
            <div v-if="searchError" class="px-4 py-4 border-b border-[var(--color-border)] bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]">
              <div class="text-sm font-semibold text-[var(--color-tag-warning)]">@{{ searchError }}</div>
              <div class="mt-2 flex flex-wrap gap-2">
                <button @click="doSearch" class="px-3 py-2 rounded-xl border border-[var(--color-tag-warning)] text-sm">再試行</button>
                <a href="{{ route('components.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">部品一覧へ</a>
                <a href="{{ route('projects.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">案件管理へ</a>
              </div>
            </div>
            <button v-for="item in launcherResults" :key="`${item.type}-${item.label}`" type="button"
              @click="openItem(item)"
              class="w-full text-left px-4 py-3 border-b border-[var(--color-border)] last:border-b-0 hover:bg-[var(--color-card-even)] transition-colors">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">
                  @{{ item.icon }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="text-xs px-2 py-0.5 rounded-full border border-[var(--color-border)]">@{{ item.type }}</span>
                    <span class="font-semibold">@{{ item.label }}</span>
                  </div>
                  <div class="text-sm opacity-60">@{{ item.sub }}</div>
                </div>
                <span class="opacity-40 flex-shrink-0">↗</span>
              </div>
            </button>
            <div v-if="!launcherResults.length" class="px-4 py-5 text-sm opacity-40 text-center">
              見つかりません
            </div>
          </div>

        </div>
      </div>

      <aside class="space-y-4">
        <section v-if="summaryError" class="rounded-3xl border border-[var(--color-tag-warning)] p-5 bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))] shadow-sm">
          <div class="font-semibold text-[var(--color-tag-warning)]">ホーム要約の一部取得に失敗しました</div>
          <div class="text-sm opacity-80 mt-2">@{{ summaryError }}</div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button @click="fetchSummary" class="px-3 py-2 rounded-xl border border-[var(--color-tag-warning)] text-sm">再読込</button>
            <a href="{{ route('components.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">部品一覧へ</a>
            <a href="{{ route('projects.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">案件管理へ</a>
          </div>
        </section>
        <section id="today-section" class="scroll-mt-28 rounded-3xl border border-[var(--color-border)] p-5 card-even shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] opacity-50">Today</p>
              <h2 class="text-lg font-bold">今日の確認事項</h2>
            </div>
          </div>
          <div class="space-y-3">
            <button v-for="card in statusCards" :key="card.title" type="button" @click="openItem(card)"
              class="w-full rounded-2xl border border-[var(--color-border)] p-4 text-left hover:border-[var(--color-primary)] transition-colors bg-[var(--color-bg)]">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0 text-lg"
                  :style="{ backgroundColor: card.color }">
                  @{{ card.icon }}
                </div>
                <div class="min-w-0">
                  <div class="text-xs opacity-50">@{{ card.title }}</div>
                  <div class="font-bold text-lg leading-tight mt-0.5">@{{ card.value }}</div>
                  <div class="text-sm opacity-65 mt-1">@{{ card.desc }}</div>
                </div>
              </div>
            </button>
          </div>

        </section>
      </aside>
    </section>

    <section id="quick-actions-section" class="scroll-mt-28">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between mb-4">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-50">Work Menu</p>
          <h2 class="text-2xl font-bold">業務別メニュー</h2>
        </div>
        <a href="{{ route('settings.home') }}"
          class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors no-underline text-inherit">
          主要アクションを設定
        </a>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <a v-for="action in quickActions" :key="action.key"
          :href="action.url"
          class="relative flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 text-left hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all bg-[var(--color-bg)] no-underline text-inherit">
          <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">
            @{{ action.icon }}
          </div>
          <div class="min-w-0">
            <div class="font-semibold text-sm">@{{ action.label }}</div>
            <div class="text-xs opacity-50 truncate">@{{ action.desc }}</div>
          </div>
          <span v-if="action.badge"
            class="ml-auto bg-amber-500 text-white text-xs rounded-full min-w-6 h-6 px-1.5 flex items-center justify-center font-bold flex-shrink-0">
            @{{ action.badge > 9 ? '9+' : action.badge }}
          </span>
        </a>
      </div>
      <div v-if="preferenceError" class="mt-4 rounded-2xl border border-[var(--color-tag-warning)] px-4 py-3 text-sm bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-warning)]">@{{ preferenceError }}</div>
        <div class="mt-2">
          <a href="{{ route('settings.home') }}" class="inline-flex items-center gap-2 rounded-xl border border-[var(--color-tag-warning)] px-3 py-2 no-underline text-inherit">ホーム設定を開く</a>
        </div>
      </div>

    </section>

    <section id="recent-section" class="scroll-mt-28 rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-bg)] shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-50">Recent</p>
          <h2 class="text-2xl font-bold">最近使った機能</h2>
        </div>
        <a href="{{ route('components.index') }}" class="text-sm no-underline hover:text-[var(--color-primary)] transition-colors">部品一覧を見る</a>
      </div>
      <div class="grid gap-3 lg:grid-cols-2">
        <a v-for="item in recentItems" :key="item.href"
          :href="item.href"
          class="flex items-center justify-between gap-3 rounded-2xl px-4 py-4 border border-[var(--color-border)] hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-colors no-underline text-inherit">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">@{{ item.icon }}</div>
            <div class="min-w-0 text-left">
              <div class="font-semibold text-sm truncate">@{{ item.name }}</div>
              <div class="text-xs opacity-50 truncate">@{{ item.group }}</div>
            </div>
          </div>
          <span class="opacity-35 flex-shrink-0">›</span>
        </a>
      </div>
      <div v-if="recentItems.length === 0" class="text-center py-8 opacity-40 text-sm">部品が登録されていません</div>

    </section>

    <section id="favorites-section" class="scroll-mt-28 rounded-3xl border border-[var(--color-border)] p-6 bg-[var(--color-bg)] shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-50">Favorites</p>
          <h2 class="text-2xl font-bold">お気に入りパーツ</h2>
        </div>
        <a href="{{ route('components.index') }}" class="text-sm no-underline hover:text-[var(--color-primary)] transition-colors">部品一覧で管理</a>
      </div>
      <div class="grid gap-3 lg:grid-cols-2">
        <a v-for="item in favoriteItems" :key="item.href"
          :href="item.href"
          class="flex items-center justify-between gap-3 rounded-2xl px-4 py-4 border border-[var(--color-border)] hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-colors no-underline text-inherit">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">@{{ item.icon }}</div>
            <div class="min-w-0 text-left">
              <div class="font-semibold text-sm truncate">@{{ item.name }}</div>
              <div class="text-xs opacity-50 truncate">@{{ item.group }}</div>
            </div>
          </div>
          <span class="opacity-35 flex-shrink-0">›</span>
        </a>
      </div>
      <div v-if="favoriteItems.length === 0" class="text-center py-8 opacity-40 text-sm">お気に入りパーツはまだありません</div>
    </section>

    <!-- 全機能一覧 -->
    <section id="all-functions-section" class="scroll-mt-28">
      <div class="mb-6">
        <p class="text-xs uppercase tracking-[0.2em] opacity-50">Shortcuts</p>
        <h2 class="text-2xl font-bold">全機能ショートカット</h2>
      </div>

      {{-- 部品管理 --}}
      <div class="mb-6">
        <h3 class="text-xs font-semibold uppercase tracking-widest opacity-50 mb-3">業務別メニュー / 部品管理</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          @foreach ([
            ['icon'=>'🔩','label'=>'部品一覧','desc'=>'登録部品の検索・絞り込み','route'=>'components.index'],
            ['icon'=>'➕','label'=>'部品登録','desc'=>'新規部品を登録する','route'=>'components.create'],
            ['icon'=>'⚖️','label'=>'部品比較','desc'=>'複数部品のスペックを比較','route'=>'components.compare'],
            ['icon'=>'📥','label'=>'CSVインポート','desc'=>'CSVで部品を一括登録','route'=>'csv.import'],
          ] as $fn)
          @php($requiresAdmin = $fn['route'] === 'csv.import')
          @php($requiresEdit = in_array($fn['route'], ['components.create']))
          @php($disabled = ($requiresAdmin && !$isAdmin) || ($requiresEdit && !$canEdit))
          @php($reason = $requiresAdmin ? '管理者のみ実行できます' : '閲覧者のため登録できません')
          @if (!$disabled)
          <a href="{{ route($fn['route']) }}"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all no-underline text-inherit">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">{{ $fn['icon'] }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-sm">{{ $fn['label'] }}</div>
              <div class="text-xs opacity-50 truncate">{{ $fn['desc'] }}</div>
            </div>
          </a>
          @else
          <div class="feature-disabled flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 bg-[var(--color-card-even)]">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">{{ $fn['icon'] }}</div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 font-semibold text-sm">
                <span class="feature-lock">{{ $requiresAdmin ? '管' : '編' }}</span>
                <span>{{ $fn['label'] }}</span>
              </div>
              <div class="text-xs opacity-50 truncate">{{ $reason }}</div>
            </div>
          </div>
          @endif
          @endforeach
        </div>
      </div>

      {{-- 在庫・棚管理 --}}
      <div class="mb-6">
        <h3 class="text-xs font-semibold uppercase tracking-widest opacity-50 mb-3">在庫・棚管理</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          @foreach ([
            ['icon'=>'📥','label'=>'入庫','desc'=>'購入部品を順に入庫する','route'=>'stock.in'],
            ['icon'=>'⚠️','label'=>'在庫警告','desc'=>'発注点を下回る部品を確認','route'=>'stock.alert'],
            ['icon'=>'🛒','label'=>'部品発注','desc'=>'発注候補を商社別に確認・出力','route'=>'stock.orders'],
            ['icon'=>'🗄️','label'=>'保管棚管理','desc'=>'棚マップと棚卸し','route'=>'locations.index'],
            ['icon'=>'🏪','label'=>'商社管理','desc'=>'仕入先・商社の管理','route'=>'suppliers.index'],
          ] as $fn)
          <a href="{{ route($fn['route']) }}"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all no-underline text-inherit">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">{{ $fn['icon'] }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-sm">{{ $fn['label'] }}</div>
              <div class="text-xs opacity-50 truncate">{{ $fn['desc'] }}</div>
            </div>
          </a>
          @endforeach
        </div>
      </div>

      {{-- 案件・設計ツール --}}
      <div class="mb-6">
        <h3 class="text-xs font-semibold uppercase tracking-widest opacity-50 mb-3">案件・設計ツール</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          @foreach ([
            ['icon'=>'📋','label'=>'案件管理','desc'=>'案件ごとの部品・コスト管理','route'=>'projects.index'],
            ['icon'=>'🧮','label'=>'エンジニア電卓','desc'=>'式計算・進数変換・物理定数','route'=>'tools.calc'],
            ['icon'=>'🔬','label'=>'設計解析ツール','desc'=>'ADC/電源/誤差/熱など設計解析','route'=>'tools.design'],
            ['icon'=>'🔌','label'=>'ネットワーク探索','desc'=>'抵抗/容量の直並列組み合わせ','route'=>'tools.network'],
          ] as $fn)
          <a href="{{ route($fn['route']) }}"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all no-underline text-inherit">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">{{ $fn['icon'] }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-sm">{{ $fn['label'] }}</div>
              <div class="text-xs opacity-50 truncate">{{ $fn['desc'] }}</div>
            </div>
          </a>
          @endforeach
        </div>
      </div>

      {{-- マスタ・ログ --}}
      <div class="mb-6">
        <h3 class="text-xs font-semibold uppercase tracking-widest opacity-50 mb-3">業務別メニュー / マスタ・ログ</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          @foreach ([
            ['icon'=>'⚙️','label'=>'マスタ管理','desc'=>'分類・パッケージ・スペック種別','route'=>'master.index'],
            ['icon'=>'📝','label'=>'操作ログ','desc'=>'変更履歴の監査ログ','route'=>'audit.index'],
            ['icon'=>'🔗','label'=>'Altium連携','desc'=>'Altium Designerとの部品リンク','route'=>'altium.index'],
          ] as $fn)
          @php($requiresAdmin = in_array($fn['route'], ['audit.index', 'altium.index']))
          @if (!$requiresAdmin || $isAdmin)
          <a href="{{ route($fn['route']) }}"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all no-underline text-inherit">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">{{ $fn['icon'] }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-sm">{{ $fn['label'] }}</div>
              <div class="text-xs opacity-50 truncate">{{ $fn['desc'] }}</div>
            </div>
          </a>
          @else
          <div class="feature-disabled flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 bg-[var(--color-card-even)]">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">{{ $fn['icon'] }}</div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 font-semibold text-sm"><span class="feature-lock">管</span><span>{{ $fn['label'] }}</span></div>
              <div class="text-xs opacity-50 truncate">管理者のみ操作できます</div>
            </div>
          </div>
          @endif
          @endforeach
        </div>
      </div>

      {{-- 管理・設定 --}}
      <div class="mb-2">
        <h3 class="text-xs font-semibold uppercase tracking-widest opacity-50 mb-3">業務別メニュー / 管理・設定</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          @if ($canEdit)
          <a href="{{ route('settings.integrations') }}"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all no-underline text-inherit">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">🔑</div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 font-semibold text-sm"><span class="feature-lock">編</span><span>連携設定</span></div>
              <div class="text-xs opacity-50 truncate">Notion APIトークン等の設定</div>
            </div>
          </a>
          @else
          <div class="feature-disabled flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 bg-[var(--color-card-even)]">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">🔑</div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 font-semibold text-sm"><span class="feature-lock">編</span><span>連携設定</span></div>
              <div class="text-xs opacity-50 truncate">閲覧者は変更できません</div>
            </div>
          </div>
          @endif
          @if ($isAdmin)
          <a href="{{ route('users.index') }}"
            class="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 hover:border-[var(--color-primary)] hover:bg-[var(--color-card-even)] transition-all no-underline text-inherit">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">👤</div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 font-semibold text-sm"><span class="feature-lock">管</span><span>ユーザー管理</span></div>
              <div class="text-xs opacity-50 truncate">ユーザーの招待・ロール変更</div>
            </div>
          </a>
          @else
          <div class="feature-disabled flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 bg-[var(--color-card-even)]">
            <div class="w-9 h-9 rounded-xl bg-[var(--color-card-even)] flex items-center justify-center flex-shrink-0 text-lg">👤</div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 font-semibold text-sm"><span class="feature-lock">管</span><span>ユーザー管理</span></div>
              <div class="text-xs opacity-50 truncate">管理者のみ操作できます</div>
            </div>
          </div>
          @endif
        </div>
      </div>

    </section>

    @include('partials.app-breadcrumbs', ['items' => [['label' => 'ダッシュボード', 'current' => true]], 'class' => 'mt-6'])
  </main>

</div>
</body>
</html>
