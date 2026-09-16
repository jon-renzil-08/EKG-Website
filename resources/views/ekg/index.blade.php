@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-heartbeat me-2"></i>Daftar Hasil EKG Pasien</h3>
                        <div class="d-flex gap-2">
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-search text-muted" id="searchIcon"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control border-start-0"
                                    placeholder="Cari nama / kode pasien..." value="{{ request('search') }}">
                                @if (request('search'))
                                    <a href="{{ route('ekg.index') }}" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="font-weight: bold; color: black;">No</th>
                                        <th style="font-weight: bold; color: black;">ID</th>
                                        <th style="font-weight: bold; color: black;">Name</th>
                                        <th style="font-weight: bold; color: black;">Age</th>
                                        <th style="font-weight: bold; color: black;">Gender</th>
                                        <th style="font-weight: bold; color: black;">Pacemaker</th>
                                        <th style="font-weight: bold; color: black;">Source</th>
                                        <th style="font-weight: bold; color: black;">Result EKG</th>
                                        <th style="font-weight: bold; color: black;">Examination Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($ekgResults as $ekg)
                                        <tr>
                                            <td>{{ ($ekgResults->currentPage() - 1) * $ekgResults->perPage() + $loop->iteration }}
                                            </td>
                                            <td style="font-weight: bold;">{{ $ekg->patient->patient_code }}</td>
                                            <td>{{ $ekg->patient->name }}</td>
                                            <td>{{ $ekg->patient->age }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $ekg->patient->gender == 'Male' ? 'primary' : ($ekg->patient->gender == 'Female' ? 'danger' : 'secondary') }}">
                                                    {{ $ekg->patient->gender }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $ekg->patient->pacemaker == 'Yes' ? 'warning' : 'success' }}">
                                                    {{ $ekg->patient->pacemaker }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $ekg->patient->source }}</span>
                                            </td>
                                            <td>
                                                <a href="{{ route('ekg.download', $ekg->id) }}"
                                                    class="btn btn-sm btn-success" target="_blank">
                                                    <i class="fas fa-download me-1"></i>
                                                    Download PDF
                                                </a>
                                            </td>
                                            <td>{{ $ekg->examination_date->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">Tidak ada data EKG</td>
                                        </tr>
                                    @endforelse

                                </tbody>
                            </table>
                            <div class="">
                                {{ $ekgResults->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('script.search')
@endsection
