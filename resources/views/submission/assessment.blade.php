@extends('layouts.app')

@section('title', 'Penilaian Tim')

@section('content')
    <header class="site-header dashboard-site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="Navigasi peserta">
            <a class="dashboard-nav-link" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="dashboard-nav-link" href="{{ route('submission.show') }}">Submission</a>
            <a class="dashboard-nav-link dashboard-nav-link-active" href="{{ route('submission.assessment.show') }}" aria-current="page">Penilaian</a>
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

    <main class="dashboard-page submission-page">
        <div class="dashboard-top">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark"></span> CSFEST2026 · Hackathon</p>
                <h1 class="dashboard-greeting">Penilaian tim</h1>
                <p class="dashboard-subtitle">Hasil penilaian juri untuk submission timmu.</p>
            </div>
            <span class="dashboard-account-chip"><span aria-hidden="true"></span> Hasil penilaian</span>
        </div>

        @if ($assessmentError)
            <div class="form-alert" role="alert">{{ $assessmentError }}</div>
        @elseif ($assessments === [])
            <section class="dashboard-card participant-assessment-empty">
                <h2>Belum ada penilaian</h2>
                <p>Hasil penilaian akan muncul di halaman ini setelah juri memberikan nilai untuk submission timmu.</p>
            </section>
        @else
            <section class="dashboard-card participant-assessment-card">
                <div class="submission-heading">
                    <span class="submission-icon" aria-hidden="true">✓</span>
                    <div>
                        <h2>Hasil dari para juri</h2>
                        <p>Setiap baris berisi penilaian dari satu juri. Tiap aspek memiliki bobot yang sama.</p>
                    </div>
                </div>

                <div class="participant-assessment-table-wrap" role="region" aria-label="Tabel penilaian juri" tabindex="0">
                    <table class="participant-assessment-table">
                        <caption>Nilai diberikan pada skala 1–5. Total adalah jumlah lima aspek, maksimum 25.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Juri</th>
                                @foreach (\App\Services\GoogleSheetsAssessmentClient::CRITERIA as $label)
                                    <th scope="col">{{ $label }}</th>
                                @endforeach
                                <th scope="col">Total</th>
                                <th scope="col">Komentar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assessments as $assessment)
                                <tr>
                                    <th scope="row">{{ $assessment['judge_name'] }}</th>
                                    @foreach (\App\Services\GoogleSheetsAssessmentClient::CRITERIA as $criterion => $label)
                                        <td>{{ $assessment['scores'][$criterion] }}</td>
                                    @endforeach
                                    <td class="participant-assessment-total">{{ $assessment['total'] }} / 25</td>
                                    <td>{{ filled($assessment['comment']) ? $assessment['comment'] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
@endsection
