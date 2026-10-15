#!/usr/bin/env bash
# Render setiap berkas *.mmd di docs/architecture/ menjadi PNG.
#
# Jalankan dari akar repositori:
#   bash docs/architecture/render.sh
#
# Prasyarat: Node.js tersedia. mermaid-cli diambil lewat npx, jadi tidak ada
# dependensi baru yang masuk ke package.json proyek ini.

set -euo pipefail

# Versi dipatok supaya hasil render tidak berubah sendiri saat mermaid-cli
# merilis versi baru.
readonly MERMAID_VERSION="11"

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

shopt -s nullglob
for f in "$here"/*.mmd; do
  name="$(basename "$f" .mmd)"
  args=(-i "$f" -o "$here/$name.png" -b white -s 2)

  # Tema opsional per diagram: docs/architecture/<nama>.css
  if [ -f "$here/$name.css" ]; then
    args+=(-C "$here/$name.css")
  fi

  npx -y "@mermaid-js/mermaid-cli@${MERMAID_VERSION}" "${args[@]}"
  printf '  %-24s -> %s.png\n' "$name" "$name"
done

