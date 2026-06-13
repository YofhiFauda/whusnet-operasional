#!/usr/bin/env bash
set -e

MODE="${1:-status}"
AI_DIR=".ai"
LOG_DIR="$AI_DIR/logs"

now() {
  date "+%Y-%m-%d %H:%M:%S"
}

stamp() {
  date "+%Y%m%d-%H%M%S"
}

ensure_ai_dir() {
  mkdir -p "$AI_DIR"
  mkdir -p "$LOG_DIR"
}

current_task_value() {
  local key="$1"
  sed -n "s/^$key:[[:space:]]*//p" docs/TASKS.md | head -n 1
}

current_task_block() {
  local task="$1"

  awk -v task="$task" '
    $0 == "### " task { in_task = 1 }
    in_task { print }
    in_task && /^---$/ { exit }
  ' docs/TASKS.md
}

write_if_missing() {
  local file="$1"
  local content="$2"

  if [ ! -s "$file" ]; then
    printf "%s\n" "$content" > "$file"
  fi
}

confirm_external_agent() {
  local tool="$1"
  local phase="$2"

  if [ "${AI_ALLOW_EXTERNAL:-}" = "1" ]; then
    return 0
  fi

  echo "Mode '$phase' akan menjalankan agent eksternal: $tool."
  echo "Mode ini dapat mengubah file context .ai/* dan menulis log baru."

  if [ ! -t 0 ]; then
    echo "ERROR: Jalankan dari terminal interaktif atau set AI_ALLOW_EXTERNAL=1 jika memang ingin lanjut." >&2
    return 1
  fi

  read -rp "Lanjut? (y/n): " confirm

  if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Dibatalkan."
    return 1
  fi
}

phase_header() {
  local title="$1"
  local log_file="$2"

  echo ""
  echo "======================================"
  echo "$title"
  echo "======================================"
  echo "Mulai     : $(now)"
  echo "Mode      : $MODE"
  echo "Log file  : $log_file"
  echo "--------------------------------------"
}

phase_footer() {
  local exit_code="$1"
  local log_file="$2"

  echo "--------------------------------------"
  echo "Selesai   : $(now)"
  echo "Exit code : $exit_code"
  echo "Log file  : $log_file"
  echo "======================================"
  echo ""
}

append_log_index() {
  local phase="$1"
  local log_file="$2"
  local exit_code="$3"

  {
    echo "- $(now) | $phase | exit=$exit_code | $log_file"
  } >> "$LOG_DIR/index.md"

  cp "$log_file" "$LOG_DIR/latest.log"
}

bootstrap_ai_context() {
  ensure_ai_dir

  sync_ai_context

  write_if_missing "$AI_DIR/DECISIONS.md" "# Decisions

Record important project or implementation decisions here.
"

  write_if_missing "$AI_DIR/CHANGELOG_AI.md" "# AI Changelog

Record AI workflow changes and build summaries here.
"

  write_if_missing "$AI_DIR/REVIEW_NOTES.md" "# Review Notes

Review notes from Gemini/Codex workflow will be written here.
"

  cat << EOF
## Bootstrap Selesai

## File Context Dibuat
- $AI_DIR/ACTIVE_TASK.md
- $AI_DIR/SESSION_STATE.md
- $AI_DIR/HANDOFF.md
- $AI_DIR/DECISIONS.md
- $AI_DIR/CHANGELOG_AI.md
- $AI_DIR/REVIEW_NOTES.md
- $LOG_DIR/

## Catatan
File context task disinkronkan dari docs/TASKS.md.
File pendukung yang sudah berisi data tidak dioverwrite.
EOF
}

sync_ai_context() {
  ensure_ai_dir

  local current_sprint
  local current_module
  local current_task
  local task_block

  current_sprint="$(current_task_value "Current Sprint")"
  current_module="$(current_task_value "Current Module")"
  current_task="$(current_task_value "Current Task")"

  if [ -z "$current_sprint" ] || [ -z "$current_module" ] || [ -z "$current_task" ]; then
    echo "ERROR: Current Sprint/Module/Task tidak lengkap di docs/TASKS.md." >&2
    return 1
  fi

  task_block="$(current_task_block "$current_task")"

  if [ -z "$task_block" ]; then
    echo "ERROR: Detail task '$current_task' tidak ditemukan di docs/TASKS.md." >&2
    return 1
  fi

  cat > "$AI_DIR/ACTIVE_TASK.md" << EOF
# Active Task

Source of truth: docs/TASKS.md
Last synced at: $(now)

Current Sprint: $current_sprint
Current Module: $current_module
Current Task: $current_task
Status: In Progress

## Task Detail

$task_block
EOF

  cat > "$AI_DIR/HANDOFF.md" << EOF
# Handoff

## Dari Agent
Local sync script

## Untuk Agent
Agent berikutnya wajib membaca docs/TASKS.md dan .ai/ACTIVE_TASK.md sebelum bekerja.

## Task Aktif
$current_task

## Ringkasan Scope
Ikuti task aktif di docs/TASKS.md. Jangan mengerjakan task lain atau modul sprint berikutnya.

## Scope yang Boleh Dikerjakan
Modul: $current_module

## Scope yang Tidak Boleh Dikerjakan
Semua fitur di luar task aktif, fitur post-MVP, dan modul sprint berikutnya.

## File yang Boleh Diubah
Hanya file yang relevan dengan task aktif setelah scope check.

## File yang Tidak Boleh Disentuh
File yang tidak terkait task aktif.

## Acceptance Criteria
Lihat checklist dan acceptance criteria di .ai/ACTIVE_TASK.md.

## Instruksi untuk Agent Berikutnya
Jalankan scope check sebelum coding. Gunakan docs/TASKS.md sebagai source of truth.

## Catatan Risiko
Context ini dibuat lokal tanpa memanggil gemini/codex.
EOF

  cat > "$AI_DIR/SESSION_STATE.md" << EOF
# Session State

## Status Project Saat Ini
Context .ai disinkronkan dari docs/TASKS.md.

## Last Updated
$(now)

## Workflow Phase
Local Sync

## Agent Terakhir
scripts/ai.sh sync

## Pekerjaan Terakhir
Sinkronisasi file .ai/ACTIVE_TASK.md, .ai/HANDOFF.md, dan .ai/SESSION_STATE.md dari docs/TASKS.md.

## File yang Terakhir Dibaca
- docs/TASKS.md

## File yang Terakhir Diubah
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/SESSION_STATE.md

## Status Task
$current_task masih In Progress sesuai docs/TASKS.md.

## Catatan untuk Agent Berikutnya
Jangan percaya log lama sebagai source of truth. Gunakan docs/TASKS.md dan .ai/ACTIVE_TASK.md hasil sync terbaru.
EOF

  echo "Context .ai berhasil disinkronkan dari docs/TASKS.md."
  echo "Current Sprint : $current_sprint"
  echo "Current Module : $current_module"
  echo "Current Task   : $current_task"
}

run_bootstrap_logged() {
  ensure_ai_dir

  local log_file="$LOG_DIR/$(stamp)-bootstrap.log"
  phase_header "FASE 0 - Bootstrap .ai Lokal" "$log_file" | tee "$log_file"

  echo "[1/4] Menyiapkan folder .ai dan .ai/logs" | tee -a "$log_file"
  echo "[2/4] Membuat file context jika belum ada" | tee -a "$log_file"
  echo "[3/4] Menjaga file existing agar tidak dioverwrite" | tee -a "$log_file"
  echo "[4/4] Menampilkan ringkasan bootstrap" | tee -a "$log_file"
  echo "--------------------------------------" | tee -a "$log_file"

  set +e
  bootstrap_ai_context 2>&1 | tee -a "$log_file"
  local exit_code=${PIPESTATUS[0]}
  set -e

  phase_footer "$exit_code" "$log_file" | tee -a "$log_file"
  append_log_index "bootstrap" "$log_file" "$exit_code"

  return "$exit_code"
}

run_sync_logged() {
  ensure_ai_dir

  local log_file="$LOG_DIR/$(stamp)-sync.log"
  phase_header "Sinkronisasi .ai dari docs/TASKS.md" "$log_file" | tee "$log_file"

  echo "[1/3] Membaca Current Sprint/Module/Task dari docs/TASKS.md" | tee -a "$log_file"
  echo "[2/3] Menulis ulang .ai/ACTIVE_TASK.md, .ai/HANDOFF.md, dan .ai/SESSION_STATE.md" | tee -a "$log_file"
  echo "[3/3] Menjadikan log sync sebagai latest.log agar log lama tidak menyesatkan" | tee -a "$log_file"
  echo "--------------------------------------" | tee -a "$log_file"

  set +e
  sync_ai_context 2>&1 | tee -a "$log_file"
  local exit_code=${PIPESTATUS[0]}
  set -e

  phase_footer "$exit_code" "$log_file" | tee -a "$log_file"
  append_log_index "sync" "$log_file" "$exit_code"

  return "$exit_code"
}

run_gemini_to_file() {
  local label="$1"
  local phase="$2"
  local output_file="$3"
  local prompt="$4"

  confirm_external_agent "gemini" "$phase"
  ensure_ai_dir

  local log_file="$LOG_DIR/$(stamp)-$phase.log"
  local tmp_file
  tmp_file="$(mktemp)"

  phase_header "$label" "$log_file" | tee "$log_file"
  echo "[1/5] Menyiapkan prompt fase $phase" | tee -a "$log_file"
  echo "[2/5] Menjalankan Gemini dan menampilkan output live" | tee -a "$log_file"
  echo "[3/5] Menyimpan stdout/stderr lengkap ke log" | tee -a "$log_file"
  echo "[4/5] Menulis output Gemini ke file context: $output_file" | tee -a "$log_file"
  echo "[5/5] Menampilkan exit code fase" | tee -a "$log_file"
  echo "--------------------------------------" | tee -a "$log_file"

  set +e
  gemini -p "$prompt" 2>&1 | tee "$tmp_file" | tee -a "$log_file"
  local exit_code=${PIPESTATUS[0]}
  set -e

  {
    echo "# Generated by AI workflow"
    echo
    echo "Generated at: $(now)"
    echo
    cat "$tmp_file"
  } > "$output_file"

  rm -f "$tmp_file"
  echo "" | tee -a "$log_file"
  echo "Context ditulis ke: $output_file" | tee -a "$log_file"

  phase_footer "$exit_code" "$log_file" | tee -a "$log_file"
  append_log_index "$phase" "$log_file" "$exit_code"

  return "$exit_code"
}

run_codex_logged() {
  local phase="$1"
  local prompt="$2"

  confirm_external_agent "codex" "$phase"
  ensure_ai_dir

  local log_file="$LOG_DIR/$(stamp)-$phase.log"
  phase_header "Menjalankan Codex: $phase" "$log_file" | tee "$log_file"
  echo "[1/5] Menyiapkan prompt Codex" | tee -a "$log_file"
  echo "[2/5] Menjalankan codex exec --sandbox workspace-write" | tee -a "$log_file"
  echo "[3/5] Menampilkan output Codex live di terminal" | tee -a "$log_file"
  echo "[4/5] Menyimpan stdout/stderr lengkap ke log" | tee -a "$log_file"
  echo "[5/5] Menampilkan exit code fase" | tee -a "$log_file"
  echo "--------------------------------------" | tee -a "$log_file"

  set +e
  codex exec --sandbox workspace-write "$prompt" 2>&1 | tee -a "$log_file"
  local exit_code=${PIPESTATUS[0]}
  set -e

  phase_footer "$exit_code" "$log_file" | tee -a "$log_file"
  append_log_index "$phase" "$log_file" "$exit_code"

  return "$exit_code"
}

PROMPT_PLAN=$(cat << 'EOF'
Anda bertindak sebagai Planner / Scope Reader.

Tampilkan setiap langkah yang Anda lakukan secara eksplisit. Jika membaca file, sebutkan nama file yang dibaca. Jika menemukan risiko, tulis risiko tersebut langsung.

Baca:
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Jangan coding.
Jangan mengubah file.
Jangan memanggil tool untuk menulis file.

Tugas:
1. Tentukan task aktif.
2. Tentukan scope boleh dan tidak boleh.
3. Tentukan acceptance criteria.
4. Tentukan file yang kemungkinan dibuat atau diubah.
5. Tentukan risiko keluar scope.

Output:
## Log Pekerjaan
## Posisi Project Saat Ini
## Scope yang Boleh Dikerjakan
## Scope yang Tidak Boleh Dikerjakan
## Acceptance Criteria
## File yang Kemungkinan Dibuat atau Diubah
## Risiko Keluar Scope
## Handoff untuk Codex
EOF
)

PROMPT_BUILD=$(cat << 'EOF'
Anda bertindak sebagai Builder.

Tampilkan setiap langkah yang Anda lakukan secara eksplisit. Sebelum membaca, mencari, mengubah file, menjalankan test, atau update task, tulis apa yang akan dilakukan dan alasannya.

Baca terlebih dahulu:
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/SESSION_STATE.md

Kerjakan hanya task aktif di docs/TASKS.md.

Aturan:
1. Jangan mengerjakan task lain.
2. Jangan mengerjakan modul sprint berikutnya.
3. Jangan membuat fitur post-MVP.
4. Jangan mengubah file yang tidak relevan.
5. Sebelum coding, tampilkan scope check dan rencana file yang akan dibuat/diubah.
6. Setelah coding, jelaskan cara test.
7. Setelah selesai, update docs/TASKS.md.

Output akhir wajib berisi:
## Task Selesai
## File Diubah
## Alasan Perubahan
## Cara Test
## Acceptance Criteria
## Risiko / Catatan
## Next Task
EOF
)

PROMPT_REVIEW=$(cat << 'EOF'
Anda bertindak sebagai Reviewer.

Tampilkan setiap langkah review yang Anda lakukan secara eksplisit. Jika membaca file atau diff, sebutkan apa yang dibaca dan temuan utamanya.

Baca:
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/SESSION_STATE.md
- .ai/CHANGELOG_AI.md

Gunakan git diff untuk melihat perubahan aktual jika tool tersedia.

Jangan coding.
Jangan mengubah file.
Jangan memanggil tool untuk menulis file.

Tugas:
1. Review apakah perubahan sesuai task aktif.
2. Cek apakah ada fitur di luar scope.
3. Cek acceptance criteria.
4. Cek risiko bug/RBAC/database.
5. Berikan revisi untuk Codex jika ada.

Output:
## Log Pekerjaan
## Kesimpulan Review
## Temuan
## Perbaikan Wajib
## Perbaikan Opsional
## Status Acceptance Criteria
## Rekomendasi
EOF
)

PROMPT_FIX=$(cat << 'EOF'
Anda bertindak sebagai Fixer.

Tampilkan setiap langkah yang Anda lakukan secara eksplisit. Sebelum membaca, mencari, mengubah file, menjalankan test, atau update context, tulis apa yang akan dilakukan dan alasannya.

Baca:
- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md
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
EOF
)

PROMPT_CLOSE=$(cat << 'EOF'
Anda bertindak sebagai Task Closer.

Tampilkan setiap langkah yang Anda lakukan secara eksplisit. Jika membaca status task atau review notes, sebutkan hasilnya.

Baca:
- .ai/ACTIVE_TASK.md
- .ai/SESSION_STATE.md
- .ai/REVIEW_NOTES.md
- docs/TASKS.md

Jangan coding.
Jangan mengubah file.
Jangan memanggil tool untuk menulis file.

Tugas:
1. Cek apakah task aktif layak ditandai selesai.
2. Tentukan task berikutnya.
3. Berikan rekomendasi update docs/TASKS.md.
4. Berikan rekomendasi reset .ai/HANDOFF.md untuk fase plan berikutnya.

Output:
## Log Pekerjaan
## Task Ditutup
## Status Akhir
## Task Berikutnya
## Rekomendasi Update Context
EOF
)

case "$MODE" in
  bootstrap)
    run_bootstrap_logged
    ;;

  sync)
    run_sync_logged
    ;;

  plan)
    run_gemini_to_file "Menjalankan Gemini: Planner / Scope Reader" "plan" "$AI_DIR/HANDOFF.md" "$PROMPT_PLAN"
    {
      echo "# Active Task"
      echo
      echo "Source of truth: docs/TASKS.md"
      echo "Last planned at: $(now)"
      echo
      grep -A 20 "Current Sprint:" docs/TASKS.md || true
    } > "$AI_DIR/ACTIVE_TASK.md"
    {
      echo "# Session State"
      echo
      echo "Last updated: $(now)"
      echo "Workflow phase: Plan"
      echo "Latest handoff: $AI_DIR/HANDOFF.md"
      echo "Latest log: $LOG_DIR/latest.log"
    } > "$AI_DIR/SESSION_STATE.md"
    ;;

  build)
    run_codex_logged "build" "$PROMPT_BUILD"
    ;;

  review)
    run_gemini_to_file "Menjalankan Gemini: Reviewer" "review" "$AI_DIR/REVIEW_NOTES.md" "$PROMPT_REVIEW"
    {
      echo "# Session State"
      echo
      echo "Last updated: $(now)"
      echo "Workflow phase: Review"
      echo "Latest review notes: $AI_DIR/REVIEW_NOTES.md"
      echo "Latest log: $LOG_DIR/latest.log"
    } > "$AI_DIR/SESSION_STATE.md"
    ;;

  fix)
    run_codex_logged "fix" "$PROMPT_FIX"
    ;;

  close)
    run_gemini_to_file "Menjalankan Gemini: Task Closer" "close" "$AI_DIR/HANDOFF.md" "$PROMPT_CLOSE"
    {
      echo "# Session State"
      echo
      echo "Last updated: $(now)"
      echo "Workflow phase: Close"
      echo "Latest close recommendation: $AI_DIR/HANDOFF.md"
      echo "Latest log: $LOG_DIR/latest.log"
    } > "$AI_DIR/SESSION_STATE.md"
    echo "Menjalankan sync lokal setelah close agar .ai mengikuti docs/TASKS.md..."
    run_sync_logged
    ;;

  status)
    ensure_ai_dir
    echo -e "\n===== ACTIVE TASK ====="
    [ -f "$AI_DIR/ACTIVE_TASK.md" ] && cat "$AI_DIR/ACTIVE_TASK.md" || echo "[Belum ada task aktif]"

    echo -e "\n===== HANDOFF ====="
    [ -f "$AI_DIR/HANDOFF.md" ] && cat "$AI_DIR/HANDOFF.md" || echo "[Belum ada handoff]"

    echo -e "\n===== SESSION STATE ====="
    [ -f "$AI_DIR/SESSION_STATE.md" ] && cat "$AI_DIR/SESSION_STATE.md" || echo "[Belum ada session state]"

    echo -e "\n===== LOG TERAKHIR ====="
    [ -f "$LOG_DIR/latest.log" ] && tail -n 80 "$LOG_DIR/latest.log" || echo "[Belum ada log]"
    echo ""
    ;;

  logs)
    ensure_ai_dir
    echo -e "\n===== LOG INDEX ====="
    [ -f "$LOG_DIR/index.md" ] && tail -n 30 "$LOG_DIR/index.md" || echo "[Belum ada log index]"
    echo -e "\n===== LOG TERAKHIR ====="
    [ -f "$LOG_DIR/latest.log" ] && cat "$LOG_DIR/latest.log" || echo "[Belum ada log]"
    echo ""
    ;;

  *)
    echo "Perintah tidak dikenali. Gunakan:"
    echo "  ./scripts/ai.sh bootstrap"
    echo "  ./scripts/ai.sh sync"
    echo "  ./scripts/ai.sh plan"
    echo "  ./scripts/ai.sh build"
    echo "  ./scripts/ai.sh review"
    echo "  ./scripts/ai.sh fix"
    echo "  ./scripts/ai.sh close"
    echo "  ./scripts/ai.sh status"
    echo "  ./scripts/ai.sh logs"
    exit 1
    ;;
esac
