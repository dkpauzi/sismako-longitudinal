<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\AcademicPeriod;

class VakQuestionnaireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $now = Carbon::now();

            // Find a valid counselor, if any
            $counselor = User::role('guru_bk')->first();

            // Find an active academic period (or fallback to any)
            $academicPeriod = AcademicPeriod::where('is_active', true)->first();
            if (!$academicPeriod) {
                $academicPeriod = AcademicPeriod::first();
            }

            // 1. Create Questionnaire Header
            $questionnaireId = DB::table('bk_questionnaires')->insertGetId([
                'title' => 'Asesmen Diagnostik Non-Kognitif: Gaya Belajar (VAK)',
                'description' => 'Instrumen ini bertujuan untuk memetakan kecenderungan gaya belajar siswa (Visual, Auditori, atau Kinestetik). Diadopsi dari Seri Manual GLS Kemdikbud 2018.',
                'instructions' => 'Pilih satu jawaban yang paling menggambarkan kebiasaan atau reaksi spontan Anda dalam situasi belajar maupun aktivitas sehari-hari. Tidak ada jawaban benar atau salah.',
                'counselor_id' => $counselor?->id,
                'academic_period_id' => $academicPeriod ? $academicPeriod->id : 1,
                'status' => 'published',
                'starts_at' => $now,
                'ends_at' => $now->copy()->addMonths(6), // Active for 1 semester
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 2. 14 Questions from Kemdikbud (2018)
            $questionsData = [
                [
                    'text' => 'Jika saya harus belajar cara melakukan sesuatu, saya belajar paling baik ketika saya:',
                    'options' => [
                        ['text' => 'menonton seseorang menunjukkan caranya.', 'code' => 'VISUAL'],
                        ['text' => 'mendengarkan seseorang yang memberi tahu saya caranya.', 'code' => 'AUDITORI'],
                        ['text' => 'mencoba untuk melakukannya sendiri.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Ketika saya membaca, saya sering menemukan bahwa saya:',
                    'options' => [
                        ['text' => 'memvisualisasikan apa yang saya baca di mata batin saya.', 'code' => 'VISUAL'],
                        ['text' => 'membaca dengan keras atau mendengarkan kata-kata di dalam kepala saya.', 'code' => 'AUDITORI'],
                        ['text' => 'gelisah dan mencoba "merasakan" isi bacaan.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Ketika diminta menunjukkan arah, saya:',
                    'options' => [
                        ['text' => 'melihat tempat-tempat yang sebenarnya dalam pikiran saya ketika saya mengatakannya atau lebih suka menggambarnya.', 'code' => 'VISUAL'],
                        ['text' => 'tidak memiliki kesulitan dalam memberi keterangan secara verbal.', 'code' => 'AUDITORI'],
                        ['text' => 'harus menunjuk atau menggerakkan tubuh saya ketika saya memberi tahu.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Jika saya tidak yakin bagaimana mengeja kata, saya:',
                    'options' => [
                        ['text' => 'menuliskan untuk menentukan apakah itu terlihat benar.', 'code' => 'VISUAL'],
                        ['text' => 'mengeja dengan keras untuk menentukan apakah kedengarannya benar.', 'code' => 'AUDITORI'],
                        ['text' => 'menuliskan untuk menentukan apakah itu terasa benar.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Ketika saya menulis, saya:',
                    'options' => [
                        ['text' => 'peduli betapa rapi dan baik huruf-huruf dan kata-kata saya muncul.', 'code' => 'VISUAL'],
                        ['text' => 'sering mengucapkan huruf dan kata-kata untuk diri sendiri.', 'code' => 'AUDITORI'],
                        ['text' => 'mendorong kuat pena atau pensil saya dan dapat merasakan aliran kata atau huruf ketika saya membentuknya.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Jika saya harus mengingat daftar barang, saya akan mengingatnya dengan baik jika saya:',
                    'options' => [
                        ['text' => 'menuliskannya.', 'code' => 'VISUAL'],
                        ['text' => 'mengatakannya berulang untuk diri sendiri.', 'code' => 'AUDITORI'],
                        ['text' => 'memindahkan dan menggunakan jari saya untuk memberi nama setiap item.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Saya lebih suka guru yang:',
                    'options' => [
                        ['text' => 'menggunakan papan atau LCD saat mereka mengajar.', 'code' => 'VISUAL'],
                        ['text' => 'berbicara dengan banyak ekspresi.', 'code' => 'AUDITORI'],
                        ['text' => 'melakukan aktivitas langsung.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Ketika mencoba berkonsentrasi, saya mengalami kesulitan ketika:',
                    'options' => [
                        ['text' => 'ada banyak kekacauan atau gerakan di dalam ruangan.', 'code' => 'VISUAL'],
                        ['text' => 'ada banyak suara di dalam ruangan.', 'code' => 'AUDITORI'],
                        ['text' => 'saya harus duduk diam untuk waktu yang lama.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Saat memecahkan masalah, saya:',
                    'options' => [
                        ['text' => 'menulis atau menggambar diagram untuk melihatnya.', 'code' => 'VISUAL'],
                        ['text' => 'berdialog dengan diri sendiri tentang masalah tersebut.', 'code' => 'AUDITORI'],
                        ['text' => 'menggunakan seluruh tubuh saya atau gerakkan benda untuk membantu saya berpikir.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Ketika diberikan instruksi tertulis tentang bagaimana membangun sesuatu, saya:',
                    'options' => [
                        ['text' => 'membaca secara diam-diam dan mencoba memvisualisasikan bagaimana bagian-bagian itu akan cocok satu sama lain.', 'code' => 'VISUAL'],
                        ['text' => 'membaca dengan keras dan berbicara pada diri sendiri saat saya menyatukan bagian-bagiannya.', 'code' => 'AUDITORI'],
                        ['text' => 'mencoba untuk menyatukan bagian-bagian terlebih dahulu dan membacanya nanti.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Untuk tetap sibuk sambil menunggu, saya:',
                    'options' => [
                        ['text' => 'melihat sekeliling, mencermati, atau membaca.', 'code' => 'VISUAL'],
                        ['text' => 'berbicara atau mendengarkan orang lain.', 'code' => 'AUDITORI'],
                        ['text' => 'berjalan-jalan, memanipulasi benda dengan tangan saya, atau menggerakkan/ mengguncangkan kaki saya saat saya duduk.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Jika saya harus secara verbal menggambarkan sesuatu kepada orang lain, saya akan:',
                    'options' => [
                        ['text' => 'menyingkat saja karena saya tidak suka berbicara panjang lebar.', 'code' => 'VISUAL'],
                        ['text' => 'berbicara secara rinci karena saya suka bicara.', 'code' => 'AUDITORI'],
                        ['text' => 'menggunakan isyarat dan bergerak sambil berbicara.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Jika seseorang secara lisan menggambarkan sesuatu kepada saya, saya akan:',
                    'options' => [
                        ['text' => 'mencoba untuk memvisualisasikan apa yang dikatakannya.', 'code' => 'VISUAL'],
                        ['text' => 'menikmati mendengarkannya, tetapi ingin menyela dan berbicara sendiri.', 'code' => 'AUDITORI'],
                        ['text' => 'menjadi bosan jika uraiannya terlalu panjang dan terperinci.', 'code' => 'KINESTETIK'],
                    ]
                ],
                [
                    'text' => 'Ketika mencoba mengingat nama, saya ingat:',
                    'options' => [
                        ['text' => 'wajah, tetapi lupa nama.', 'code' => 'VISUAL'],
                        ['text' => 'nama, tetapi lupa wajah.', 'code' => 'AUDITORI'],
                        ['text' => 'situasi saya temui orang tersebut, selain nama atau wajah orang tersebut.', 'code' => 'KINESTETIK'],
                    ]
                ]
            ];

            // 3. Insert Questions and Options
            $questionOrder = 1;
            foreach ($questionsData as $qData) {
                $questionId = DB::table('bk_questions')->insertGetId([
                    'questionnaire_id' => $questionnaireId,
                    'question_text' => $qData['text'],
                    'question_type' => 'single_choice',
                    'order' => $questionOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $optionOrder = 1;
                $optionsToInsert = [];
                foreach ($qData['options'] as $optData) {
                    $optionsToInsert[] = [
                        'question_id' => $questionId,
                        'option_text' => $optData['text'],
                        'option_code' => $optData['code'],
                        'score_weight' => 1.00,
                        'order' => $optionOrder,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $optionOrder++;
                }
                DB::table('bk_question_options')->insert($optionsToInsert);

                $questionOrder++;
            }
        });
    }
}
