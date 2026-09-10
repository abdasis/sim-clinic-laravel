<?php

namespace Tests\Unit;

use App\Support\ReceiptAddress;
use PHPUnit\Framework\TestCase;

/**
 * Alamat klinik di atas kertas thermal 48mm.
 *
 * Lahir dari nota sungguhan: alamat kliniknya membawa tautan Google Maps yang
 * memakan dua baris penuh — deretan karakter acak yang tidak bisa diklik siapa
 * pun di atas kertas.
 */
class ReceiptAddressTest extends TestCase
{
    public function test_it_drops_links_whatever_their_scheme(): void
    {
        $this->assertSame(
            'Jl Merdeka 10',
            ReceiptAddress::format('Jl Merdeka 10 https://maps.app.goo.gl/5MwdHVGJ6E'),
        );

        // Persis seperti yang tercetak di nota: skemanya ikut terpenggal.
        $this->assertSame(
            'Jl Merdeka 10',
            ReceiptAddress::format('Jl Merdeka 10 os://maps.app.goo.gl/5MwdHVGJ6E'),
        );
    }

    public function test_it_drops_bare_links_without_a_scheme(): void
    {
        $this->assertSame(
            'Jl Merdeka 10',
            ReceiptAddress::format('Jl Merdeka 10, www.mebaclinic.com'),
        );
    }

    public function test_it_collapses_line_breaks_into_one_paragraph(): void
    {
        $this->assertSame(
            'Jl Merdeka 10 Medan Selayang',
            ReceiptAddress::format("Jl Merdeka 10\n\n  Medan   Selayang"),
        );
    }

    /**
     * Alamat klinik yang wajar dibiarkan utuh. Memotongnya lebih pendek
     * menghasilkan penggalan yang tidak menuntun siapa pun ke mana pun, dan
     * alamat yang salah lebih buruk daripada alamat yang panjang.
     */
    public function test_it_leaves_a_reasonable_address_intact(): void
    {
        $address = 'Jl Ringroad Blok A 10, Tanjung Sari, Kecamatan Medan Selayang';

        $this->assertSame($address, ReceiptAddress::format($address));
    }

    public function test_it_fences_an_address_that_truly_runs_away(): void
    {
        $trimmed = ReceiptAddress::format(
            'Jl Ringroad Pusat Bisnis Center Blok A Nomor 10, Kelurahan Tanjung Sari, '
            .'Kecamatan Medan Selayang, Kota Medan, Sumatera Utara 20132',
        );

        $this->assertNotNull($trimmed);
        $this->assertLessThanOrEqual(92, mb_strlen($trimmed));
        $this->assertStringEndsWith('…', $trimmed);
        // Dipotong di batas kata: tidak ada spasi menggantung sebelum elipsis.
        $this->assertStringNotContainsString(' …', $trimmed);
    }

    public function test_it_returns_nothing_when_only_a_link_remains(): void
    {
        $this->assertNull(ReceiptAddress::format('https://maps.app.goo.gl/abc'));
        $this->assertNull(ReceiptAddress::format('   '));
        $this->assertNull(ReceiptAddress::format(null));
    }
}
