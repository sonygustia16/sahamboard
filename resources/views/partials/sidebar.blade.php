<div class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-left">
    <div>
        <div class="brand-text">Sony Gustia</div>
        <div class="brand-sub">Pro Trader Suite</div>
    </div>
</div>
        <button type="button" id="sidebarToggle" class="sidebar-toggle-btn" title="Tutup Menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="nav-section-label">Riset & Screening</div>
    <a href="{{ route('index') }}" class="nav-link {{ request()->routeIs('index') ? 'active' : '' }}">
        Filter Lengkap
    </a>
    <a href="{{ route('analysis.index') }}" class="nav-link {{ request()->routeIs('analysis.*') ? 'active' : '' }}">
        Analysis &amp; Chart
    </a>
    <a href="{{ route('watchlist.index') }}" class="nav-link {{ request()->routeIs('watchlist.*') ? 'active' : '' }}">
        Watchlist
    </a>

    <div class="nav-section-label">Trading Plan</div>
    <a href="{{ route('entry.index') }}" class="nav-link {{ request()->routeIs('entry.*') ? 'active' : '' }}">
        Entry Plan
    </a>
    <a href="{{ route('lotsizing.index') }}" class="nav-link {{ request()->routeIs('lotsizing.*') ? 'active' : '' }}">
        Lot Sizing
    </a>
    <a href="{{ route('money-management.index') }}" class="nav-link {{ request()->routeIs('money-management.*') ? 'active' : '' }}">
        Money Management
    </a>

    <div class="sidebar-footer">
        v1.0 (Laravel) · Data: Yahoo Finance Proxy
    </div>
</div>