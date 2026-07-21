<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\RouteKeyCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Obfuscasi route key (Option A, zero-DB). Menjamin:
 *  - Codec Feistel adalah BIJEKSI (round-trip 1:1, tak ada tabrakan) → binding aman.
 *  - Token tidak sama dengan id mentah (URL ter-obfuscate).
 *  - Route model binding (dipakai Filament) mengembalikan record yang benar.
 *  - Token satu model tidak "bocor" jadi valid di model lain (salt per-kelas).
 */
class RouteKeyObfuscationTest extends TestCase
{
    use RefreshDatabase;

    public function test_codec_is_reversible_and_collision_free(): void
    {
        $codec = RouteKeyCodec::for(Student::class);
        $seen = [];
        $badRoundTrip = null;
        $collision = null;

        // Loop internal (satu assertion di akhir) — hindari overhead 40k assertion.
        for ($id = 1; $id <= 20000; $id++) {
            $token = $codec->encode($id);
            if ($codec->decode($token) !== $id) {
                $badRoundTrip = $id;
                break;
            }
            if (isset($seen[$token])) {
                $collision = "{$id} vs {$seen[$token]}";
                break;
            }
            $seen[$token] = $id;
        }

        $this->assertNull($badRoundTrip, "Gagal round-trip pada id={$badRoundTrip}");
        $this->assertNull($collision, "Tabrakan token: {$collision}");
        $this->assertCount(20000, $seen, 'Semua token harus unik pada 20.000 id.');

        // Edge cases.
        foreach ([0, 1, 999999, 2147483647] as $id) {
            $this->assertSame($id, $codec->decode($codec->encode($id)));
        }
    }

    public function test_invalid_token_decodes_to_null(): void
    {
        $codec = RouteKeyCodec::for(Student::class);
        $this->assertNull($codec->decode(''));
        $this->assertNull($codec->decode('!!!not-base62!!!'));
    }

    public function test_token_differs_from_raw_id_per_model(): void
    {
        // Model yang sama, id sama → token sama (deterministik).
        $this->assertSame(
            RouteKeyCodec::for(Student::class)->encode(7),
            RouteKeyCodec::for(Student::class)->encode(7),
        );
        // Model berbeda, id sama → token BERBEDA (salt per-kelas).
        $this->assertNotSame(
            RouteKeyCodec::for(Student::class)->encode(7),
            RouteKeyCodec::for(Teacher::class)->encode(7),
        );
    }

    public function test_model_route_binding_round_trips(): void
    {
        $u = User::factory()->create();
        $student = Student::create([
            'user_id' => $u->id, 'name' => 'Uji', 'nisn' => '3000000009', 'gender' => 'L', 'status' => 'active',
        ]);

        $key = $student->getRouteKey();

        // URL tidak memuat id mentah.
        $this->assertNotSame((string) $student->id, $key);
        $this->assertFalse(ctype_digit($key) && $key === (string) $student->id);

        // Binding (mekanisme yang dipakai Filament) mengembalikan record yang tepat.
        $resolved = (new Student())->resolveRouteBinding($key);
        $this->assertNotNull($resolved);
        $this->assertSame($student->id, $resolved->id);

        // Token acak/rusak → tidak me-resolve apa pun (404 anggun, bukan error).
        $this->assertNull((new Student())->resolveRouteBinding('zzzzzz'));
    }
}
