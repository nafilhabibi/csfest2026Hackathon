@extends('layouts.app')

@section('title', 'Kelola tim dan studi kasus')

@section('content')
    <div class="announcement">
        <span class="announcement-tag">ADMIN</span>
        <span>Kelola akun tim dan studi kasus CSFEST2026</span>
    </div>

    <header class="site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            CSFEST<span>2026</span>
        </a>
        <nav class="nav-links" aria-label="Navigasi admin">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <button class="button button-outline button-small theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span aria-hidden="true" data-theme-icon>☾</span>
                <span data-theme-label>Mode malam</span>
            </button>
            <form class="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button button-outline button-small" type="submit">Keluar</button>
            </form>
        </nav>
    </header>

    <main class="dashboard-page">
        <div class="dashboard-top">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark"></span> Administrasi hackathon</p>
                <h1 class="dashboard-greeting">Kelola tim dan studi kasus</h1>
                <p class="dashboard-subtitle">Buat akun untuk satu tim, atur studi kasus, dan tetapkan tema kepada tim peserta.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="form-alert form-alert-success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="form-alert" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($dataError)
            <div class="form-alert" role="alert">
                {{ $dataError }}
            </div>
        @endif

        <div class="management-grid">
            <section class="dashboard-card admin-form-card">
                <h2 class="management-heading">Buat akun tim</h2>
                <p class="management-intro">Nama tim menjadi identitas login. Buat satu akun untuk seluruh anggota tim.</p>
                <form method="POST" action="{{ route('admin.participants.store') }}">
                    @csrf

                    <div class="form-field">
                        <label for="name">Nama tim</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required>
                        @error('name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="password">Kata sandi awal</label>
                        <input id="password" name="password" type="password" minlength="8" maxlength="1024" required>
                        @error('password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="password_confirmation">Konfirmasi kata sandi</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="1024" required>
                    </div>

                    <div class="form-field">
                        <label for="new_team_theme">Studi kasus (opsional)</label>
                        <select id="new_team_theme" name="theme_id">
                            <option value="">Belum ditetapkan</option>
                            @foreach ($themes as $theme)
                                <option value="{{ $theme['id'] }}" @selected((string) old('theme_id') === (string) $theme['id'])>
                                    {{ $theme['title'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('theme_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button auth-submit" type="submit">
                        Buat akun tim <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>

            <section class="dashboard-card management-card">
                <h2 class="management-heading">Tambah studi kasus</h2>
                <p class="management-intro">Daftar studi kasus ini dapat digunakan ulang untuk beberapa tim.</p>
                <form method="POST" action="{{ route('admin.themes.store') }}">
                    @csrf

                    <div class="form-field">
                        <label for="title">Nama studi kasus</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" required>
                        @error('title')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="description">Deskripsi studi kasus</label>
                        <textarea id="description" name="description" rows="5" maxlength="10000">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button auth-submit" type="submit">
                        Tambah studi kasus <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>
        </div>

        <section class="dashboard-card management-card management-section">
            <div class="management-section-heading">
                <div>
                    <h2 class="management-heading">Daftar studi kasus</h2>
                    <p class="management-intro">Perbarui nama atau deskripsi tanpa menghapus penugasan tim.</p>
                </div>
            </div>

            @forelse ($themes as $theme)
                <form class="theme-edit-row" method="POST" action="{{ route('admin.themes.update', $theme['id']) }}">
                    @csrf
                    @method('PATCH')
                    <div class="form-field">
                        <label for="theme_title_{{ $theme['id'] }}">Nama studi kasus</label>
                        <input id="theme_title_{{ $theme['id'] }}" name="title" type="text" value="{{ $theme['title'] }}" maxlength="255" required>
                    </div>
                    <div class="form-field">
                        <label for="theme_description_{{ $theme['id'] }}">Deskripsi</label>
                        <textarea id="theme_description_{{ $theme['id'] }}" name="description" rows="3" maxlength="10000">{{ $theme['description'] ?? '' }}</textarea>
                    </div>
                    <button class="button button-small" type="submit">Simpan perubahan</button>
                </form>
            @empty
                <p class="empty-state">Belum ada studi kasus. Tambahkan studi kasus terlebih dahulu.</p>
            @endforelse
        </section>

        <section class="dashboard-card management-card management-section">
            <div class="management-section-heading">
                <div>
                    <h2 class="management-heading">Penetapan tema per tim</h2>
                    <p class="management-intro">Tema yang sama dapat ditetapkan kepada lebih dari satu tim.</p>
                </div>
            </div>

            @if (count($participants) > 0)
                <div class="team-list">
                    @foreach ($participants as $participant)
                        <div class="team-row">
                            <div class="team-info">
                                <strong>{{ $participant['name'] }}</strong>
                                <span>
                                    @if (is_array($participant['theme'] ?? null))
                                        {{ $participant['theme']['title'] }}
                                    @else
                                        Belum ditetapkan
                                    @endif
                                </span>
                            </div>
                            <form class="team-assignment-form" method="POST" action="{{ route('admin.participants.theme.update', $participant['id']) }}">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="participant_theme_{{ $participant['id'] }}">Studi kasus untuk {{ $participant['name'] }}</label>
                                <select id="participant_theme_{{ $participant['id'] }}" name="theme_id">
                                    <option value="">Belum ditetapkan</option>
                                    @foreach ($themes as $theme)
                                        <option value="{{ $theme['id'] }}" @selected((string) ($participant['theme_id'] ?? '') === (string) $theme['id'])>
                                            {{ $theme['title'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="button button-small" type="submit">Simpan tema</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="empty-state">Belum ada akun tim untuk ditetapkan ke studi kasus.</p>
            @endif
        </section>
    </main>
@endsection
