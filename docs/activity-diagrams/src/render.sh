#!/usr/bin/env bash
# render.sh — render semua *.puml di docs/activity-diagrams/src/
# jadi PNG di docs/activity-diagrams/img/ via kroki.io.
#
# Butuh: curl. Tidak butuh Java, tidak butuh install PlantUML lokal.

set -euo pipefail

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
IMG_DIR="$(cd "$SRC_DIR/../img" && pwd)"
KROKI_URL="https://kroki.io/plantuml/png"

count=0
for puml in "$SRC_DIR"/*.puml; do
    [ -f "$puml" ] || continue
    name="$(basename "$puml" .puml)"
    out="$IMG_DIR/$name.png"

    echo "→ $name.puml → img/$name.png"

    http_code=$(curl -sS -o "$out" -w "%{http_code}" \
        -H "Content-Type: text/plain" \
        --data-binary "@$puml" \
        "$KROKI_URL")

    if [ "$http_code" != "200" ]; then
        echo "  FAIL http $http_code:"
        cat "$out"
        rm -f "$out"
        exit 1
    fi

    count=$((count + 1))
done

echo ""
echo "Selesai: $count file di-render ke $IMG_DIR"
