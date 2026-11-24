@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <header class="site-header dashboard-site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="Navigasi utama">
            <a class="dashboard-nav-link dashboard-nav-link-active" href="{{ route('dashboard') }}" aria-current="page">Dashboard</a>
            @if (($user['role'] ?? null) === 'admin')
                <a class="dashboard-nav-link" href="{{ route('admin.participants.create') }}">Pusat Pengelolaan</a>
                <a class="dashboard-nav-link" href="{{ route('admin.submissions.index') }}">Submission</a>
            @else
                @if ($theme)
                    <a class="dashboard-nav-link" href="{{ route('submission.show') }}">Submission</a>
                @endif
            @endif
        </nav>
        <div class="dashboard-nav-actions">
            <span class="dashboard-nav-user">
                <span class="dashboard-nav-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user['name'] ?? 'P', 0, 1)) }}</span>
                <span>{{ $user['name'] ?? 'Peserta' }}</span>
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

    <main class="dashboard-page">
        <div class="dashboard-top">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark"></span> {{ ($user['role'] ?? null) === 'admin' ? 'Portal panitia' : 'Portal peserta' }}</p>
                <h1 class="dashboard-greeting">Halo, {{ $user['name'] ?? 'Peserta' }}.</h1>
                <p class="dashboard-subtitle">
                    {{ ($user['role'] ?? null) === 'admin'
                        ? 'Pantau dan kelola kebutuhan hackathon dari satu tempat.'
                        : 'Ruang kerja timmu untuk mengikuti CSFEST2026.' }}
                </p>
            </div>
            <span class="dashboard-account-chip">
                <span aria-hidden="true"></span>
                {{ ($user['role'] ?? null) === 'admin' ? 'Akun panitia' : 'Akun tim peserta' }}
            </span>
        </div>

        @if (session('submission_access_error'))
            <div class="form-alert" role="alert">{{ session('submission_access_error') }}</div>
        @endif

        <div class="dashboard-grid">
            <section class="dashboard-card dashboard-feature">
                <div class="dashboard-feature-content">
                    <span class="eyebrow"><span class="eyebrow-mark"></span> CSFEST2026 · Hackathon</span>
                    <h2>Innovating Integrated Digital Solutions for Better Campus Life</h2>
                    <p>Ruang untuk mengubah tantangan kehidupan kampus menjadi solusi digital yang terhubung, bermanfaat, dan berdampak.</p>
                </div>
            </section>

            <aside class="dashboard-card profile-card">
                <div class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user['name'] ?? 'P', 0, 1)) }}</div>
                <h2>{{ $user['name'] ?? 'Peserta' }}</h2>
                <p>{{ ($user['role'] ?? null) === 'admin' ? 'Panitia CSFEST2026' : 'Akun tim peserta' }}</p>
                <span class="profile-status">Akun terhubung</span>
                @if (($user['role'] ?? null) === 'admin')
                    <a class="button button-small profile-action" href="{{ route('admin.participants.create') }}">
                        Buka pusat pengelolaan <span aria-hidden="true">→</span>
                    </a>
                @endif
            </aside>
        </div>

        @if (($user['role'] ?? null) === 'participant')
            <section class="dashboard-card case-card" id="team-case">
                <div class="case-card-heading">
                    <div>
                        <span class="eyebrow"><span class="eyebrow-mark"></span> Ruang kerja tim</span>
                        <h2>Studi kasus tim</h2>
                    </div>
                    <span class="case-status-chip {{ $theme ? 'case-status-ready' : 'case-status-pending' }}">
                        <span aria-hidden="true"></span>
                        {{ $themeError ? 'Informasi belum tersedia' : ($theme ? 'Tema ditetapkan' : 'Menunggu penetapan') }}
                    </span>
                </div>
                <div class="case-card-content">
                    @if ($themeError)
                        <p class="form-alert" role="alert">{{ $themeError }}</p>
                    @elseif ($theme)
                        <h3>{{ $theme['title'] }}</h3>
                        @if (filled($theme['description'] ?? null))
                            <p class="case-description">{{ $theme['description'] }}</p>
                        @endif
                    @else
                        <h3>Belum ada studi kasus yang ditetapkan</h3>
                        <p class="case-description">Panitia akan menetapkan studi kasus untuk timmu. Informasi akan muncul di sini setelah tersedia.</p>
                    @endif
                </div>
            </section>
        @endif
    </main>
@endsection
