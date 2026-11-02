#!/bin/bash
# Jalankan Lighthouse CI per kelompok, berurutan, dengan jeda supaya RAM pulih.
#   ./run-lighthouse-groups.sh              # semua kelompok
#   ./run-lighthouse-groups.sh pelanggan    # satu/beberapa kelompok saja
# Hasil: lighthouse-reports/<kelompok>/ ; ringkasan skor: node lighthouse-summary.cjs
cd "$(dirname "$0")" || exit 1

# Hanya satu run pada satu waktu: dua run berbagi folder .lighthouseci (saling
# hapus) dan dua Chrome sekaligus menghabiskan RAM WSL.
exec 9>/tmp/lighthouse-run.lock
if ! flock -n 9; then
  echo "Sudah ada run Lighthouse lain yang berjalan. Tunggu selesai atau: pkill -f lhci; pkill -f chrome"
  exit 1
fi

export CHROME_PATH="${CHROME_PATH:-/usr/bin/google-chrome-stable}"

GROUPS_ALL=(umum pelanggan keuangan worksheet fop infrastruktur ticketing laporan gudang)
TARGETS=("$@")
[ ${#TARGETS[@]} -eq 0 ] && TARGETS=("${GROUPS_ALL[@]}")

for g in "${TARGETS[@]}"; do
  echo "=== Kelompok: $g ==="
  rm -rf ".lighthouseci" "lighthouse-reports/$g"
  LH_GROUP="$g" npx lhci autorun 2>&1 | grep -vi "punycode\|trace-deprecation\|GitHub token"
  echo "--- selesai $g (exit ${PIPESTATUS[0]}), jeda 10 dtk ---"
  sleep 10
done
echo "Semua selesai. Ringkasan: node lighthouse-summary.cjs"
