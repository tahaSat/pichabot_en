#!/usr/bin/env bash
# Start PHP + ngrok, write the public hostname into config.php, and set the Telegram webhook.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-8080}"
cd "$ROOT"

mkdir -p logs storage/cache storage/sessions/panel storage/withdraw_receipts

php_bin="$(command -v php)"
if [[ -z "$php_bin" ]]; then
  echo "php not found on PATH" >&2
  exit 1
fi

if ! lsof -nP -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "Starting PHP server on 127.0.0.1:${PORT} ..."
  nohup "$php_bin" -S "127.0.0.1:${PORT}" -t "$ROOT" >logs/php-server.log 2>&1 &
  echo $! >logs/php-server.pid
  sleep 0.4
else
  echo "PHP already listening on port ${PORT}"
fi

if ! lsof -nP -iTCP:4040 -sTCP:LISTEN >/dev/null 2>&1; then
  echo "Starting ngrok ..."
  nohup ngrok http "$PORT" --log=stdout >logs/ngrok.log 2>&1 &
  echo $! >logs/ngrok.pid
fi

host=""
for _ in $(seq 1 40); do
  tunnels_json="$(curl -sS --max-time 2 http://127.0.0.1:4040/api/tunnels 2>/dev/null || true)"
  if [[ -n "$tunnels_json" ]]; then
    host="$(
      printf '%s' "$tunnels_json" | "$php_bin" -r '
        $j = json_decode(stream_get_contents(STDIN), true);
        foreach (($j["tunnels"] ?? []) as $t) {
          $url = (string) ($t["public_url"] ?? "");
          if (str_starts_with($url, "https://")) {
            echo preg_replace("#^https://#", "", $url);
            exit(0);
          }
        }
      ' || true
    )"
  fi
  if [[ -n "$host" ]]; then
    break
  fi
  sleep 0.5
done

if [[ -z "$host" ]]; then
  echo "Could not read ngrok HTTPS URL from http://127.0.0.1:4040/api/tunnels" >&2
  echo "Is ngrok authenticated? Run: ngrok config check" >&2
  exit 1
fi

"$php_bin" -r '
$f = $argv[1];
$host = $argv[2];
$c = file_get_contents($f);
if ($c === false) { fwrite(STDERR, "cannot read $f\n"); exit(1); }
$n = preg_replace("/\\\$domainhosts = '\''[^'\'']*'\'';/", "\$domainhosts = '\''{$host}'\'';", $c, 1, $count);
if ($count !== 1) { fwrite(STDERR, "failed to patch \$domainhosts in $f\n"); exit(1); }
file_put_contents($f, $n);
echo "config.php \$domainhosts = {$host}\n";
' -- "$ROOT/config.php" "$host"

echo "Initializing schema (table.php) ..."
"$php_bin" "$ROOT/table.php" >/tmp/pichabot-table.php.out 2>&1 || true
if grep -qi 'error' /tmp/pichabot-table.php.out 2>/dev/null; then
  echo "table.php reported errors (see /tmp/pichabot-table.php.out)"
fi

echo "Setting Telegram webhook ..."
"$php_bin" -r '
require $argv[1] . "/config.php";
require $argv[1] . "/botapi.php";
$url = "https://" . $domainhosts . "/index.php";
$res = telegram("setWebhook", [
    "url" => $url,
    "allowed_updates" => json_encode([
        "message", "callback_query", "channel_post", "pre_checkout_query",
        "inline_query", "chat_member", "my_chat_member",
    ]),
]);
echo "webhook url: {$url}\n";
echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (empty($res["ok"])) { exit(1); }
' -- "$ROOT"

echo
echo "Local stack is up."
echo "  Panel:   http://127.0.0.1:${PORT}/panel/login.php"
echo "  Public:  https://${host}/"
echo "  Inspect: http://127.0.0.1:4040"
echo "  Stop:    scripts/stop-local.sh"
