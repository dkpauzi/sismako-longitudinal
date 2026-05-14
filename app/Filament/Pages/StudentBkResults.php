<?php

namespace App\Filament\Pages;

use App\Models\AcademicPeriod;
use App\Models\BkStudentResponse;
use App\Models\ClassHomeroom;
use App\Models\TeachingAssignment;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Halaman untuk Guru & Wali Kelas melihat riwayat asesmen BK siswa.
 *
 * Akses dibatasi ketat: guru hanya bisa melihat data siswa di kelas
 * yang mereka ajar atau kelola sebagai wali kelas pada periode aktif.
 */
class StudentBkResults extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Hasil Asesmen BK';
    protected static ?string $title           = 'Riwayat Asesmen Kognitif BK';
    protected static ?int    $navigationSort  = 6;
    protected static ?string $navigationGroup = 'Bimbingan Konseling';

    protected static string $view = 'filament.pages.student-bk-results';

    public ?int $selectedClassroomId = null;

    /**
     * Akses: guru, guru_bk, wali kelas, kepsek, super_admin.
     */
    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole([
            'super_admin', 'admin', 'headmaster', 'teacher', 'guru_bk',
        ]);
    }

    public function mount(): void
    {
        // Default: pilih kelas pertama yang tersedia
        $classrooms = $this->getAccessibleClassrooms();
        $this->selectedClassroomId = $classrooms->keys()->first();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedClassroomId')
                    ->label('Pilih Kelas')
                    ->options(fn () => $this->getAccessibleClassrooms())
                    ->live()
                    ->searchable()
                    ->placeholder('Pilih kelas untuk melihat data...'),
            ])
            ->columns(1);
    }

    /**
     * Dapatkan daftar kelas yang boleh diakses oleh user saat ini.
     *
     * - super_admin / admin / headmaster / guru_bk: semua kelas
     * - teacher: hanya kelas yang diajar atau di-wali-kelasi
     */
    public function getAccessibleClassrooms(): Collection
    {
        $user = Auth::user();

        // Admin/kepsek/guru BK bisa melihat semua kelas
        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster', 'guru_bk'])) {
            $activePeriod = AcademicPeriod::where('is_active', true)->first();

            if (! $activePeriod) {
                return collect();
            }

            // Ambil semua kelas yang punya kuesioner BK di periode aktif
            return \App\Models\Classroom::orderBy('name')->pluck('name', 'id');
        }

        // Guru: hanya kelas yang diajar atau di-wali-kelasi
        $teacher = $user->teacher;

        if (! $teacher) {
            return collect();
        }

        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return collect();
        }

        // Kelas dari SK Mengajar
        $teachingClassroomIds = TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('academic_period_id', $activePeriod->id)
            ->pluck('classroom_id');

        // Kelas dari Wali Kelas
        $homeroomClassroomIds = ClassHomeroom::where('teacher_id', $teacher->id)
            ->where('academic_period_id', $activePeriod->id)
            ->where('is_current', true)
            ->pluck('classroom_id');

        $allClassroomIds = $teachingClassroomIds->merge($homeroomClassroomIds)->unique();

        return \App\Models\Classroom::whereIn('id', $allClassroomIds)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Ambil respons BK yang sudah dievaluasi untuk kelas yang dipilih.
     * Eager-load semua relasi untuk menghindari N+1.
     */
    public function getEvaluatedResponses(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->selectedClassroomId) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Verifikasi akses: pastikan classroom ada di daftar yang boleh diakses
        $accessibleIds = $this->getAccessibleClassrooms()->keys()->toArray();

        if (! in_array($this->selectedClassroomId, $accessibleIds)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Ambil semua student yang terdaftar di kelas ini pada periode aktif
        $studentIds = \App\Models\Enrollment::where('classroom_id', $this->selectedClassroomId)
            ->where('academic_period_id', $activePeriod->id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->toArray();

        if (empty($studentIds)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Query respons yang sudah dievaluasi, dengan eager loading penuh
        return BkStudentResponse::with([
                'questionnaire:id,title',
                'student.user:id,name',
            ])
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('evaluated_at')
            ->whereHas('questionnaire', function ($query) use ($activePeriod) {
                $query->where('academic_period_id', $activePeriod->id);
            })
            ->orderBy('evaluated_at', 'desc')
            ->get();
    }

    /**
     * Sediakan data ke Blade view.
     */
    protected function getViewData(): array
    {
        return [
            'responses'   => $this->getEvaluatedResponses(),
            'hasClassroom' => $this->selectedClassroomId !== null,
        ];
    }
}
