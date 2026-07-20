<?php

namespace Tests\Feature;

use App\Filament\Imports\LearningObjectiveImporter;
use Tests\TestCase;

/**
 * Mandate 2 — impor TP tidak boleh melaporkan baris KOSONG (ghost row Excel)
 * sebagai "gagal". isEmptyRow() menentukan baris yang di-skip diam-diam di
 * resolveRecord() (dipanggil sebelum validasi Filament v3).
 */
class LearningObjectiveImporterEmptyRowTest extends TestCase
{
    public function test_fully_empty_row_is_detected(): void
    {
        $this->assertTrue(LearningObjectiveImporter::isEmptyRow([
            'subject' => null, 'code' => '', 'content' => null, 'attribute' => '   ',
        ]));

        // Baris tanpa kunci sama sekali juga dianggap kosong.
        $this->assertTrue(LearningObjectiveImporter::isEmptyRow([]));
    }

    public function test_valid_row_is_not_skipped(): void
    {
        $this->assertFalse(LearningObjectiveImporter::isEmptyRow([
            'subject' => 5, 'code' => 'MTK-7-1', 'content' => 'Isi', 'attribute' => 'Ringkas',
        ]));
    }

    public function test_partially_filled_row_is_not_skipped_and_stays_validatable(): void
    {
        // Hanya content terisi → BUKAN baris kosong → tetap diproses & divalidasi
        // (akan gagal wajar karena subject kosong), bukan di-skip diam-diam.
        $this->assertFalse(LearningObjectiveImporter::isEmptyRow([
            'subject' => null, 'code' => null, 'content' => 'Ada isi', 'attribute' => null,
        ]));
    }
}
