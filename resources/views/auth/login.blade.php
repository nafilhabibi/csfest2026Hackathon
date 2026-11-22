@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <div class="announcement">
        <span class="announcement-tag">CSFEST2026</span>
        <span>Temukan ide besar untuk kehidupan kampus yang lebih baik.</span>
    </div>

    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="CSFEST2026 Beranda">
            CSFEST<span>2026</span>
        </a>
        <nav class="nav-links" aria-label="Navigasi utama">
            <a href="{{ route('home') }}">Beranda</a>
            <button class="button button-outline button-small theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span aria-hidden="true" data-theme-icon>☾</span>
                <span data-theme-label>Mode malam</span>
            </button>
            <a class="button button-small" href="{{ route('login') }}">Masuk</a>
        </nav>
    </header>

    <main class="auth-shell">
        <section class="auth-aside">
            <div class="auth-aside-copy">
                <span class="eyebrow"><span class="eyebrow-mark"></span> Campus Solution Fest</span>
                <h1>Ide terbaikmu bisa mengubah <em>kampus.</em></h1>
                <p>Masuk ke portal CSFEST2026 untuk melanjutkan perjalananmu membangun solusi digital terintegrasi.</p>
            </div>
            <div class="auth-aside-meta">Innovating Integrated Digital Solutions for Better Campus Life</div>
        </section>

        <section class="auth-form-wrap">
            <div class="auth-form">
                <span class="eyebrow"><span class="eyebrow-mark"></span> Portal peserta</span>
                <h2>Selamat datang</h2>
                <p class="auth-form-intro">Masuk menggunakan akun yang terdaftar untuk mengakses dashboard CSFEST2026.</p>

                @if ($errors->any())
                    <div class="form-alert" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="nama@email.com"
                            autocomplete="email"
                            required
                            autofocus
                        >
                        @error('email')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="password">Kata sandi</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Masukkan kata sandi"
                            autocomplete="current-password"
                            required
                        >
                        @error('password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button auth-submit" type="submit">
                        Masuk ke dashboard <span aria-hidden="true">→</span>
                    </button>
                </form>

                <a class="auth-back" href="{{ route('home') }}">← Kembali ke beranda</a>
            </div>
        </section>
    </main>
@endsection
