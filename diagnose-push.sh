#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/html/yuklip"
LOG_FILE="${APP_DIR}/storage/logs/laravel.log"
QUEUE_SERVICE="yuklip-queue-worker"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

header() { echo -e "\n${CYAN}━━━ $1 ━━━${NC}"; }
ok()     { echo -e "  ${GREEN}✔${NC} $1"; }
warn()   { echo -e "  ${YELLOW}⚠${NC} $1"; }
fail()   { echo -e "  ${RED}✖${NC} $1"; }

cd "$APP_DIR" || { echo "Cannot cd to $APP_DIR"; exit 1; }

# ── 1. Queue worker status ──────────────────────────────────────────
header "Queue Worker"
if systemctl is-active --quiet "$QUEUE_SERVICE" 2>/dev/null; then
  ok "$QUEUE_SERVICE is running"
else
  fail "$QUEUE_SERVICE is NOT running — push notifications dispatched via jobs will never fire"
fi

# ── 2. Push subscriptions ───────────────────────────────────────────
header "Push Subscriptions"
SUB_COUNT=$(php artisan tinker --execute="echo \App\Models\User::whereHas('pushSubscriptions')->count();" 2>/dev/null | tail -1)
if [[ "$SUB_COUNT" -gt 0 ]] 2>/dev/null; then
  ok "$SUB_COUNT user(s) with active push subscriptions"
else
  fail "0 users with push subscriptions — no one to send to (PushNotificationService::broadcast silently returns)"
fi

TOTAL_SUBS=$(php artisan tinker --execute="echo DB::table('push_subscriptions')->count();" 2>/dev/null | tail -1)
echo -e "  Total subscription rows: ${TOTAL_SUBS:-unknown}"

# ── 3. VAPID keys ──────────────────────────────────────────────────
header "VAPID Configuration"
VAPID_PUBLIC=$(php artisan tinker --execute="echo config('webpush.vapid.public_key');" 2>/dev/null | tail -1)
VAPID_PRIVATE=$(php artisan tinker --execute="echo config('webpush.vapid.private_key');" 2>/dev/null | tail -1)

if [[ -n "$VAPID_PUBLIC" && "$VAPID_PUBLIC" != "" ]]; then
  ok "VAPID public key is set"
else
  fail "VAPID public key is MISSING — push will fail"
fi

if [[ -n "$VAPID_PRIVATE" && "$VAPID_PRIVATE" != "" ]]; then
  ok "VAPID private key is set"
else
  fail "VAPID private key is MISSING — push will fail"
fi

# ── 4. Failed jobs ─────────────────────────────────────────────────
header "Failed Jobs (last 24h)"
FAILED=$(php artisan tinker --execute="echo DB::table('failed_jobs')->where('failed_at','>=',now()->subDay())->count();" 2>/dev/null | tail -1)
if [[ "$FAILED" -gt 0 ]] 2>/dev/null; then
  warn "$FAILED failed job(s) in last 24h"
  echo ""
  php artisan tinker --execute="
    DB::table('failed_jobs')
      ->where('failed_at','>=',now()->subDay())
      ->latest('failed_at')
      ->limit(5)
      ->get(['uuid','payload','exception','failed_at'])
      ->each(function(\$j){
        \$name = json_decode(\$j->payload,true)['displayName'] ?? '?';
        echo \"  [{$j->failed_at}] {$name}\n\";
        echo '    '.Str::limit(\$j->exception,200).\"\\n\\n\";
      });
  " 2>/dev/null || true
else
  ok "No failed jobs in last 24h"
fi

# ── 5. Pending jobs in queue ───────────────────────────────────────
header "Pending Jobs"
PENDING=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
if [[ "$PENDING" -gt 0 ]] 2>/dev/null; then
  warn "$PENDING job(s) stuck in queue — worker may not be processing"
else
  ok "Job queue is empty (all processed)"
fi

# ── 6. Recent webhooks received ────────────────────────────────────
header "Recent Webhooks (last 10)"
php artisan tinker --execute="
  DB::table('webhook_logs')
    ->latest('created_at')
    ->limit(10)
    ->get(['id','source','status_code','created_at'])
    ->each(function(\$w){
      echo \"  [\$w->created_at] \$w->source  HTTP \$w->status_code\n\";
    });
" 2>/dev/null || warn "Could not query webhook_logs"

# ── 7. Recent system log entries ───────────────────────────────────
header "Recent System Logs (last 15)"
php artisan tinker --execute="
  DB::table('system_logs')
    ->latest('created_at')
    ->limit(15)
    ->get(['agent','action','status','error_message','created_at'])
    ->each(function(\$s){
      \$err = \$s->error_message ? \" — \$s->error_message\" : '';
      echo \"  [\$s->created_at] \$s->agent/\$s->action  \$s->status\$err\n\";
    });
" 2>/dev/null || warn "Could not query system_logs"

# ── 8. Laravel log — push notification errors ─────────────────────
header "Push Notification Errors in Laravel Log (last 50 matches)"
if [[ -f "$LOG_FILE" ]]; then
  grep -i "push notification failed\|WebPush\|push.*error\|webpush" "$LOG_FILE" | tail -50 || ok "No push-related errors found in log"
else
  warn "Log file not found at $LOG_FILE"
fi

# ── 9. Laravel log — recent errors (any) ──────────────────────────
header "Recent Errors in Laravel Log (last 20 lines with ERROR)"
if [[ -f "$LOG_FILE" ]]; then
  grep "\\.ERROR:" "$LOG_FILE" | tail -20 || ok "No recent ERROR entries"
else
  warn "Log file not found"
fi

echo ""
echo -e "${CYAN}━━━ Diagnosis Complete ━━━${NC}"
echo ""
echo "Common causes for 'webhook received but no push sent':"
echo "  1. No push subscriptions — user never enabled notifications in browser"
echo "  2. VAPID keys missing or wrong — .env VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY"
echo "  3. Queue worker not running — push dispatched to job queue but never executed"
echo "  4. Subscription endpoint expired — browser revoked the push subscription"
echo "  5. Silent catch in PushNotificationService::broadcast — check laravel.log for 'Push notification failed'"
echo ""
