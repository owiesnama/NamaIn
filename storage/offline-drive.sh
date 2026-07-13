#!/bin/sh
# Drive the offline (SQLite) runtime end-to-end from inside the container.
set -e
BASE=http://127.0.0.1:8123
HOST="Host: nama.offline.test"
JAR=/tmp/offline-cookies.txt
rm -f "$JAR"

urldecode() { python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))' 2>/dev/null || php -r 'echo urldecode(trim(fgets(STDIN)));'; }

# 1. login page -> cookies + csrf
curl -s -c "$JAR" -o /dev/null -H "$HOST" "$BASE/login"
XSRF=$(grep XSRF-TOKEN "$JAR" | awk '{print $7}' | urldecode)

# 2. login
echo "login: $(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/login" \
  -H "$HOST" -H "X-XSRF-TOKEN: $XSRF" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"offline@namain.test","password":"offline-pass-1"}')"

# 3. POS session page (the spike's key render)
curl -s -b "$JAR" -c "$JAR" -o /tmp/pos.html -w "pos page: %{http_code}\n" -H "$HOST" "$BASE/pos"
grep -c 'id="app"' /tmp/pos.html >/dev/null && echo "pos page: inertia app root present"

# 4. POS product search (known ilike pgsql-ism -> expected to break on sqlite)
echo "pos search: $(curl -s -b "$JAR" -o /tmp/possearch.html -w '%{http_code}' -H "$HOST" "$BASE/pos?search=cola")"

# 5. checkout twice with the same idempotency key
XSRF=$(grep XSRF-TOKEN "$JAR" | tail -1 | awk '{print $7}' | urldecode)
PAYLOAD='{"session_id":1,"items":[{"product_id":1,"quantity":3,"price":5}],"total":15,"payment_method":"cash","idempotency_key":"offline-key-1"}'
for i in 1 2; do
  echo "checkout #$i: $(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/pos/checkout" \
    -H "$HOST" -H "X-XSRF-TOKEN: $XSRF" -H 'Content-Type: application/json' -H 'Accept: application/json' -d "$PAYLOAD")"
done

# 6. receipt for the created invoice
INV=$(sqlite3 database/offline.sqlite "select id from invoices where idempotency_key='offline-key-1'" 2>/dev/null || echo 1)
curl -s -b "$JAR" -o /tmp/receipt.html -w "receipt: %{http_code}\n" -H "$HOST" "$BASE/invoice/receipt/${INV:-1}"
grep -o 'INV-SA-[0-9][0-9]-R0-[0-9]*' /tmp/receipt.html | head -1
