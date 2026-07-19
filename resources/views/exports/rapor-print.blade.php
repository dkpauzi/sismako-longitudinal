<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor {{ $student->name }}</title>
    <style>
        @page {
            margin: 110px 40px 60px 40px; /* Top, Right, Bottom, Left */
        }
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.3;
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 11px;
        }
        .header-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        
        h2.title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }

        table.content-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: auto;
        }
        table.content-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.content-table th, table.content-table td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
        }
        table.content-table th {
            font-weight: bold;
            text-align: center;
        }

        .alert-banner {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }

        .side-by-side {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .side-by-side td {
            vertical-align: top;
            padding: 0;
        }
        .side-table {
            width: 100%;
            border-collapse: collapse;
        }
        .side-table th, .side-table td {
            border: 1px solid #000;
            padding: 5px;
        }

        .signature-table {
            width: 100%;
            margin-top: 30px;
            text-align: center;
            page-break-inside: avoid;
        }
        .signature-table td {
            vertical-align: bottom;
            height: 100px;
        }
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 80%;
            margin-bottom: 3px;
        }
        
    </style>
</head>
<body>

    @if(!$isOfficial)
    <div class="alert-banner">
        DOKUMEN INFORMASI AKADEMIK — Ini merupakan Rapor Bayangan berbasis sistem dan BUKAN Rapor Resmi.
    </div>
    @endif

    <table class="header-table">
        <tr>
            <td width="15%">Nama Murid</td>
            <td width="2%">:</td>
            <td width="48%">{{ strtoupper($student->name) }}</td>
            <td width="15%">Kelas</td>
            <td width="2%">:</td>
            <td width="18%">{{ $classroom->name }}</td>
        </tr>
        <tr>
            <td>NIS/NISN</td>
            <td>:</td>
            <td>{{ $student->nis }} / {{ $student->nisn }}</td>
            <td>Fase</td>
            <td>:</td>
            <td>D</td> <!-- As per SMPN 45, D for SMP -->
        </tr>
        <tr>
            <td>Sekolah</td>
            <td>:</td>
            <td>{{ strtoupper($schoolIdentity['name'] ?? 'SMP NEGERI 45 SIJUNJUNG') }}</td>
            <td>Semester</td>
            <td>:</td>
            <td>{{ $semesterLabel ?? ($semester === 'odd' ? 'Ganjil' : 'Genap') }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td>:</td>
            <td>{{ $schoolIdentity['address'] ?? 'Pasar Jumat Muaro' }}</td>
            <td>Tahun Ajaran</td>
            <td>:</td>
            <td>{{ $period->name }}</td>
        </tr>
    </table>

    <h2 class="title">LAPORAN HASIL BELAJAR</h2>

    <table class="content-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Mata Pelajaran</th>
                <th width="10%">Nilai Akhir</th>
                <th width="60%">Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="4" style="font-weight: bold; background-color: #f9f9f9;">Mata Pelajaran Wajib</td>
            </tr>
            @foreach($akademikAssignments as $index => $ta)
                @php
                    $grade = $finalGrades[$ta->id] ?? null;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $ta->subject->name }}</td>
                    <td class="text-center">{{ $grade ? number_format($grade->final_score, 0) : '-' }}</td>
                    <td style="text-align: justify;">{{ $grade ? $grade->narrative_description : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Kokurikuler -->
    <table class="content-table">
        <thead>
            <tr>
                <th>Proyek Penguatan Profil Pelajar Pancasila (Kokurikuler)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: justify; padding: 10px;">
                    {{-- Satu siswa bisa mengikuti lebih dari satu projek P5 per semester --}}
                    @forelse($kokurikulerGrades as $kokurikulerGrade)
                        <strong>Tema/Proyek: {{ $kokurikulerGrade->project_title }}</strong><br><br>
                        {{ $kokurikulerGrade->narrative_description }}
                        @if(!$loop->last)<br><br><hr style="border: 0; border-top: 1px dashed #999;"><br>@endif
                    @empty
                        Belum ada catatan kokurikuler untuk semester ini.
                    @endforelse
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Ekstrakurikuler -->
    <table class="content-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Ekstrakurikuler</th>
                <th width="60%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ekstrakurikuler as $idx => $ekskul)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $ekskul->teachingAssignment->subject->name }}</td>
                    <td style="text-align: justify;">{{ $ekskul->description ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">Tidak mengikuti kegiatan ekstrakurikuler</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Ketidakhadiran & Catatan -->
    <table class="side-by-side">
        <tr>
            <td width="40%" style="padding-right: 10px;">
                <table class="side-table">
                    <thead>
                        <tr>
                            <th colspan="2">Ketidakhadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td width="50%">Sakit</td>
                            <td width="50%">: {{ $totalSakit }} hari</td>
                        </tr>
                        <tr>
                            <td>Izin</td>
                            <td>: {{ $totalIzin }} hari</td>
                        </tr>
                        <tr>
                            <td>Tanpa Keterangan</td>
                            <td>: {{ $totalAlpha }} hari</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td width="60%" style="padding-left: 10px;">
                <table class="side-table" style="height: 100%;">
                    <thead>
                        <tr>
                            <th>Catatan Wali Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="height: 60px; text-align: justify;">
                                {{-- Kita dapat mengambil catatan wali kelas jika ada relasinya di Enrollment atau model lain, untuk sekarang placeholder standar atau kosong --}}
                                Tingkatkan terus prestasimu, {{ $student->name }}!
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Tanggapan Orang Tua -->
    <table class="content-table">
        <thead>
            <tr>
                <th>Tanggapan Orang Tua/Wali Murid</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="height: 60px;"></td>
            </tr>
        </tbody>
    </table>

    @if($isOfficial)
    <table class="signature-table">
        <tr>
            <td width="33%">
                Mengetahui,<br>Orang Tua Murid
                <br><br><br><br>
                <div class="signature-line"></div>
            </td>
            <td width="34%">
                <br>Kepala Sekolah
                <br><br><br><br>
                <b><u>{{ $schoolIdentity['headmaster_name'] ?? 'Imma Setiawati' }}</u></b><br>
                NIP {{ $schoolIdentity['headmaster_nip'] ?? '196706121990032010' }}
            </td>
            <td width="33%">
                {{ $schoolIdentity['city'] ?? 'Muaro' }}, {{ date('d F Y') }}<br>Wali Kelas
                <br><br><br><br>
                <b><u>{{ $homeroom->teacher->name }}</u></b><br>
                NIP {{ $homeroom->teacher->nip ?? '-' }}
            </td>
        </tr>
    </table>
    @endif

<script type="text/php">
    if (isset($pdf)) {
        $textRight = "Halaman : {PAGE_NUM}";
        $textLeft = "{{ $student->classroom->name ?? '' }} {{ strtoupper($student->name) }} | {{ $student->nis }}";
        $size = 9;
        $font = $fontMetrics->getFont("sans-serif");
        $y = $pdf->get_height() - 30; // 30 units from the bottom
        
        $pdf->page_text(40, $y, $textLeft, $font, $size, array(0,0,0));
        $pdf->page_text($pdf->get_width() - 100, $y, $textRight, $font, $size, array(0,0,0));
    }
</script>
</body>
</html>
