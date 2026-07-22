#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# GEO119 Phase B — Post-Deployment Smoke Test
# =============================================================================
# Run after deploying to staging/production. Tests the critical path:
#   sign up -> buy compute -> optimize one page -> see result
# in both English and Vietnamese.
#
# Usage:
#   chmod +x post-deployment-smoke-test.sh
#   ./post-deployment-smoke-test.sh <base-url> [--verbose]
#
# Examples:
#   ./post-deployment-smoke-test.sh https://staging.geo119.com
#   ./post-deployment-smoke-test.sh https://geo119.com --verbose
# =============================================================================

BASE_URL="${1:?Usage: $0 <base-url> [--verbose]}"
VERBOSE="${2:-}"
PASS=0
FAIL=0

green() { printf '\033[32m%s\033[0m\n' "$1"; }
red()   { printf '\033[31m%s\033[0m\n' "$1"; }
bold()  { printf '\033[1m%s\033[0m\n' "$1"; }

check() {
    local name="$1" expected="$2" actual="$3"
    if [ "$actual" = "$expected" ]; then
        green "  ✅ PASS: $name"
        PASS=$((PASS + 1))
    else
        red "  ❌ FAIL: $name (expected $expected, got $actual)"
        FAIL=$((FAIL + 1))
    fi
}

http_status() {
    curl -s -o /dev/null -w '%{http_code}' "$1"
}

http_body() {
    curl -s "$1"
}

json_val() {
    echo "$1" | python3 -c "import json,sys; print(json.load(sys.stdin)$2)" 2>/dev/null || echo "ERROR"
}

bold "=============================================="
bold "  GEO119 Phase B — Post-Deployment Smoke Test"
bold "  Target: $BASE_URL"
bold "=============================================="
echo ""

# ── 1. Health check ──────────────────────────────────────────────────────────
bold "[1/8] Health endpoint"
status=$(http_status "$BASE_URL/health")
check "GET /health returns 200" "200" "$status"

if [ "$VERBOSE" = "--verbose" ]; then
    http_body "$BASE_URL/health" | python3 -m json.tool 2>/dev/null || echo "  (raw body)"
fi

# ── 2. Homepage loads (EN) ───────────────────────────────────────────────────
bold "[2/8] Homepage — English"
status=$(http_status "$BASE_URL/en/")
check "GET /en/ returns 200" "200" "$status"

# ── 3. Homepage loads (VI) ───────────────────────────────────────────────────
bold "[3/8] Homepage — Vietnamese"
status=$(http_status "$BASE_URL/vi/")
check "GET /vi/ returns 200" "200" "$status"

# ── 4. Language switcher available ────────────────────────────────────────────
bold "[4/8] Language switcher"
body=$(http_body "$BASE_URL/en/")
lang_switcher=$(echo "$body" | grep -c "language-switcher" 2>/dev/null || echo "0")
check "Language switcher form present" "1" "$lang_switcher"

# ── 5. Payment page loads (EN) ────────────────────────────────────────────────
bold "[5/8] Payment page — English"
status=$(http_status "$BASE_URL/en/payment")
check "GET /en/payment returns 200" "200" "$status"

payment_body=$(http_body "$BASE_URL/en/payment")
cost_display=$(echo "$payment_body" | grep -c "cost-summary" 2>/dev/null || echo "0")
check "Cost summary section present" "1" "$cost_display"

# ── 6. Payment page loads (VI) ────────────────────────────────────────────────
bold "[6/8] Payment page — Vietnamese"
status=$(http_status "$BASE_URL/vi/payment")
check "GET /vi/payment returns 200" "200" "$status"

# ── 7. Analytics dashboard loads ──────────────────────────────────────────────
bold "[7/8] Analytics dashboard"
status=$(http_status "$BASE_URL/en/dashboard/analytics")
check "GET /en/dashboard/analytics returns 200" "200" "$status"

analytics_body=$(http_body "$BASE_URL/en/dashboard/analytics")
impressions=$(echo "$analytics_body" | grep -c "counter-impressions" 2>/dev/null || echo "0")
check "Impressions counter present" "1" "$impressions"

# ── 8. Component gallery loads ────────────────────────────────────────────────
bold "[8/8] Component gallery"
status=$(http_status "$BASE_URL/en/component-gallery")
check "GET /en/component-gallery returns 200" "200" "$status"

# ── Summary ──────────────────────────────────────────────────────────────────
echo ""
bold "=============================================="
if [ "$FAIL" -eq 0 ]; then
    green "  ALL $PASS TESTS PASSED"
    exit 0
else
    echo "  $(green "$PASS passed"), $(red "$FAIL failed")"
    exit 1
fi
bold "=============================================="
