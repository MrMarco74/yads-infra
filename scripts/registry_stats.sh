#!/usr/bin/env bash
# registry_stats.sh — Aggregate registry pull statistics from anonymized nginx logs
# Output: JSON to stdout
# Usage: bash registry_stats.sh [--days 7]
# Requirements: mmdblookup + GeoLite2-Country.mmdb at /etc/mmdb/GeoLite2-Country.mmdb
#               (apt install mmdb-bin, download from maxmind.com)

set -euo pipefail

LOG_FILE="${REGISTRY_STATS_LOG:-/var/log/nginx/registry_stats.log}"
MMDB="${REGISTRY_MMDB:-/etc/mmdb/GeoLite2-Country.mmdb}"
DAYS="${1:-7}"
if [[ "${1:-}" == "--days" ]]; then DAYS="${2:-7}"; fi

if [[ ! -f "$LOG_FILE" ]]; then
    echo '{"error":"log file not found","pulls_today":0,"pulls_week":0,"pulls_total":0,"by_image":{},"by_country":{},"timeline":[]}'
    exit 0
fi

TODAY=$(date +%Y-%m-%d)
WEEK_AGO=$(date -d "$DAYS days ago" +%Y-%m-%d 2>/dev/null || date -v-${DAYS}d +%Y-%m-%d)

# Parse log: extract image name from /v2/<name>/manifests/<tag> or /v2/<name>/blobs/<digest>
# Format: anon_ip [DD/Mon/YYYY:HH:MM:SS +0000] "METHOD /v2/... HTTP/1.1" STATUS "UA" BYTES

pulls_today=0
pulls_week=0
pulls_total=0
declare -A by_image
declare -A by_country
declare -A by_day

while IFS= read -r line; do
    # Extract date
    date_str=$(echo "$line" | grep -oP '\[\K[^\]]+' | head -1)
    log_date=$(date -d "${date_str}" +%Y-%m-%d 2>/dev/null || \
               python3 -c "from datetime import datetime; print(datetime.strptime('${date_str}', '%d/%b/%Y:%H:%M:%S %z').strftime('%Y-%m-%d'))" 2>/dev/null || echo "")
    [[ -z "$log_date" ]] && continue

    # Extract image from request URI
    uri=$(echo "$line" | grep -oP '"[A-Z]+ \K[^"]+(?= HTTP)' || true)
    image=$(echo "$uri" | grep -oP '/v2/\K[^/]+(?:/[^/]+)*(?=/manifests|/blobs)' || true)
    [[ -z "$image" ]] && continue

    # Extract anon IP
    anon_ip=$(echo "$line" | awk '{print $1}')

    # GeoIP lookup (best-effort)
    country="Unknown"
    if [[ -f "$MMDB" ]] && command -v mmdblookup &>/dev/null; then
        country=$(mmdblookup --file "$MMDB" --ip "${anon_ip%.*}.1" country iso_code 2>/dev/null | grep -oP '"[A-Z]{2}"' | tr -d '"' || echo "Unknown")
        [[ -z "$country" ]] && country="Unknown"
    fi

    pulls_total=$((pulls_total + 1))
    [[ "$log_date" == "$TODAY" ]] && pulls_today=$((pulls_today + 1))
    [[ "$log_date" > "$WEEK_AGO" || "$log_date" == "$WEEK_AGO" ]] && pulls_week=$((pulls_week + 1))

    by_image["$image"]=$(( ${by_image["$image"]:-0} + 1 ))
    by_country["$country"]=$(( ${by_country["$country"]:-0} + 1 ))
    by_day["$log_date"]=$(( ${by_day["$log_date"]:-0} + 1 ))

done < "$LOG_FILE"

# Build JSON
python3 - <<PYEOF
import json, sys

by_image   = {$(for k in "${!by_image[@]}"; do echo "\"$k\": ${by_image[$k]},"; done | head -c -1)}
by_country = {$(for k in "${!by_country[@]}"; do echo "\"$k\": ${by_country[$k]},"; done | head -c -1)}
by_day     = {$(for k in "${!by_day[@]}"; do echo "\"$k\": ${by_day[$k]},"; done | head -c -1)}

result = {
    "pulls_today": $pulls_today,
    "pulls_week":  $pulls_week,
    "pulls_total": $pulls_total,
    "by_image":    dict(sorted(by_image.items(),   key=lambda x: -x[1])[:20]),
    "by_country":  dict(sorted(by_country.items(), key=lambda x: -x[1])[:10]),
    "timeline":    [{"date": k, "count": v} for k, v in sorted(by_day.items())[-$DAYS:]],
}
print(json.dumps(result))
PYEOF
