#!/usr/bin/env bash

# Import credential + workflow Anteraja Frozen ke n8n yang berjalan di
# cluster. Nilai rahasia dibaca langsung dari Secret cluster supaya tidak
# pernah ada nilai asli yang masuk ke repo.
#
# Pemakaian: ./deploy/n8n/deploy.sh
set -euo pipefail

CONTEXT="${CONTEXT:-innovare-cloud}"
NAMESPACE="${NAMESPACE:-prod-other}"
N8N_DIR="$(cd "$(dirname "$0")" && pwd)"
WORKFLOWS=(
  "$N8N_DIR/workflows/anteraja-frozen-outbound.json"
  "$N8N_DIR/workflows/anteraja-frozen-inbound.json"
)

kc() { kubectl --context "$CONTEXT" -n "$NAMESPACE" "$@"; }
secret() { kc get secret "$1" -o jsonpath="{.data.$2}" | base64 --decode; }

pod="$(kc get pod -l app.kubernetes.io/name=n8n -o jsonpath='{.items[0].metadata.name}')"
echo "n8n pod: $pod"

db_password="$(secret n8n-secrets DB_POSTGRESDB_PASSWORD)"
waha_api_key="$(secret waha-secrets WAHA_API_KEY)"
app_token="$(secret n8n-secrets ANTERAJA_WEBHOOK_TOKEN)"
hook_token="$(secret n8n-secrets WAHA_HOOK_TOKEN)"
llm_key="$(secret n8n-secrets OPENAI_COMPAT_API_KEY 2>/dev/null || echo '')"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

jq -n \
  --arg dbPassword "$db_password" \
  --arg wahaApiKey "$waha_api_key" \
  --arg llmKey "$llm_key" \
  --arg appToken "$app_token" \
  --arg hookToken "$hook_token" \
  '[
    {
      id: "anterajaStatePg",
      name: "Anteraja State DB",
      type: "postgres",
      data: { host: "postgres-rw", database: "n8n", user: "postgres", password: $dbPassword, port: 5432, ssl: "disable" }
    },
    { id: "anterajaWaha", name: "Anteraja WAHA", type: "httpHeaderAuth", data: { name: "X-Api-Key", value: $wahaApiKey } },
    { id: "anterajaLlm", name: "Anteraja LLM", type: "httpHeaderAuth", data: { name: "Authorization", value: ("Bearer " + $llmKey) } },
    { id: "anterajaAppHook", name: "Anteraja App Hook", type: "httpHeaderAuth", data: { name: "X-Anteraja-Token", value: $appToken } },
    { id: "anterajaWahaHook", name: "Anteraja WAHA Hook", type: "httpHeaderAuth", data: { name: "X-Anteraja-Hook", value: $hookToken } }
  ]' > "$tmp_dir/credentials.json"

copy_in() {
  local source="$1"
  local target="$2"
  if kc exec "$pod" -- which tar >/dev/null 2>&1; then
    kc cp "$source" "$pod:$target"
  else
    kc exec -i "$pod" -- sh -c "cat > $target" < "$source"
  fi
}

copy_in "$tmp_dir/credentials.json" /tmp/anteraja-credentials.json
kc exec "$pod" -- n8n import:credentials --input=/tmp/anteraja-credentials.json

for workflow in "${WORKFLOWS[@]}"; do
  name="$(basename "$workflow")"
  id="$(jq -r '.id // empty' "$workflow")"
  echo "import workflow: $name (id=$id)"
  copy_in "$workflow" "/tmp/$name"
  kc exec "$pod" -- n8n import:workflow --input="/tmp/$name"

  # Import selalu menonaktifkan workflow; aktifkan kembali memakai ID tetap
  # yang ditulis di file workflow.
  if [ -n "$id" ]; then
    kc exec "$pod" -- n8n update:workflow --id="$id" --active=true >/dev/null 2>&1 || true
  fi
done

kc exec "$pod" -- rm -f /tmp/anteraja-credentials.json /tmp/anteraja-frozen-outbound.json /tmp/anteraja-frozen-inbound.json

# Perubahan status aktif baru terpasang setelah n8n direstart.
kc rollout restart deploy/n8n
kc rollout status deploy/n8n --timeout=200s
kc exec "$(kc get pod -l app.kubernetes.io/name=n8n -o jsonpath='{.items[0].metadata.name}')" -- n8n list:workflow

echo "Selesai. Kedua workflow aktif di https://n8n.kagchi.my.id"

