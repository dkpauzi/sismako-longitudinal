<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Models\Concerns\HasHashidsRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Class User
 *
 * Model utama untuk otentikasi pengguna aplikasi.
 * Menggunakan Spatie Permission untuk manajemen Role (Super Admin, Teacher, Student).
 *
 * @package App\Models
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, HasHashidsRouteKey;

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active', // Status aktif akun (true = bisa login, false = diblokir)
    ];

    /**
     * Atribut yang harus disembunyikan saat serialisasi (misal: return JSON).
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Konversi tipe data atribut secara otomatis (Casting).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean', // Memastikan is_active selalu dianggap true/false
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT ACCESS CONTROL
    |--------------------------------------------------------------------------
    */

    /**
     * Menentukan apakah user ini boleh mengakses Panel Admin (Filament).
     *
     * Logika: User harus memiliki status 'is_active' = true.
     * Anda bisa menambahkan logika lain, misal: && $this->hasVerifiedEmail()
     *
     * @param Panel $panel
     * @return bool
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Hanya user yang statusnya AKTIF yang boleh login ke dashboard
        return $this->is_active;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS (HUBUNGAN ANTAR TABEL)
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke Profil Guru.
     * Digunakan jika user ini berperan sebagai Guru.
     *
     * @return HasOne
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Relasi ke Profil Siswa.
     * Digunakan jika user ini berperan sebagai Siswa.
     *
     * @return HasOne
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Relasi ke siswa yang diampu sebagai Wali Siswa.
     * Satu akun wali bisa terhubung ke banyak siswa (saudara kandung).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function guardianStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_user_id');
    }

    /**
     * Relasi ke kuesioner BK yang dibuat oleh user ini (jika berperan sebagai Guru BK).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function createdQuestionnaires(): HasMany
    {
        return $this->hasMany(BkQuestionnaire::class, 'counselor_id');
    }

    /**
     * Relasi ke rekam bimbingan konseling yang dicatat oleh user ini (jika berperan sebagai Guru BK).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function counselingRecordsAsCounselor(): HasMany
    {
        return $this->hasMany(BkCounselingRecord::class, 'counselor_id');
    }

    /**
     * Prioritas role dari yang tertinggi. Dipakai untuk menurunkan nilai kolom
     * legacy `users.role` dari role Spatie (sumber kebenaran).
     */
    private const ROLE_PRIORITY = [
        'super_admin', 'admin', 'headmaster', 'guru_bk', 'teacher', 'guardian', 'student',
    ];

    /**
     * Selaraskan kolom legacy `users.role` dengan role Spatie yang melekat
     * (Audit 1.2). Mengambil role berprioritas tertinggi; default 'student'
     * bila belum ada role. Dipanggil setelah akun disimpan lewat UserResource.
     */
    public function syncLegacyRoleColumn(): void
    {
        $names = $this->getRoleNames()->all();

        $resolved = 'student';
        foreach (self::ROLE_PRIORITY as $candidate) {
            if (in_array($candidate, $names, true)) {
                $resolved = $candidate;
                break;
            }
        }

        if ($this->role !== $resolved) {
            $this->role = $resolved;
            $this->saveQuietly();
        }
    }
}