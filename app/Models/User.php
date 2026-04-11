<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory, Notifiable, HasRoles;

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
}