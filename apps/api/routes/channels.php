<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Kotak masuk klinik hanya boleh didengar staf klinik itu sendiri. Id tenant
| dibandingkan sebagai angka karena nilainya datang dari URL sebagai string;
| tanpa cast, "01" dan "1" akan lolos sebagai dua tenant berbeda.
|
*/

Broadcast::channel('tenant.{tenantId}.inbox', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === (int) $tenantId;
});
