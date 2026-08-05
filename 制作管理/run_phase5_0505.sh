#!/usr/bin/env bash
set -euo pipefail

# 目的: Phase 5-5 から 5-7 の作業再開をログと通知で促す一回限りリマインダ。
# 入力: cron実行時刻と固定リポジトリパス。出力: makinglogへの追記、任意のデスクトップ通知/syslog。
# 動作条件: crontab が利用できること。副作用: 対象cronマーカーの行を削除する。
REPO_ROOT="/web/BitsKeep"
LOG_FILE="$REPO_ROOT/制作管理/makinglog.md"
PLAN_FILE="$REPO_ROOT/制作管理/実装進捗チェックリスト.md"
MARKER="# bitskeep_phase5_0505_once"

timestamp="$(date '+%Y-%m-%d %H:%M:%S %Z')"

{
  echo
  echo "---"
  echo
  echo "## [大佐] ${timestamp}"
  echo
  echo "### 05:05 スケジュール起動: Phase 5-5 から 5-7 の実行開始リマインド"
  echo
  echo "- ローカルスケジューラから起動した"
  echo "- 対象は \`5-5 エラーハンドリング/再開導線\` \`5-6 UI説明過多の削減と状態駆動UI\` \`5-7 共通表示フォーマット\`"
  echo "- 実行順は \`5-5 -> 5-6 P0 -> 5-6 P1 -> 5-6 P2/P3 -> 5-7\`"
  echo "- Codex セッションを開き、チェックリストとこのログを起点に続行する"
} >> "$LOG_FILE"

if command -v notify-send >/dev/null 2>&1; then
  notify-send "BitsKeep 05:05" "Phase 5-5〜5-7 の実行時間です。Codex を開いてチェックリストから続行してください。"
fi

if command -v logger >/dev/null 2>&1; then
  logger "BitsKeep 05:05 reminder: continue Phase 5-5 to 5-7 from $PLAN_FILE"
fi

tmp_cron="$(mktemp)"
crontab -l 2>/dev/null | grep -v "$MARKER" > "$tmp_cron" || true
crontab "$tmp_cron"
rm -f "$tmp_cron"
