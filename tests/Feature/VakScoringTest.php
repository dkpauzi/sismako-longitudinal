<?php

namespace Tests\Feature;

use App\Models\BkAnswer;
use App\Models\BkQuestion;
use App\Models\BkQuestionnaire;
use App\Models\BkQuestionOption;
use App\Models\BkStudentResponse;
use App\Models\Student;
use App\Services\VakScoringService;
use App\Models\User;
use App\Models\AcademicPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VakScoringTest extends TestCase
{
    use RefreshDatabase;

    private VakScoringService $scoringService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoringService = new VakScoringService();
    }

    private function setupResponseWithAnswers(array $answerCounts): BkStudentResponse
    {
        $counselor = User::factory()->create(['role' => 'teacher', 'username' => 'counselor123']);
        $studentUser = User::factory()->create(['role' => 'student', 'username' => 'student123']);
        $guardianUser = User::factory()->create(['role' => 'guardian', 'username' => 'guardian123']);
        $academicPeriod = AcademicPeriod::create([
            'start_year' => 2026,
            'end_year' => 2027,
            'semester' => 'odd',
            'is_active' => true
        ]);

        $questionnaire = BkQuestionnaire::create([
            'title' => 'Test VAK',
            'status' => 'published',
            'counselor_id' => $counselor->id,
            'academic_period_id' => $academicPeriod->id,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'guardian_user_id' => $guardianUser->id,
            'nisn' => '12345' . rand(100,999),
            'name' => 'Test Student',
            'gender' => 'L',
            'status' => 'active',
        ]);

        $response = BkStudentResponse::create([
            'questionnaire_id' => $questionnaire->id,
            'student_id' => $student->id,
            'submitted_at' => now(),
        ]);

        foreach ($answerCounts as $code => $count) {
            for ($i = 0; $i < $count; $i++) {
                $question = BkQuestion::create([
                    'questionnaire_id' => $questionnaire->id,
                    'question_text' => 'Q' . $i,
                    'question_type' => 'single_choice'
                ]);
                $option = BkQuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Opt',
                    'option_code' => $code
                ]);

                BkAnswer::create([
                    'response_id' => $response->id,
                    'question_id' => $question->id,
                    'selected_option_id' => $option->id,
                ]);
            }
        }

        return $response;
    }

    public function test_it_calculates_dominant_visual_style()
    {
        // 6 Visual, 5 Auditori, 3 Kinestetik
        $response = $this->setupResponseWithAnswers([
            'VISUAL' => 6,
            'AUDITORI' => 5,
            'KINESTETIK' => 3
        ]);

        $result = $this->scoringService->score($response);

        $this->assertEquals('Visual', $result['dominant_style']);
        $this->assertEquals(round((6 / 14) * 100, 2), $result['dominant_percentage']);
        $this->assertStringContainsString('Visual:', $result['recommendation']);
    }

    public function test_it_handles_tied_scores()
    {
        // 5 Visual, 5 Auditori, 4 Kinestetik
        $response = $this->setupResponseWithAnswers([
            'VISUAL' => 5,
            'AUDITORI' => 5,
            'KINESTETIK' => 4
        ]);

        $result = $this->scoringService->score($response);

        $this->assertEquals('Campuran (Visual-Auditori)', $result['dominant_style']);
        $this->assertEquals(round((5 / 14) * 100, 2), $result['dominant_percentage']);
        $this->assertStringContainsString('Anda memiliki gaya belajar campuran', $result['recommendation']);
        $this->assertStringContainsString('Visual:', $result['recommendation']);
        $this->assertStringContainsString('Auditori:', $result['recommendation']);
    }

    public function test_it_handles_empty_answers()
    {
        $response = $this->setupResponseWithAnswers([]);

        $result = $this->scoringService->score($response);

        $this->assertEquals('Tidak Diketahui', $result['dominant_style']);
        $this->assertEquals(0, $result['dominant_percentage']);
    }
}
