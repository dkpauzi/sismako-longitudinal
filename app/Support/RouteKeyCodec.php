<?php

namespace App\Support;

/**
 * Codec obfuscasi ID untuk route key (pengganti Hashids, ZERO dependency).
 *
 * Alasan: paket hashids/hashids tak dapat dipasang di lingkungan ini (network),
 * dan constraint proyek justru melarang penambahan dependency/kolom DB. Codec ini
 * memenuhi tujuan yang sama: mengubah integer PK menjadi string pendek non-sekuensial
 * secara MATEMATIS (tanpa hit DB, tanpa kolom baru), dan dapat dibalik.
 *
 * Mekanisme: Feistel cipher 32-bit (4 ronde) — bijeksi pada [0, 2^32), sehingga
 * setiap id unik terpetakan ke satu token unik dan sebaliknya (dibuktikan uji
 * round-trip). Hasil di-encode base62. Kunci ronde diturunkan dari APP_KEY +
 * nama model, sehingga token satu model tidak valid untuk model lain.
 */
final class RouteKeyCodec
{
    private const ROUNDS = 4;
    private const BASE62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** @var array<string,self> */
    private static array $cache = [];

    private int $keySeed;

    private function __construct(string $salt)
    {
        // crc32 → int 32-bit deterministik sebagai benih kunci ronde.
        $this->keySeed = crc32($salt);
    }

    /** Instance ter-cache per kelas model (kunci = APP_KEY | FQCN). */
    public static function for(string $modelClass): self
    {
        return self::$cache[$modelClass] ??= new self((string) config('app.key') . '|' . $modelClass);
    }

    public function encode(int $id): string
    {
        $v = $id & 0xFFFFFFFF;
        [$l, $r] = $this->feistel(($v >> 16) & 0xFFFF, $v & 0xFFFF, encrypt: true);
        $token = (($l & 0xFFFF) << 16) | ($r & 0xFFFF);

        return $this->toBase62($token);
    }

    /** @return int|null id asli, atau null bila token tidak valid. */
    public function decode(string $token): ?int
    {
        $num = $this->fromBase62($token);
        if ($num === null) {
            return null;
        }

        [$l, $r] = $this->feistel(($num >> 16) & 0xFFFF, $num & 0xFFFF, encrypt: false);

        return (($l & 0xFFFF) << 16) | ($r & 0xFFFF);
    }

    /**
     * Ronde Feistel seimbang atas dua paruh 16-bit.
     * Enkripsi: (L,R) → (R, L XOR F(R)). Dekripsi membalik ronde secara terbalik.
     *
     * @return array{0:int,1:int}
     */
    private function feistel(int $l, int $r, bool $encrypt): array
    {
        if ($encrypt) {
            for ($i = 0; $i < self::ROUNDS; $i++) {
                [$l, $r] = [$r, $l ^ $this->roundFn($r, $i)];
            }
        } else {
            for ($i = self::ROUNDS - 1; $i >= 0; $i--) {
                // Balikan dari (l,r)→(r, l^F(r)): prevR = l ; prevL = r ^ F(l).
                [$l, $r] = [$r ^ $this->roundFn($l, $i), $l];
            }
        }

        return [$l & 0xFFFF, $r & 0xFFFF];
    }

    /** Fungsi ronde deterministik → 16-bit. */
    private function roundFn(int $half, int $round): int
    {
        return crc32($half . ':' . $round . ':' . $this->keySeed) & 0xFFFF;
    }

    private function toBase62(int $num): string
    {
        if ($num === 0) {
            return '0';
        }

        $out = '';
        while ($num > 0) {
            $out = self::BASE62[$num % 62] . $out;
            $num = intdiv($num, 62);
        }

        return $out;
    }

    private function fromBase62(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $num = 0;
        for ($i = 0, $len = strlen($token); $i < $len; $i++) {
            $pos = strpos(self::BASE62, $token[$i]);
            if ($pos === false) {
                return null; // karakter di luar alfabet → token tak valid
            }
            $num = $num * 62 + $pos;
        }

        return $num;
    }
}
