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

    public function test_kokurikuler_grades_has_unique_constraint()
    {
        $indexes = \Illuminate\Support\Facades\DB::select("PRAGMA index_list('kokurikuler_grades')");
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index->unique) {
                $columns = \Illuminate\Support\Facades\DB::select("PRAGMA index_info('{$index->name}')");
                $colNames = array_map(function($col) { return $col->name; }, $columns);
                if (in_array('teaching_assignment_id', $colNames) && in_array('student_id', $colNames)) {
                    $hasUnique = true;
                    break;
                }
            }
        }
        $this->assertTrue($hasUnique, 'kokurikuler_grades is missing unique constraint on teaching_assignment_id and student_id');
    }
}
