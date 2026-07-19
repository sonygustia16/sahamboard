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
    <a href="{{ route('screening.index') }}" class="nav-link {{ request()->routeIs('screening.*') ? 'active' : '' }}">
        Screening
    </a>
    <a href="{{ route('watchlist.index') }}" class="nav-link {{ request()->routeIs('watchlist.*') ? 'active' : '' }}" style="position:relative;">
        Watchlist
        <span id="watchlistAlertBadge" style="display:none; position:absolute; right:0.6rem; top:50%; transform:translateY(-50%); background:var(--loss); color:#fff; font-family:var(--mono); font-size:0.65rem; font-weight:700; border-radius:10px; padding:0.05rem 0.45rem;"></span>
    </a>

    <div class="sidebar-footer">
        v1.0 (Laravel) · Data: Yahoo Finance Proxy
        <form method="POST" action="{{ route('logout') }}" style="margin-top: 0.6rem;">
            @csrf
            <button type="submit" style="background:none;border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:0.35rem 0.6rem;font-family:var(--mono);font-size:0.65rem;cursor:pointer;width:100%;">
                Logout
            </button>
        </form>
    </div>
</div>