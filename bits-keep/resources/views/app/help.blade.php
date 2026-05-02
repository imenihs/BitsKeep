<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>使い方ガイド - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    /* 目次アクティブ状態 */
    .toc-link { transition: color 0.15s, font-weight 0.15s; }
    .toc-link.is-active { font-weight: 700; opacity: 1; color: var(--color-primary, #3b82f6); }
    /* コードブロック */
    .help-code { font-family: monospace; background: var(--color-bg-alt, #f3f4f6); border-radius: 4px; padding: 2px 6px; font-size: 0.8em; }
    /* ステップバッジ */
    .step-badge { display: inline-flex; align-items: center; justify-content: center; width: 1.4em; height: 1.4em; border-radius: 50%; background: var(--color-primary, #3b82f6); color: #fff; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
    /* 注意バナー */
    .warn-banner { border-left: 3px solid #f59e0b; background: #fffbeb; padding: 8px 12px; border-radius: 0 4px 4px 0; }
    /* テーブル共通 */
    .help-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .help-table th, .help-table td { padding: 6px 10px; border-bottom: 1px solid var(--color-border); text-align: left; }
    .help-table th { opacity: 0.6; font-weight: 600; }
  </style>
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '使い方ガイド'])
<div class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '使い方ガイド', 'current' => true]]])

  <header class="mb-8 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">BitsKeep 使い方ガイド</h1>
    <p class="text-sm opacity-60 mt-1">電子部品の在庫・調達・案件を一元管理するシステムの操作手順</p>
  </header>

  <div class="flex gap-10">

    <!-- 目次（サイドバー） -->
    <nav id="toc" class="hidden lg:block w-52 shrink-0 text-sm">
      <div class="sticky top-6 space-y-0.5">
        <div class="font-semibold mb-3 opacity-50 text-xs uppercase tracking-wider">目次</div>
        <a href="#start"     class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">はじめる前に</a>
        <a href="#dashboard" class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">ダッシュボード</a>
        <a href="#parts"     class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">部品管理</a>
        <a href="#inventory" class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">在庫管理</a>
        <a href="#order"     class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">発注管理</a>
        <a href="#project"   class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">案件管理</a>
        <a href="#location"  class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">保管棚管理</a>
        <a href="#supplier"  class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">商社管理</a>
        <a href="#master"    class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">マスタ管理</a>
        <a href="#users"     class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">ユーザー管理</a>
        <a href="#auditlog"  class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">操作ログ</a>
        <a href="#tools"     class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">設計ツール</a>
        <a href="#altium"    class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">Altium 連携</a>
        <a href="#csv"       class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">CSV インポート</a>
        <a href="#backup"    class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">データのバックアップ</a>
        <a href="#notion"    class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">Notion 連携設定</a>
        <a href="#trouble"   class="toc-link block py-1 opacity-70 hover:opacity-100 no-underline">困ったときは</a>
      </div>
    </nav>

    <!-- 本文 -->
    <div class="flex-1 min-w-0 space-y-12 text-sm leading-relaxed">

      <!-- はじめる前に -->
      <section id="start">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">はじめる前に</h2>

        <h3 class="font-semibold mb-2">ログインとアカウント</h3>
        <p class="mb-4 opacity-80">管理者から招待メールが届いたら、メール内のリンクからパスワードを設定してログインします。招待メールが届いていない場合は管理者に連絡してください。</p>

        <h3 class="font-semibold mb-2">権限</h3>
        <p class="mb-2 opacity-80">ログイン後、画面右上に自分の権限が表示されます。権限を変更したい場合は管理者に連絡してください。</p>
        <table class="help-table mb-5">
          <thead><tr><th>表示</th><th>できること</th></tr></thead>
          <tbody>
            <tr><td><span class="role-pill role-admin">管理</span>（赤枠）</td><td class="opacity-80">すべての操作</td></tr>
            <tr><td><span class="role-pill role-editor">編集</span></td><td class="opacity-80">部品・在庫・案件の登録・編集</td></tr>
            <tr><td><span class="role-pill role-viewer">閲覧</span></td><td class="opacity-80">一覧・詳細の参照のみ</td></tr>
          </tbody>
        </table>

        <h3 class="font-semibold mb-3">最初にやること</h3>
        <p class="mb-3 opacity-80">部品を登録する前に、以下のマスタを先に登録してください。これらがないと部品登録時に選択肢が空になります。</p>
        <ol class="space-y-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80"><strong>部品分類を登録する</strong> — 「マスタ管理 → 部品分類」で「抵抗」「コンデンサ」「FPGA」などを追加します。部品には複数の部品分類を付けられます。</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80"><strong>パッケージ分類とパッケージ詳細を登録する</strong> — 「マスタ管理 → パッケージ分類」で「SMD」「THT」を追加し、その配下に「0402」「SOT-23」などを追加します。</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80"><strong>部品分類ごとのスペック詳細を登録する</strong> — 「マスタ管理 → 部品分類」で入力候補の親を用意し、「マスタ管理 → スペック詳細」で「静電容量」「耐圧」「周波数」などを追加します。英語名・記号・別名・標準単位も設定できます。</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">4</span><span class="opacity-80"><strong>商社を登録する</strong> — 「商社管理」で仕入先を追加します。リードタイムや送料無料閾値も登録できます。</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">5</span><span class="opacity-80"><strong>保管棚を登録する</strong> — 「保管棚管理」で棚を追加します。グループ（例: メインラック）でまとめて管理できます。</span></li>
        </ol>
      </section>

      <!-- ダッシュボード -->
      <section id="dashboard">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">ダッシュボード</h2>
        <p class="mb-4 opacity-80">ログイン直後に表示される起点画面です。日常業務の確認事項とショートカットが集まっています。</p>

        <h3 class="font-semibold mb-2">今日の確認事項</h3>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li><strong>在庫警告件数</strong> — 発注点を下回った部品の件数。クリックすると在庫警告画面へ移動します</li>
          <li><strong>最近アクセスした部品</strong> — 直近で開いた部品へのショートカット。再確認したい部品にすぐ戻れます</li>
        </ul>

        <h3 class="font-semibold mb-2">グローバル検索（Ctrl+K）</h3>
        <p class="mb-2 opacity-80"><kbd class="help-code">Ctrl+K</kbd>（Mac は <kbd class="help-code">Cmd+K</kbd>）でどのページからでも検索ランチャーを起動できます。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>型番・通称でインクリメンタル検索（入力しながらリアルタイムで候補が絞り込まれる）</li>
          <li><kbd class="help-code">Enter</kbd> で先頭候補の詳細ページへ移動</li>
          <li><kbd class="help-code">↑</kbd><kbd class="help-code">↓</kbd> で候補を選択、<kbd class="help-code">Esc</kbd> で閉じる</li>
        </ul>

        <h3 class="font-semibold mb-2">業務別メニュー</h3>
        <p class="mb-2 opacity-80">ダッシュボード下部に、部品管理、在庫・購買、案件・設計、マスタ・利用設定、管理・ログ・動作設定の分類でショートカットが並んでいます。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>ショートカットの並び順は自分でカスタマイズできます</li>
          <li>ヘッダ右上の「ホーム設定」→ ドラッグで並び替え → 「保存」</li>
        </ul>

        <h3 class="font-semibold mb-2">全機能一覧</h3>
        <p class="opacity-80">ヘッダの「全機能一覧」からすべての画面にアクセスできます。登録部品を扱う機能と、マスタ・利用設定、管理・ログ・動作設定を分け、権限がない機能はグレーアウトで理由が表示されます。</p>
      </section>

      <!-- 部品管理 -->
      <section id="parts">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">部品管理</h2>

        <h3 class="font-semibold mb-3">部品を新規登録する</h3>
        <p class="mb-3 opacity-80">「部品一覧」→ 右上「+ 新規登録」から登録画面を開きます。</p>
        <table class="help-table mb-4">
          <thead><tr><th>項目</th><th>必須</th><th>説明</th></tr></thead>
          <tbody>
            <tr><td>型番</td><td>✓</td><td class="opacity-80">メーカー型番。重複チェックあり</td></tr>
            <tr><td>名称</td><td></td><td class="opacity-80">通称・社内管理名</td></tr>
            <tr><td>メーカー</td><td></td><td class="opacity-80">製造元名</td></tr>
            <tr><td>入手可否</td><td></td><td class="opacity-80">量産中 / EOL / 在庫限り / 非推奨</td></tr>
            <tr><td>部品分類</td><td></td><td class="opacity-80">複数選択可。マスタ管理で事前登録が必要</td></tr>
            <tr><td>パッケージ分類</td><td></td><td class="opacity-80">大分類を選ぶとパッケージが絞り込まれる</td></tr>
            <tr><td>パッケージ</td><td></td><td class="opacity-80">0402・SOT-23 など</td></tr>
            <tr><td>登録単位</td><td></td><td class="opacity-80">単体 / シリーズ。シリーズを選ぶ場合は部品シリーズとシリーズ値へ紐づける</td></tr>
            <tr><td>代表保管棚</td><td></td><td class="opacity-80">この部品を通常置く棚。入庫時の初期値に使われる</td></tr>
            <tr><td>在庫下限</td><td></td><td class="opacity-80">この数を下回ると在庫警告に表示される発注点</td></tr>
            <tr><td>スペック</td><td></td><td class="opacity-80">「部品分類」「スペック候補」「入力テンプレート」「登録済みスペック」の順に編集します。<code>スペック候補</code> は「追加」で1行、<code>入力テンプレート</code> は「一式追加」で複数行を追加します。値種別は <code>TYP</code>、<code>MIN-MAX</code>、<code>≤MAX</code>、<code>≥MIN</code>、<code>Min/Typ/Max</code> から選びます。入力中は「標準単位換算: 1000Ω / 入力値: 1kΩ」のようなプレビューが表示されます。候補にないスペック詳細は部品分類が一意に決まる場合だけ行内の「+」から追加できます</td></tr>
            <tr><td>仕入先</td><td></td><td class="opacity-80">商社・商社型番・単価・購入単位・価格ブレーク。複数追加可</td></tr>
            <tr><td>部品画像</td><td></td><td class="opacity-80">jpg/png/webp、5MB まで</td></tr>
            <tr><td>データシート</td><td></td><td class="opacity-80">PDF ファイル。複数添付可。各PDFに表示名を付けられる</td></tr>
            <tr><td>カスタムフィールド</td><td></td><td class="opacity-80">任意のキーと値を自由に追加</td></tr>
          </tbody>
        </table>
        <p class="mb-5 opacity-80">フォームは上部と下部の両方に「保存」ボタンがあります。長いフォームを入力し終えた後、上に戻らず保存できます。未保存のまま別ページへ移動しようとすると確認ダイアログが表示されます。</p>

        <h3 class="font-semibold mb-3">スペック詳細の選択</h3>
        <p class="mb-2 opacity-80">部品登録と部品詳細のスペック編集は、説明カードではなく <strong>部品分類</strong>、<strong>スペック候補</strong>、<strong>入力テンプレート</strong>、<strong>登録済みスペック</strong> の構造で操作します。</p>
        <p class="mb-2 opacity-80"><strong>部品分類</strong> はマスタ上の部品分類と <strong>フィルタしない</strong> から選びます。部品詳細のスペック編集では、現在の部品分類を初期選択します。<strong>スペック候補</strong> は選択中の部品分類で絞り込まれ、<strong>追加</strong> で1行を登録済みスペックへ追加します。</p>
        <p class="mb-2 opacity-80"><strong>入力テンプレート</strong> は選択中の部品分類に紐づくテンプレートを選び、<strong>一式追加</strong> でまとめて行を追加します。選択時は <strong>[ピーク耐圧 V_RRM]</strong> のように実際に追加されるスペック詳細の日本語名と略号をチップで表示し、単なる件数表示だけにはしません。既に同じスペック詳細がある行は重複追加しません。</p>
        <p class="mb-5 opacity-80">候補やテンプレート自体を整える場合は、スペック編集内のマスタ導線から、選択中の部品分類を引き継いで <strong>スペック詳細</strong>、<strong>スペック候補設定</strong>、<strong>共通スペック詳細</strong>、<strong>許容差スペック詳細</strong>、<strong>入力テンプレート</strong> を開けます。行内の <strong>+</strong> は選択中の部品分類に個別スペック詳細を作るショートカットです。</p>

        <h3 class="font-semibold mb-3">データシート解析補助</h3>
        <p class="mb-2 opacity-80">現状の部品登録では、データシートからの補助入力に次の 3 系統があります。</p>
        <ul class="list-disc list-inside space-y-1 mb-3 opacity-80">
          <li><strong>ChatGPTで自動入力</strong> — Tampermonkey userscript 経由で <code>PDF選択 → 一時アップロード → ChatGPT Web 解析 → レビュー</code> まで自動化します</li>
          <li><strong>ChatGPTから貼り付け</strong> — ChatGPT で PDF を読ませた結果 JSON を貼り付けてレビューします</li>
          <li><strong>Geminiで解析</strong> — 外部サービス利用を許容する環境だけの任意導線です</li>
        </ul>
        <p class="mb-2 opacity-80">どちらの導線でも、解析後はいったんレビュー用モーダルが開きます。ここで基本情報・部品分類候補・入力テンプレート候補・パッケージ候補・スペック候補を確認し、必要なら修正してからフォームへ適用します。</p>
        <ul class="list-disc list-inside space-y-1 mb-3 opacity-80">
          <li><strong>部品分類候補</strong> — データシートから読み取った複数候補を既存部品分類へ紐付けて選択</li>
          <li><strong>パッケージ候補</strong> — データシートから読み取った候補の中から、既存の <code>パッケージ分類 / パッケージ詳細</code> へ紐付けた 1 件を選択</li>
          <li><strong>スペック候補</strong> — 日本語名、英語名、記号、別名を使って既存スペック詳細へ照合し、未一致項目はレビュー画面の「+」から追加、削除</li>
        </ul>
        <p class="mb-3 opacity-80">データシート上の元表記は <strong>データシート表記</strong> として確認用に表示されますが、保存時は既存のスペック詳細へ紐付けます。表記ゆれはスペック詳細の別名に寄せて管理します。</p>
        <p class="mb-3 opacity-80"><strong>ChatGPTで自動入力</strong> は <strong>Tampermonkey</strong> userscript を前提に、<strong>PDF選択 → BitsKeepへ一時アップロード → ChatGPT Web 解析 → 結果レビュー → 保存</strong> を自動化します。自動化が失敗した場合は、その場で <strong>ChatGPTから貼り付け</strong> へ切り替えられます。</p>
        <p class="mb-3 opacity-80">Tampermonkey helper が未接続でも、部品登録画面を開いただけでは案内モーダルを自動表示しません。<strong>ChatGPTで自動入力</strong> などの解析操作を押した時だけ、導入・更新・手動貼り付けへの切替案内を表示します。</p>
        <ul class="list-disc list-inside space-y-1 mb-3 opacity-80">
          <li>一時PDFは署名付き URL で ChatGPT タブへ渡されます</li>
          <li>一時PDFの有効期限は 2 時間です</li>
          <li>保存成功後は正式なデータシートとして登録され、一時リンクは再利用できません</li>
          <li>JSON 抽出失敗時は、取得済み応答テキストをコピーして手動貼り付けへ退避できます</li>
        </ul>
        @php($chatGptHelperUrl = url('/tampermonkey/bitskeep-chatgpt-helper.user.js').'?v='.filemtime(public_path('tampermonkey/bitskeep-chatgpt-helper.user.js')))
        @php($chatGptHelperMinVersion = config('services.chatgpt_helper.min_version'))
        <h4 class="font-medium mb-1 opacity-80">Tampermonkey 導入手順</h4>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80"><a href="{{ $chatGptHelperUrl }}" target="_blank" rel="noreferrer" class="link-text">userscript を開く</a> から Tampermonkey へインストールする</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80"><code>https://bits-keep.rwc.0t0.jp/*</code> と <code>https://chatgpt.com/*</code> への実行を許可する</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">ChatGPT にログインした状態で部品登録画面を再読込する</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">4</span><span class="opacity-80">PDF を選択して <strong>ChatGPTで自動入力</strong> を押す</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">5</span><span class="opacity-80">自動化が失敗したら案内モーダルから <strong>ChatGPTから貼り付け</strong> へ切り替える</span></li>
        </ol>
        <h4 class="font-medium mb-1 opacity-80">userscript 更新手順</h4>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">部品登録画面の <strong>ChatGPT自動解析</strong> モーダルで <strong>userscript を更新</strong> を押す</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">Tampermonkey の更新確認画面で <strong>再インストール</strong> または <strong>更新</strong> を承認する</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80"><strong>再読込して反映</strong> を押して、部品登録画面へ更新版 userscript を反映する</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">4</span><span class="opacity-80">再読込後に <strong>helper v{{ $chatGptHelperMinVersion }}</strong> 以上の成功トーストが出れば更新完了。旧版のままなら更新ダイアログが再表示される</span></li>
        </ol>

        <h3 class="font-semibold mb-3">既存部品を複製して登録する</h3>
        <p class="mb-2 opacity-80">同一メーカーの別パッケージ品など、似た部品を素早く登録するときに使います。</p>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">複製元の部品詳細ページを開く</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">「複製」ボタンをクリック</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">型番・パッケージなど差分だけ修正して「保存」</span></li>
        </ol>
        <p class="mb-5 opacity-80">画像・データシート・スペック・仕入先情報がすべてコピーされます。元部品との関係も記録されます。</p>

        <h3 class="font-semibold mb-3">部品を探す</h3>
        <h4 class="font-medium mb-1 opacity-80">基本検索</h4>
        <p class="mb-3 opacity-80">部品一覧上部の検索バーで型番・名称・メーカーをキーワード検索します。入力しながらリアルタイムで絞り込まれます。</p>

        <h4 class="font-medium mb-1 opacity-80">詳細フィルタ</h4>
        <p class="mb-2 opacity-80">「詳細条件」を開くと以下の条件で絞り込めます。</p>
        <ul class="list-disc list-inside space-y-1 mb-3 opacity-80">
          <li>部品分類（複数選択可）</li>
          <li>入手可否（量産中 / EOL / 在庫限り / 非推奨）</li>
          <li>メーカー名</li>
          <li>パッケージ分類 / パッケージ詳細</li>
          <li>スペック詳細 + 値の範囲（例: 静電容量 100nF〜10μF）</li>
          <li>在庫状態（在庫あり / 在庫切れ / 警告中）</li>
          <li>購入日の範囲</li>
        </ul>
        <p class="mb-5 opacity-80">適用中のフィルタは「フィルタチップ」として表示され、1クリックで個別に解除できます。</p>

        <h4 class="font-medium mb-1 opacity-80">並び順</h4>
        <p class="mb-5 opacity-80">既定は部品分類、パッケージ分類、パッケージ、型番の順に並ぶ台帳向け表示です。型番内の数字は自然順で扱うため、2SC945 は 2SC1815 より前に並びます。必要に応じて更新順、通称順、型番順へ切り替えできます。</p>

        <h3 class="font-semibold mb-3">部品を比較する</h3>
        <ol class="space-y-2 mb-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">部品一覧でカード左上のチェックボックスを選択（最大5件）</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">画面下部に表示される「比較」ボタンをクリック</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">比較画面でスペック・価格・在庫を横並びで確認</span></li>
        </ol>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>差分がある行はハイライト表示されます</li>
          <li>「差異のある行のみ表示」チェックで差分だけに絞り込めます</li>
          <li>比較画面から直接案件に部品を追加できます</li>
        </ul>

        <h3 class="font-semibold mb-3">部品詳細ページ</h3>
        <p class="mb-2 opacity-80">部品カードをクリックすると詳細ページが開きます。確認できる情報と操作は以下の通りです。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>基本情報・スペック・仕入先（最安値商社も表示）・画像・データシート（PDF を直接開けます）</li>
          <li>棚別在庫合計（新品 / 中古 別）</li>
          <li><strong>入庫 / 出庫</strong> ボタン（→ 在庫管理セクションを参照）</li>
          <li>在庫履歴（入出庫のすべてのログ）</li>
          <li>関連案件（この部品を使っている案件一覧）</li>
          <li>類似部品（同じ部品分類・パッケージの代替候補。「類似部品を探す」ボタンで比較画面へ移動できます）</li>
          <li>Altium リンク（シンボル名・フットプリント名）</li>
          <li><strong>削除</strong> — 管理者のみ。論理削除のため復元可能です</li>
        </ul>
      </section>

      <!-- 在庫管理 -->
      <section id="inventory">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">在庫管理</h2>

        <h3 class="font-semibold mb-3">入庫する — 部品詳細から</h3>
        <ol class="space-y-2 mb-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">部品詳細ページ → 右上「入庫」ボタン</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">以下の項目を入力して「確定」</span></li>
        </ol>
        <table class="help-table mb-5">
          <thead><tr><th>項目</th><th>説明</th></tr></thead>
          <tbody>
            <tr><td>数量</td><td class="opacity-80">入庫する個数</td></tr>
            <tr><td>区分</td><td class="opacity-80">新品 / 中古</td></tr>
            <tr><td>入庫先棚</td><td class="opacity-80">置く棚を選択（代表保管棚が初期値）。同じ条件の棚なら既存ブロックに加算、異なる条件なら新規ブロックが作られる</td></tr>
            <tr><td>ロット番号</td><td class="opacity-80">任意。ロット管理が必要な場合に入力</td></tr>
            <tr><td>備考</td><td class="opacity-80">任意</td></tr>
          </tbody>
        </table>

        <h3 class="font-semibold mb-3">入庫する — まとめて入庫（複数部品）</h3>
        <p class="mb-2 opacity-80">まとめ買いした複数部品を連続で処理したいときに便利です。</p>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">ダッシュボード「在庫入力」または全機能一覧 →「在庫入力」</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">型番・通称で部品を検索して追加</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">各部品の数量・棚・区分・ロットを入力</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">4</span><span class="opacity-80">「一括登録」で確定。次の部品へ連続して処理できます</span></li>
        </ol>

        <h3 class="font-semibold mb-3">出庫する</h3>
        <ol class="space-y-2 mb-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">部品詳細ページ →「出庫」ボタン</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">数量・用途（任意）・備考（任意）を入力して「確定」</span></li>
        </ol>
        <p class="mb-5 opacity-80">在庫が不足している場合はエラーが表示されます。出庫後は在庫履歴に記録されます。</p>

        <h3 class="font-semibold mb-3">在庫履歴を確認する</h3>
        <p class="mb-5 opacity-80">部品詳細ページ下部の「在庫履歴」セクションに、入出庫のすべてのログが表示されます。日時・操作者・数量・棚・備考を確認できます。</p>

        <h3 class="font-semibold mb-3">在庫警告を確認する</h3>
        <p class="mb-2 opacity-80">「在庫下限」（発注点）を下回った部品は自動的に在庫警告として検出されます。</p>
        <ul class="list-disc list-inside space-y-1 mb-3 opacity-80">
          <li>ダッシュボードの警告バッジで件数を常時確認できます</li>
          <li>全機能一覧 →「在庫警告」で一覧を確認できます</li>
        </ul>
        <p class="mb-5 opacity-80">発注点は部品登録・編集画面の「在庫下限」フィールドで設定します。</p>

        <h3 class="font-semibold mb-3">棚卸しをする</h3>
        <p class="mb-2 opacity-80">実際の在庫数とシステムの在庫数を照合・修正します。</p>
        <ol class="space-y-2 mb-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">「保管棚」→ 右上「棚卸しモード」をオン</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">画面上部にオレンジのバーが表示されます（棚卸し中の目印）</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">各棚の「実数」欄に実際に数えた在庫数を入力（現在のシステム値がデフォルト表示）</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">4</span><span class="opacity-80">差分（±）がリアルタイムで表示されます。問題なければ「確定」で保存</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">5</span><span class="opacity-80">確定すると差分がシステムの在庫に反映されます</span></li>
        </ol>
        <p class="opacity-80">確定せずに終了したい場合は「棚卸しモード終了」ボタンを押します。変更は破棄されます。</p>
      </section>

      <!-- 発注管理 -->
      <section id="order">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">発注管理</h2>

        <h3 class="font-semibold mb-3">在庫警告を確認する</h3>
        <p class="mb-2 opacity-80">全機能一覧 →「在庫警告」で発注点を下回った部品の一覧を表示します。各行には以下の情報が表示されます。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>型番・通称</li>
          <li>現在の在庫数（新品 / 中古 別）</li>
          <li>発注点との差分</li>
        </ul>

        <h3 class="font-semibold mb-3">発注リストを作る</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">在庫警告画面で発注したい部品にチェックを入れる</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">「発注リストへ追加」ボタンをクリック</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">全機能一覧 →「発注画面」へ移動すると追加した部品が一覧表示されます</span></li>
        </ol>

        <h3 class="font-semibold mb-3">発注画面で内容を決める</h3>
        <p class="mb-2 opacity-80">発注画面では各部品について以下を設定します。</p>
        <table class="help-table mb-5">
          <thead><tr><th>項目</th><th>説明</th></tr></thead>
          <tbody>
            <tr><td>購入単位</td><td class="opacity-80">バラ / テープ / トレー / リール / 箱</td></tr>
            <tr><td>購入数量</td><td class="opacity-80">発注する個数</td></tr>
            <tr><td>購入商社</td><td class="opacity-80">仕入先を選択。選択すると商社型番と単価が自動入力される</td></tr>
          </tbody>
        </table>

        <h3 class="font-semibold mb-3">商社別 CSV を出力する</h3>
        <p class="mb-2 opacity-80">「〇〇商社 CSV出力」ボタンで、その商社向けの発注シートを CSV で書き出します。</p>
        <p class="opacity-80">CSV の列: 型番 / 通称 / パッケージ / 購入単位 / 商社 / 商社型番 / 数量 / 単価 / 小計</p>
      </section>

      <!-- 案件管理 -->
      <section id="project">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">案件管理</h2>

        <h3 class="font-semibold mb-3">案件を作成する</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">全機能一覧 →「案件管理」→「+ 案件を追加」</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">案件名・事業（Notion 事業 DB と紐付く）を入力して保存</span></li>
        </ol>

        <h3 class="font-semibold mb-3">使用部品を追加する</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">案件一覧から案件をクリックして右ペインを開く</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">「使用部品」セクションで部品を検索して追加</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">使用数を入力。コスト積算が自動計算されます</span></li>
        </ol>

        <h3 class="font-semibold mb-3">Notion 同期</h3>
        <p class="mb-2 opacity-80">「連携設定」で Notion API トークンと事業データベース ID を設定すると、Notion 上の案件データを BitsKeep に同期できます。</p>
        <ul class="list-disc list-inside space-y-1 mb-2 opacity-80">
          <li>同期は「案件管理」画面の「同期」ボタンで手動実行します</li>
          <li>同期結果（成功 / 失敗 / 件数）が画面上に表示されます</li>
          <li>Notion で作成した案件は <span class="help-code">origin: Notion</span> として取り込まれます</li>
        </ul>
        <p class="opacity-80">Notion 連携の設定方法は「<a href="#notion" class="underline opacity-80">Notion 連携設定</a>」を参照してください。</p>
      </section>

      <!-- 保管棚管理 -->
      <section id="location">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">保管棚管理</h2>
        <p class="mb-4 opacity-80">部品を物理的に保管する棚の情報を管理します。管理者のみが登録・廃止・復元できます。</p>

        <h3 class="font-semibold mb-3">棚を登録する（管理者のみ）</h3>
        <ol class="space-y-2 mb-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">「保管棚」→「+ 棚を追加」</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">以下を入力して保存</span></li>
        </ol>
        <table class="help-table mb-5">
          <thead><tr><th>項目</th><th>説明</th></tr></thead>
          <tbody>
            <tr><td>棚コード</td><td class="opacity-80">識別コード（例: A-01）。ユニークである必要があります</td></tr>
            <tr><td>棚名称</td><td class="opacity-80">表示名（例: 棚A-1段目）</td></tr>
            <tr><td>グループ</td><td class="opacity-80">棚をまとめるグループ名（例: メインラック、引き出し）</td></tr>
            <tr><td>並び順</td><td class="opacity-80">一覧での表示順（数値が小さいほど上に表示）</td></tr>
            <tr><td>備考</td><td class="opacity-80">任意</td></tr>
          </tbody>
        </table>

        <h3 class="font-semibold mb-3">棚を廃止する / 復元する（管理者のみ）</h3>
        <p class="mb-2 opacity-80">使わなくなった棚は「廃止」にすると選択候補から消えます。過去の在庫データへの影響はありません。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>「廃止」— 新規入庫時の棚選択候補から非表示になる</li>
          <li>「復元」— 廃止済み棚をいつでも現役に戻せる</li>
          <li>「完全削除」— 棚別在庫も実績もない場合のみ許可される</li>
        </ul>

        <h3 class="font-semibold mb-2">棚卸しモード</h3>
        <p class="opacity-80">→「<a href="#inventory" class="underline opacity-80">在庫管理 — 棚卸しをする</a>」を参照してください。</p>
      </section>

      <!-- 商社管理 -->
      <section id="supplier">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">商社管理</h2>
        <p class="mb-4 opacity-80">仕入先（商社・販売店）の情報を管理します。登録は管理者のみが行えます。</p>

        <h3 class="font-semibold mb-3">商社を登録する（管理者のみ）</h3>
        <p class="mb-2 opacity-80">「商社管理」→「+ 新規追加」から登録します。</p>
        <table class="help-table mb-5">
          <thead><tr><th>項目</th><th>説明</th></tr></thead>
          <tbody>
            <tr><td>商社名</td><td class="opacity-80">必須。重複不可</td></tr>
            <tr><td>URL</td><td class="opacity-80">商社の Web サイト URL</td></tr>
            <tr><td>リードタイム</td><td class="opacity-80">発注から納品までの日数（目安）</td></tr>
            <tr><td>送料無料閾値</td><td class="opacity-80">この金額以上の注文で送料無料になる金額</td></tr>
            <tr><td>識別カラー</td><td class="opacity-80">商社を色で区別するためのカラー設定。一覧や発注画面で色分け表示に使われる</td></tr>
            <tr><td>備考</td><td class="opacity-80">任意</td></tr>
          </tbody>
        </table>

        <h3 class="font-semibold mb-2">商社を取引停止 / 復元する（管理者のみ）</h3>
        <p class="opacity-80">取引がなくなった商社は「取引停止」にすると部品登録などの選択候補から非表示になります。過去の価格履歴や部品との紐付けはそのまま残ります。「復元」でいつでも元に戻せます。</p>
      </section>

      <!-- マスタ管理 -->
      <section id="master">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">マスタ管理</h2>
        <p class="mb-4 opacity-80">部品登録時に選択する「部品分類」「パッケージ詳細」「スペック詳細」と、スペック候補設定・入力テンプレートを管理します。全機能一覧 →「マスタ管理」から操作します。</p>
        <p class="mb-4 opacity-80">スペック系のタブは <strong>部品分類</strong>、<strong>スペック詳細</strong>、<strong>スペック候補設定</strong>、<strong>共通スペック詳細</strong>、<strong>許容差スペック詳細</strong>、<strong>入力テンプレート</strong> に責務を分け、入力テンプレートは一番右に置いています。</p>
        <p class="mb-4 opacity-80">スペック系タブの左ペインは部品分類を選ぶための領域に限定し、分類名の横にそのタブで扱う登録数を小さく「登録:4個」のように表示します。選択後の右ペインヘッダでは、個別、入力候補、共通、許容差、テンプレート、行、採用中、未追加のような短いバッジで内訳を確認します。</p>

        <h3 class="font-semibold mb-3">部品分類</h3>
        <p class="mb-2 opacity-80">「抵抗」「コンデンサ」「FPGA」など部品の種類を表すタグです。1つの部品に複数の部品分類を付けられます。部品分類はスペック詳細の候補と入力テンプレートの親でもあります。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>一覧はドラッグ&ドロップで表示順を変更できます</li>
          <li>使わなくなった部品分類は「アーカイブ」で候補から外せます（部品への紐付けは残ります）</li>
          <li>「復元」でいつでも候補に戻せます</li>
          <li>まったく使っていない場合のみ「完全削除」が可能です</li>
        </ul>

        <h3 class="font-semibold mb-3">部品分類と候補スペック詳細</h3>
        <p class="mb-2 opacity-80"><strong>部品分類</strong> は部品そのものに付ける種類・検索タグであり、スペック詳細の候補・並び順・既定値を束ねる入力候補グループでもあります。</p>
        <p class="mb-5 opacity-80">例: 部品分類が BJT のとき、その中に VCEO、コレクタ電流、hFE、fT などの候補スペック詳細を並べます。</p>

        <h3 class="font-semibold mb-3">部品シリーズ</h3>
        <p class="mb-2 opacity-80">抵抗・コンデンサ・インダクタなど、同一シリーズの中に値違いが大量にある部品は、単体部品を1件ずつ並べるのではなく、<strong>部品シリーズ</strong> とシリーズ値一覧で扱います。一方で IC・MCU・センサ・モジュールなど、型番ごとの差分が大きい部品は従来通り単体部品として管理します。</p>
        <p class="mb-2 opacity-80">部品シリーズ画面は <code>/component-series</code> です。部品分類には <strong>シリーズを使わない</strong>、<strong>シリーズ登録も使う</strong>、<strong>シリーズ登録を推奨</strong> を設定できます。</p>
        <p class="mb-4 opacity-80">部品登録画面の <strong>シリーズ</strong> は、既存の部品シリーズとシリーズ値へ登録部品を紐づける導線です。登録済みシリーズがない場合は <strong>部品シリーズ未登録</strong> と表示し、マスタ・利用設定の部品シリーズで先にシリーズ値一覧を作成します。</p>
        <p class="mb-2 opacity-80">値展開方式は E系列だけに固定しません。シリーズごとに <strong>E系列</strong>、<strong>基準系列 + 追加値</strong>、<strong>任意値リスト</strong>、<strong>範囲/刻み</strong> を選べるようにします。たとえば E12 を基準にしつつ一部だけ E24 の値を追加する混在や、ツェナー電圧・水晶周波数・ヒューズ電流・コネクタ極数のように E系列へ乗らない値も正式に扱います。</p>
        <p class="mb-2 opacity-80">部品分類を選ぶと <strong>スペック詳細</strong> の候補をその部品分類に絞ります。単位は選択したスペック詳細の標準単位を自動で使い、シリーズ側では直接編集しません。パッケージは <strong>パッケージ分類</strong> を選んでから <strong>パッケージ詳細</strong> を選びます。</p>
        <p class="mb-2 opacity-80">E系列は <strong>開始値</strong> と <strong>終了値</strong> を <code>1</code>、<code>0.1</code>、<code>1k</code>、<code>10G</code> のような実数で入力します。0ΩはE系列では生成できないため、<strong>0を含む</strong> チェックで追加します。<strong>範囲/刻み</strong> は別方式で、開始値・終了値・刻み幅を実数で入力します。</p>
        <p class="mb-5 opacity-80">シリーズ値一覧では、まだ部品として登録していない値と、在庫・仕入先を持つ登録済み部品を分けて扱います。必要な値だけを選び、登録部品として作成できます。</p>

        <h3 class="font-semibold mb-3">パッケージ分類・パッケージ詳細</h3>
        <p class="mb-2 opacity-80">パッケージは2階層で管理します。部品登録時は大分類を選んでからパッケージを選びます。</p>
        <table class="help-table mb-3">
          <thead><tr><th>階層</th><th>例</th><th>説明</th></tr></thead>
          <tbody>
            <tr><td>パッケージ分類</td><td class="opacity-80">SMD、THT、BGA</td><td class="opacity-80">大分類。パッケージの親になる</td></tr>
            <tr><td>パッケージ詳細</td><td class="opacity-80">0402、0603、SOT-23</td><td class="opacity-80">パッケージ分類に属する個別形状</td></tr>
          </tbody>
        </table>
        <p class="mb-2 opacity-80">パッケージ分類を選ぶとパッケージの候補が自動で絞り込まれます。</p>
        <p class="mb-5 opacity-80">パッケージ詳細の外形寸法は <code>X / Y / Z</code> で登録します。<code>X</code> は縦または長手方向、<code>Y</code> は横または幅、<code>Z</code> は実装高さです。</p>

        <h3 class="font-semibold mb-3">スペック詳細</h3>
        <p class="mb-2 opacity-80">「静電容量」「耐圧」「周波数」など、検索・比較したい仕様項目を定義します。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>各スペック詳細に日本語名・英語名・記号・別名・標準単位を設定できます</li>
          <li>英語名・記号・別名は、英語データシートや略記号から同じスペック詳細へ照合するために使います</li>
          <li>同じ日本語名のスペック詳細を複数登録できます。例: <code>電源電圧</code> を <code>VDD</code> と <code>VCC</code> で別々に持つ場合は、記号・別名で区別します</li>
          <li>「スペック詳細」タブでは先に部品分類を選び、その部品分類に持たせるスペック詳細を追加・編集・アーカイブします</li>
          <li>スペック詳細は <code>抵抗値</code> <code>耐圧</code> のように、選択中の部品分類でよく使う仕様項目です。ほかの部品分類でも使いたい項目は、「共通スペック詳細」か「許容差スペック詳細」から候補に入れます</li>
          <li>共通スペック詳細は「共通スペック詳細」タブで左の部品分類を選び、登録済みの共通スペック詳細から、その部品分類の入力候補に入れるものを選びます</li>
          <li>スペック候補設定タブの行「編集」は、候補側の範囲・扱い・既定の入力形式・既定単位・メモだけを編集します。名称・記号・別名・単位などスペック詳細そのものの編集は、各スペック詳細タブの「編集」で行います</li>
          <li>スペック候補設定タブで候補へ追加するときは、「スペック詳細から追加」「共通スペック詳細から追加」「許容差スペック詳細から追加」の3系統から既存項目を選びます。このタブではスペック詳細の新規作成やフリー入力を開きません</li>
          <li>スペック候補設定タブの候補追加・候補設定編集・候補順のドラッグ&ドロップは操作完了時に自動保存します。上下ボタンでの並び替えは使いません</li>
          <li>許容差スペック詳細は共通スペック詳細と兄弟の別棚です。左の部品分類を選び、登録済みの許容差スペック詳細から、その部品分類の入力候補に入れるものを選びます。通常/許容差を切り替えるラジオや、許容差タブ内の行別「許容差」バッジは置きません</li>
          <li>許容差スペック詳細の例: <code>抵抗値許容差 ±5%</code>、<code>容量許容差 B級 ±0.1pF</code>、<code>容量許容差 Z級 +80/-20%</code>、<code>抵抗温度係数 ±100ppm/℃</code>、<code>容量温度特性 X7R</code></li>
          <li>記号は HTML ではなく <code>h_FE</code> <code>V_CBO</code> <code>V_CE-(sat)</code> のように保存します。<code>_</code> は下付き、<code>~</code> は上付き、<code>-</code> は通常表示へ戻す区切りです</li>
          <li>基準単位は1つ設定できます（例: 静電容量 → F、電流 → A）</li>
          <li><code>B</code> / <code>bit</code> / <code>bps</code> 系の接頭辞は10進（<code>T/G/M/k/無印</code>）または IEC（<code>Ti/Gi/Mi/Ki</code>）のどちらか一方を選びます。一方を選ぶともう一方は自動で外れ、<code>m/u/n/p/f</code> は候補に出しません</li>
          <li>単位は省略可（無次元の場合や任意テキストで管理したい場合）</li>
          <li>登録画面では、基準単位から <code>uA</code> <code>kΩ</code> <code>ns</code> のような読みやすい接頭語付き表示へ自動変換します。<code>1MB</code> は <code>1000000 B</code>、<code>1MiB</code> は <code>1048576 B</code> として区別します</li>
          <li>部品登録画面のスペック行に自由な「名前」欄はありません。必ずスペック詳細を選び、候補にない場合だけその場の追加モーダルでスペック詳細を登録します</li>
          <li>値種別は <code>TYP</code>（代表値）、<code>MIN-MAX</code>（範囲）、<code>≤MAX</code>（最大のみ）、<code>≥MIN</code>（最小のみ）、<code>Min/Typ/Max</code> を扱え、検索は常に標準単位へ換算して行います</li>
          <li>スペック詳細の入力順は「スペック候補設定」タブで部品分類ごとの候補順として変更します</li>
          <li>マスタ管理の並び順は一覧のドラッグ&ドロップで変更します。編集モーダルでは内部用の並び順数値を入力しません</li>
        </ul>
        <h3 class="font-semibold mb-3">入力テンプレート</h3>
        <p class="mb-5 opacity-80">入力テンプレートは、部品分類ごとにスペック行をまとめて追加する初期行セットです。候補スペック詳細の所属・並び順・既定値は「スペック候補設定」タブ、入力テンプレートは一番右の「入力テンプレート」タブで編集します。<strong>共通</strong> は部品分類として表示・選択せず、共通スペック詳細として管理します。</p>
        <p class="mb-5 opacity-80">UI表示名は <strong>部品分類</strong> / <strong>パッケージ詳細</strong> / <strong>スペック詳細</strong> / <strong>スペック候補設定</strong> / <strong>候補スペック詳細</strong> / <strong>入力テンプレート</strong> / <strong>共通スペック詳細</strong> / <strong>許容差スペック詳細</strong> に整理しています。</p>

        <h3 class="font-semibold mb-2">マスタ共通の廃止 / 復元 / 完全削除</h3>
        <p class="opacity-80">「廃止（アーカイブ）」にすると選択候補から非表示になりますが、すでに紐付けられた部品への影響はありません。「候補から外す」は部品分類内の候補関係だけを解除する操作で、スペック詳細そのものを削除する操作ではありません。候補から外す操作だけは確認ダイアログを挟みます。「復元」でいつでも候補に戻せます。他の部品から参照されていない場合のみ「完全削除」が許可されます。</p>
      </section>

      <!-- ユーザー管理 -->
      <section id="users">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">ユーザー管理</h2>
        <p class="mb-4 opacity-80">全機能一覧 →「ユーザー管理」から操作します。管理者のみが使える機能です。</p>

        <h3 class="font-semibold mb-3">ユーザーを招待する</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">「招待」ボタンをクリック</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">メールアドレスとロール（管理者 / 編集者 / 閲覧者）を入力して「送信」</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">受信者がメール内のリンクからパスワードを設定してログイン完了</span></li>
        </ol>

        <h3 class="font-semibold mb-3">ロールを変更する</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">ユーザー一覧でロールバッジ横の「変更」ボタンをクリック</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">新しいロールを選択</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">確認ダイアログで「変更する」→ 即時反映</span></li>
        </ol>

        <h3 class="font-semibold mb-3">ユーザーを無効化 / 有効化する</h3>
        <p class="mb-5 opacity-80">退職・離脱したユーザーは「無効化」でログインを禁止できます。データはそのまま残ります。「有効化」でいつでも元に戻せます。操作前に確認ダイアログが表示されます。</p>

        <h3 class="font-semibold mb-2">表示名を変更する</h3>
        <p class="opacity-80">ユーザー一覧の「名前編集」ボタンから表示名を変更できます。変更は画面上の表示名に反映されます。</p>
      </section>

      <!-- 操作ログ -->
      <section id="auditlog">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">操作ログ</h2>
        <p class="mb-4 opacity-80">全機能一覧 →「操作ログ」で確認できます。管理者のみが閲覧できます。部品・在庫・マスタへのすべての変更が自動記録されます。</p>

        <h3 class="font-semibold mb-3">ログの内容</h3>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>操作日時</li>
          <li>操作者</li>
          <li>操作種別（作成 / 更新 / 削除）</li>
          <li>対象テーブルとレコード ID</li>
          <li>変更前後の差分（JSON 形式。クリックで展開できます）</li>
        </ul>

        <h3 class="font-semibold mb-2">フィルタ</h3>
        <p class="opacity-80">日付範囲・操作者・操作種別・テーブル名でフィルタリングできます。「誰がいつ何を変更したか」を素早く追跡できます。誤った変更の原因調査や、意図しない変更の確認に使用します。</p>
      </section>

      <!-- 設計ツール -->
      <section id="tools">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">設計ツール</h2>
        <p class="mb-4 opacity-80">全機能一覧 →「設計ツール」から使えます。電子回路の設計補助ツール群です。</p>

        <h3 class="font-semibold mb-3">抵抗/容量ネットワーク探索</h3>
        <p class="mb-2 opacity-80">抵抗/容量ネットワーク、分圧、可変抵抗の計算を同じ画面で扱います。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>上位タブはネットワーク探索、分圧、可変抵抗です</li>
          <li>ネットワーク探索タブは抵抗/容量ネットワーク専用です。分圧UIは置かず、抵抗と容量で直列/並列の合成式を切り替えます</li>
          <li>分圧タブはVR調整なし/VR調整ありを切り替えます。どちらも同じ分圧設計条件を使い、違いはVR素子を入れて出力範囲を調整するかどうかです</li>
          <li>分圧条件は比率指定に加え、入力電圧 Vin と出力電圧 Vout から目標比率を直接計算できます。VR調整ありでは同じ切替で出力下限/上限を電圧または比率範囲として指定できます</li>
          <li>VR調整なし/ありは同じ負荷条件を指定できます。抵抗負荷は出力からGNDへの抵抗で∞は無負荷、電流負荷は出力から引き抜く電流で0Aは無負荷です。電流負荷の計算には Vin が必要です</li>
          <li>無負荷は記号入力に依存せず、抵抗負荷は∞ボタン、電流負荷は0Aボタンで設定できます。この操作はVR調整なし/ありの両方で使えます</li>
          <li>分圧は R1/R2、実比率、負荷込み出力電圧、総抵抗、誤差、分圧列の回路電流、各抵抗の消費電力、抵抗合計電力を表示します</li>
          <li>登録済み在庫部品の値だけを使った探索もできます</li>
          <li>可変抵抗 + 固定抵抗は、基準抵抗値と可変幅から要求範囲を作ります。可変幅は % と Ω の両方を使えます</li>
          <li>基準位置は上限基準、中心基準、下限基準から選べます。上限基準では 10kΩ、20% が 8kΩ〜10kΩ になります</li>
          <li>理想値と標準値候補は分けて表示し、8kΩ のような理想値だけを採用候補として扱いません</li>
          <li>固定抵抗を標準値へ丸めて範囲を満たせない場合は、可変抵抗を範囲側へ補正して固定抵抗を再計算します</li>
          <li>VR調整ありは、出力下限/上限と指定した基準VR値から R上/R下 の固定抵抗候補を出し、負荷込みで端点を計算します。基準VR値は設計で使うVR素子の公称値として固定し、10kΩ指定時に200Ωのような大きく異なるVR値は採用しません。候補値ソース、許容誤差、総抵抗範囲はVR調整なしと同じ入力を使い、最大回路電流とR上/VR/R下の最大消費電力を表示します</li>
          <li>現実装は公称値と無負荷/指定負荷モデルです。許容差、VRワイパ抵抗、電力、負荷の動的変動などは別途確認が必要なためPASSは出さず、範囲を含む候補はCHECK、足りない候補はWARNで表示します</li>
        </ul>

        <h3 class="font-semibold mb-3">エンジニアリング計算（Calc）</h3>
        <p class="mb-2 opacity-80">電子回路設計に特化した計算機です。</p>
        <ul class="list-disc list-inside space-y-1 mb-5 opacity-80">
          <li>SI 接頭辞（p/n/μ/m/k/M/G/T）と PC 系接頭辞（Ki/Mi/Gi/Ti）に対応</li>
          <li>複素数演算。虚数は <span class="help-code">j</span> を使用。極座標表記（deg/rad 切り替え）対応</li>
          <li>論理演算（and/or/not/exor/exnor/nor/nand）対応</li>
          <li>進数表示（2進/8進/16進）。最大ビット数・signed/unsigned を選択可</li>
          <li><span class="help-code">solve(式, 変数)</span> で方程式を解ける（例: <span class="help-code">solve(10=1/((1/x)+(1/20)), x)</span>）</li>
          <li>ユーザー定義関数、配列集計、色コード、日時差分に対応</li>
          <li>計算履歴が残り、過去の式を再利用できます</li>
          <li>関数一覧、補完候補、構文表示、固定式、結果コピーを使えます</li>
        </ul>

        <h3 class="font-semibold mb-2">設計解析ツール</h3>
        <p class="mb-2 opacity-80">ADC 設計・電解コンデンサ寿命推定・センサ分圧/温度変換・電流検出・インタフェース余裕解析・電源余裕解析・ロジックIC参照・コネクタ視点補助・0Ω/未実装/ジャンパ整理など、専門的な設計解析ツールがタブで並んでいます。</p>
        <ul class="list-disc list-inside space-y-1 opacity-80">
          <li>入力は <span class="help-code">基本条件</span> <span class="help-code">最悪条件</span> <span class="help-code">部品定格</span> <span class="help-code">出力・保存</span> に整理されています</li>
          <li>結果カードには PASS/WARN/FAIL/CHECK、支配要因、不足条件、次アクション、コピー用サマリが表示されます</li>
          <li>条件不足のTVS、ヒューズ、保護協調、ロジックIC参照、コネクタ定格未確認は PASS にせず CHECK と不足条件を返します</li>
          <li>コネクタ/ケーブル/ジャンパ系では mating face/solder side、Pin1、量産初期値、BOM注記、重複Ref、未定義状態を確認できます</li>
          <li>登録部品を指定すると、その部品のスペック値を解析入力へ反映できます</li>
          <li>解析条件は案件、部品、BOMの行番号や識別名と一緒に保存でき、保存済み解析との差分比較もできます</li>
          <li>MCU ADC入力、3.3V電源、I2Cバス、TVS保護の解析テンプレートを入力へ反映または複製保存できます</li>
        </ul>
      </section>

      <!-- Altium 連携 -->
      <section id="altium">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">Altium 連携</h2>
        <p class="mb-4 opacity-80">Altium Designer の SchLib / PcbLib ファイルをシステムに登録し、部品とシンボル名・フットプリント名を紐付けます。</p>

        <h3 class="font-semibold mb-3">ライブラリを登録する（管理者のみ）</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">全機能一覧 →「Altium 連携」→「+ ライブラリを追加」</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">種別（SchLib / PcbLib）・ライブラリ名・ファイルパスを入力して保存</span></li>
        </ol>

        <h3 class="font-semibold mb-2">部品にシンボル・フットプリントを紐付ける</h3>
        <ol class="space-y-2 mb-0">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">部品詳細ページの「Altium リンク」セクションを開く</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">登録済みのライブラリを選択し、シンボル名またはフットプリント名を入力して保存</span></li>
        </ol>
      </section>

      <!-- CSV インポート -->
      <section id="csv">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">CSV インポート</h2>
        <p class="mb-4 opacity-80">全機能一覧 →「CSV インポート」から操作します。既存スプレッドシートやデータベースから部品データをまとめて登録できます。</p>

        <h3 class="font-semibold mb-3">インポートの流れ（4ステップ）</h3>
        <ol class="space-y-3 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80"><strong>テンプレート取得</strong> — 「テンプレートをダウンロード」で CSV テンプレートを取得します</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80"><strong>データ記入</strong> — テンプレートの列に従って部品データを入力します。型番は必須。部品分類・パッケージ・商社はマスタに登録済みの名称を使います</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80"><strong>アップロード・プレビュー</strong> — 記入済み CSV をアップロードするとプレビューが表示されます。列のマッピングを確認してください</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">4</span><span class="opacity-80"><strong>インポート実行</strong> — 「インポート実行」で一括登録します</span></li>
        </ol>
        <p class="opacity-80">エラーがある行はスキップして正常行だけ登録されます。エラーの詳細はインポート後の結果画面で行番号付きで確認できます。</p>
      </section>

      <!-- データのバックアップ -->
      <section id="backup">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">データのバックアップ</h2>
        <p class="mb-4 opacity-80">全機能一覧 →「データのバックアップ」から操作します。管理者のみが使える機能です。</p>

        <h3 class="font-semibold mb-3">バックアップを取得する</h3>
        <ol class="space-y-2 mb-3">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">「📥 ダウンロード」ボタンをクリック</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80"><span class="help-code">bitskeep_YYYYMMDD_HHmmss.sql.gz</span> がダウンロードされます</span></li>
        </ol>
        <p class="mb-5 opacity-80">定期的に手元に保管してください。このファイルが障害時の唯一の復旧手段になります。</p>

        <h3 class="font-semibold mb-3">バックアップから書き戻す</h3>
        <div class="warn-banner mb-3">
          <p class="opacity-90">⚠ 現在の登録データがすべて上書きされます。復元する前に必ず最新のバックアップを取得してください。</p>
        </div>
        <ol class="space-y-2 mb-0">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">「バックアップから書き戻す」セクションへ移動</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80"><span class="help-code">.sql</span> または <span class="help-code">.sql.gz</span> ファイルをアップロード</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">確認ダイアログで「OK」→ 書き戻し実行</span></li>
        </ol>
      </section>

      <!-- Notion 連携設定 -->
      <section id="notion">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">Notion 連携設定</h2>
        <p class="mb-4 opacity-80">全機能一覧 →「連携設定」から操作します。管理者のみが設定できます。Notion 上の案件データを BitsKeep に同期するために必要な設定です。</p>

        <h3 class="font-semibold mb-3">Notion API トークンを取得する</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">Notion の「インテグレーション設定」（notion.so/my-integrations）でインテグレーションを作成</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">「内部インテグレーショントークン」をコピー</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">同期したい Notion データベースを開き、右上メニュー →「コネクト」でインテグレーションを追加</span></li>
        </ol>

        <h3 class="font-semibold mb-3">BitsKeep に設定する</h3>
        <ol class="space-y-2 mb-5">
          <li class="flex gap-3"><span class="step-badge mt-0.5">1</span><span class="opacity-80">全機能一覧 →「連携設定」を開く</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">2</span><span class="opacity-80">Notion API トークンを入力して「保存」</span></li>
          <li class="flex gap-3"><span class="step-badge mt-0.5">3</span><span class="opacity-80">同期対象の事業データベース ID を入力して「保存」（Notion の URL にある 32 文字の ID）</span></li>
        </ol>
        <p class="mb-5 opacity-80">設定済みのトークンはマスク表示されます。「削除」ボタンを明示的に押さない限り、フォームを保存しても既存のトークンは上書きされません。</p>

        <h3 class="font-semibold mb-2">同期する</h3>
        <p class="opacity-80">「案件管理」→「同期」ボタンで Notion から案件データを取り込みます。同期結果（成功 / 失敗 / 件数）が画面上に表示されます。同期エラーの場合は原因（認証失敗 / データベース未共有 / 0件など）が表示され、次のアクションへの導線が出ます。</p>
      </section>

      <!-- 困ったときは -->
      <section id="trouble">
        <h2 class="text-lg font-bold mb-5 pb-2 border-b border-[var(--color-border)]">困ったときは</h2>
        <table class="help-table">
          <thead><tr><th>困りごと</th><th>対処方法</th></tr></thead>
          <tbody>
            <tr><td class="py-2">部品が見つからない</td><td class="py-2 opacity-80">部品一覧の「詳細条件」でメーカー・パッケージ・スペック範囲を試す。<a href="#parts" class="underline">詳細フィルタ</a> を参照</td></tr>
            <tr><td class="py-2">在庫数が実物と合わない</td><td class="py-2 opacity-80">部品詳細の「在庫履歴」で入出庫ログを確認し、原因を特定。その後「<a href="#inventory" class="underline">棚卸し</a>」で修正</td></tr>
            <tr><td class="py-2">商社を選択できない</td><td class="py-2 opacity-80">「<a href="#supplier" class="underline">商社管理</a>」で先に商社を登録する（管理者のみ）</td></tr>
            <tr><td class="py-2">パッケージが候補に出ない</td><td class="py-2 opacity-80">「<a href="#master" class="underline">マスタ管理 → パッケージ分類 → パッケージ詳細</a>」に追加する</td></tr>
            <tr><td class="py-2">部品分類が候補に出ない</td><td class="py-2 opacity-80">「<a href="#master" class="underline">マスタ管理 → 部品分類</a>」に追加する</td></tr>
            <tr><td class="py-2">スペック詳細が候補に出ない</td><td class="py-2 opacity-80">「<a href="#master" class="underline">マスタ管理 → スペック詳細</a>」に追加する。部品登録・詳細編集のスペック行からも管理者ならその場で追加できます</td></tr>
            <tr><td class="py-2">スペックの単位が出ない</td><td class="py-2 opacity-80">「<a href="#master" class="underline">マスタ管理 → スペック詳細</a>」で基準単位を設定する</td></tr>
            <tr><td class="py-2">入力を間違えて保存してしまった</td><td class="py-2 opacity-80">部品詳細の「編集」で修正する。管理者は「<a href="#auditlog" class="underline">操作ログ</a>」で変更前の値を確認できる</td></tr>
            <tr><td class="py-2">Notion 同期が失敗する</td><td class="py-2 opacity-80">「<a href="#notion" class="underline">連携設定</a>」でトークンとデータベースIDを再確認。Notion 側でインテグレーションへのアクセス許可も確認する</td></tr>
            <tr><td class="py-2">発注リストが消えた</td><td class="py-2 opacity-80">発注画面はブラウザ内に一時保存しています。ブラウザの保存データを消すと発注リストも消えます</td></tr>
            <tr><td class="py-2">画面の表示が崩れる</td><td class="py-2 opacity-80">ブラウザをハードリロード（<kbd class="help-code">Ctrl+Shift+R</kbd>）する</td></tr>
            <tr><td class="py-2">登録データを以前の状態に戻したい</td><td class="py-2 opacity-80">「<a href="#backup" class="underline">データのバックアップ → バックアップから書き戻す</a>」を参照（管理者のみ）</td></tr>
            <tr><td class="py-2">権限を上げてほしい</td><td class="py-2 opacity-80">管理者に「<a href="#users" class="underline">ユーザー管理 → ロール変更</a>」を依頼する</td></tr>
            <tr><td class="py-2">アーカイブしたマスタを元に戻したい</td><td class="py-2 opacity-80">「<a href="#master" class="underline">マスタ管理</a>」でアーカイブ済み表示をオンにして「復元」</td></tr>
            <tr><td class="py-2">廃止した棚が必要になった</td><td class="py-2 opacity-80">「<a href="#location" class="underline">保管棚管理</a>」で廃止済み棚を表示して「復元」（管理者のみ）</td></tr>
          </tbody>
        </table>
      </section>

    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '使い方ガイド', 'current' => true]], 'class' => 'mt-12'])
</div>

<script>
// スクロール連動で現在表示中の章を目次でハイライト
(function () {
  const sections = document.querySelectorAll('section[id]');
  const tocLinks = document.querySelectorAll('#toc .toc-link');
  if (!sections.length || !tocLinks.length) return;

  const linkMap = {};
  tocLinks.forEach(link => {
    const id = link.getAttribute('href').replace('#', '');
    linkMap[id] = link;
  });

  const setActive = (id) => {
    tocLinks.forEach(l => l.classList.remove('is-active'));
    if (linkMap[id]) linkMap[id].classList.add('is-active');
  };

  // rootMargin: 上60px（ヘッダ分）を除いた領域の上端25%に入ったら発火
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) setActive(entry.target.id);
    });
  }, { rootMargin: '-60px 0px -70% 0px', threshold: 0 });

  sections.forEach(s => observer.observe(s));

  // 初期状態
  setActive(sections[0].id);
})();
</script>
</body>
</html>
