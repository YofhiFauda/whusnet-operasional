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

AI_SCRIPT="./scripts/ai.sh"

pause() {
  echo ""
  echo -e -n "${YELLOW}👉 Tekan Enter untuk kembali ke menu...${NC}"
  read -r
}

header() {
  clear
  echo -e "${CYAN}${BOLD}╔══════════════════════════════════════╗${NC}"
  echo -e "${CYAN}${BOLD}║       ${YELLOW}🤖 AI Agent Workflow CLI       ${CYAN}${BOLD}║${NC}"
  echo -e "${CYAN}${BOLD}║  ${BLUE}Gemini Planner${NC} + ${GREEN}Codex Builder    ${CYAN}${BOLD}║${NC}"
  echo -e "${CYAN}${BOLD}╚══════════════════════════════════════╝${NC}"
  echo ""
}

require_ai_script() {
  if [ ! -f "$AI_SCRIPT" ]; then
    echo -e "${RED}❌ ERROR: $AI_SCRIPT tidak ditemukan.${NC}"
    echo -e "${YELLOW}💡 Pastikan file scripts/ai.sh sudah dibuat.${NC}"
    pause
    return 1
  fi

  if [ ! -x "$AI_SCRIPT" ]; then
    chmod +x "$AI_SCRIPT"
  fi
}

require_ai_folder() {
  if [ ! -d ".ai" ]; then
    echo -e "${RED}⚠️ Folder .ai belum ada.${NC}"
    echo -e "${YELLOW}💡 Jalankan FASE 0 Bootstrap terlebih dahulu.${NC}"
    pause
    return 1
  fi
}

confirm_run() {
  local label="$1"
  echo ""
  echo -e "${CYAN}▶ Anda akan menjalankan:${BOLD} $label${NC}"
  echo -e -n "${YELLOW}❓ Lanjut? (y/n): ${NC}"
  read -r confirm

  if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo -e "${RED}⛔ Dibatalkan.${NC}"
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
  echo -e "${MAGENTA}${BOLD}🚀 Menjalankan $label...${NC}"
  echo -e "${MAGENTA}--------------------------------------${NC}"
  
  # Eksekusi script utama
  AI_ALLOW_EXTERNAL=1 $AI_SCRIPT "$command"
  
  echo -e "${MAGENTA}--------------------------------------${NC}"
  echo -e "${GREEN}✅ $label selesai.${NC}"
  pause
}

show_status() {
  require_ai_folder || return

  header
  echo -e "${BLUE}${BOLD}===== 📋 ACTIVE TASK =====${NC}"
  if [ -f ".ai/ACTIVE_TASK.md" ]; then
    cat .ai/ACTIVE_TASK.md
  else
    echo -e "${NC}.ai/ACTIVE_TASK.md belum ada.${NC}"
  fi

  echo ""
  echo -e "${YELLOW}${BOLD}===== 🤝 HANDOFF =====${NC}"
  if [ -f ".ai/HANDOFF.md" ]; then
    cat .ai/HANDOFF.md
  else
    echo -e "${NC}.ai/HANDOFF.md belum ada.${NC}"
  fi

  echo ""
  echo -e "${GREEN}${BOLD}===== 💾 SESSION STATE =====${NC}"
  if [ -f ".ai/SESSION_STATE.md" ]; then
    cat .ai/SESSION_STATE.md
  else
    echo -e "${NC}.ai/SESSION_STATE.md belum ada.${NC}"
  fi

  pause
}

show_review_notes() {
  require_ai_folder || return

  header
  echo -e "${MAGENTA}${BOLD}===== 🔍 REVIEW NOTES =====${NC}"
  if [ -f ".ai/REVIEW_NOTES.md" ]; then
    cat .ai/REVIEW_NOTES.md
  else
    echo -e "${NC}.ai/REVIEW_NOTES.md belum ada.${NC}"
  fi

  pause
}

show_changelog() {
  require_ai_folder || return

  header
  echo -e "${CYAN}${BOLD}===== 📝 AI CHANGELOG =====${NC}"
  if [ -f ".ai/CHANGELOG_AI.md" ]; then
    cat .ai/CHANGELOG_AI.md
  else
    echo -e "${NC}.ai/CHANGELOG_AI.md belum ada.${NC}"
  fi

  pause
}

show_ai_logs() {
  require_ai_folder || return

  header
  echo -e "${CYAN}${BOLD}===== 📑 AI LOG INDEX (Last 30) =====${NC}"
  if [ -f ".ai/logs/index.md" ]; then
    tail -n 30 .ai/logs/index.md
  else
    echo -e "${NC}.ai/logs/index.md belum ada.${NC}"
  fi

  echo ""
  echo -e "${BLUE}${BOLD}===== 📄 AI LOG TERAKHIR =====${NC}"
  if [ -f ".ai/logs/latest.log" ]; then
    cat .ai/logs/latest.log
  else
    echo -e "${NC}.ai/logs/latest.log belum ada.${NC}"
  fi

  pause
}

edit_active_task() {
  require_ai_folder || return

  ${EDITOR:-nano} .ai/ACTIVE_TASK.md
}

show_git_status() {
  header
  echo -e "${YELLOW}${BOLD}===== 🐙 GIT STATUS =====${NC}"
  git status --short || true

  echo ""
  echo -e "${CYAN}${BOLD}===== 📊 GIT DIFF STAT =====${NC}"
  git diff --stat || true

  echo ""
  echo -e -n "${YELLOW}❓ Tampilkan full git diff? (y/n): ${NC}"
  read -r show_diff

  if [ "$show_diff" = "y" ] || [ "$show_diff" = "Y" ]; then
    echo ""
    echo -e "${BLUE}${BOLD}===== 📜 FULL GIT DIFF =====${NC}"
    git diff || true
  fi

  pause
}

while true; do
  header

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
  echo -e "  ${NC}8)  ${NC}✏️  Edit Active Task"
  echo -e "  ${NC}9)  ${NC}🔍 Lihat Review Notes"
  echo -e "  ${NC}10) ${NC}📝 Lihat AI Changelog"
  echo -e "  ${NC}11) ${NC}🐙 Git Status / Diff"
  echo -e "  ${NC}12) ${NC}📑 Lihat Log AI Terakhir"
  echo ""
  echo -e "  ${RED}0)  🚪 Keluar${NC}"
  echo ""

  echo -e -n "${YELLOW}👉 Masukkan pilihan: ${NC}"
  read -r choice

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
      echo -e "${GREEN}Sampai jumpa! 👋${NC}"
      exit 0
      ;;
    *)
      echo -e "${RED}❌ Pilihan tidak valid.${NC}"
      pause
      ;;
  esac
done