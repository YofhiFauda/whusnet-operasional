# AI Prompts 
# Website Billing ISP Berbasis Master Data Pelanggan 

Gunakan prompt ini setiap kali bekerja dengan AI coding.

---

## 1. Prompt Awal Setiap Sesi

```md
Baca semua dokumen berikut sebelum bekerja:

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Setelah membaca, jawab:

1. Kita sedang berada di sprint berapa?
2. Task aktif saat ini apa?
3. Modul apa yang boleh disentuh?
4. Modul apa yang tidak boleh disentuh?
5. Acceptance criteria apa yang harus dipenuhi?
6. Apakah ada risiko scope creep?

Jangan coding dulu sebelum saya menyetujui rencana Anda.
2. Prompt Planner
Anda bertindak sebagai Technical Planner.

Berdasarkan dokumen project:

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Tugas Anda:
Buat breakdown detail untuk task aktif di `docs/TASKS.md`.

Jangan menulis kode.

Output yang saya mau:

1. Nama task aktif.
2. Tujuan task.
3. File yang kemungkinan dibuat/diubah.
4. Urutan pengerjaan.
5. Dependency.
6. Acceptance criteria.
7. Risiko teknis.
8. Hal yang tidak boleh dikerjakan.
9. Cara test manual.
10. Rekomendasi apakah task ini terlalu besar dan perlu dipecah.
3. Prompt Builder
Anda bertindak sebagai Builder.

Baca terlebih dahulu:

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Kerjakan hanya task aktif di `docs/TASKS.md`.

Aturan:
1. Jangan mengerjakan task lain.
2. Jangan mengerjakan modul sprint berikutnya.
3. Jangan membuat fitur post-MVP.
4. Jangan mengubah file yang tidak relevan.
5. Sebelum coding, tampilkan rencana file yang akan dibuat/diubah.
6. Setelah coding, jelaskan cara test.
7. Setelah selesai, update `docs/TASKS.md`.

Output akhir wajib berisi:

- Task dikerjakan
- File diubah
- Alasan perubahan
- Cara test
- Status acceptance criteria
- Risiko/catatan
- Next task
4. Prompt Reviewer
Anda bertindak sebagai Reviewer.

Review perubahan kode terakhir berdasarkan:

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md

Cek:

1. Apakah perubahan sesuai task aktif?
2. Apakah ada fitur di luar scope?
3. Apakah ada modul sprint berikutnya yang disentuh?
4. Apakah acceptance criteria terpenuhi?
5. Apakah ada risiko bug?
6. Apakah RBAC aman?
7. Apakah struktur database sesuai konsep?
8. Apakah ada file yang tidak seharusnya diubah?
9. Apakah perlu refactor?
10. Apakah task boleh dinyatakan selesai?

Jangan menulis fitur baru.
Berikan review dan rekomendasi perbaikan saja.
5. Prompt Debugger
Anda bertindak sebagai Debugger.

Saya mengalami error berikut:

[PASTE ERROR DI SINI]

Baca konteks project:

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/TASKS.md

Aturan:
1. Fokus hanya pada error ini.
2. Jangan membuat fitur baru.
3. Jangan mengubah modul yang tidak terkait error.
4. Jelaskan penyebab error.
5. Berikan langkah perbaikan.
6. Jika perlu coding, sebutkan file yang akan diubah dulu.
7. Setelah perbaikan, jelaskan cara test.

Jangan lompat ke task lain.
6. Prompt Scope Check
Sebelum implementasi, lakukan scope check.

Jawab:

1. Task ini masuk sprint berapa?
2. Modul apa yang disentuh?
3. Requirement PRD mana yang relevan?
4. File apa saja yang akan dibuat/diubah?
5. File apa saja yang tidak boleh disentuh?
6. Fitur apa yang tidak boleh dibuat?
7. Acceptance criteria apa yang harus terpenuhi?
8. Apakah task ini berpotensi keluar MVP?
9. Apakah perlu bertanya ke user sebelum lanjut?

Jangan coding sebelum scope check selesai.
7. Prompt Lanjut Task Berikutnya
Baca `docs/TASKS.md`.

Tentukan task berikutnya yang statusnya Todo setelah task terakhir Done.

Sebelum coding:
1. Jelaskan task berikutnya.
2. Jelaskan alasan task tersebut harus dikerjakan sekarang.
3. Jelaskan dependency.
4. Jelaskan file yang akan dibuat/diubah.
5. Jelaskan acceptance criteria.

Jangan coding sebelum saya menyetujui.
8. Prompt Cegah AI Keluar Scope
Cek apakah rencana implementasi Anda keluar dari scope MVP.

Bandingkan dengan:

- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md

Jika ada fitur di luar MVP, pisahkan ke bagian:

Post-MVP Backlog

Jangan implementasikan fitur post-MVP.
9. Prompt Commit Message
Buatkan commit message berdasarkan perubahan terakhir.

Format:

type(scope): short summary

Body:
- apa yang berubah
- kenapa berubah
- cara test
- acceptance criteria yang terpenuhi

Gunakan type:
- feat
- fix
- docs
- refactor
- test
- chore

---

# FILE: docs/TOMORROW_START.md

```md
# Tomorrow Start Guide
# Rencana Kerja Besok

## Tujuan Besok
Tujuan besok bukan langsung membuat semua website.

Tujuan besok adalah:

1. Menyiapkan project.
2. Menyiapkan dokumen kontrol AI.
3. Memastikan AI membaca scope.
4. Memulai Sprint 1 dengan task kecil.
5. Tidak loncat ke POP, pelanggan, billing, atau pembayaran sebelum RBAC dasar siap.

---

## Langkah 1 — Buat Folder Dokumen
Di root project, buat folder:

```bash
mkdir -p docs