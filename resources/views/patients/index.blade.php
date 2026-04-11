<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pasien (RBAC & Enkripsi)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">PT Global Medika Sehat - HIS Secure</span>
    </div>
</nav>

<div class="container mt-5">
    <h3 class="mb-4">Daftar Pasien (RBAC & Enkripsi)</h3>

    {{-- Form tambah pasien hanya untuk admin --}}
    @if (Auth::user()->isAdmin())
    <form action="{{ route('patients.store') }}" method="POST" class="mb-4">
        @csrf
        <div class="row">
            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="Nama" required>
            </div>
            <div class="col-md-2">
                <input type="date" name="birth_date" class="form-control" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="nik" class="form-control" placeholder="NIK" required>
            </div>
            <div class="col-md-2">
                <input type="text" name="medical_record_number" class="form-control" placeholder="No. Rekam Medis" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Tambah</button>
            </div>
        </div>
    </form>
    @endif

    {{-- 🔍 Form Pengecekan Enkripsi --}}
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Pengecekan Data Pasien (Encrypted)</div>
        <div class="card-body">
            <form id="checkForm">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" id="checkName" class="form-control" placeholder="Masukkan Nama" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="checkNik" class="form-control" placeholder="Masukkan NIK terenkripsi" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">Cek Data</button>
                    </div>
                </div>
            </form>
            <div id="result" class="mt-3"></div>
        </div>
    </div>

    {{-- Tabel daftar pasien --}}
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Tanggal Lahir</th>
                {{-- <th>NIK (Didekripsi)</th> --}}
                <th>No. Rekam Medis</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($patients as $index => $patient)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $patient->name }}</td>
                <td>{{ $patient->birth_date }}</td>
                {{-- <td>{{ $patient->nik_encrypted }}</td> --}}
                <td>{{ $patient->medical_record_number }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
document.getElementById('checkForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const name = document.getElementById('checkName').value;
    const nik = document.getElementById('checkNik').value;
    const resultDiv = document.getElementById('result');

    try {
        const response = await fetch("{{ route('patients.check') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ name, nik })
        });

        console.log('Response status:', response.status);
        const data = await response.json();
        console.log('Response data:', data);

        if (data.match) {
            resultDiv.innerHTML = `<div class="alert alert-success mt-3">✅ Data cocok! Nama dan NIK terenkripsi sesuai.</div>`;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger mt-3">❌ Data tidak cocok! Pastikan nama dan NIK terenkripsi benar.</div>`;
        }
    } catch (error) {
        console.error('Fetch error:', error);
        resultDiv.innerHTML = `<div class="alert alert-warning mt-3">Akses Berhasil</div>`;
    }
});
</script>

</body>
</html>
