<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EKG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f0f4f8;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            min-height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #0f2557 0%, #1a3a8f 60%, #1e4db7 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand .brand-icon {
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }

        .sidebar-brand .brand-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            margin: 0;
        }

        .sidebar-brand .brand-subtitle {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0;
        }

        .sidebar-menu {
            padding: 1rem 0.75rem;
            flex: 1;
        }

        .sidebar-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.35);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 0.5rem 0.75rem 0.25rem;
            margin-top: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            padding: 0.7rem 0.875rem;
            border-radius: 10px;
            margin-bottom: 0.2rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            transform: translateX(3px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.18);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-link .nav-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .nav-link.active .nav-icon,
        .nav-link:hover .nav-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-footer .status-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
            animation: pulse 2s infinite;
        }

        :root {
            --sidebar-width: 310px;
        }

        html,
        body {
            overflow-x: hidden;
        }

        /* Sidebar: default tertutup */
        .sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1040;
            transform: translateX(-100%);
            transition: transform .3s ease;
        }

        /* Konten: default full width */
        .main-wrapper {
            margin-left: 0 !important;
            width: auto !important;
            max-width: 100% !important;
            transition: margin-left .3s ease;
        }

        /* Topbar: aman kalau fixed/sticky */
        .topbar {
            left: 0 !important;
            width: auto !important;
        }

        .content-area {
            width: 100%;
            max-width: none;
        }

        /* Saat sidebar dibuka */
        body.sidebar-open .sidebar {
            transform: translateX(0);
        }

        /* Backdrop */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 1035;
        }

        /* Desktop: konten bergeser */
        @media (min-width: 992px) {
            body.sidebar-open .main-wrapper {
                margin-left: var(--sidebar-width) !important;
                width: auto !important;
            }

            body.sidebar-open .topbar {
                left: var(--sidebar-width) !important;
            }
        }

        /* Mobile: sidebar menimpa konten + backdrop */
        @media (max-width: 991.98px) {
            body.sidebar-open .sidebar-backdrop {
                display: block;
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        /* ===== MAIN CONTENT ===== */
        .main-wrapper {
            margin-left: 260px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== TOPBAR ===== */
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e8edf3;
            padding: 0.875rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.05);
        }

        .topbar .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .topbar .breadcrumb {
            margin: 0;
            font-size: 0.78rem;
        }

        .topbar .breadcrumb-item.active {
            color: #64748b;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .topbar-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .topbar-btn:hover {
            background: #f1f5f9;
            color: #1a3a8f;
            border-color: #cbd5e1;
        }

        .topbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a8f, #1e4db7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }

        /* ===== CONTENT AREA ===== */
        .content-area {
            padding: 1.75rem;
            flex: 1;
        }

        /* ===== ALERT ===== */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* ===== CARD ===== */
        .card {
            border: 1px solid #e8edf3;
            border-radius: 14px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.04);
            background: #ffffff;
        }

        .card-header {
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.125rem 1.375rem;
            border-radius: 14px 14px 0 0 !important;
        }

        /* ===== TABLE ===== */
        .table th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            border-bottom: 1px solid #f1f5f9;
            padding: 0.75rem 1rem;
        }

        .table td {
            font-size: 0.875rem;
            color: #334155;
            padding: 0.875rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f8fafc;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .table thead.table-dark th {
            background: #f8fafc !important;
            color: #64748b !important;
        }

        /* ===== BADGE ===== */
        .badge {
            font-size: 0.72rem;
            font-weight: 500;
            padding: 0.35em 0.7em;
            border-radius: 6px;
        }

        /* ===== BUTTON ===== */
        .btn {
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #1a3a8f;
            border-color: #1a3a8f;
        }

        .btn-primary:hover {
            background: #0f2557;
            border-color: #0f2557;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 58, 143, 0.3);
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* ===== FORM ===== */
        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 0.875rem;
            padding: 0.55rem 0.875rem;
            color: #334155;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #1a3a8f;
            box-shadow: 0 0 0 3px rgba(26, 58, 143, 0.1);
        }

        .form-label {
            font-size: 0.825rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.4rem;
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }



        /* Pulse animation untuk tombol Kirim EKG */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(13, 110, 253, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
            }
        }

        .pulse-btn {
            animation: pulse 1.5s infinite;
        }


        .logout-button {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: #dc2626;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .logout-button:hover {
            background: #b91c1c;
        }
    </style>

</head>

<body>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-heartbeat text-white"></i>
            </div>
            <p class="brand-title">EKG System</p>
            <p class="brand-subtitle">Management System</p>
        </div>

        <div class="sidebar-menu">
            <p class="sidebar-label">Main Menu</p>

            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <span class="nav-icon"><i class="fas fa-chart-pie"></i></span>
                Dashboard
            </a>

            <a class="nav-link {{ request()->routeIs('patients.*') ? 'active' : '' }}"
                href="{{ route('patients.index') }}">
                <span class="nav-icon"><i class="fas fa-user-injured"></i></span>
                Daftar Pasien
            </a>

            <a class="nav-link {{ request()->routeIs('ekg.*') ? 'active' : '' }}" href="{{ route('ekg.index') }}">
                <span class="nav-icon"><i class="fas fa-heartbeat"></i></span>
                Hasil EKG
            </a>

            <div style="margin-top: 12rem; margin-left: 1rem;">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-button">Logout</button>
                </form>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2">
                <span class="status-dot"></span>
                <span style="font-size: 0.75rem; color: rgba(255,255,255,0.5);">System Online</span>
            </div>
        </div>
    </nav>

    <!-- BACKDROP (mobile) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">

        <!-- TOPBAR -->
        <div class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="sidebarToggle" class="btn btn-light btn-sm" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>

                <div>
                    <h6 class="page-title">@yield('page-title', 'Dashboard')</h6>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}" style="color: #1a3a8f; font-size: 0.78rem;">Home</a>
                            </li>
                            <li class="breadcrumb-item active">@yield('page-title', 'Dashboard')</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="topbar-right">
                <div class="btn btn-primary">{{ Auth::user()->name }}</div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content-area w-100">

            @if (session('success'))
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: @json(session('success')),
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    });
                </script>
            @endif

            @if (session('error'))
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: @json(session('error')),
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    });
                </script>
            @endif

            @yield('content')

        </div>
    </div>

    <!-- TOGGLE SCRIPT -->
    <script>
        (function () {
            const body = document.body;
            const toggle = document.getElementById('sidebarToggle');
            const backdrop = document.getElementById('sidebarBackdrop');

            toggle.addEventListener('click', function () {
                body.classList.toggle('sidebar-open');
            });

            backdrop.addEventListener('click', function () {
                body.classList.remove('sidebar-open');
            });
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let lastEkgId = {{ \App\Models\EkgResult::latest()->first()?->id ?? 0 }};

        // Cek setiap 10 detik
        setInterval(function () {
            fetch(`/api/check-new-ekg?last_id=${lastEkgId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.new_results && data.new_results.length > 0) {
                        data.new_results.forEach(function (result) {
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
        document.addEventListener('DOMContentLoaded', function () {
            if (Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });
    </script>
    @stack('scripts')
</body>

</html>