CLAUDE.md を読んでから作業する。

ユーザー依頼:

パッケージ詳細の並び替えと、スペック候補設定の並び替えのUIデザインちょっとだけ違うの、ルール違反。
あと、設計されてるけど未実装を全部実装して。実装、テスト、ログ、ドキュメントをマルチワーカーで並列処理して。

実行条件:
- `/web/documents/BitsKeep` を作業ルートにする。
- まず `CLAUDE.md` を読む。
- 既存の汚れたワークツリーを前提に、ユーザーや他エージェントの変更を戻さない。
- `apply_patch` で編集する。
- 明示的にサブワーカーを使い、実装、テスト、ログ、ドキュメントを並列化する。
- ただし未実装項目は全リポジトリの無限スコープに広げず、直近のマスタ管理・スペック詳細・接頭語・並び替えUIに関係する設計済み未実装から優先して実装する。範囲外の巨大機能が残る場合は、仕様・チェックリストへ未着手理由と分割計画を残す。
- 最低限、パッケージ詳細とスペック候補設定のドラッグ&ドロップUIデザインは同一ルールへ揃える。
- 実装後は `node --check resources/js/pages/master-list.js`、`php -l resources/views/app/master-list.blade.php`、`php -l resources/views/app/help.blade.php`、`npm run build`、`php artisan test --filter=UiApiSurfaceSmokeTest`、`php artisan test`、`php artisan view:cache`、`php artisan view:clear`、`git diff --check` を実行する。
- `制作管理/makinglog.md` に開始・完了・テスト結果を書く。
- 最終メッセージは日本語で `[大将]` から始め、変更点と検証結果を簡潔にまとめる。
