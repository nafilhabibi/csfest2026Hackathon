@extends('layouts.app')

@section('title', 'Buat akun peserta')

@section('content')
    @php($user = session('app_user', []))
    <div class="announcement">
        <span class="announcement-tag">ADMIN</span>
        <span>Kelola akun peserta CSFEST2026</span>
    </div>

    <header class="site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            CSFEST<span>2026</span>
        </a>
        <nav class="nav-links" aria-label="Navigasi admin">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <form class="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button button-outline button-small" type="submit">Keluar</button>
            </form>
        </nav>
    </header>

    <main class="dashboard-page">
        <div class="dashboard-top">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark"></span> Administrasi peserta</p>
                <h1 class="dashboard-greeting">Buat akun peserta</h1>
                <p class="dashboard-subtitle">Masukkan identitas dan kata sandi awal untuk peserta baru.</p>
            </div>
        </div>

        <section class="dashboard-card" style="max-width: 620px; padding: 28px;">
            @if (session('status'))
                <div class="form-alert" role="status" style="margin: 0 0 20px; background: #edf7f0; border-color: #bfe3ca; color: #35784d;">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="form-alert" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.participants.store') }}">
                @csrf

                <div class="form-field">
                    <label for="name">Nama peserta</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="email">Email peserta</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required>
                    @error('email')
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

                <button class="button auth-submit" type="submit">
                    Buat akun peserta <span aria-hidden="true">→</span>
                </button>
            </form>
        </section>
    </main>
@endsection
