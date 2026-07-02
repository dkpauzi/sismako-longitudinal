<?php

namespace Tests\Feature;

use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditRedundancyFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_settings_table_no_longer_has_redundant_columns()
    {
        $columns = Schema::getColumnListing('school_settings');
        
        $this->assertNotContains('school_name', $columns);
        $this->assertNotContains('npsn', $columns);
        $this->assertNotContains('address', $columns);
        $this->assertNotContains('phone', $columns);
        $this->assertNotContains('email', $columns);
        $this->assertNotContains('website', $columns);
        $this->assertNotContains('logo_path', $columns);
        $this->assertNotContains('principal_name', $columns);
        
        // It should still have these
        $this->assertContains('school_profile_id', $columns);
        $this->assertContains('kop_surat_path', $columns);
    }

    public function test_school_profiles_table_has_postal_code()
    {
        $columns = Schema::getColumnListing('school_profiles');
        $this->assertContains('postal_code', $columns);
    }

    public function test_bk_questionnaire_targets_does_not_have_academic_period_id()
    {
        $columns = Schema::getColumnListing('bk_questionnaire_targets');
        $this->assertNotContains('academic_period_id', $columns);
    }

    /**
     * Desain disengaja: TIDAK ada unique constraint pada kokurikuler_grades —
     * satu siswa boleh mengikuti banyak projek P5 dalam satu periode
     * (lihat komentar migrasi create_kbm_and_assessment_tables).
     */
    public function test_kokurikuler_grades_allows_multiple_projects_per_student_per_period()
    {
        $period = \App\Models\AcademicPeriod::create([
            'start_year' => 2025,
            'end_year' => 2026,
            'semester' => 'odd',
            'start_date' => '2025-07-14',
            'end_date' => '2025-12-20',
            'is_active' => true,
        ]);

        $student = \App\Models\Student::create([
            'name' => 'Siswa P5',
            'gender' => 'L',
        ]);

        foreach (['Sampahku Tanggung Jawabku', 'Kearifan Lokal Nagari'] as $title) {
            \App\Models\KokurikulerGrade::create([
                'student_id' => $student->id,
                'academic_period_id' => $period->id,
                'project_title' => $title,
                'narrative_description' => 'Deskripsi projek ' . $title,
            ]);
        }

        $this->assertSame(2, \App\Models\KokurikulerGrade::where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->count());
    }
}
