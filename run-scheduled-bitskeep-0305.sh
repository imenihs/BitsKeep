#!/usr/bin/env bash
set -euo pipefail

# 目的: 2026-04-29 03:05 の一回限りCodex自動実行をログ付きで起動する。
# 入力: 固定プロンプトファイルとcron環境。出力: scheduled_logs 配下の実行ログと最終応答。
# 動作条件: codex CLI と crontab が利用できること。副作用: 一回限りcronエントリを削除する。
WORKDIR="/web/BitsKeep"
PROMPT_FILE="$WORKDIR/scheduled-bitskeep-0305-prompt.md"
LOG_DIR="$WORKDIR/制作管理/scheduled_logs"
STAMP="$(date '+%Y%m%d_%H%M%S')"
RUN_LOG="$LOG_DIR/bitskeep_0305_${STAMP}.log"
LAST_MESSAGE="$LOG_DIR/bitskeep_0305_${STAMP}_final.md"
CRON_MARKER="# BitsKeep scheduled 0305 2026-04-29"

mkdir -p "$LOG_DIR"

{
  echo "[$(date '+%Y-%m-%d %H:%M:%S %Z')] scheduled job start"
  cd "$WORKDIR"
  set +e
  /usr/local/bin/codex exec \
    -C "$WORKDIR" \
    --sandbox workspace-write \
    --ask-for-approval never \
    --output-last-message "$LAST_MESSAGE" \
    - < "$PROMPT_FILE"
  status=$?
  set -e
  echo "[$(date '+%Y-%m-%d %H:%M:%S %Z')] codex exit status: $status"
  if crontab -l >/tmp/bitskeep_cron_current.$$ 2>/dev/null; then
    grep -vF "$CRON_MARKER" /tmp/bitskeep_cron_current.$$ | crontab -
    rm -f /tmp/bitskeep_cron_current.$$
    echo "[$(date '+%Y-%m-%d %H:%M:%S %Z')] removed one-shot cron entry"
  fi
  exit "$status"
} >> "$RUN_LOG" 2>&1
