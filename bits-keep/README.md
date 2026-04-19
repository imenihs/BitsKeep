# BitsKeep

電子部品の在庫・調達・案件管理 Web アプリケーション。

---

## 目次

1. [できること](#できること)
2. [各機能の使い方](#各機能の使い方)
3. [困ったときのショートカット](#困ったときのショートカット)
4. [環境の前提と構築方法](#環境の前提と構築方法)
5. [バックアップと復元](#バックアップと復元)
6. [API 一覧](#api-一覧)
7. [ライセンス](#ライセンス)

---

## できること

| 機能 | 概要 |
|---|---|
| 部品管理 | 電子部品の登録・編集・画像/データシート添付・スペック管理 |
| 在庫管理 | 入庫・出庫・棚割り当て・棚卸し・在庫警告 |
| 発注管理 | 在庫警告から発注リストを作成、商社別 CSV 出力 |
| 商社管理 | 仕入先・購入単価・リードタイム登録 |
| 保管棚管理 | 棚のグループ管理・廃止・復元・棚卸しモード |
| マスタ管理 | 部品分類・パッケージ分類・スペック種別 |
| 案件管理 | 案件と使用部品の紐付け・Notion 同期 |
| Altium 連携 | SchLib/PcbLib の登録と部品詳細からのシンボル紐付け |
| 設計ツール | 抵抗網・エンジニアリング計算補助 |
| ユーザー管理 | 招待・ロール設定（管理者/編集者/閲覧者） |
| 操作ログ | 全 CRUD 操作の履歴閲覧（管理者専用） |
| DBバックアップ | pg_dump ダウンロード・SQL アップロードによる書き戻し |

---

## 各機能の使い方

### 部品の登録

1. ヘッダの「部品一覧」→ 右上「+ 新規登録」
2. 型番・名称・分類・パッケージを入力
3. スペック（容量・耐圧など）を「+ スペック追加」で追記
4. 仕入先と単価を「仕入先」セクションに入力
5. 「保存」で確定

### 在庫を増やす（入庫）

1. 部品詳細ページ → 右上「入庫」ボタン
2. 棚・数量・新品/中古・メモを入力して確定
3. もしくは「在庫入力」メニューからまとめて入庫

### 在庫警告と発注

1. ヘッダの「在庫警告」で発注点を下回った部品を確認
2. 対象部品にチェックを入れ「発注リストへ追加」
3. 「発注画面」で商社・購入単位・数量を設定し「CSV 出力」

### 棚卸し

1. 「保管棚」→ 右上「棚卸しモード」をオン
2. 画面上部のステータスバーに実数を入力
3. 「確定」で保存（差分が在庫に反映）

### CSV インポート

1. 「CSV インポート」メニューへ
2. テンプレートをダウンロードして部品データを記入
3. ファイルをアップロードしてインポート実行

### ユーザーを招待する（管理者のみ）

1. 「ユーザー管理」→ 「招待」ボタン
2. メールアドレスとロールを入力して送信
3. 受信したメールのリンクからパスワードを設定してログイン

---

## 困ったときのショートカット

| 困りごと | 参照先 |
|---|---|
| 部品が見つからない | 部品一覧の詳細検索（メーカー・パッケージ・スペック範囲）を使う |
| 在庫数が合わない | 部品詳細の「在庫履歴」で入出庫ログを確認 |
| 商社が選べない | 「商社管理」で商社を先に登録する |
| パッケージが選べない | 「マスタ管理」→「パッケージ分類」→「詳細パッケージ」に追加 |
| Notion 同期が失敗する | 「連携設定」で Notion トークンと DB ID を確認 |
| 操作を間違えた | 「操作ログ」（管理者）で変更内容を確認、手動修正する |
| DB を以前の状態に戻したい | 「DBバックアップ」からダウンロード済みのダンプを書き戻す |
| ページの CSS/JS が壊れた | サーバーで `rm public/hot` を実行後にリロード |

---

## 環境の前提と構築方法

### 本番環境

| 項目 | 値 |
|---|---|
| OS | Ubuntu 22.04 |
| Web サーバー | Apache2 + PHP-FPM |
| PHP | 8.3 |
| フレームワーク | Laravel 12 |
| フロントエンド | Vue 3 + Vite + Tailwind CSS 3 |
| DB | PostgreSQL 16 |
| アクセス URL | https://bits-keep.rwc.0t0.jp/ |

### 初期構築手順

```bash
# 1. リポジトリ取得
git clone <repo> /web/documents/BitsKeep
cd /web/documents/BitsKeep/bits-keep

# 2. PHP 依存インストール
composer install --no-dev

# 3. JS 依存インストール＆ビルド
npm ci
npm run build

# 4. 環境設定
cp .env.example .env
php artisan key:generate
# .env の DB_* / APP_URL / FILESYSTEM_DISK を設定

# 5. DB マイグレーション（本番接続先を確認してから実行）
php artisan migrate --force

# 6. ストレージリンク
php artisan storage:link

# 7. Apache の DocumentRoot を bits-keep/public/ に向ける
```

### コード変更後の反映

```bash
cd bits-keep
npm run build        # フロントエンド再ビルド（必須）
# その後ブラウザをハードリロード
```

### やってはいけない操作

| 操作 | 理由 |
|---|---|
| `composer run dev` | `public/hot` が生成され CSS/JS が全滅する |
| `php artisan serve` | Apache と競合する |
| `public/hot` を残したまま | Vite HMR に接続しようとして失敗する |
| `.env` を SQLite に変更して `migrate --force` | 本番 DB が空になる（2026-04-12 事故再現） |

### DB 変更前の必須チェック

1. 接続先確認（`DB_HOST` / `DB_DATABASE` を目視）
2. 件数確認（`SELECT COUNT(*) FROM components` など）
3. バックアップ確認（ダウンロード済みのダンプがあるか）
4. 復元手段確認（ダンプから `psql` で書き戻せる状態か）

4 点未確認のまま破壊的 DB 操作を実行してはならない。

---

## バックアップと復元

### バックアップ取得（UI）

1. ログイン（管理者アカウント）
2. ヘッダ右上メニュー → 「DBバックアップ」
3. 「📥 ダウンロード」ボタン → `bitskeep_YYYYMMDD_HHmmss.sql.gz` を保存

### 手動取得（CLI）

```bash
PGPASSWORD=postgres pg_dump \
  -h 127.0.0.1 -p 5432 -U postgres bitskeep \
  | gzip > /tmp/bitskeep_$(date +%Y%m%d).sql.gz
```

### 書き戻し（UI）

1. 「DBバックアップ」→「バックアップから書き戻す」
2. `.sql` または `.sql.gz` ファイルをアップロード
3. 確認ダイアログで「OK」→ 書き戻し実行

### 書き戻し（CLI）

```bash
# .sql.gz の場合
zcat /tmp/bitskeep_YYYYMMDD.sql.gz \
  | PGPASSWORD=postgres psql -h 127.0.0.1 -p 5432 -U postgres bitskeep

# .sql の場合
PGPASSWORD=postgres psql -h 127.0.0.1 -p 5432 -U postgres bitskeep \
  < /tmp/bitskeep_YYYYMMDD.sql
```

### ファイル（画像・データシート）のバックアップ

部品画像・データシートは `bits-keep/storage/app/public/` に保存されている。

```bash
# アーカイブ作成
tar -czf /tmp/bitskeep_files_$(date +%Y%m%d).tar.gz \
  /web/documents/BitsKeep/bits-keep/storage/app/public/

# 復元
tar -xzf /tmp/bitskeep_files_YYYYMMDD.tar.gz -C /
```

---

## API 一覧

全エンドポイントは `/api/` プレフィックス、認証必須（Cookie セッション）。

| メソッド | パス | 概要 | 権限 |
|---|---|---|---|
| GET | `/api/components` | 部品一覧（ページネーション・フィルタ対応） | 全員 |
| POST | `/api/components` | 部品登録 | 編集者以上 |
| GET | `/api/components/{id}` | 部品詳細 | 全員 |
| PUT | `/api/components/{id}` | 部品更新 | 編集者以上 |
| DELETE | `/api/components/{id}` | 部品削除 | 管理者 |
| GET | `/api/categories` | 分類一覧 | 全員 |
| POST | `/api/categories` | 分類登録 | 編集者以上 |
| GET | `/api/package-groups` | パッケージ分類一覧 | 全員 |
| GET | `/api/packages` | 詳細パッケージ一覧 | 全員 |
| GET | `/api/spec-types` | スペック種別一覧 | 全員 |
| GET | `/api/suppliers` | 商社一覧 | 全員 |
| POST | `/api/suppliers` | 商社登録 | 編集者以上 |
| GET | `/api/locations` | 保管棚一覧 | 全員 |
| POST | `/api/locations` | 棚登録 | 管理者 |
| POST | `/api/locations/inventory` | 棚卸し確定 | 編集者以上 |
| GET | `/api/stock-alerts` | 在庫警告一覧 | 全員 |
| GET | `/api/stock-orders/component/{id}/pending` | 発注中注文確認 | 全員 |
| POST | `/api/transactions` | 入出庫登録 | 編集者以上 |
| GET | `/api/projects` | 案件一覧 | 全員 |
| GET | `/api/businesses` | 事業一覧 | 全員 |
| GET | `/api/altium-links` | Altium ライブラリ一覧 | 全員 |
| GET | `/api/users` | ユーザー一覧 | 管理者 |
| PATCH | `/api/users/{id}/role` | ロール変更 | 管理者 |
| PATCH | `/api/users/{id}/active` | 有効化/無効化 | 管理者 |
| GET | `/api/audit-logs` | 操作ログ | 管理者 |
| GET | `/api/backup/download` | DB ダンプダウンロード | 管理者 |
| POST | `/api/backup/restore` | DB 書き戻し | 管理者 |
| GET | `/api/settings/integrations/notion` | Notion 設定取得 | 管理者 |
| PUT | `/api/settings/integrations/notion` | Notion 設定保存 | 管理者 |

---

## ライセンス

BitsKeep 本体のコードは非公開・個人事業用途。

### 使用ライブラリ

| ライブラリ | ライセンス |
|---|---|
| Laravel 12 | MIT |
| Vue 3 | MIT |
| Vite | MIT |
| Tailwind CSS 3 | MIT |
| PostgreSQL (pg_dump) | PostgreSQL License |
| PHP 8.3 | PHP License |
