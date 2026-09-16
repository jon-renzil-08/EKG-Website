@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-users me-2"></i>Tambah Pasien</h3>
                </div>

                <div class="card-body">
                    <form action="{{ route('patients.store') }}" method="POST">
                        @csrf

                        {{-- NAME --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" id="name" required placeholder="Inputkan Nama Pasien">
                        </div>

                        {{-- AGE --}}
                        <div class="mb-3">
                            <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                            <input type="number" name="age" class="form-control" id="age" min="1" max="120" required placeholder="Inputkan Umur Pasien">
                        </div>

                        

                        {{-- GENDER --}}
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="gender" class="form-control" required>
                                <option value="" disabled selected>-- Choose Gender --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Unknown">Unknown</option>
                            </select>
                        </div>

                        {{-- PACEMAKER --}}
                        <div class="mb-3">
                            <label for="pacemaker" class="form-label">Pacemaker <span class="text-danger">*</span></label>
                            <select name="pacemaker" id="pacemaker" class="form-control" required>
                                <option value="" disabled selected>-- Pacemaker Installed? --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>

                        {{-- SOURCE --}}
                        <div class="mb-3">
                            <label for="source" class="form-label">Source <span class="text-danger">*</span></label>
                            <select name="source" id="source" class="form-control" required>
                                <option value="" disabled selected>-- Choose Source --</option>
                                <option value="Inpatient">Inpatient</option>
                                <option value="Outpatient">Outpatient</option>
                                <option value="Physical Exam">Physical Exam</option>
                            </select>
                        </div>

                        {{-- ACTION BUTTONS --}}
                        <div class="mb-4">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-1"></i> Save
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



