<div class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-dot">SB</div>
        <div>
            <div class="brand-text">SahamBoard</div>
            <div class="brand-sub">Pro Trader Suite</div>
        </div>
    </div>

    <div class="nav-section-label">Riset & Screening</div>
    <a href="{{ route('index') }}" class="nav-link {{ request()->routeIs('index') ? 'active' : '' }}">
        <span class="nav-icon">▤</span> Filter Lengkap
    </a>
    <a href="{{ route('analysis.index') }}" class="nav-link {{ request()->routeIs('analysis.*') ? 'active' : '' }}">
        <span class="nav-icon">📈</span> Analysis &amp; Chart
    </a>
    <a href="{{ route('watchlist.index') }}" class="nav-link {{ request()->routeIs('watchlist.*') ? 'active' : '' }}">
        <span class="nav-icon">★</span> Watchlist
    </a>

    <div class="nav-section-label">Trading Plan</div>
    <a href="{{ route('entry.index') }}" class="nav-link {{ request()->routeIs('entry.*') ? 'active' : '' }}">
        <span class="nav-icon">✎</span> Entry Plan
    </a>
    <a href="{{ route('lotsizing.index') }}" class="nav-link {{ request()->routeIs('lotsizing.*') ? 'active' : '' }}">
        <span class="nav-icon">⚖</span> Lot Sizing
    </a>
    <a href="{{ route('money-management.index') }}" class="nav-link {{ request()->routeIs('money-management.*') ? 'active' : '' }}">
        <span class="nav-icon">◎</span> Money Management
    </a>

    <div class="sidebar-footer">
        v1.0 (Laravel) · Data: Yahoo Finance Proxy
    </div>
</div>
