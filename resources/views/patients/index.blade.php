@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-users me-2"></i>Daftar Pasien</h3>
                        <div class="d-flex gap-2">
                            <!-- Search Input -->
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-search text-muted" id="searchIcon"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control border-start-0"
                                    placeholder="Cari nama / kode pasien..." value="{{ request('search') }}">
                                @if (request('search'))
                                    <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
                            </div>
                            <a href="{{ route('patients.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Tambah Pasien
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive patient-table-wrapper" id="tableContainer" style="overflow-x:auto">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="font-weight: bold; color: black;">No</th>
                                        <th style="font-weight: bold; color: black;">Barcode</th>
                                        <th style="font-weight: bold; color: black;">Name</th>
                                        <th style="font-weight: bold; color: black;">Age</th>
                                        <th style="font-weight: bold; color: black;">Gender</th>
                                        <th style="font-weight: bold; color: black;">Pacemaker</th>
                                        <th style="font-weight: bold; color: black;">Source</th>
                                        <th style="font-weight: bold; color: black;">Worklist</th>
                                        <th style="font-weight: bold; color: black;">Status</th>
                                        <th style="font-weight: bold; color: black;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="patientTable">
                                    @forelse($patients as $patient)
                                        @php
                                            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                                            $barcode = base64_encode(
                                                $generator->getBarcode(
                                                    $patient->patient_code,
                                                    \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128,
                                                ),
                                            );
                                        @endphp
                                        <tr class="patient-row">
                                            <td>{{ ($patients->currentPage() - 1) * $patients->perPage() + $loop->iteration }}
                                            </td>
                                            {{-- <td style="font-weight: bold;">{{ $patient->patient_code }}</td> --}}

                                            <td>
                                                <div style="background: white; padding: 5px; display: inline-block;">
                                                    <img src="data:image/png;base64,{{ $barcode }}"
                                                        style="width: 200px; height: 60px; display: block;">

                                                    <small style="display: block; text-align: center; margin-top: 5px;">
                                                        ID : {{ $patient->patient_code }}
                                                    </small>
                                                </div>
                                            </td>
                                            <td>{{ $patient->name }}</td>
                                            <td>{{ $patient->age }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $patient->gender == 'Male' ? 'primary' : ($patient->gender == 'Female' ? 'danger' : 'secondary') }}">
                                                    {{ $patient->gender }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $patient->pacemaker == 'Yes' ? 'warning' : 'success' }}">
                                                    {{ $patient->pacemaker }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $patient->source }}</span>
                                            </td>
                                            <td>
                                                @if ($patient->isInWorklist === 1)
                                                    <span class="badge bg-success">Yes</span>
                                                @else
                                                    <span class="badge bg-danger">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($patient->isInWorklist === 2)
                                                    <span class="badge bg-success p-2">
                                                        <i class="fas fa-check-circle me-1"></i> Selesai EKG
                                                    </span>
                                                @elseif ($patient->isInWorklist === 1)
                                                    <span class="badge bg-warning p-2">
                                                        <i class="fas fa-clock me-1"></i> Dalam Antrian
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger p-2">
                                                        <i class="fas fa-times-circle me-1"></i> Belum Dikirim
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                <div class="d-flex align-items-center gap-1">
                                                    @if ($patient->isInWorklist === 0)
                                                        <button class="btn btn-sm btn-primary send-to-worklist pulse-btn"
                                                            data-id="{{ $patient->id }}">
                                                            <i class="fas fa-heartbeat me-1"></i>
                                                            Kirim EKG
                                                        </button>
                                                    @endif

                                                    <a href="{{ route('patients.edit', $patient) }}"
                                                        class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip"
                                                        data-bs-placement="bottom" data-bs-title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <button class="btn btn-sm btn-outline-danger delete-btn"
                                                        data-id="{{ $patient->id }}" data-name="{{ $patient->name }}">
                                                        <i class="fas fa-trash"></i>
                                                    </button>

                                                    <form id="delete-form-{{ $patient->id }}"
                                                        action="{{ route('patients.destroy', $patient->id) }}"
                                                        method="POST" style="display:none;">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>


                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="emptyRow">
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-user-slash me-2"></i>Tidak ada data pasien
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                            </table>
                            <div class="">
                                {{ $patients->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Container -->
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
            <div id="toastSuccess" class="toast align-items-center text-white bg-success border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>
                        <span id="toastSuccessMsg"></span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>

            <div id="toastError" class="toast align-items-center text-white bg-danger border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-times-circle me-2"></i>
                        <span id="toastErrorMsg"></span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>

    @include('script.delete')
    @include('script.search')

    <script>
        // Enable tooltips on all elements with data-bs-toggle="tooltip"
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl)
            })
        })
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltip Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl)
            })

            // Fungsi show toast
            function showToast(type, message) {
                if (type === 'success') {
                    document.getElementById('toastSuccessMsg').textContent = message;
                    var toast = new bootstrap.Toast(document.getElementById('toastSuccess'), {
                        delay: 3000
                    });
                    toast.show();
                } else {
                    document.getElementById('toastErrorMsg').textContent = message;
                    var toast = new bootstrap.Toast(document.getElementById('toastError'), {
                        delay: 3000
                    });
                    toast.show();
                }
            }

            // Handler untuk tombol kirim
            document.querySelectorAll('.send-to-worklist').forEach(function(button) {
                button.addEventListener('click', function() {
                    const patientId = this.getAttribute('data-id');
                    const btn = this;

                    // Simpan konten asli button
                    const originalContent = btn.innerHTML;

                    // Tampilkan loading spinner
                    btn.classList.remove('pulse-btn');
                    btn.disabled = true;
                    btn.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Mengirim...
            `;

                    fetch(`/send-to-worklist/${patientId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                id: patientId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Kembalikan button ke semula
                            btn.disabled = false;
                            btn.innerHTML = originalContent;

                            if (data.success) {
                                showToast('success', 'Pasien berhasil dikirim ke worklist!');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast('error', 'Gagal: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Kembalikan button ke semula
                            btn.disabled = false;
                            btn.innerHTML = originalContent;

                            showToast('error', 'Terjadi kesalahan saat mengirim!');
                        });
                });
            });
        });
    </script>


    <script>
        let lastEkgId = {{ \App\Models\EkgResult::latest()->first()?->id ?? 0 }};

        // Cek setiap 10 detik
        setInterval(function() {
            fetch(`/api/check-new-ekg?last_id=${lastEkgId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.new_results && data.new_results.length > 0) {
                        data.new_results.forEach(function(result) {
                            showEkgNotification(result.patient_name, result.patient_code);
                            lastEkgId = result.ekg_id;
                        });
                    }
                })
                .catch(err => console.log('Polling error:', err));
        }, 10000); // 10 detik

        function showEkgNotification(patientName, patientId) {
            // Hapus toast lama kalau ada
            const oldToast = document.getElementById('ekgToast');
            if (oldToast) oldToast.remove();

            // Buat toast baru
            const toastHtml = `
            <div id="ekgToast" 
                class="toast align-items-center text-white bg-success border-0 show"
                role="alert" 
                style="position:fixed; top:20px; right:20px; z-index:9999; min-width:320px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-heartbeat me-2"></i>
                        <b>Hasil EKG Masuk!</b><br>
                        Pasien: <b>${patientName}</b><br>
                        ID: <b>${patientId}</b><br>
                        <small class="text-white-50">${new Date().toLocaleTimeString()}</small>
                    </div>
                    <button type="button" 
                        class="btn-close btn-close-white me-2 m-auto"
                        onclick="document.getElementById('ekgToast').remove()">
                    </button>
                </div>
            </div>
        `;

            document.body.insertAdjacentHTML('beforeend', toastHtml);

            // Auto hilang setelah 8 detik
            setTimeout(() => {
                const toast = document.getElementById('ekgToast');
                if (toast) toast.remove();
            }, 8000);

            // Browser notification
            if (Notification.permission === 'granted') {
                new Notification('🔔 Hasil EKG Masuk!', {
                    body: `Pasien ${patientName} (${patientId}) sudah selesai EKG`,
                    icon: '/favicon.ico'
                });
            }

            // Reload tabel pasien
            setTimeout(() => location.reload(), 3000);
        }

        // Minta izin browser notification
        document.addEventListener('DOMContentLoaded', function() {
            if (Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });
    </script>
@endsection
