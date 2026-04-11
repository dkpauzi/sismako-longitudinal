<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi (Mass Assignment).
     * Kita ganti 'name' menjadi pecahan tahun dan semester agar mudah difilter.
     */
    protected $fillable = [
        'start_year', // Contoh: 2025
        'end_year',   // Contoh: 2026
        'semester',   // Enum: 'odd' (Ganjil) atau 'even' (Genap)
        'start_date',
        'end_date',
        'is_active',
    ];

    /**
     * Casting tipe data otomatis.
     */
    protected $casts = [
        'start_year' => 'integer',
        'end_year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Menambahkan atribut virtual 'name' ke dalam JSON response
     * (Berguna jika data diambil via API).
     */
    protected $appends = ['name'];

    /**
     * ----------------------------------------------------------------
     * ACCESSOR (KOLOM VIRTUAL)
     * ----------------------------------------------------------------
     * Ini membuat kita tetap bisa memanggil $period->name
     * meskipun kolom 'name' sudah tidak ada di database.
     * Hasilnya: "2025/2026 Ganjil"
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function () {
                $semLabel = $this->semester === 'odd' ? 'Ganjil' : 'Genap';
                return "{$this->start_year}/{$this->end_year} {$semLabel}";
            }
        );
    }

    /**
     * ----------------------------------------------------------------
     * LOGIKA OTOMATIS (BOOT)
     * ----------------------------------------------------------------
     * Saat menyimpan data, jika 'is_active' diset TRUE,
     * maka otomatis matikan 'is_active' pada periode lain.
     */
    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->is_active) {
                // Update semua data lain menjadi tidak aktif (false)
                static::where('id', '!=', $model->id)->update(['is_active' => false]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELASI (RELATIONSHIPS)
    |--------------------------------------------------------------------------
    */

    // Relasi ke Pendaftaran Siswa (Anggota Kelas)
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    // Relasi ke SK Mengajar (Penugasan Guru)
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}