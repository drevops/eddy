#!/usr/bin/env bash
##
# Opt-in post-start hook: expose the dev server via a Cloudflare quick tunnel.
#
# Activates only when CLOUDFLARE_TUNNEL is truthy and the `cloudflared` binary
# is on PATH. Starts (or reuses a healthy) quick tunnel pointing at the local
# PHP webserver and writes the public HTTPS URL to `.env` as TUNNEL_URL, which
# `eddy-start`, `provision`, and `info` then display and which
# `make`/`ahoy drush` and `login` pass to Drush.
#
# No Cloudflare account, DNS, or config is needed - quick tunnels mint an
# ephemeral `*.trycloudflare.com` hostname. Install `cloudflared` via mise,
# brew, or apt. Remove or rename this file to disable. The current working
# directory is the project root.

set -eu

# Skip unless CLOUDFLARE_TUNNEL opts in.
case "${CLOUDFLARE_TUNNEL:-}" in
  "" | 0 | false | no | off) exit 0 ;;
esac

if ! command -v cloudflared >/dev/null 2>&1; then
  echo "[cloudflared] Not found on PATH; skipping tunnel."
  exit 0
fi

port="${WEBSERVER_PORT:-8000}"
url_pattern='https://[a-z0-9-]+\.trycloudflare\.com'
mkdir -p .logs
pid_file=".logs/cloudflared.pid"
log_file=".logs/cloudflared.log"

# Print the PID from the pid file only when it is numeric AND still a running
# cloudflared process; otherwise print nothing. Guards against signalling an
# unrelated process that has since recycled the PID.
tunnel_pid() {
  [ -f "$pid_file" ] || return 0
  _pid="$(cat "$pid_file" 2>/dev/null || true)"
  case "$_pid" in
    "" | *[!0-9]*) return 0 ;;
  esac
  ps -p "$_pid" -o command= 2>/dev/null | grep -q cloudflared || return 0
  printf '%s' "$_pid"
}

# Replace the TUNNEL_URL line in .env (last-assignment-wins). An empty value
# removes it so a dead URL is never left behind.
set_tunnel_url() {
  touch .env
  env_tmp=".env.cloudflared.$$"
  grep -v '^TUNNEL_URL=' .env >"$env_tmp" 2>/dev/null || true
  if [ -n "${1:-}" ]; then
    printf 'TUNNEL_URL=%s\n' "$1" >>"$env_tmp"
  fi
  mv "$env_tmp" .env
}

# Reuse an existing tunnel only when its process is alive AND its public URL
# answers - a live process is not proof of a reachable tunnel, as quick tunnels
# can drop their edge connection while cloudflared keeps running.
existing_pid="$(tunnel_pid)"
if [ -n "$existing_pid" ]; then
  existing_url="$(grep -oE "$url_pattern" "$log_file" 2>/dev/null | tail -1 || true)"
  if [ -n "$existing_url" ] && curl -sf -o /dev/null -m 5 "$existing_url"; then
    set_tunnel_url "$existing_url"
    echo "[cloudflared] Reusing healthy tunnel: $existing_url"
    exit 0
  fi
  echo "[cloudflared] Existing tunnel unhealthy; restarting."
  kill "$existing_pid" 2>/dev/null || true
fi

echo "[cloudflared] Starting quick tunnel for http://localhost:${port}"
nohup cloudflared tunnel --url "http://localhost:${port}" --no-autoupdate >"$log_file" 2>&1 &
new_pid=$!
echo "$new_pid" >"$pid_file"

url=""
for _ in $(seq 1 30); do
  url="$(grep -oE "$url_pattern" "$log_file" 2>/dev/null | head -1 || true)"
  [ -n "$url" ] && break
  sleep 1
done

if [ -z "$url" ]; then
  # The tunnel never published a URL: tear the process down and clear any stale
  # TUNNEL_URL rather than leaving a half-started tunnel behind. Kept non-fatal
  # so an opt-in tunnel failure does not abort `start`.
  kill "$new_pid" 2>/dev/null || true
  rm -f "$pid_file"
  set_tunnel_url ""
  echo "[cloudflared] Tunnel URL not available; see ${log_file}. Continuing."
  exit 0
fi

set_tunnel_url "$url"
echo "[cloudflared] Public URL: ${url}"
