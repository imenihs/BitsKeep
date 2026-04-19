<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>使い方ガイド - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '使い方ガイド'])
<div class="px-4 py-4 sm:px-6 sm:py-6 max-w-4xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '使い方ガイド', 'current' => true]]])

  <header class="mb-8 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">BitsKeep 使い方ガイド</h1>
    <p class="text-sm opacity-60 mt-1">電子部品の在庫・調達・案件を一元管理するシステムの操作手順</p>
  </header>

  <div class="flex gap-8">
    <!-- 目次（サイドバー） -->
    <nav class="hidden lg:block w-52 shrink-0">
      <div class="sticky top-6 text-sm space-y-1">
        <div class="font-semibold mb-2 opacity-60 text-xs uppercase tracking-wider">目次</div>
        <a href="#login" class="block py-1 opacity-70 hover:opacity-100 no-underline">ログインとアカウント</a>
        <a href="#overview" class="block py-1 opacity-70 hover:opacity-100 no-underline">画面の見方</a>
        <a href="#register" class="block py-1 opacity-70 hover:opacity-100 no-underline">部品を登録する</a>
        <a href="#inventory" class="block py-1 opacity-70 hover:opacity-100 no-underline">在庫を管理する</a>
        <a href="#order" class="block py-1 opacity-70 hover:opacity-100 no-underline">発注する</a>
        <a href="#project" class="block py-1 opacity-70 hover:opacity-100 no-underline">案件に部品を紐付ける</a>
        <a href="#master" class="block py-1 opacity-70 hover:opacity-100 no-underline">マスタを整備する</a>
        <a href="#users" class="block py-1 opacity-70 hover:opacity-100 no-underline">ユーザーを追加する</a>
        <a href="#trouble" class="block py-1 opacity-70 hover:opacity-100 no-underline">困ったときは</a>
        <a href="#admin" class="block py-1 opacity-70 hover:opacity-100 no-underline">管理者向け操作</a>
      </div>
    </nav>

    <!-- 本文 -->
    <div class="flex-1 min-w-0 space-y-10 text-sm leading-relaxed">

      <!-- ログインとアカウント -->
      <section id="login">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">ログインとアカウント</h2>

        <h3 class="font-semibold mb-2">初回ログイン</h3>
        <p class="mb-4 opacity-80">管理者から招待メールが届いたら、メール内のリンクからパスワードを設定してログインします。</p>

        <h3 class="font-semibold mb-2">権限について</h3>
        <p class="mb-2 opacity-80">ログイン後、画面右上に自分の権限が表示されます。権限を変更したい場合は管理者に連絡してください。</p>
        <table class="w-full border-collapse text-xs mb-4">
          <thead><tr class="border-b border-[var(--color-border)] opacity-60"><th class="py-1.5 pr-4 text-left">権限</th><th class="py-1.5 text-left">できること</th></tr></thead>
          <tbody>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4 font-medium"><span class="role-pill role-admin">管理</span></td><td class="py-1.5 opacity-80">すべての操作</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4 font-medium"><span class="role-pill role-editor">編集</span></td><td class="py-1.5 opacity-80">部品・在庫の登録・編集</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4 font-medium"><span class="role-pill role-viewer">閲覧</span></td><td class="py-1.5 opacity-80">一覧・詳細の参照のみ</td></tr>
          </tbody>
        </table>
      </section>

      <!-- 画面の見方 -->
      <section id="overview">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">画面の見方</h2>
        <h3 class="font-semibold mb-2">ヘッダ</h3>
        <ul class="list-disc list-inside space-y-1 mb-4 opacity-80">
          <li>左の「BitsKeep」ロゴ → ダッシュボードへ戻る</li>
          <li>「使い方」→ このページ</li>
          <li>「全機能一覧」→ すべての画面へのショートカット</li>
          <li>右上のロールバッジ → 自分の権限表示</li>
        </ul>
        <h3 class="font-semibold mb-2">ダッシュボード</h3>
        <ul class="list-disc list-inside space-y-1 opacity-80">
          <li><strong>今日の確認事項</strong> — 在庫警告件数と最近アクセスした部品</li>
          <li><strong>業務別メニュー</strong> — 在庫入力・発注・案件管理などのショートカット</li>
          <li><strong>Ctrl+K</strong> — どのページからでも使えるグローバル検索</li>
        </ul>
      </section>

      <!-- 部品を登録する -->
      <section id="register">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">部品を登録する</h2>

        <h3 class="font-semibold mb-2">新規登録</h3>
        <ol class="list-decimal list-inside space-y-1 mb-4 opacity-80">
          <li>「部品一覧」→ 右上「+ 新規登録」</li>
          <li>型番・名称・分類・パッケージ・メーカー・入手可否を入力</li>
          <li>スペック（容量・耐圧など）を「+ スペック追加」で入力</li>
          <li>仕入先・商社型番・単価を「仕入先」セクションに入力</li>
          <li>必要に応じて画像やデータシートを添付</li>
          <li>「保存」で確定</li>
        </ol>

        <h3 class="font-semibold mb-2">既存部品を複製して登録</h3>
        <p class="mb-4 opacity-80">部品詳細ページの「複製」ボタンを使うと、型番と差分だけ修正して素早く登録できます。</p>

        <h3 class="font-semibold mb-2">部品を探す</h3>
        <ul class="list-disc list-inside space-y-1 mb-4 opacity-80">
          <li>キーワード検索（型番・名称・メーカー）</li>
          <li>分類・パッケージ・スペック値の範囲絞り込み</li>
          <li>在庫状態（在庫あり・切れ・警告中）でのフィルタ</li>
          <li>お気に入り登録した部品だけ表示</li>
        </ul>

        <h3 class="font-semibold mb-2">部品を比較する</h3>
        <p class="opacity-80">一覧でチェックボックスを選び（最大5件）、「比較」ボタンで仕様・価格を横並び表示します。</p>
      </section>

      <!-- 在庫を管理する -->
      <section id="inventory">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">在庫を管理する</h2>

        <h3 class="font-semibold mb-2">入庫する</h3>
        <p class="mb-1 opacity-80"><strong>部品詳細から:</strong> 部品詳細ページ → 右上「入庫」→ 棚・数量・新品/中古を入力して確定</p>
        <p class="mb-4 opacity-80"><strong>まとめて入庫:</strong> ダッシュボードまたは全機能一覧から「在庫入力」を使うと複数部品を一度に処理できます</p>

        <h3 class="font-semibold mb-2">出庫する</h3>
        <p class="mb-4 opacity-80">部品詳細ページ → 「出庫」ボタン → 数量・用途を入力して確定</p>

        <h3 class="font-semibold mb-2">在庫履歴を確認する</h3>
        <p class="mb-4 opacity-80">部品詳細ページ下部の「在庫履歴」で、いつ誰が入出庫したかのログを確認できます。</p>

        <h3 class="font-semibold mb-2">棚卸しをする</h3>
        <ol class="list-decimal list-inside space-y-1 opacity-80">
          <li>「保管棚」→ 右上「棚卸しモード」をオン</li>
          <li>画面上部のオレンジのバーに実際の在庫数を入力</li>
          <li>「確定」で保存（システム在庫と差分があれば調整されます）</li>
        </ol>
      </section>

      <!-- 発注する -->
      <section id="order">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">発注する</h2>

        <h3 class="font-semibold mb-2">在庫警告を確認する</h3>
        <p class="mb-4 opacity-80">ダッシュボードの「在庫警告」バッジ、または全機能一覧の「在庫警告」で、発注点を下回った部品の一覧を確認します。</p>

        <h3 class="font-semibold mb-2">発注リストを作って CSV 出力する</h3>
        <ol class="list-decimal list-inside space-y-1 opacity-80">
          <li>在庫警告画面で発注したい部品にチェックを入れる</li>
          <li>「発注リストへ追加」で発注画面に送る</li>
          <li>発注画面で商社・購入単位・数量を設定</li>
          <li>「〇〇商社 CSV出力」で商社ごとに発注シートを出力</li>
        </ol>
      </section>

      <!-- 案件に部品を紐付ける -->
      <section id="project">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">案件に部品を紐付ける</h2>

        <h3 class="font-semibold mb-2">案件を作成する</h3>
        <p class="mb-4 opacity-80">全機能一覧 → 「案件管理」→「+ 案件を追加」で案件名と事業を入力します。</p>

        <h3 class="font-semibold mb-2">使用部品を追加する</h3>
        <p class="mb-4 opacity-80">案件詳細の「使用部品」セクションから部品を検索して追加します。使用数も記録できます。</p>

        <h3 class="font-semibold mb-2">Notion と連携する</h3>
        <p class="opacity-80">「連携設定」で Notion API トークンと案件データベース ID を設定すると、Notion の案件データを同期できます。</p>
      </section>

      <!-- マスタを整備する -->
      <section id="master">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">マスタを整備する</h2>
        <p class="mb-4 opacity-80">部品登録時に選ぶ「分類」「パッケージ」「スペック種別」「商社」「保管棚」は、あらかじめ登録が必要です。</p>
        <table class="w-full border-collapse text-xs mb-4">
          <thead><tr class="border-b border-[var(--color-border)] opacity-60"><th class="py-1.5 pr-4 text-left">マスタ</th><th class="py-1.5 pr-4 text-left">画面</th><th class="py-1.5 text-left">何を登録するか</th></tr></thead>
          <tbody>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4">部品分類</td><td class="py-1.5 pr-4 opacity-60">マスタ管理 → 分類</td><td class="py-1.5 opacity-80">抵抗・コンデンサ・FPGA など</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4">パッケージ分類</td><td class="py-1.5 pr-4 opacity-60">マスタ管理 → パッケージ分類</td><td class="py-1.5 opacity-80">SMD・THT など大分類</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4">詳細パッケージ</td><td class="py-1.5 pr-4 opacity-60">マスタ管理 → 詳細パッケージ</td><td class="py-1.5 opacity-80">0402・SOT-23 など個別形状</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4">スペック種別</td><td class="py-1.5 pr-4 opacity-60">マスタ管理 → スペック種別</td><td class="py-1.5 opacity-80">容量・耐圧・周波数 など</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4">商社</td><td class="py-1.5 pr-4 opacity-60">商社管理</td><td class="py-1.5 opacity-80">仕入先・購入先の会社情報</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-1.5 pr-4">保管棚</td><td class="py-1.5 pr-4 opacity-60">保管棚管理</td><td class="py-1.5 opacity-80">棚の名称・グループ・番号</td></tr>
          </tbody>
        </table>
        <p class="opacity-80">使わなくなったマスタは「廃止（アーカイブ）」にすると選択候補から消えます。過去データへの影響はなく、「復元」でいつでも元に戻せます。</p>
      </section>

      <!-- ユーザーを追加する -->
      <section id="users">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">ユーザーを追加する <span class="text-xs font-normal opacity-50">管理者のみ</span></h2>
        <ol class="list-decimal list-inside space-y-1 mb-4 opacity-80">
          <li>全機能一覧 → 「ユーザー管理」</li>
          <li>「招待」ボタンでメールアドレスとロールを入力して送信</li>
          <li>相手が受信メールのリンクからパスワードを設定してログイン完了</li>
        </ol>
        <p class="opacity-80">ロールの変更は、ユーザー管理画面の一覧から「変更」ボタンで行えます。</p>
      </section>

      <!-- 困ったときは -->
      <section id="trouble">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">困ったときは</h2>
        <table class="w-full border-collapse text-xs">
          <thead><tr class="border-b border-[var(--color-border)] opacity-60"><th class="py-1.5 pr-4 text-left">困りごと</th><th class="py-1.5 text-left">対処方法</th></tr></thead>
          <tbody>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">部品が見つからない</td><td class="py-2 opacity-80">部品一覧の詳細検索（メーカー・パッケージ・スペック範囲）を試す</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">在庫数が実物と合わない</td><td class="py-2 opacity-80">部品詳細の「在庫履歴」で入出庫ログを確認し、棚卸しで修正</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">商社を選択できない</td><td class="py-2 opacity-80">「商社管理」で先に商社を登録する</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">パッケージが候補に出ない</td><td class="py-2 opacity-80">「マスタ管理 → パッケージ分類 → 詳細パッケージ」に追加する</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">分類が候補に出ない</td><td class="py-2 opacity-80">「マスタ管理 → 分類」に追加する</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">入力を間違えて保存してしまった</td><td class="py-2 opacity-80">部品詳細の「編集」で修正する（管理者は操作ログで変更履歴を確認できます）</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">Notion 同期が失敗する</td><td class="py-2 opacity-80">「連携設定」で Notion トークンと DB ID を確認する</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">画面の表示が崩れる</td><td class="py-2 opacity-80">ブラウザをハードリロード（Ctrl+Shift+R）する</td></tr>
            <tr class="border-b border-[var(--color-border)]"><td class="py-2 pr-4">権限を上げてほしい</td><td class="py-2 opacity-80">管理者に連絡する</td></tr>
          </tbody>
        </table>
      </section>

      <!-- 管理者向け操作 -->
      <section id="admin">
        <h2 class="text-lg font-bold mb-4 pb-2 border-b border-[var(--color-border)]">管理者向け操作</h2>

        <h3 class="font-semibold mb-2">操作ログを確認する</h3>
        <p class="mb-4 opacity-80">全機能一覧 → 「操作ログ」で、誰がいつ何を変更したかを確認できます。日付・操作者・操作種別でフィルタできます。</p>

        <h3 class="font-semibold mb-2">CSV で部品をまとめて登録する</h3>
        <ol class="list-decimal list-inside space-y-1 mb-4 opacity-80">
          <li>全機能一覧 → 「CSV インポート」</li>
          <li>テンプレートをダウンロードして部品データを記入</li>
          <li>ファイルをアップロードしてインポート実行</li>
        </ol>

        <h3 class="font-semibold mb-2">DB をバックアップ・復元する</h3>
        <p class="mb-2 opacity-80">全機能一覧 → 「DB バックアップ」で操作します。</p>
        <ul class="list-disc list-inside space-y-1 mb-4 opacity-80">
          <li><strong>ダウンロード:</strong>「📥 ダウンロード」ボタンで現時点の DB 全体を保存します。定期的に手元に保管してください。</li>
          <li><strong>書き戻し:</strong> 保存済みの SQL ファイルをアップロードすると DB に上書き反映されます。書き戻す前に必ず最新のバックアップを取得してください。</li>
        </ul>

        <h3 class="font-semibold mb-2">Altium ライブラリを管理する</h3>
        <p class="opacity-80">全機能一覧 → 「Altium 連携」で .SchLib / .PcbLib のパスを登録します。部品詳細ページからシンボル名・フットプリント名をライブラリと紐付けできます。</p>
      </section>

    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '使い方ガイド', 'current' => true]], 'class' => 'mt-10'])
</div>
</body>
</html>
