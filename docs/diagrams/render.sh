#!/usr/bin/env bash
# Render setiap berkas *.d2 di docs/diagrams/ menjadi tiga bentuk:
#   *.svg  - vektor, versi paling tajam, dipakai oleh lihat.sh
#   *.png  - gambar siap tempel ke laporan atau slide
#   *.txt  - teks ASCII untuk dibaca langsung di terminal
#
# Jalankan dari akar repositori:
#   bash docs/diagrams/render.sh
#
# Prasyarat: biner d2 ada di PATH. Pemasangan:
#   curl -fsSL https://d2lang.com/install.sh | sh -s --
#
# Catatan layout: dagre dipakai untuk SVG dan PNG karena paling enak dilihat,
# sedangkan tala dipakai untuk teks karena menghasilkan lebar paling kecil.

set -euo pipefail

export PATH="$HOME/.local/bin:$PATH"

if ! command -v d2 >/dev/null 2>&1; then
  echo "d2 belum terpasang atau tidak ada di PATH." >&2
  echo "Pasang dulu: curl -fsSL https://d2lang.com/install.sh | sh -s --" >&2
  exit 1
fi

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

render_one() {
  local src="$1"
  local name="$2"
  d2 --layout dagre --pad 40 "$src" "$here/$name.svg"
  d2 --layout dagre --pad 60 "$src" "$here/$name.png"
  d2 --layout tala --pad 2 "$src" "$here/$name.txt"
  printf '  %-16s -> %s.svg  %s.png  %s.txt\n' "$name" "$name" "$name" "$name"
}

shopt -s nullglob
for f in "$here"/*.d2; do
  render_one "$f" "$(basename "$f" .d2)"
done
