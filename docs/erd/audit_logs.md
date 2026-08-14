# `audit_logs`

Catatan aksi kritis (spec 001, FR-028). Implementasi target via `spatie/laravel-activitylog` dengan custom Activity model + custom table name `audit_logs` (ganti default `activity_log`).

> **ponytail**: code saat ini masih native — `App\Models\AuditLog` (model biasa) + `LogAuditAction::handle()` memakai `AuditLog::create([...])` manual, dan `spatie/laravel-activitylog` belum ada di `composer.json`. Doc ini adalah desain target migrasi. Migrasi saat: aksi audit bertambah kompleks (model-event auto-log, timeline, getChanges) atau kebutuhan `activity()` helper muncul.

## Setup (diverifikasi via Context7 — spatie/laravel-activitylog)

```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

Custom model (override table name + connection):

```php
namespace App\Models;

use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected $table = 'audit_logs';
    // protected $connection = '...'; // bila perlu
}
```

Daftarkan di `config/activitylog.php`:

```php
'activity_model' => \App\Models\Activity::class,
```

## Kolom (mapping field spatie)

| Field spatie | Tipe | Isi | Catatan |
|--------------|------|-----|---------|
| id | bigint | PK auto | Default spatie |
| log_name | string, nullable | Namespace modul aksi | mis. `tenant`, `auth`, `user`, `patient`, `booking`, `transaction`, `medical_record` |
| description | string | Action code | mis. `tenant.registered`, `user.login`, `user.invited`, `patient.created` |
| subject_id | bigint unsigned, nullable | morph target | Model target (Tenant, User, Patient, dst.) — `performedOn($model)` |
| subject_type | string, nullable | morph target | Otomatis dari class subject |
| causer_id | bigint unsigned, nullable | morph actor | User pelaku; nullable untuk aksi anonim (registrasi publik) — `causedBy($user)` |
| causer_type | string, nullable | morph actor | Otomatis dari class causer |
| properties | json | Context aksi | `tenant_id`, slug, email target, status lama/baru, ip_address — `withProperties([...])` |
| created_at | timestamp | | Default spatie |
| updated_at | timestamp | | Default spatie |

## API spatie (diverifikasi Context7)

```php
activity()
    ->causedBy($user)
    ->performedOn($someContentModel)
    ->withProperties(['tenant_id' => $tenantId, 'ip_address' => $ip])
    ->log('user.login');

$last = Activity::all()->last();
$last->description;              // 'user.login'
$last->subject;                 // instance model target
$last->causer;                  // instance User
$last->getProperty('ip_address');
```

Query per tenant (tenant_id disimpan di `properties`):

```php
Activity::where('properties->tenant_id', $tenantId)->get();
```

## Wrapper `LogAuditAction`

Aksi dicatat via `App\Actions\LogAuditAction` — bungkus `activity()` helper agar signature tetap konsisten lintas controller (`LogAuditAction::handle(action, subject, causer, context, tenant)`). Signature saat ini sudah sesuai; isi `handle()` diganti dari `AuditLog::create([...])` ke rantai `activity()` di atas.

Mapping native → spatie:

| Native (saat ini) | Spatie (target) |
|---|---|
| `AuditLog::create([...])` | `activity()->...->log($action)` |
| `action` (string) | `description` |
| `subject_type` + `subject_id` | `performedOn($subject)` (morph otomatis) |
| `causer_id` | `causedBy($causer)` (morph otomatis) |
| `properties` (array) | `withProperties($context)` |
| — | `log_name` (baru: namespace modul, mis. `auth`) |
| `tenant_id` (kolom eksplisit) | `properties->tenant_id` (via `withProperties`) |

## Catatan

- **ponytail**: tidak tambah kolom `tenant_id` — disimpan di field native spatie `properties->tenant_id`. Query lewat `Activity::where('properties->tenant_id', ...)`.
- **ponytail**: index JSON path `properties->tenant_id` add saat skala terbukti lambat.
- `causedBy(null)` / omit → causer nullable untuk aksi anonim (registrasi publik tenant).
- Migration spatie dipakai apa adanya (cukup rename table via custom model `$table`).

## Logged Actions (FR-028)

Registrasi tenant, login, manajemen user (undang/hapus/ubah peran), ubah status tenant, plus aksi klinik (staff/service/patient/booking/transaction/medical_record). Dicatat via `LogAuditAction` dari 18 caller controller.

## Relasi

- morphTo `subject` (model target) — `$activity->subject`
- morphTo `causer` (User) — `$activity->causer`
- Tidak relasi Eloquent langsung ke tenant — query lewat `properties->tenant_id`.

## Delete Rule

- Audit log **immutable, tidak pernah hapus** (FR-028). `causer_id`/`subject_id` adalah morph (bukan constrained FK), jadi user/pasien/model di-soft-delete maupun hard-delete **tidak** memutus record audit log. `causer_type`/`subject_type` + id tetap merujuk walau target sudah tidak ada (resolve morph return null). Ini diinginkan: audit tidak boleh hilang saat aktor/target dihapus.