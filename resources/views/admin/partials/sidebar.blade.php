<aside class="admin-sidebar">
    <strong><i class="fa-solid fa-layer-group"></i> nozu.me CMS</strong>
    <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <span class="sidebar-label">İçerikler</span>
    <a class="{{ request()->routeIs('admin.anime.*') ? 'active' : '' }}" href="{{ route('admin.anime.index') }}"><i class="fa-solid fa-tv"></i> Anime</a>
    <a class="{{ request()->routeIs('admin.manga.*') ? 'active' : '' }}" href="{{ route('admin.manga.index') }}"><i class="fa-solid fa-book-open"></i> Manga</a>
    <a class="{{ request()->routeIs('admin.characters.*') ? 'active' : '' }}" href="{{ route('admin.characters.index') }}"><i class="fa-regular fa-user"></i> Karakterler</a>
    <a class="{{ request()->routeIs('admin.people.*') ? 'active' : '' }}" href="{{ route('admin.people.index') }}"><i class="fa-solid fa-user-group"></i> Sanatçılar</a>
    <a class="{{ request()->routeIs('admin.studios.*') ? 'active' : '' }}" href="{{ route('admin.studios.index') }}"><i class="fa-solid fa-building"></i> Stüdyolar</a>
    <span class="sidebar-label">Operasyon</span>
    <a class="{{ request()->routeIs('admin.import-queue*') ? 'active' : '' }}" href="{{ route('admin.import-queue') }}"><i class="fa-solid fa-list-check"></i> Import Queue</a>
    <a class="{{ request()->routeIs('admin.sync.*') ? 'active' : '' }}" href="{{ route('admin.sync.index') }}"><i class="fa-solid fa-rotate"></i> Smart Sync</a>
    <a class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}" href="{{ route('admin.settings') }}"><i class="fa-solid fa-gear"></i> Ayarlar</a>
    <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="fa-solid fa-users-gear"></i> Kullanıcılar</a>
    <a class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="fa-regular fa-flag"></i> Şikayetler</a>
    <a href="{{ route('home') }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Siteye Git</a>
    <form method="post" action="{{ route('admin.logout') }}">@csrf<button><i class="fa-solid fa-arrow-right-from-bracket"></i> Çıkış</button></form>
</aside>
