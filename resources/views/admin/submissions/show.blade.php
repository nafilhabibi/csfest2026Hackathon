@extends('layouts.app')

@section('title', 'Detail submission')

@section('content')
    @php
        $isAdmin = session('app_user.role') === 'admin';
        $userName = session('app_user.name', 'Pengguna');
    @endphp

    <header class="site-header dashboard-site-header portal-site-header">
        <a class="brand" href="{{ $isAdmin ? route('dashboard') : route('admin.submissions.index') }}" aria-label="CSFEST2026">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="{{ $isAdmin ? 'Navigasi admin' : 'Navigasi juri' }}">
            @if ($isAdmin)
                <a class="dashboard-nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="dashboard-nav-link" href="{{ route('admin.participants.create') }}">Pusat pengelolaan</a>
            @endif
            <a class="dashboard-nav-link dashboard-nav-link-active" href="{{ route('admin.submissions.index') }}" aria-current="page">Submission</a>
        </nav>
        <div class="dashboard-nav-actions">
            <span class="dashboard-nav-user">
                <span class="dashboard-nav-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($userName, 0, 1)) }}</span>
                <span>{{ $userName }}</span>
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
                <p class="eyebrow"><span class="eyebrow-mark"></span> Detail submission</p>
                <h1 class="dashboard-greeting">{{ $team['name'] ?? 'Submission tim' }}</h1>
                <p class="dashboard-subtitle">Informasi dan prototype yang dikirimkan oleh tim.</p>
            </div>
            <a class="button button-outline button-small" href="{{ route('admin.submissions.index') }}">← Kembali ke daftar tim</a>
        </div>

        @if ($teamError)
            <div class="form-alert" role="alert">{{ $teamError }}</div>
        @endif

        @if ($mediaError)
            <div class="form-alert" role="alert">{{ $mediaError }}</div>
        @endif

        @if (is_array($team['theme'] ?? null) && filled($team['theme']['title'] ?? null))
            <p class="admin-submission-theme">Tema tim: <strong>{{ $team['theme']['title'] }}</strong></p>
        @endif

        @if (session('app_user.role') === 'judge')
            <section class="dashboard-card admin-submission-detail-card judge-assessment-card">
                <div class="admin-submission-list-heading">
                    <div>
                        <span class="admin-submission-detail-label">Penilaian juri</span>
                        <h2>{{ $assessment ? 'Perbarui penilaian' : 'Beri penilaian' }}</h2>
                        <p>Nilai setiap aspek dari 1 sampai 5. Semua aspek memiliki bobot yang sama.</p>
                    </div>
                    @if ($assessment)
                        <span class="admin-submission-badge admin-submission-badge-submitted">
                            <span aria-hidden="true"></span> Total {{ $assessment['total'] }} / 25
                        </span>
                    @endif
                </div>

                @if (session('status'))
                    <div class="form-alert form-alert-success" role="status">{{ session('status') }}</div>
                @endif

                @if ($assessmentError)
                    <div class="form-alert" role="alert">{{ $assessmentError }}</div>
                @endif

                @if ($errors->has('assessment'))
                    <div class="form-alert" role="alert">{{ $errors->first('assessment') }}</div>
                @endif

                <p class="judge-score-guide">
                    @foreach (\App\Services\GoogleSheetsAssessmentClient::SCORE_LABELS as $score => $scoreLabel)
                        @if (! $loop->first) · @endif
                        {{ $score }} — {{ $scoreLabel }}
                    @endforeach
                </p>

                <form method="POST" action="{{ route('admin.submissions.assessment.store', $submission->participant_id) }}">
                    @csrf

                    @foreach (\App\Services\GoogleSheetsAssessmentClient::CRITERIA as $criterion => $label)
                        <div class="form-field">
                            <label for="score_{{ $criterion }}">{{ $label }}</label>
                            <select id="score_{{ $criterion }}" name="scores[{{ $criterion }}]" required>
                                <option value="">Pilih nilai</option>
                                @foreach (\App\Services\GoogleSheetsAssessmentClient::SCORE_LABELS as $score => $scoreLabel)
                                    <option
                                        value="{{ $score }}"
                                        @selected((string) old('scores.'.$criterion, $assessment['scores'][$criterion] ?? '') === (string) $score)
                                    >
                                        {{ $score }} — {{ $scoreLabel }}
                                    </option>
                                @endforeach
                            </select>
                            @error('scores.'.$criterion)
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="form-field">
                        <label for="assessment_comment">Komentar (opsional)</label>
                        <textarea id="assessment_comment" name="comment" rows="4" maxlength="5000">{{ old('comment', $assessment['comment'] ?? '') }}</textarea>
                        @error('comment')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button auth-submit" type="submit">
                        {{ $assessment ? 'Simpan perubahan nilai' : 'Simpan penilaian' }} <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>
        @endif

        <div class="admin-submission-detail-grid">
            <section class="dashboard-card admin-submission-detail-card">
                <div class="admin-submission-list-heading">
                    <div>
                        <span class="admin-submission-detail-label">Informasi submission</span>
                        <h2>{{ $submission->submitter_name }}</h2>
                    </div>
                    <span class="admin-submission-badge admin-submission-badge-submitted">
                        <span aria-hidden="true"></span> Sudah submission
                    </span>
                </div>

                <dl class="admin-submission-fields">
                    <div>
                        <dt>GitHub repository</dt>
                        <dd>
                            @php
                                $githubHost = parse_url($submission->github_link, PHP_URL_HOST);
                                $githubScheme = parse_url($submission->github_link, PHP_URL_SCHEME);
                                $githubUrlIsSafe = in_array($githubHost, ['github.com', 'www.github.com'], true)
                                    && in_array($githubScheme, ['http', 'https'], true);
                            @endphp
                            @if ($githubUrlIsSafe)
                                <a href="{{ $submission->github_link }}" target="_blank" rel="noopener noreferrer">{{ $submission->github_link }}</a>
                            @else
                                {{ $submission->github_link }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Catatan dari tim</dt>
                        <dd class="admin-submission-notes">{{ filled($submission->additional_text) ? $submission->additional_text : 'Tidak ada catatan tambahan.' }}</dd>
                    </div>
                    <div>
                        <dt>Dikirim pada</dt>
                        <dd>{{ $submission->created_at }}</dd>
                    </div>
                </dl>
            </section>

            <section class="dashboard-card admin-submission-detail-card">
                <span class="admin-submission-detail-label">Prototype tim</span>
                <h2>Media submission</h2>
                @if ($prototypeUrl)
                    @if (str_starts_with($submission->prototype_mime_type, 'image/'))
                        <a class="admin-submission-media-link" href="{{ $prototypeUrl }}" target="_blank" rel="noopener noreferrer">
                            <img class="admin-submission-image" src="{{ $prototypeUrl }}" alt="Prototype yang dikirim oleh {{ $team['name'] ?? 'tim' }}">
                            <span>Buka gambar ukuran penuh ↗</span>
                        </a>
                    @elseif (str_starts_with($submission->prototype_mime_type, 'video/'))
                        <video class="admin-submission-video" controls playsinline preload="metadata" src="{{ $prototypeUrl }}">
                            Browser tidak mendukung pemutaran video.
                        </video>
                        <a class="admin-submission-media-link" href="{{ $prototypeUrl }}" target="_blank" rel="noopener noreferrer">
                            Buka video di tab baru
                        </a>
                    @else
                        <a class="button button-small" href="{{ $prototypeUrl }}" target="_blank" rel="noopener noreferrer">Buka file prototype ↗</a>
                    @endif
                @else
                    <p class="admin-submission-empty">File prototype belum tersedia.</p>
                @endif
            </section>
        </div>
    </main>
@endsection
