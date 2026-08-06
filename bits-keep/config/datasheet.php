<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 既定の解析エンジン
    |--------------------------------------------------------------------------
    | 連携設定で未指定の場合に使うエンジン。利用者はエンジンを毎回選ばず、
    | 運用設定としてここまたは連携設定で決める。
    */
    'default_engine' => env('DATASHEET_ENGINE', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | 解析の待ち時間上限（秒）
    |--------------------------------------------------------------------------
    | ワーカー側で解析1件に許す上限。超過した解析は失敗として扱い、
    | 画面から再実行できるようにする。
    |
    | 実測ではテキスト方式が 40〜70 秒、ページ画像方式が 250 秒程度かかる。
    | 画像方式はページ数に比例して伸びるため、上限に余裕を持たせる。
    | 品質は十分なのに時間だけを理由に失敗させると、利用者は原因を掴めない。
    */
    'analysis_timeout' => (int) env('DATASHEET_ANALYSIS_TIMEOUT', 600),

    'claude' => [

        // claude 実行ファイル。PATH に依存させず絶対パス指定を既定にする
        'binary' => env('CLAUDE_BINARY', '/usr/local/bin/claude'),

        /*
        | Claude の設定と認証情報を置くディレクトリ。
        | 認証トークンは自動更新されるため、キューワーカーの実行ユーザから
        | 書き込み可能である必要がある。Webサーバ実行ユーザのホームは使わない。
        */
        'home' => env('CLAUDE_CONFIG_DIR', '/var/lib/bitskeep-claude'),

        // 解析に使うモデル。未指定なら Claude 側の既定モデルに任せる
        'model' => env('CLAUDE_MODEL') ?: null,

        /*
        | 解析ごとの作業ディレクトリを置く親ディレクトリ。
        | リポジトリ配下へ置くと CLAUDE.md などの指示ファイルが読み込まれ
        | 解析プロンプトが汚染されるため、リポジトリ外を既定にする。
        */
        'workspace_root' => env('CLAUDE_WORKSPACE_ROOT', sys_get_temp_dir().'/bitskeep-datasheet'),
    ],

    'pdf' => [

        // PDFからテキスト層を取り出すコマンド
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', '/usr/bin/pdftotext'),

        // PDFをページ画像へ変換するコマンド。テキスト層のないPDFで使う
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', '/usr/bin/pdftoppm'),

        /*
        | テキスト層ありと判断する最小文字数。
        | これを下回るPDFはスキャン原稿と見なしてページ画像へ切り替える。
        */
        'text_threshold' => (int) env('DATASHEET_PDF_TEXT_THRESHOLD', 800),

        /*
        | 解析へ渡すテキストの最大文字数。
        | データシートは巻末に長い注記や履歴が続くため、先頭側を優先して切る。
        */
        'max_text_length' => (int) env('DATASHEET_PDF_MAX_TEXT', 120000),

        /*
        | ページ画像化するときの最大ページ数。
        | 全ページを画像で渡すと利用枠を大きく消費するため上限を設ける。
        */
        'max_image_pages' => (int) env('DATASHEET_PDF_MAX_IMAGE_PAGES', 12),

        // ページ画像の解像度（dpi）。小さすぎると規格表の数値が読めない
        'image_dpi' => (int) env('DATASHEET_PDF_IMAGE_DPI', 150),

        // PDF変換処理1回に許す上限秒数
        'convert_timeout' => (int) env('DATASHEET_PDF_CONVERT_TIMEOUT', 120),
    ],
];
