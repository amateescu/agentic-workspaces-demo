#!/bin/sh
# Sends one message to the Drupal CMS assistant through the chatbot's HTTP API,
# as admin, on a named thread. Same request path as the browser chat widget.
#
#   sh scripts/chat.sh <thread-id> "<message>"
#
# Appends the request and the reply to chat-logs/<thread-id>.log and prints
# the reply text. The curl call is capped at 10 minutes (the site's PHP
# max_execution_time), and the web container caps PHP memory at 1 GB.
set -e
THREAD="$1"; MSG="$2"
[ -n "$THREAD" ] && [ -n "$MSG" ] || { echo "usage: $0 <thread-id> <message>" >&2; exit 2; }
cd "$(dirname "$0")/.."
mkdir -p chat-logs
BASE=$(ddev exec printenv DDEV_PRIMARY_URL | tr -d '\r')
JAR=chat-logs/cookies-$THREAD.txt
LOG=chat-logs/$THREAD.log
if [ ! -f "$JAR" ]; then
  LINK=$(ddev drush uli --uri=$BASE --no-browser 2>/dev/null | tail -1)
  curl -sk -c "$JAR" -b "$JAR" -L -o /dev/null --max-time 60 "$LINK"
fi
TOKEN=$(curl -sk -b "$JAR" -c "$JAR" -X POST --max-time 60 "$BASE/api/deepchat/session")
BODY=$(php -r 'echo json_encode(["assistant_id"=>"drupal_cms_assistant","thread_id"=>$argv[1],"messages"=>[["role"=>"user","text"=>$argv[2]]]]);' "$THREAD" "$MSG")
{ echo; echo "=== $(date -Is) USER: $MSG"; } >> "$LOG"
T0=$(date +%s)
RESP=$(curl -sk -b "$JAR" -c "$JAR" --max-time 600 -H "Content-Type: application/json" -H "X-CSRF-Token: $TOKEN" -X POST --data "$BODY" "$BASE/api/deepchat?token=$TOKEN" || echo '{"error":"curl failed or timed out"}')
T1=$(date +%s)
{ echo "--- $(($T1 - $T0))s RAW:"; echo "$RESP"; } >> "$LOG"
printf '%s' "$RESP" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo is_array($d) ? strip_tags((string) ($d["text"] ?? $d["html"] ?? json_encode($d))) : "(unparsed) " . stream_get_contents(STDIN), "\n";'
