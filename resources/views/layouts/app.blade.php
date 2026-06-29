<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SahamBoard · @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head-scripts')
</head>
<body>
<div class="app-shell" id="appShell">

    @include('partials.sidebar')

    <div class="main-content">

        <div class="page-header">
            <div style="display:flex; align-items:flex-start; gap:0.9rem;">
                <button type="button" id="sidebarToggle" class="sidebar-toggle-btn" title="Buka/Tutup Menu">
                    <span></span><span></span><span></span>
                </button>
                <div>
                    <h1>@yield('page-title')</h1>
                    <p>@yield('page-subtitle')</p>
                </div>
            </div>
            <div class="live-pill"><span class="dot"></span> Live Yahoo Feed</div>
        </div>

        @if (session('success'))
            <div class="info-banner" style="border-color: rgba(16,185,129,0.3); background: rgba(16,185,129,0.08); color:#6ee7b7;">
                ✓ {{ session('success') }}
            </div>
        @endif

        @yield('content')

    </div>
</div>

@stack('body-scripts')
<script>
    const appShell = document.getElementById('appShell');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const SIDEBAR_KEY = 'sahamboard_sidebar_collapsed';

    function applySidebarState() {
        if (localStorage.getItem(SIDEBAR_KEY) === '1') {
            appShell.classList.add('sidebar-collapsed');
        }
    }
    applySidebarState();

    sidebarToggle.addEventListener('click', function () {
        appShell.classList.toggle('sidebar-collapsed');
        localStorage.setItem(SIDEBAR_KEY, appShell.classList.contains('sidebar-collapsed') ? '1' : '0');
    });
</script>
</body>
</html>