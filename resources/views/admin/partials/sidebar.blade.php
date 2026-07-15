<aside class="admin-sidebar">
    <strong>nozu.me CMS</strong>
    <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
    <span class="sidebar-label">İçerikler</span>
    <a class="{{ request()->routeIs('admin.anime.*') ? 'active' : '' }}" href="{{ route('admin.anime.index') }}">Anime</a>
    <a class="{{ request()->routeIs('admin.manga.*') ? 'active' : '' }}" href="{{ route('admin.manga.index') }}">Manga</a>
    <a class="{{ request()->routeIs('admin.characters.*') ? 'active' : '' }}" href="{{ route('admin.characters.index') }}">Karakterler</a>
    <a class="{{ request()->routeIs('admin.people.*') ? 'active' : '' }}" href="{{ route('admin.people.index') }}">Sanatçılar</a>
    <a class="{{ request()->routeIs('admin.studios.*') ? 'active' : '' }}" href="{{ route('admin.studios.index') }}">Stüdyolar</a>
    <span class="sidebar-label">Operasyon</span>
    <a class="{{ request()->routeIs('admin.import-queue*') ? 'active' : '' }}" href="{{ route('admin.import-queue') }}">Import Queue</a>
    <a class="{{ request()->routeIs('admin.sync.*') ? 'active' : '' }}" href="{{ route('admin.sync.index') }}">Smart Sync</a>
    <a class="{{ request()->routeIs('admin.status') ? 'active' : '' }}" href="{{ route('admin.status') }}">Sistem Durumu</a>
    <a class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}" href="{{ route('admin.settings') }}">Ayarlar</a>
    <a href="{{ route('home') }}" target="_blank" rel="noopener">Siteye Git</a>
    <form method="post" action="{{ route('admin.logout') }}">@csrf<button>Çıkış</button></form>
</aside>
