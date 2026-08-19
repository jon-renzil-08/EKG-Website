@extends('layouts.app')

@section('content')
<div class="container-fluid">
	<div class="row">
		<div class="col-12">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h3><i class="fas fa-users me-2"></i>Update Data Pasien</h3>
				</div>

				<div class="card-body">
					<form action="{{ route('patients.update', $patient->id) }}" method="POST">
						@csrf
						@method('PUT')

						{{-- NAME --}}
						<div class="mb-3">
							<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
							<input type="text" value="{{ old('name', $patient->name) }}" name="name" class="form-control" id="name"
								required>
						</div>

						{{-- AGE --}}
						<div class="mb-3">
							<label for="age" class="form-label">Age <span class="text-danger">*</span></label>
							<input type="number" value="{{ old('age', $patient->age) }}" name="age" class="form-control" id="age"
								min="1" max="120" required>
						</div>

						{{-- GENDER --}}
						<div class="mb-3">
							<label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
							<select name="gender" id="gender" class="form-control" required>
								<option value="" disabled>-- Choose Gender --</option>
								<option value="Male" {{ old('gender', $patient->gender) == 'Male' ? 'selected' : '' }}>Male</option>
								<option value="Female" {{ old('gender', $patient->gender) == 'Female' ? 'selected' : '' }}>Female
								</option>
								<option value="Unknown" {{ old('gender', $patient->gender) == 'Unknown' ? 'selected' : '' }}>Unknown
								</option>
							</select>
						</div>

						{{-- PACEMAKER --}}
						<div class="mb-3">
							<label for="pacemaker" class="form-label">Pacemaker <span class="text-danger">*</span></label>
							<select name="pacemaker" id="pacemaker" class="form-control" required>
								<option value="" disabled selected>-- Pacemaker Installed? --</option>
								<option value="Yes" {{ old('pacemaker', $patient->pacemaker) == 'Yes' ? 'selected' : '' }}>Yes</option>
								<option value="No" {{ old('pacemaker', $patient->pacemaker) == 'No' ? 'selected' : '' }}>No</option>
							</select>
						</div>

						{{-- SOURCE --}}
						<div class="mb-3">
							<label for="source" class="form-label">Source <span class="text-danger">*</span></label>
							<select name="source" id="source" class="form-control" required>
								<option value="" disabled selected>-- Choose Source --</option>
								<option value="Inpatient" {{ old('source', $patient->source) == 'Inpatient' ? 'selected' : '' }}>Inpatient</option>
								<option value="Outpatient" {{ old('source', $patient->source) == 'Outpatient' ? 'selected' : '' }}>Outpatient</option>
								<option value="Physical Exam" {{ old('source', $patient->source) == 'Physical Exam' ? 'selected' : '' }}>Physical Exam</option>
							</select>
						</div>

						{{-- ACTION BUTTONS --}}
						<div class="mb-4">
							<div class="d-flex justify-content-end">
								<button type="submit" class="btn btn-primary me-2">
									<i class="fas fa-save me-1"></i> Save Changes
								</button>
								<a href="{{ route('patients.index') }}" class="btn btn-secondary">
									<i class="fas fa-times me-1"></i> Cancel
								</a>
							</div>
						</div>

					</form>
				</div>

			</div>
		</div>
	</div>
</div>
@endsection