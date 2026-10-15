#!/usr/bin/env bash
# Buka diagram hasil render langsung dari terminal WSL.
#
# Contoh:
#   bash docs/diagrams/lihat.sh                 # buka alur-lengkap.svg
#   bash docs/diagrams/lihat.sh fsm-status      # buka fsm-status.svg
#   bash docs/diagrams/lihat.sh alur-lengkap.png
#   bash docs/diagrams/lihat.sh -t fsm-status   # tampilkan versi ASCII di terminal
#
# SVG dan PNG dibuka memakai aplikasi Windows, karena terminal teks tidak bisa
# menampilkan gambar. Versi ASCII dipakai kalau tetap ingin dibaca di terminal.

set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "${1:-}" = "-t" ]; then
  target="${2:-alur-lengkap}"
  file="$here/$target.txt"
  if [ ! -f "$file" ]; then
    echo "Belum ada $file. Jalankan dulu: bash docs/diagrams/render.sh" >&2
    exit 1
  fi
  echo "Versi ASCII, lebar $(awk '{ if (length($0) > m) m = length($0) } END { print m }' "$file") kolom."
  echo "Kalau terminal terlalu sempit, pakai: less -S '$file'"
  echo
  cat "$file"
  exit 0
fi

target="${1:-alur-lengkap}"
case "$target" in
  *.svg|*.png) file="$here/$target" ;;
  *) file="$here/$target.svg" ;;
esac

if [ ! -f "$file" ]; then
  echo "Belum ada $file. Jalankan dulu: bash docs/diagrams/render.sh" >&2
  exit 1
fi

win="$(wslpath -w "$file")"
echo "Membuka $win"
explorer.exe "$win" >/dev/null 2>&1 || true
