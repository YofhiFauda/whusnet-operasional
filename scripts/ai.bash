#!/usr/bin/env bash
set -e

MODE="$1"

case "$MODE" in

  bootstrap)
    gemini -p "
Anda bertindak sebagai AI Project Context Bootstrapper.

Baca:
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Jangan coding fitur aplikasi.

Tugas:
1. Buat folder .ai jika belum ada.
2. Buat file berikut jika belum ada:
   - .ai/ACTIVE_TASK.md
   - .ai/SESSION_STATE.md
   - .ai/HANDOFF.md
   - .ai/DECISIONS.md
   - .ai/CHANGELOG_AI.md
   - .ai/REVIEW_NOTES.md
3. Isi template awal yang ringkas.
4. Jangan overwrite file yang sudah berisi data penting.

Output:
## Bootstrap Selesai
## File Context Dibuat
## Catatan
"
    ;;

  plan)
    gemini -p "
Anda bertindak sebagai Planner / Scope Reader.

Baca:
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Jangan coding.

Tugas:
1. Tentukan task aktif.
2. Tentukan scope boleh dan tidak boleh.
3. Tentukan acceptance criteria.
4. Update .ai/ACTIVE_TASK.md.
5. Update .ai/HANDOFF.md untuk Codex.
6. Update .ai/SESSION_STATE.md.

Output:
## Posisi Project Saat Ini
## Scope yang Boleh Dikerjakan
## Scope yang Tidak Boleh Dikerjakan
## Acceptance Criteria
## File yang Kemungkinan Dibuat atau Diubah
## Risiko Keluar Scope
## File Context yang Diupdate
"
    ;;

  build)
    codex exec --sandbox workspace-write "
Anda bertindak sebagai Builder.

Baca hanya:
- AGENTS.md
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/SESSION_STATE.md

Jangan membaca dokumen besar kecuali .ai/HANDOFF.md belum jelas.

Tugas:
1. Kerjakan hanya task aktif.
2. Ikuti instruksi di .ai/HANDOFF.md.
3. Jangan keluar scope.
4. Setelah selesai, update .ai/CHANGELOG_AI.md.
5. Update .ai/SESSION_STATE.md.
6. Jika task benar-benar selesai, update docs/TASKS.md.

Output:
## Task Selesai
## File Diubah
## Alasan Perubahan
## Cara Test
## Acceptance Criteria
## Risiko / Catatan
## File Context yang Diupdate
## Next Task
"
    ;;

  review)
    gemini -p "
Anda bertindak sebagai Reviewer.

Baca:
- AGENTS.md
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/SESSION_STATE.md
- .ai/CHANGELOG_AI.md

Gunakan git diff untuk melihat perubahan aktual.

Jika perlu validasi scope, baca hanya bagian relevan dari:
- docs/MVP_SCOPE.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Jangan coding.

Tugas:
1. Review apakah perubahan sesuai task aktif.
2. Cek apakah ada fitur di luar scope.
3. Cek acceptance criteria.
4. Cek risiko bug/RBAC/database.
5. Update .ai/REVIEW_NOTES.md.
6. Update .ai/HANDOFF.md jika ada revisi untuk Codex.
7. Update .ai/SESSION_STATE.md.

Output:
## Kesimpulan Review
## Temuan
## Perbaikan Wajib
## Perbaikan Opsional
## Status Acceptance Criteria
## File Context yang Diupdate
## Rekomendasi
"
    ;;

  fix)
    codex exec --sandbox workspace-write "
Anda bertindak sebagai Fixer.

Baca:
- AGENTS.md
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/REVIEW_NOTES.md
- .ai/SESSION_STATE.md

Tugas:
1. Fix hanya issue di .ai/REVIEW_NOTES.md.
2. Jangan membuat fitur baru.
3. Jangan memperluas scope.
4. Update .ai/CHANGELOG_AI.md.
5. Update .ai/SESSION_STATE.md.

Output:
## Fix Selesai
## File Diubah
## Alasan Perubahan
## Cara Test
## Status Review Notes
## File Context yang Diupdate
## Catatan
"
    ;;

  close)
    gemini -p "
Anda bertindak sebagai Task Closer.

Baca:
- .ai/ACTIVE_TASK.md
- .ai/SESSION_STATE.md
- .ai/REVIEW_NOTES.md
- docs/TASKS.md

Jangan coding.

Tugas:
1. Cek apakah task aktif layak ditandai selesai.
2. Jika layak, update docs/TASKS.md.
3. Tentukan task berikutnya.
4. Update .ai/ACTIVE_TASK.md untuk task berikutnya.
5. Update .ai/SESSION_STATE.md.
6. Reset .ai/HANDOFF.md untuk menunggu fase plan berikutnya.

Output:
## Task Ditutup
## Status Akhir
## Task Berikutnya
## File Context yang Diupdate
"
    ;;

  status)
    echo ""
    echo "===== ACTIVE TASK ====="
    cat .ai/ACTIVE_TASK.md
    echo ""
    echo "===== HANDOFF ====="
    cat .ai/HANDOFF.md
    echo ""
    echo "===== SESSION STATE ====="
    cat .ai/SESSION_STATE.md
    ;;

  *)
    echo "Gunakan:"
    echo "  ./scripts/ai bootstrap"
    echo "  ./scripts/ai plan"
    echo "  ./scripts/ai build"
    echo "  ./scripts/ai review"
    echo "  ./scripts/ai fix"
    echo "  ./scripts/ai close"
    echo "  ./scripts/ai status"
    exit 1
    ;;
esac