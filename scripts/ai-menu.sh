#!/usr/bin/env bash
set -e

AI_SCRIPT="./scripts/ai.sh"

pause() {
  echo ""
  read -rp "Tekan Enter untuk kembali ke menu..."
}

header() {
  clear
  echo "======================================"
  echo " AI Agent Workflow CLI"
  echo " Gemini Planner/Reviewer + Codex Builder"
  echo "======================================"
  echo ""
}

require_ai_script() {
  if [ ! -f "$AI_SCRIPT" ]; then
    echo "ERROR: $AI_SCRIPT tidak ditemukan."
    echo "Pastikan file scripts/ai.sh sudah dibuat."
    pause
    return 1
  fi

  if [ ! -x "$AI_SCRIPT" ]; then
    chmod +x "$AI_SCRIPT"
  fi
}

require_ai_folder() {
  if [ ! -d ".ai" ]; then
    echo "Folder .ai belum ada."
    echo "Jalankan FASE 0 Bootstrap terlebih dahulu."
    pause
    return 1
  fi
}

confirm_run() {
  local label="$1"
  echo ""
  echo "Anda akan menjalankan: $label"
  read -rp "Lanjut? (y/n): " confirm

  if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Dibatalkan."
    pause
    return 1
  fi
}

run_phase() {
  local command="$1"
  local label="$2"

  require_ai_script || return

  if [ "$command" != "bootstrap" ]; then
    require_ai_folder || return
  fi

  confirm_run "$label" || return

  echo ""
  echo "Menjalankan $label..."
  echo "--------------------------------------"
  AI_ALLOW_EXTERNAL=1 $AI_SCRIPT "$command"
  echo "--------------------------------------"
  echo "$label selesai."
  pause
}

show_status() {
  require_ai_folder || return

  header
  echo "===== ACTIVE TASK ====="
  if [ -f ".ai/ACTIVE_TASK.md" ]; then
    cat .ai/ACTIVE_TASK.md
  else
    echo ".ai/ACTIVE_TASK.md belum ada."
  fi

  echo ""
  echo "===== HANDOFF ====="
  if [ -f ".ai/HANDOFF.md" ]; then
    cat .ai/HANDOFF.md
  else
    echo ".ai/HANDOFF.md belum ada."
  fi

  echo ""
  echo "===== SESSION STATE ====="
  if [ -f ".ai/SESSION_STATE.md" ]; then
    cat .ai/SESSION_STATE.md
  else
    echo ".ai/SESSION_STATE.md belum ada."
  fi

  pause
}

show_review_notes() {
  require_ai_folder || return

  header
  echo "===== REVIEW NOTES ====="
  if [ -f ".ai/REVIEW_NOTES.md" ]; then
    cat .ai/REVIEW_NOTES.md
  else
    echo ".ai/REVIEW_NOTES.md belum ada."
  fi

  pause
}

show_changelog() {
  require_ai_folder || return

  header
  echo "===== AI CHANGELOG ====="
  if [ -f ".ai/CHANGELOG_AI.md" ]; then
    cat .ai/CHANGELOG_AI.md
  else
    echo ".ai/CHANGELOG_AI.md belum ada."
  fi

  pause
}

show_ai_logs() {
  require_ai_folder || return

  header
  echo "===== AI LOG INDEX ====="
  if [ -f ".ai/logs/index.md" ]; then
    tail -n 30 .ai/logs/index.md
  else
    echo ".ai/logs/index.md belum ada."
  fi

  echo ""
  echo "===== AI LOG TERAKHIR ====="
  if [ -f ".ai/logs/latest.log" ]; then
    cat .ai/logs/latest.log
  else
    echo ".ai/logs/latest.log belum ada."
  fi

  pause
}

edit_active_task() {
  require_ai_folder || return

  ${EDITOR:-nano} .ai/ACTIVE_TASK.md
}

show_git_status() {
  header
  echo "===== GIT STATUS ====="
  git status --short || true

  echo ""
  echo "===== GIT DIFF STAT ====="
  git diff --stat || true

  echo ""
  read -rp "Tampilkan full git diff? (y/n): " show_diff

  if [ "$show_diff" = "y" ] || [ "$show_diff" = "Y" ]; then
    echo ""
    echo "===== FULL GIT DIFF ====="
    git diff || true
  fi

  pause
}

while true; do
  header

  echo "Pilih fase:"
  echo ""
  echo "  1) FASE 0 - Bootstrap .ai"
  echo "  2) FASE 1 - Gemini Plan / Scope Reader"
  echo "  3) FASE 2 - Codex Build / Coding"
  echo "  4) FASE 3 - Gemini Review"
  echo "  5) FASE 4 - Codex Fix"
  echo "  6) FASE 5 - Close Task"
  echo ""
  echo "Monitoring:"
  echo "  7) Lihat Status Context"
  echo "  8) Edit Active Task"
  echo "  9) Lihat Review Notes"
  echo "  10) Lihat AI Changelog"
  echo "  11) Git Status / Diff"
  echo "  12) Lihat Log AI Terakhir"
  echo ""
  echo "  0) Keluar"
  echo ""

  read -rp "Masukkan pilihan: " choice

  case "$choice" in
    1)
      run_phase "bootstrap" "FASE 0 - Bootstrap .ai"
      ;;
    2)
      run_phase "plan" "FASE 1 - Gemini Plan / Scope Reader"
      ;;
    3)
      run_phase "build" "FASE 2 - Codex Build / Coding"
      ;;
    4)
      run_phase "review" "FASE 3 - Gemini Review"
      ;;
    5)
      run_phase "fix" "FASE 4 - Codex Fix"
      ;;
    6)
      run_phase "close" "FASE 5 - Close Task"
      ;;
    7)
      show_status
      ;;
    8)
      edit_active_task
      ;;
    9)
      show_review_notes
      ;;
    10)
      show_changelog
      ;;
    11)
      show_git_status
      ;;
    12)
      show_ai_logs
      ;;
    0)
      echo "Keluar."
      exit 0
      ;;
    *)
      echo "Pilihan tidak valid."
      pause
      ;;
  esac
done
