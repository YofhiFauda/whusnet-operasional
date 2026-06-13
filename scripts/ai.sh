#!/usr/bin/env bash
set -e

# --- PENGATURAN WARNA & FORMAT TAMPILAN ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# --- SISTEM TIMER & SPINNER MENU ---
MENU_TIMER_PID=""

# Fungsi untuk mematikan timer dengan aman
stop_menu_timer() {
  if [ -n "$MENU_TIMER_PID" ]; then
    kill "$MENU_TIMER_PID" 2>/dev/null || true
    MENU_TIMER_PID=""
    tput cnorm # Kembalikan kursor ke normal
  fi
}

# Trap keamanan: Jika keluar script/di-cancel (Ctrl+C), pastikan timer mati
trap 'stop_menu_timer' EXIT SIGINT SIGTERM

# Fungsi Background Worker untuk Timer
run_menu_timer() {
  local start_time=$(date +%s)
  local spin_chars=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏')
  local spin_idx=0
  
  tput civis # Sembunyikan kursor agar tidak flicker
  
  while true; do
    local current_time=$(date +%s)
    local elapsed=$((current_time - start_time))
    local mins=$(printf "%02d" $((elapsed / 60)))
    local secs=$(printf "%02d" $((elapsed % 60)))
    
    local char="${spin_chars[$spin_idx]}"
    spin_idx=$(( (spin_idx + 1) % 10 ))
    
    # Simpan posisi (di prompt), lompat ke Baris 6, cetak spinner+waktu, kembali ke prompt
    printf "\e[s\e[6;1H\e[K  \e[36m%s\e[0m \e[32mSesi Menu Aktif...\e[0m \e[1;33m[%s:%s]\e[0m\e[u" "$char" "$mins" "$secs"
    
    sleep 0.1
  done
}

pause() {
  echo ""
  echo -e -n "${YELLOW}👉 Tekan Enter untuk kembali ke menu...${NC}"
  read -r
}

# --- LOGIKA UTAMA SCRIPT ---
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

  echo -e "${YELLOW}⚠️  Mode '$phase' akan menjalankan agent eksternal: ${BOLD}$tool${NC}."
  echo -e "${YELLOW}💡 Mode ini dapat mengubah file context .ai/* dan menulis log baru.${NC}"

  if [ ! -t 0 ]; then
    echo -e "${RED}❌ ERROR: Jalankan dari terminal interaktif atau set AI_ALLOW_EXTERNAL=1 jika memang ingin lanjut.${NC}" >&2
    return 1
  fi

  echo -e -n "${YELLOW}❓ Lanjut? (y/n): ${NC}"
  read -r confirm

  if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo -e "${RED}⛔ Dibatalkan.${NC}"
    return 1
  fi
}

phase_header() {
  local title="$1"
  local log_file="$2"
  echo ""
  echo "======================================"
  echo "🚀 $title"
  echo "======================================"
  echo "🕒 Mulai    : $(now)"
  echo "⚙️  Mode     : $MODE"
  echo "📂 Log file : $log_file"
  echo "--------------------------------------"
}

phase_footer() {
  local exit_code="$1"
  local log_file="$2"
  echo "--------------------------------------"
  echo "🏁 Selesai   : $(now)"
  echo "🚦 Exit code : $exit_code"
  echo "📂 Log file  : $log_file"
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

  write_if_missing "$AI_DIR/DECISIONS.md" "# Decisions\n\nRecord important project or implementation decisions here.\n"
  write_if_missing "$AI_DIR/CHANGELOG_AI.md" "# AI Changelog\n\nRecord AI workflow changes and build summaries here.\n"
  write_if_missing "$AI_DIR/REVIEW_NOTES.md" "# Review Notes\n\nReview notes from Gemini/Codex workflow will be written here.\n"

  cat << EOF

## ✨ Bootstrap Selesai

## 📝 File Context Dibuat
- $AI_DIR/ACTIVE_TASK.md
- $AI_DIR/SESSION_STATE.md
- $AI_DIR/HANDOFF.md
- $AI_DIR/DECISIONS.md
- $AI_DIR/CHANGELOG_AI.md
- $AI_DIR/REVIEW_NOTES.md
- $LOG_DIR/

## 💡 Catatan
File context task disinkronkan dari docs/TASKS.md.
File pendukung yang sudah berisi data tidak dioverwrite.
EOF
}

sync_ai_context() {
  ensure_ai_dir

  local current_sprint current_module current_task task_block
  current_sprint="$(current_task_value "Current Sprint")"
  current_module="$(current_task_value "Current Module")"
  current_task="$(current_task_value "Current Task")"

  if [ -z "$current_sprint" ] || [ -z "$current_module" ] || [ -z "$current_task" ]; then
    echo -e "${RED}❌ ERROR: Current Sprint/Module/Task tidak lengkap di docs/TASKS.md.${NC}" >&2
    return 1
  fi

  task_block="$(current_task_block "$current_task")"

  if [ -z "$task_block" ]; then
    echo -e "${RED}❌ ERROR: Detail task '$current_task' tidak ditemukan di docs/TASKS.md.${NC}" >&2
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

  cat > "$AI_DIR/REVIEW_NOTES.md" << EOF
# Review Notes

Source of truth: docs/TASKS.md
Last reset at: $(now)

## Task Aktif
$current_task

## Status
Belum ada review untuk task aktif ini.

## Catatan
File ini di-reset saat sync agar agent berikutnya tidak membaca review task lama.
Jalankan mode review setelah build task aktif selesai.
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
Sinkronisasi file .ai/ACTIVE_TASK.md, .ai/HANDOFF.md, .ai/REVIEW_NOTES.md, dan .ai/SESSION_STATE.md dari docs/TASKS.md.

## File yang Terakhir Dibaca
- docs/TASKS.md

## File yang Terakhir Diubah
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/REVIEW_NOTES.md
- .ai/SESSION_STATE.md

## Status Task
$current_task masih In Progress sesuai docs/TASKS.md.

## Catatan untuk Agent Berikutnya
Jangan percaya log lama sebagai source of truth. Gunakan docs/TASKS.md dan .ai/ACTIVE_TASK.md hasil sync terbaru.
REVIEW_NOTES.md sengaja dikosongkan untuk task aktif sampai mode review dijalankan lagi.
EOF

  echo -e "${GREEN}✅ Context .ai berhasil disinkronkan dari docs/TASKS.md.${NC}"
  echo -e "🏃 ${CYAN}Current Sprint :${NC} $current_sprint"
  echo -e "📦 ${BLUE}Current Module :${NC} $current_module"
  echo -e "🎯 ${YELLOW}Current Task   :${NC} $current_task"
}

run_bootstrap_logged() {
  ensure_ai_dir
  local log_file="$LOG_DIR/$(stamp)-bootstrap.log"
  phase_header "FASE 0 - Bootstrap .ai Lokal" "$log_file" | tee "$log_file"

  echo "👉 [1/4] Menyiapkan folder .ai dan .ai/logs" | tee -a "$log_file"
  echo "👉 [2/4] Membuat file context jika belum ada" | tee -a "$log_file"
  echo "👉 [3/4] Menjaga file existing agar tidak dioverwrite" | tee -a "$log_file"
  echo "👉 [4/4] Menampilkan ringkasan bootstrap" | tee -a "$log_file"
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

  echo "👉 [1/3] Membaca Current Sprint/Module/Task dari docs/TASKS.md" | tee -a "$log_file"
  echo "👉 [2/3] Menulis ulang .ai/ACTIVE_TASK.md, .ai/HANDOFF.md, .ai/REVIEW_NOTES.md, dan .ai/SESSION_STATE.md" | tee -a "$log_file"
  echo "👉 [3/3] Menjadikan log sync sebagai latest.log agar log lama tidak menyesatkan" | tee -a "$log_file"
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
  local label="$1" phase="$2" output_file="$3" prompt="$4"
  confirm_external_agent "gemini" "$phase"
  ensure_ai_dir

  local log_file="$LOG_DIR/$(stamp)-$phase.log"
  local tmp_file
  tmp_file="$(mktemp)"

  phase_header "$label" "$log_file" | tee "$log_file"
  echo "👉 [1/5] Menyiapkan prompt fase $phase" | tee -a "$log_file"
  echo "👉 [2/5] Menjalankan Gemini dan menampilkan output live" | tee -a "$log_file"
  echo "👉 [3/5] Menyimpan stdout/stderr lengkap ke log" | tee -a "$log_file"
  echo "👉 [4/5] Menulis output Gemini ke file context: $output_file" | tee -a "$log_file"
  echo "👉 [5/5] Menampilkan exit code fase" | tee -a "$log_file"
  echo "--------------------------------------" | tee -a "$log_file"

  set +e
  if command -v bat >/dev/null 2>&1; then
    gemini -p "$prompt" 2>&1 | tee "$tmp_file" | tee -a "$log_file" | bat -l markdown --color=always --style=plain --paging=never
  else
    gemini -p "$prompt" 2>&1 | tee "$tmp_file" | tee -a "$log_file"
  fi
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
  echo "💾 Context ditulis ke: $output_file" | tee -a "$log_file"

  phase_footer "$exit_code" "$log_file" | tee -a "$log_file"
  append_log_index "$phase" "$log_file" "$exit_code"
  return "$exit_code"
}

run_codex_logged() {
  local phase="$1" prompt="$2"
  confirm_external_agent "codex" "$phase"
  ensure_ai_dir

  local log_file="$LOG_DIR/$(stamp)-$phase.log"
  phase_header "Menjalankan Codex: $phase" "$log_file" | tee "$log_file"
  echo "👉 [1/5] Menyiapkan prompt Codex" | tee -a "$log_file"
  echo "👉 [2/5] Menjalankan codex exec --sandbox workspace-write" | tee -a "$log_file"
  echo "👉 [3/5] Menampilkan output Codex live di terminal" | tee -a "$log_file"
  echo "👉 [4/5] Menyimpan stdout/stderr lengkap ke log" | tee -a "$log_file"
  echo "👉 [5/5] Menampilkan exit code fase" | tee -a "$log_file"
  echo "--------------------------------------" | tee -a "$log_file"

  set +e
  if command -v bat >/dev/null 2>&1; then
    codex exec --sandbox workspace-write "$prompt" 2>&1 | tee -a "$log_file" | bat -l markdown --color=always --style=plain --paging=never
  else
    codex exec --sandbox workspace-write "$prompt" 2>&1 | tee -a "$log_file"
  fi
  local exit_code=${PIPESTATUS[0]}
  set -e

  phase_footer "$exit_code" "$log_file" | tee -a "$log_file"
  append_log_index "$phase" "$log_file" "$exit_code"
  return "$exit_code"
}

# --- Helper untuk mencetak file dengan bat jika tersedia ---
print_file() {
  local file="$1"
  if [ -f "$file" ]; then
    if command -v bat >/dev/null 2>&1; then
      bat "$file" -l markdown --color=always --style=plain --paging=never
    else
      cat "$file"
    fi
  else
    echo -e "${YELLOW}[Belum ada file: $file]${NC}"
  fi
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

# --- FUNGSI EKSEKUSI PERINTAH ---
execute_command() {
  local cmd="$1"
  case "$cmd" in
    bootstrap)
      run_bootstrap_logged
      ;;
    sync)
      run_sync_logged
      ;;
    plan)
      run_gemini_to_file "🧠 Menjalankan Gemini: Planner / Scope Reader" "plan" "$AI_DIR/HANDOFF.md" "$PROMPT_PLAN"
      {
        current_sprint="$(current_task_value "Current Sprint")"
        current_module="$(current_task_value "Current Module")"
        current_task="$(current_task_value "Current Task")"
        task_block="$(current_task_block "$current_task")"
        echo "# Active Task"
        echo
        echo "Source of truth: docs/TASKS.md"
        echo "Last planned at: $(now)"
        echo
        echo "Current Sprint: $current_sprint"
        echo "Current Module: $current_module"
        echo "Current Task: $current_task"
        echo "Status: In Progress"
        echo
        echo "## Task Detail"
        echo
        echo "$task_block"
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
      run_codex_logged "💻 build" "$PROMPT_BUILD"
      ;;
    review)
      run_gemini_to_file "🕵️ Menjalankan Gemini: Reviewer" "review" "$AI_DIR/REVIEW_NOTES.md" "$PROMPT_REVIEW"
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
      run_codex_logged "🔧 fix" "$PROMPT_FIX"
      ;;
    close)
      run_gemini_to_file "✅ Menjalankan Gemini: Task Closer" "close" "$AI_DIR/HANDOFF.md" "$PROMPT_CLOSE"
      {
        echo "# Session State"
        echo
        echo "Last updated: $(now)"
        echo "Workflow phase: Close"
        echo "Latest close recommendation: $AI_DIR/HANDOFF.md"
        echo "Latest log: $LOG_DIR/latest.log"
      } > "$AI_DIR/SESSION_STATE.md"
      echo -e "${YELLOW}🔄 Menjalankan sync lokal setelah close agar .ai mengikuti docs/TASKS.md...${NC}"
      run_sync_logged
      ;;
    status)
      ensure_ai_dir
      echo -e "\n${BLUE}${BOLD}===== 📋 ACTIVE TASK =====${NC}"
      print_file "$AI_DIR/ACTIVE_TASK.md"
      echo -e "\n${YELLOW}${BOLD}===== 🤝 HANDOFF =====${NC}"
      print_file "$AI_DIR/HANDOFF.md"
      echo -e "\n${GREEN}${BOLD}===== 💾 SESSION STATE =====${NC}"
      print_file "$AI_DIR/SESSION_STATE.md"
      echo -e "\n${MAGENTA}${BOLD}===== 📄 LOG TERAKHIR =====${NC}"
      if [ -f "$LOG_DIR/latest.log" ]; then
        if command -v bat >/dev/null 2>&1; then
          tail -n 80 "$LOG_DIR/latest.log" | bat -l markdown --color=always --style=plain --paging=never
        else
          tail -n 80 "$LOG_DIR/latest.log"
        fi
      else
        echo -e "${YELLOW}[Belum ada log]${NC}"
      fi
      echo ""
      ;;
    logs)
      ensure_ai_dir
      echo -e "\n${CYAN}${BOLD}===== 📑 LOG INDEX =====${NC}"
      [ -f "$LOG_DIR/index.md" ] && tail -n 30 "$LOG_DIR/index.md" || echo -e "${YELLOW}[Belum ada log index]${NC}"
      echo -e "\n${BLUE}${BOLD}===== 📄 LOG TERAKHIR =====${NC}"
      print_file "$LOG_DIR/latest.log"
      echo ""
      ;;
    *)
      echo -e "${RED}❌ Perintah tidak dikenali.${NC} Gunakan:"
      echo -e "  ${GREEN}./scripts/ai.sh bootstrap${NC}"
      echo -e "  ${GREEN}./scripts/ai.sh sync${NC}"
      echo -e "  ${CYAN}./scripts/ai.sh plan${NC}"
      echo -e "  ${BLUE}./scripts/ai.sh build${NC}"
      echo -e "  ${MAGENTA}./scripts/ai.sh review${NC}"
      echo -e "  ${YELLOW}./scripts/ai.sh fix${NC}"
      echo -e "  ${GREEN}./scripts/ai.sh close${NC}"
      echo -e "  ${NC}./scripts/ai.sh status${NC}"
      echo -e "  ${NC}./scripts/ai.sh logs${NC}"
      exit 1
      ;;
  esac
}

# --- MENU UTAMA (INTERAKTIF) ATAU EKSEKUSI LANGSUNG ---

# Jika script dijalankan dengan argumen (misal: ./ai.sh plan)
if [ "$#" -gt 0 ]; then
  execute_command "$1"
  exit 0
fi

# Jika dijalankan tanpa argumen, masuk ke mode interaktif
while true; do
  clear
  echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════════╗${NC}"
  echo -e "${CYAN}${BOLD}║       ${YELLOW}🤖 AI Agent Workflow CLI               ${CYAN}${BOLD}║${NC}"
  echo -e "${CYAN}${BOLD}║  ${BLUE}Gemini Planner${NC} + ${GREEN}Codex Builder            ${CYAN}${BOLD}║${NC}"
  echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════╝${NC}"
  
  # --- Baris ke-6 untuk Timer ---
  echo "" 
  echo ""

  echo -e "${BOLD}🎯 Pilih Fase AI:${NC}\n"
  echo -e "  ${GREEN}1) ${NC}📦 FASE 0 - Bootstrap .ai"
  echo -e "  ${BLUE}2) ${NC}🧠 FASE 1 - Gemini Plan / Scope Reader"
  echo -e "  ${CYAN}3) ${NC}💻 FASE 2 - Codex Build / Coding"
  echo -e "  ${MAGENTA}4) ${NC}🕵️  FASE 3 - Gemini Review"
  echo -e "  ${YELLOW}5) ${NC}🔧 FASE 4 - Codex Fix"
  echo -e "  ${GREEN}6) ${NC}✅ FASE 5 - Close Task"
  echo ""
  echo -e "${BOLD}📊 Monitoring & Utilities:${NC}\n"
  echo -e "  ${NC}7)  ${NC}📋 Lihat Status Context"
  echo -e "  ${NC}8)  ${NC}🐙 Git Status / Diff"
  echo -e "  ${NC}9)  ${NC}📑 Lihat Log AI Terakhir"
  echo ""
  echo -e "  ${RED}0)  🚪 Keluar${NC}"
  echo ""

  # Mulai timer di background
  stop_menu_timer
  run_menu_timer &
  MENU_TIMER_PID=$!

  echo -e -n "${YELLOW}👉 Masukkan pilihan: ${NC}"
  read -r choice

  # Matikan timer segera setelah user menekan enter
  stop_menu_timer

  case "$choice" in
    1) MODE="bootstrap"; execute_command "bootstrap"; pause ;;
    2) MODE="plan"; execute_command "plan"; pause ;;
    3) MODE="build"; execute_command "build"; pause ;;
    4) MODE="review"; execute_command "review"; pause ;;
    5) MODE="fix"; execute_command "fix"; pause ;;
    6) MODE="close"; execute_command "close"; pause ;;
    7) MODE="status"; execute_command "status"; pause ;;
    8) MODE="git"; echo -e "${YELLOW}${BOLD}===== 🐙 GIT STATUS =====${NC}"; git status --short || true; pause ;;
    9) MODE="logs"; execute_command "logs"; pause ;;
    0) echo -e "${GREEN}Sampai jumpa! 👋${NC}"; exit 0 ;;
    *) echo -e "${RED}❌ Pilihan tidak valid.${NC}"; pause ;;
  esac
done