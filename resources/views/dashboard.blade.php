@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="announcement">
        <span class="announcement-tag">CSFEST2026</span>
        <span>Campus Solution Fest · Portal peserta</span>
    </div>

    <header class="site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            CSFEST<span>2026</span>
        </a>
        <nav class="nav-links" aria-label="Navigasi utama">
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
                <p class="eyebrow"><span class="eyebrow-mark"></span> Portal peserta</p>
                <h1 class="dashboard-greeting">Halo, {{ $user['name'] ?? 'Peserta' }}.</h1>
                <p class="dashboard-subtitle">Akun bersama untuk timmu di CSFEST2026.</p>
            </div>
        </div>

        <div class="dashboard-grid">
            <section class="dashboard-card dashboard-feature">
                <div class="dashboard-feature-content">
                    <span class="eyebrow"><span class="eyebrow-mark"></span> Hackathon 2026</span>
                    <h2>Innovating Integrated Digital Solutions for Better Campus Life</h2>
                    <p>Ruang untuk mengubah tantangan kehidupan kampus menjadi solusi digital yang terhubung, bermanfaat, dan berdampak.</p>
                    <a class="button" href="{{ route('home') }}">Jelajahi event <span aria-hidden="true">→</span></a>
                </div>
            </section>

            <aside class="dashboard-card profile-card">
                <div class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user['name'] ?? 'P', 0, 1)) }}</div>
                <h2>{{ $user['name'] ?? 'Peserta' }}</h2>
                <p>{{ ($user['role'] ?? null) === 'admin' ? 'Panitia CSFEST2026' : 'Akun tim peserta' }}</p>
                <span class="profile-status">Akun terhubung</span>
                @if (($user['role'] ?? null) === 'admin')
                    <a class="button button-small" style="width: 100%; margin-top: 18px;" href="{{ route('admin.participants.create') }}">
                        Kelola peserta
                    </a>
                @endif
            </aside>
        </div>

        @if (($user['role'] ?? null) === 'participant')
            <section class="dashboard-card case-card">
                <div>
                    <span class="eyebrow"><span class="eyebrow-mark"></span> Studi kasus tim</span>
                    @if ($themeError)
                        <p class="form-alert" role="alert">{{ $themeError }}</p>
                    @elseif ($theme)
                        <h2>{{ $theme['title'] }}</h2>
                        @if (filled($theme['description'] ?? null))
                            <p>{{ $theme['description'] }}</p>
                        @endif
                    @else
                        <h2>Belum ada tema yang ditetapkan</h2>
                        <p>Panitia akan menetapkan studi kasus untuk timmu. Silakan periksa kembali nanti.</p>
                    @endif
                </div>
            </section>
        @endif

        <div class="dashboard-bottom">
            <section class="dashboard-card dashboard-mini">
                <span>Event</span>
                <strong>CSFEST2026</strong>
            </section>
            <section class="dashboard-card dashboard-mini">
                <span>Program</span>
                <strong>Hackathon</strong>
            </section>
            <section class="dashboard-card dashboard-mini">
                <span>Fokus solusi</span>
                <strong>Kehidupan kampus</strong>
            </section>
        </div>
    </main>
@endsection
