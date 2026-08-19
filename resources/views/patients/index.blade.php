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
							<input type="text"
								id="searchInput"
								class="form-control border-start-0"
								placeholder="Cari nama / kode pasien..."
								value="{{ request('search') }}">
							@if(request('search'))
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
					<div class="table-responsive" id="tableContainer">
						<table class="table table-striped table-hover">
							<thead class="table-dark">
								<tr>
									<th>No</th>
									<th>ID</th>
									<th>Name</th>
									<th>Age</th>
									<th>Gender</th>
									<th>Pacemaker</th>
									<th>Source</th>
									<th>Worklist</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody id="patientTable">
								@forelse($patients as $patient)
								<tr class="patient-row">
									<td>{{ ($patients->currentPage() - 1) * $patients->perPage() + $loop->iteration }}</td>
									<td>{{ $patient->patient_code }}</td>
									<td>{{ $patient->name }}</td>
									<td>{{ $patient->age }}</td>
									<td>
										<span class="badge bg-{{ $patient->gender == 'Male' ? 'primary' : ($patient->gender == 'Female' ? 'danger' : 'secondary') }}">
											{{ $patient->gender }}
										</span>
									</td>
									<td>
										<span class="badge bg-{{ $patient->pacemaker == 'Yes' ? 'warning' : 'success' }}">
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
										<a href="{{ route('patients.edit', $patient) }}"
											class="btn btn-sm btn-outline-warning"
											data-bs-toggle="tooltip"
											data-bs-placement="bottom"
											data-bs-title="Edit">
											<i class="fas fa-edit"></i>
										</a>
										<button class="btn btn-sm btn-outline-danger delete-btn"
											data-id="{{ $patient->id }}"
											data-name="{{ $patient->name }}">
											<i class="fas fa-trash"></i>
										</button>
										<form id="delete-form-{{ $patient->id }}"
											action="{{ route('patients.destroy', $patient->id) }}"
											method="POST" style="display:none;">
											@csrf
											@method('DELETE')
										</form>
										@if ($patient->isInWorklist === 0)
										<button class="btn btn-sm btn-primary send-to-worklist pulse-btn"
											data-bs-toggle="tooltip"
											data-bs-placement="bottom"
											data-bs-title="Kirim ke Antrian Pemeriksaan EKG"
											data-id="{{ $patient->id }}">
											<i class="fas fa-heartbeat me-1"></i> Kirim EKG
										</button>
										@else
										<button class="btn btn-sm btn-success" disabled>
											<i class="fas fa-check me-1"></i> Terkirim
										</button>
										@endif
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

@endsection