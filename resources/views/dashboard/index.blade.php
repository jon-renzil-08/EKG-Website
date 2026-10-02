@extends('layouts.app')

@section('content')
<style>
    .dashboard-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }

    .gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .gradient-success {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .gradient-warning {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .gradient-info {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    }

    .gradient-danger {
        background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    }

    .stat-card {
        border-radius: 15px;
        padding: 25px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        transform: translate(30px, -30px);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 0.95rem;
        opacity: 0.9;
        margin-bottom: 0;
    }

    .stat-icon {
        font-size: 3rem;
        opacity: 0.8;
    }

    .chart-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }

    .welcome-card {
        background: linear-gradient(135deg, #2a5298 0%, #2a5298 100%);
        color: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }

    .welcome-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%);
        animation: pulse 4s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .activity-item {
        padding: 15px;
        border-left: 4px solid #667eea;
        margin-bottom: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .activity-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }

    .quick-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .quick-action-btn {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 12px 20px;
        border-radius: 25px;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 1px solid rgba(255,255,255,0.3);
    }

    .quick-action-btn:hover {
        background: rgba(255,255,255,0.3);
        color: white;
        transform: translateY(-2px);
    }

    .mini-chart {
        width: 100%;
        height: 60px;
        margin-top: 15px;
    }

    .trend-up {
        color: #28a745;
    }

    .trend-down {
        color: #dc3545;
    }

    .recent-patients {
        max-height: 400px;
        overflow-y: auto;
    }

    .patient-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #eee;
        transition: background 0.3s ease;
    }

    .patient-item:hover {
        background: #f8f9fa;
    }

    .patient-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        margin-right: 15px;
    }
</style>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-heartbeat me-2"></i>
                    Selamat Datang di Dashboard EKG
                </h2>
                <!-- <p class="mb-0 opacity-75">Sistem Monitoring Elektrokardiogram - Rumah Sakit</p> -->
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-hospital fa-5x opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card gradient-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number">{{ App\Models\Patient::count() }}</div>
                        <div class="stat-label">Total Pasien</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-arrow-up trend-up me-1"></i>
                            +12% dari bulan lalu
                        </small>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card gradient-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number">{{ App\Models\EkgResult::count() }}</div>
                        <div class="stat-label">Total EKG</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-arrow-up trend-up me-1"></i>
                            +8% dari bulan lalu
                        </small>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card gradient-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number">{{ App\Models\EkgResult::whereDate('created_at', today())->count() }}</div>
                        <div class="stat-label">EKG Hari Ini</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-clock me-1"></i>
                            Diperbarui real-time
                        </small>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card gradient-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number">{{ App\Models\Patient::whereDate('created_at', today())->count() }}</div>
                        <div class="stat-label">Pasien Baru</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-user-plus me-1"></i>
                            Hari ini
                        </small>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Recent Activity -->
    <div class="row">
        <div class="col-xl-8">
            <div class="chart-card">
                <h5 class="mb-3">
                    <i class="fas fa-chart-area me-2 text-primary"></i>
                    Grafik EKG Bulanan
                </h5>
                <div style="height: 300px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <div class="text-center">
                        <canvas id="ekgChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="chart-card">
                <h5 class="mb-3">
                    <i class="fas fa-clock me-2 text-success"></i>
                    Aktivitas Terbaru
                </h5>
                <div class="recent-patients">
                    @foreach($latestEkgs as $ekg)
                    <div class="activity-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>EKG Selesai</strong>
                                <p class="mb-0 text-muted small">
                                    Pasien: {{ ucwords(strtolower($ekg->patient->name ?? '-')) }}
                                </p>
                            </div>
                            <small class="text-muted">
                                {{ $ekg->created_at->diffForHumans() }}
                            </small>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Patients -->
    <div class="row">
        <div class="col-12">
            <div class="chart-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2 text-info"></i>
                        Pasien Terbaru
                    </h5>
                </div>
                <div class="row">
                    @foreach($recentPatients as $patient)
                    <div class="col-md-4 mb-3">
                        <div class="patient-item">
                            <div class="patient-avatar">
                                {{ strtoupper(substr($patient->name,0,1)) }}
                            </div>

                            <div class="flex-grow-1">
                                <strong>{{ ucwords(strtolower($patient->name)) }}</strong>
                                <p class="mb-0 text-muted small">
                                    <i class="fas fa-calendar me-1"></i>
                                    {{ $patient->created_at->format('d M Y') }}
                                </p>
                            </div>

                            <div class="text-end">
                                <span class="badge bg-success">
                                    {{ $patient->ekgResults()->count() }} EKG
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Animasi counter untuk angka statistik
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-number');

    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        counter.textContent = '0';

        const increment = target / 100;
        let current = 0;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.ceil(current);
            }
        }, 20);
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('ekgChart');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json(collect($monthlyEkg)->pluck('month')),
        datasets: [{
            label: 'Jumlah EKG',
            data: @json(collect($monthlyEkg)->pluck('total')),
            borderWidth: 2,
            tension: 0.4,
            fill: true
        }]
    }
});
</script>
@endsection
