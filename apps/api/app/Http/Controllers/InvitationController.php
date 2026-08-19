<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function show(string $token, InvitationService $service): JsonResponse
    {
        $invitation = $service->resolvePending($token);

        // Endpoint ini publik: siapa pun yang menebak token akan melihat
        // isinya. Karena itu yang keluar dipangkas ke yang benar-benar
        // dibutuhkan halaman undangan — alamat surel disamarkan agar tidak
        // bisa dipanen, dan nama klinik dikirim menggantikan slug supaya
        // penerima undangan tahu ini dari mana tanpa membocorkan alamat URL
        // klinik ke penebak token. Peran sengaja tidak disebut sama sekali:
        // penerima akan mengetahuinya setelah masuk.
        return response()->json([
            'data' => [
                'email' => $this->maskEmail($invitation->email),
                'tenant_name' => $invitation->tenant->name,
            ],
            'meta' => [],
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, InvitationService $service): JsonResponse
    {
        $user = $service->accept($token, $request->validated('password'));

        return response()->json([
            'data' => [],
            'meta' => [
                'redirect_to' => '/'.$user->tenant->slug.'/login',
                'message' => __('auth.password_set'),
            ],
        ]);
    }

    /**
     * Samarkan alamat surel: cukup untuk dikenali pemiliknya, tidak cukup
     * untuk dikumpulkan orang lain.
     *
     * Huruf pertama dan terakhir bagian sebelum @ dipertahankan; nama yang
     * hanya satu-dua huruf disamarkan seluruhnya, karena mempertahankan dua
     * huruf dari nama dua huruf sama saja dengan tidak menyamarkan apa pun.
     */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $masked = mb_strlen($name) <= 2
            ? str_repeat('*', max(mb_strlen($name), 1))
            : mb_substr($name, 0, 1).str_repeat('*', mb_strlen($name) - 2).mb_substr($name, -1);

        return $domain === '' ? $masked : $masked.'@'.$domain;
    }
}
