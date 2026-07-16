@extends('layouts.admin')

@section('title', 'Kullanıcılar - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero compact-hero">
                <div>
                    <p class="eyebrow">Kullanıcılar</p>
                    <h1>Rol yönetimi</h1>
                </div>
            </section>

            <section class="panel">
                <div class="admin-table">
                    @foreach($users as $user)
                        <article class="user-admin-row">
                            <div class="directory-admin-avatar">
                                @if($user->avatar_path)
                                    <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->username }}">
                                @else
                                    <span>{{ mb_substr($user->username ?: $user->email, 0, 1) }}</span>
                                @endif
                            </div>
                            <div>
                                <strong>{{ '@'.($user->username ?: 'kullanici-'.$user->id) }}</strong>
                                <small>{{ $user->email }}</small>
                            </div>
                            <form class="inline-role-form" method="post" action="{{ route('admin.users.update', $user) }}">
                                @csrf
                                @method('PUT')
                                <select name="role">
                                    <option value="user" @selected($user->role === 'user')>User</option>
                                    <option value="admin" @selected($user->role === 'admin')>Admin</option>
                                    <option value="super_admin" @selected($user->role === 'super_admin')>Super Admin</option>
                                </select>
                                <button class="button primary">Kaydet</button>
                            </form>
                        </article>
                    @endforeach
                </div>

                {{ $users->links() }}
            </section>
        </div>
    </section>
@endsection
