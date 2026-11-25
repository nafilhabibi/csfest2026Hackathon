@extends('layouts.app')

@section('title', 'Kelola tim dan studi kasus')

@section('content')
    <header class="site-header dashboard-site-header portal-site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="Navigasi admin">
            <a class="dashboard-nav-link" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="dashboard-nav-link dashboard-nav-link-active" href="{{ route('admin.participants.create') }}" aria-current="page">Pusat pengelolaan</a>
            <a class="dashboard-nav-link" href="{{ route('admin.submissions.index') }}">Submission</a>
        </nav>
        <div class="dashboard-nav-actions">
            <span class="dashboard-nav-user">
                <span class="dashboard-nav-avatar" aria-hidden="true">A</span>
                <span>Panitia</span>
            </span>
            <button class="button button-outline button-small theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span aria-hidden="true" data-theme-icon>☾</span>
                <span class="dashboard-theme-label" data-theme-label>Mode malam</span>
            </button>
            <form class="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button button-outline button-small" type="submit">Keluar</button>
            </form>
        </div>
    </header>

    <main class="dashboard-page admin-page">
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

        @php
            $assignedTeamCount = count(array_filter(
                $participants,
                fn (array $participant): bool => is_array($participant['theme'] ?? null)
            ));
            $unassignedTeamCount = count($participants) - $assignedTeamCount;
        @endphp

        <section class="admin-overview" aria-label="Ringkasan pengelolaan">
            <div class="admin-overview-heading">
                <div>
                    <span class="admin-overview-label">Pusat pengelolaan</span>
                    <h2>Semua kebutuhan hackathon, dalam satu tempat.</h2>
                </div>
                <nav class="admin-quick-links" aria-label="Navigasi pengelolaan">
                    <a href="#team-account">Akun tim <span aria-hidden="true">↓</span></a>
                    <a href="#judge-account">Akun juri <span aria-hidden="true">↓</span></a>
                    <a href="#study-cases">Studi kasus <span aria-hidden="true">↓</span></a>
                    <a href="#team-assignments">Penetapan tema <span aria-hidden="true">↓</span></a>
                </nav>
            </div>
            <div class="admin-summary-grid">
                <div class="admin-summary-card">
                    <span>Tim terdaftar</span>
                    <strong>{{ $dataError ? '—' : count($participants) }}</strong>
                </div>
                <div class="admin-summary-card">
                    <span>Studi kasus</span>
                    <strong>{{ $dataError ? '—' : count($themes) }}</strong>
                </div>
                <div class="admin-summary-card">
                    <span>Belum mendapat tema</span>
                    <strong>{{ $dataError ? '—' : $unassignedTeamCount }}</strong>
                </div>
            </div>
        </section>

        <div class="management-grid">
            <section class="dashboard-card admin-form-card" id="team-account">
                <div class="management-card-heading">
                    <span class="management-step">01</span>
                    <div>
                        <h2 class="management-heading">Buat akun tim</h2>
                        <p class="management-intro">Nama tim menjadi identitas login untuk seluruh anggota.</p>
                    </div>
                </div>
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

            <section class="dashboard-card management-card" id="judge-account">
                <div class="management-card-heading">
                    <span class="management-step">02</span>
                    <div>
                        <h2 class="management-heading">Buat akun juri</h2>
                        <p class="management-intro">Juri dapat melihat daftar dan detail submission, tanpa akses ke halaman lain.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.judges.store') }}">
                    @csrf

                    <div class="form-field">
                        <label for="judge_name">Nama juri</label>
                        <input id="judge_name" name="judge_name" type="text" value="{{ old('judge_name') }}" maxlength="255" required>
                        @error('judge_name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="judge_password">Kata sandi awal</label>
                        <input id="judge_password" name="judge_password" type="password" minlength="8" maxlength="1024" required>
                        @error('judge_password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="judge_password_confirmation">Konfirmasi kata sandi</label>
                        <input id="judge_password_confirmation" name="judge_password_confirmation" type="password" minlength="8" maxlength="1024" required>
                    </div>

                    <button class="button auth-submit" type="submit">
                        Buat akun juri <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>

            <section class="dashboard-card management-card" id="study-cases">
                <div class="management-card-heading">
                    <span class="management-step">03</span>
                    <div>
                        <h2 class="management-heading">Tambah studi kasus</h2>
                        <p class="management-intro">Satu studi kasus dapat digunakan oleh beberapa tim.</p>
                    </div>
                </div>
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
                    <div class="management-card-heading">
                        <span class="management-step">04</span>
                        <div>
                            <h2 class="management-heading">Daftar studi kasus</h2>
                            <p class="management-intro">Perbarui nama atau deskripsi tanpa menghapus penugasan tim.</p>
                        </div>
                    </div>
                </div>
                <span class="section-count">{{ $dataError ? 'Data tidak tersedia' : count($themes).' studi kasus' }}</span>
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
                <p class="empty-state">
                    {{ $dataError ? 'Data studi kasus belum dapat ditampilkan.' : 'Belum ada studi kasus. Tambahkan studi kasus terlebih dahulu.' }}
                </p>
            @endforelse
        </section>

        <section class="dashboard-card management-card management-section" id="team-assignments">
            <div class="management-section-heading">
                <div>
                    <div class="management-card-heading">
                        <span class="management-step">05</span>
                        <div>
                            <h2 class="management-heading">Penetapan tema per tim</h2>
                            <p class="management-intro">Tetapkan atau ubah studi kasus untuk setiap tim.</p>
                        </div>
                    </div>
                </div>
                <span class="section-count">
                    {{ $dataError ? 'Data tidak tersedia' : $assignedTeamCount.' dari '.count($participants).' tim mendapat tema' }}
                </span>
            </div>

            @if (count($participants) > 0)
                <div class="team-list">
                    @foreach ($participants as $participant)
                        <div class="team-row">
                            <div class="team-info">
                                <strong>{{ $participant['name'] }}</strong>
                                <span class="{{ is_array($participant['theme'] ?? null) ? 'team-assigned' : 'team-unassigned' }}">
                                    @if (is_array($participant['theme'] ?? null))
                                        <span class="team-status-dot" aria-hidden="true"></span>
                                        {{ $participant['theme']['title'] }}
                                    @else
                                        <span class="team-status-dot" aria-hidden="true"></span>
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
                <p class="empty-state">
                    {{ $dataError ? 'Data tim belum dapat ditampilkan.' : 'Belum ada akun tim untuk ditetapkan ke studi kasus.' }}
                </p>
            @endif
        </section>
    </main>
@endsection
