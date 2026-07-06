<!--
=== SYNC IMPACT REPORT ===
Version change: (uninitialized template) -> 1.0.0
Modified principles: N/A (first concrete ratification; all placeholders filled)
Added sections:
  - Core Principles I-V (Clean Code, TDD, Multi-Tenant Isolation, Simplicity, Bounded Size)
  - Section "Tech Stack & Constraints"
  - Section "Development Workflow"
  - Governance
Removed sections: none
Templates requiring updates:
  - .specify/templates/plan-template.md        — ✅ no change (Constitution Check section is generic; gates derive from this file)
  - .specify/templates/spec-template.md        — ✅ no change (acceptance scenarios align with TDD)
  - .specify/templates/tasks-template.md       — ✅ no change (already enforces test-first: "Write these tests FIRST, ensure they FAIL")
  - .specify/templates/commands/*.md           — ⚠ pending (directory empty / no command files to verify)
  - README.md                                   — ⚠ pending (optional: add pointer to this constitution)
Follow-up TODOs: none
===
-->

# Sim Clinic Laravel Constitution

Monorepo multi-tenant: backend Laravel API (PostgreSQL, single-db tenancy) +
frontend TanStack Start. Konstitusi ini mengatur semua pekerjaan spesifikasi,
implementasi, dan review pada project ini.

## Core Principles

### I. Clean Code (WAJIB)

Setiap kode yang ditulis WAJIB memenuhi standar Clean Code. Eksekusi dan
verifikasi melalui skill `/clean-code-principles` sebelum menandai task selesai.

Aturan tidak boleh dinegosiasikan:

- Nama variabel, fungsi, class, dan file WAJIB deskriptif dan self-documenting.
  Komentar hanya untuk logika rumit atau edge case — dilarang mengulang apa
  yang kode sudah nyatakan.
- Satu fungsi/method melakukan satu hal (Single Responsibility). Payload function
  kecil, level abstraksi tunggal per method.
- Duplikasi WAJIB dihilangkan (DRY) tanpa jatuh ke abstraksi prematur (lihat
  Prinsip IV).
- Dead code, komentar yang dinonaktifkan, dan boilerplate "untuk nanti"
  DILARANG di-commit.
- Error handling eksplisit di trust boundary (input user, response external
  service, akses database). Tidak ada penelan exception diam-diam.
- Format dan gaya WAJIB mengikuti konvensi yang sudah ada di file/sekitarnya.

Rasional: kode dibaca 10x lebih sering daripada ditulis. Kode tidak bersih
memperlambat review, memperbesar bug surface, dan menyulitkan onboarding.

### II. Test-Driven Development (NON-NEGOTIABLE)

TDD WAJIB diterapkan dengan siklus Red-Green-Refactor yang ketat.

Urutan tidak boleh dibalik:

1. Tulis test untuk perilaku yang dimaksud.
2. Konfirmasi test GAGAL (Red) sebelum implementasi.
3. Tulis implementasi minimum agar test LULUS (Green).
4. Refactor dengan test tetap hijau.

Aturan tambahan:

- Feature test (PHPUnit) WAJIB untuk setiap endpoint API dan user journey.
- Unit test WAJIB untuk Service/Action/ logic bisnis non-trivial.
- Test WAJIB independent dan dapat dijalankan terpisah (no shared mutable state
  antar test).
- Factory dan seeder WAJIB digunakan untuk data test — dilarang hardcode data
  di test yang menyebabkan keterikitan antar case.
- Coverage bukan target nominal; setiap cabang logika non-trivial WAJIB punya
  test yang gagal jika logikanya rusak.

Rasional: test yang ditulis lebih dulu menjamin kode memenuhi spesifikasi dan
memberikan jaring pengaman untuk refactor di masa depan.

### III. Multi-Tenant Isolation (NON-NEGOTIABLE)

Project ini menggunakan arsitektur multi-tenant single-database. Isolasi data
antar tenant WAJIB dijaga di setiap lapisan.

Aturan:

- Setiap query yang menyentuh data tenant WAJIB ter-scoped oleh tenant_id secara
  otomatis (global scope / middleware), bukan diandalkan pada ingatan developer.
- Tidak ada endpoint atau query yang boleh membocorkan data tenant ke tenant lain.
- Migrasi dan model WAJIB menjaga kolom tenant_id dengan constraint NOT NULL
  kecuali ditentukan lain (tenant pusat/central).
- Aksi administratif lintas tenant WAJIB melalui gate/policy eksplisit.

Rasional: kebocoran data antar tenant adalah kegagalan keamanan fatal yang
merusak kepercayaan klien dan berpotensi melanggar regulasi.

### IV. Simplicity (YAGNI)

Kode paling baik adalah kode yang tidak pernah ditulis. Setiap permintaan
fitur dievaluasi dulu melalui tangga berikut sebelum menulis kode:

1. Apakah ini perlu ada sama sekali? (YAGNI)
2. Apakah stdlib / fitur native platform sudah cukup?
3. Apakah sudah ada helper/util/pattern di codebase ini? — gunakan, jangan
   tulis ulang.
4. Apakah dependency yang sudah terpasang sudah menyelesaikan? — jangan tambah
   dependency baru untuk apa yang beberapa baris bisa lakukan.
5. Apakah bisa satu baris? Satu baris.
6. Baru kemudian: kode minimum yang berfungsi.

Dilarang: interface dengan satu implementasi, factory untuk satu product,
config untuk nilai yang tidak pernah berubah, scaffolding "untuk nanti".
Abstraksi hanya ketika ada minimal 2 konsumen nyata.

Rasional: kompleksitas yang tidak pernah diminta adalah utang teknis yang
ditanggung oleh semua orang setelahnya.

### V. Bounded Size

Ukuran unit kode WAJIB dibatasi untuk menjaga kemudahbacaan.

- Class PHP WAJIB <= 300 baris; method WAJIB <= 100 baris. Lebih dari itu
  WAJIB di-extract ke Service/Action/Trait.
- File komponen React WAJIB <= 300 baris. Lebih dari itu WAJIB di-extract ke
  sub-komponen (folder `components/` atau `partials/` di dalam entitas yang
  sama).
- File dan folder frontend WAJIB kebab-case dan istilah teknis Inggris.
- Teks UI WAJIB melalui sistem terjemahan (`__()` / helper `t()` dari file
  bahasa `lang/id/*.php`) — dilarang hardcode string yang terlihat user.

Rasional: batas ukuran memaksa pemecahan masalah menjadi unit yang dapat
diuji, ditinjau, dan diganti tanpa risiko luas.

## Tech Stack & Constraints

- **Backend**: Laravel API (PHP), aplikasi multi-tenant single-database.
- **Storage**: PostgreSQL.
- **Frontend**: Tanstack Start (React), TypeScript.
- **Layout**: Monorepo `apps/api/` (Laravel) + `apps/web/` (frontend).
- **Bahasa UI**: Indonesia (default locale `id`); semua teks via file bahasa.
- **Testing**: PHPUnit (backend), runner frontend mengikuti stack yang
  terpasang.
- **Format/lint**: Pint (backend), tool frontend sesuai konfigurasi monorepo.
  Command format/build HANYA dijalankan atas instruksi eksplisit user.

## Development Workflow

Alur WAJIB untuk setiap fitur/task:

1. **Spesifikasi** — fitur punya spec.md dengan user story yang dapat diuji
   mandiri sebelum implementasi dimulai.
2. **Plan** — plan.md WAJIB lulus Constitution Check sebelum riset/fase desain.
3. **Tasks** — tasks.md disusun per user story; test task ditulis lebih dulu
   dan dikonfirmasi gagal sebelum implementasi.
4. **Implementasi** — siklus TDD (Red-Green-Refactor) per task. Commit setelah
   task atau kelompok logis selesai.
5. **Review** — kode direview terhadap konstitusi ini sebelum merge:
   - Clean Code via `/clean-code-principles`.
   - Cakupan test memadai untuk logika non-trivial.
   - Tidak ada pelanggaran isolasi tenant.
   - Ukuran unit kode dalam batas.
   - Tidak ada teks UI hardcode.
6. **Validasi** — jalankan `php artisan test` sebelum menyatakan task selesai.
   Command format/build (Pint, bun build, dev server) HANYA atas instruksi
   eksplisit user.

## Governance

- Konstitusi ini mengesampingkan semua praktik lain bila bertentangan.
- Amandemen WAJIB didokumentasikan melalui perintah `/speckit-constitution`,
  mencatat rationale perubahan, dan memperbarui `LAST_AMENDED_DATE` serta
  `CONSTITUTION_VERSION` sesuai semantic versioning:
  - **MAJOR**: penghapusan/redefinisi prinsip yang tidak kompatibel mundur.
  - **MINOR**: prinsip/section baru atau perluasan material.
  - **PATCH**: klarifikasi, typo, perbaikan non-semantik.
- Setiap PR/review WAJIB memverifikasi kepatuhan terhadap prinsip aktif.
  Kompleksitas yang melanggar batas WAJIB didokumentasikan di tabel Complexity
  Tracking pada plan.md dengan alasan kebutuhan dan alternatif yang ditolak.
- Panduan development runtime mengikuti `CLAUDE.md` (global) dan skill aktif:
  `/clean-code-principles`, `laravel-best-practices`, `laravel-inertia-react`.

**Version**: 1.0.0 | **Ratified**: 2026-07-07 | **Last Amended**: 2026-07-07
