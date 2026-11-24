@extends('layouts.app')

@section('title', 'Submission tim')

@section('content')
    <header class="site-header dashboard-site-header portal-site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="Navigasi admin">
            <a class="dashboard-nav-link" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="dashboard-nav-link" href="{{ route('admin.participants.create') }}">Pusat pengelolaan</a>
            <a class="dashboard-nav-link dashboard-nav-link-active" href="{{ route('admin.submissions.index') }}" aria-current="page">Submission</a>
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

    <main class="dashboard-page admin-page admin-submissions-page">
        <div class="dashboard-top">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark"></span> Administrasi hackathon</p>
                <h1 class="dashboard-greeting">Submission tim</h1>
                <p class="dashboard-subtitle">Lihat tim yang sudah mengirimkan submission dan buka detailnya.</p>
            </div>
            <span class="dashboard-account-chip">{{ count(array_filter($participants, fn (array $participant): bool => $participant['has_submission'])) }} dari {{ count($participants) }} tim mengirim</span>
        </div>

        @if ($dataError)
            <div class="form-alert" role="alert">{{ $dataError }}</div>
        @elseif ($participants === [])
            <section class="dashboard-card admin-submission-list">
                <p class="admin-submission-empty">Belum ada tim peserta yang terdaftar.</p>
            </section>
        @else
            <section class="dashboard-card admin-submission-list" aria-label="Daftar submission tim">
                <div class="admin-submission-list-heading">
                    <div>
                        <h2>Daftar tim</h2>
                        <p>Pilih tim berstatus sudah mengirim untuk melihat submission.</p>
                    </div>
                    <span class="section-count">{{ count($participants) }} tim</span>
                </div>

                <div class="admin-submission-rows">
                    @foreach ($participants as $participant)
                        @if ($participant['has_submission'])
                            <a
                                class="admin-submission-row admin-submission-row-link"
                                href="{{ route('admin.submissions.show', ['participant' => $participant['id']]) }}"
                                aria-label="Lihat submission tim {{ $participant['name'] }}"
                            >
                                <span class="admin-submission-team">
                                    <span class="admin-submission-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($participant['name'], 0, 1)) }}</span>
                                    <span>
                                        <strong>{{ $participant['name'] }}</strong>
                                        <small>Buka detail submission <span aria-hidden="true">→</span></small>
                                    </span>
                                </span>
                                <span class="admin-submission-badge admin-submission-badge-submitted">
                                    <span aria-hidden="true"></span> Sudah submission
                                </span>
                            </a>
                        @else
                            <div class="admin-submission-row">
                                <span class="admin-submission-team">
                                    <span class="admin-submission-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($participant['name'], 0, 1)) }}</span>
                                    <span><strong>{{ $participant['name'] }}</strong></span>
                                </span>
                                <span class="admin-submission-badge admin-submission-badge-pending">
                                    <span aria-hidden="true"></span> Belum submission
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </main>
@endsection
