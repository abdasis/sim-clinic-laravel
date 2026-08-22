<?php

namespace Tests\Unit;

use App\Support\Search;
use PHPUnit\Framework\TestCase;

/**
 * Kata yang diketik pengguna diubah jadi pola LIKE yang aman.
 *
 * Yang dijaga: `%` dan `_` — dua karakter yang berarti "apa saja" bagi LIKE —
 * kembali jadi huruf biasa. Tanpa itu, mencari "diskon 50%" ikut menarik
 * "diskon 500 ribu", dan pengetiknya tidak punya cara menebak kenapa.
 */
class SearchPatternTest extends TestCase
{
    public function test_a_plain_word_is_wrapped_so_it_matches_anywhere(): void
    {
        $this->assertSame('%facial%', Search::pattern('facial'));
    }

    public function test_a_percent_sign_becomes_an_ordinary_character(): void
    {
        $this->assertSame('%diskon 50\%%', Search::pattern('diskon 50%'));
    }

    public function test_an_underscore_becomes_an_ordinary_character(): void
    {
        $this->assertSame('%facial\_glow%', Search::pattern('facial_glow'));
    }

    /**
     * Garis miring terbaliknya dilucuti lebih dulu. Kalau urutannya terbalik,
     * pelarian yang baru ditambahkan untuk `%` ikut terlarikan lagi dan
     * polanya berhenti mencocokkan apa pun.
     */
    public function test_a_backslash_is_escaped_before_the_wildcards(): void
    {
        $this->assertSame('%50\\\\\%%', Search::pattern('50\\%'));
    }

    public function test_surrounding_spaces_are_dropped(): void
    {
        $this->assertSame('%botox%', Search::pattern('  botox  '));
    }
}
