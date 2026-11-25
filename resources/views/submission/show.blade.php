@extends('layouts.app')

@section('title', 'Submission')

@section('content')
    <header class="site-header dashboard-site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CSFEST2026 Dashboard">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="Navigasi peserta">
            <a class="dashboard-nav-link" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="dashboard-nav-link dashboard-nav-link-active" href="{{ route('submission.show') }}" aria-current="page">Submission</a>
            <a class="dashboard-nav-link" href="{{ route('submission.assessment.show') }}">Penilaian</a>
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
                <h1 class="dashboard-greeting">Submission tim</h1>
                <p class="dashboard-subtitle">Kirim hasil kerja tim, tautan GitHub, dan prototype kalian.</p>
            </div>
            <span class="dashboard-account-chip"><span aria-hidden="true"></span> Satu submission per tim</span>
        </div>

        @if (session('status'))
            <div class="form-alert form-alert-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($pageError)
            <div class="form-alert" role="alert">{{ $pageError }}</div>
        @endif

        @if ($submissionClosed)
            <div class="form-alert submission-closed-alert" role="status">
                Submission telah ditutup oleh panitia. Kamu masih dapat melihat data yang tersimpan, tetapi tidak dapat mengirim atau memperbaruinya.
            </div>
        @endif

        @if ($errors->any())
            <div class="form-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="submission-layout">
            <section class="dashboard-card submission-form-card">
                <div class="submission-heading">
                    <span class="submission-icon" aria-hidden="true">↗</span>
                    <div>
                        <h2>{{ $submission ? 'Perbarui submission' : 'Kirim submission' }}</h2>
                        <p>Lengkapi detail berikut. File gambar maksimal 5 MB dan video maksimal 30 MB.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('submission.store') }}" enctype="multipart/form-data">
                    @csrf
                    <fieldset class="submission-form-fields" @disabled($submissionClosed || $pageError)>
                    <div class="form-field">
                        <label for="submitter_name">Nama <span>(orang yang melakukan submit)</span></label>
                        <input
                            id="submitter_name"
                            name="submitter_name"
                            type="text"
                            value="{{ old('submitter_name', $submission['submitter_name'] ?? $user['name'] ?? '') }}"
                            maxlength="255"
                            autocomplete="name"
                            required
                        >
                        @error('submitter_name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="github_link">GitHub Link</label>
                        <input
                            id="github_link"
                            name="github_link"
                            type="url"
                            value="{{ old('github_link', $submission['github_link'] ?? '') }}"
                            placeholder="https://github.com/tim/repository"
                            maxlength="2048"
                            inputmode="url"
                            required
                        >
                        @error('github_link')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="prototype">Media Upload <span>(prototype)</span></label>
                        <input
                            class="submission-file-input"
                            id="prototype"
                            name="prototype"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
                            aria-describedby="prototype-help prototype-status"
                            @required(! $submission)
                        >
                        <label class="submission-file-picker" for="prototype">
                            <span class="submission-file-icon" aria-hidden="true">＋</span>
                            <span>
                                <strong>{{ $submission ? 'Pilih file baru untuk mengganti prototype' : 'Pilih gambar atau video prototype' }}</strong>
                                <small>JPG, PNG, WEBP, GIF · MP4, MOV, WEBM</small>
                            </span>
                        </label>
                        <p class="submission-file-help" id="prototype-help">Pilih satu file. Nama dan pratinjau gambar akan muncul setelah file dipilih.</p>
                        <div class="submission-file-preview" data-prototype-preview hidden>
                            <img data-prototype-preview-image alt="" hidden>
                            <div>
                                <strong data-prototype-file-name></strong>
                                <small data-prototype-file-size></small>
                            </div>
                        </div>
                        <p
                            class="submission-file-status"
                            id="prototype-status"
                            data-prototype-status
                            data-empty-message="{{ $submission ? 'Prototype tersimpan tetap digunakan jika tidak memilih file pengganti.' : 'Belum ada file dipilih.' }}"
                            role="status"
                            aria-live="polite"
                        >
                            {{ $submission ? 'Prototype tersimpan tetap digunakan jika tidak memilih file pengganti.' : 'Belum ada file dipilih.' }}
                        </p>
                        @error('prototype')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        @if ($submission && $prototypeUrl)
                            <p class="submission-current-file">
                                File tersimpan:
                                <a href="{{ $prototypeUrl }}" target="_blank" rel="noopener noreferrer">Lihat prototype saat ini</a>
                            </p>
                        @endif
                    </div>

                    <div class="form-field">
                        <label for="another">Another <span>(opsional)</span></label>
                        <textarea
                            id="another"
                            name="another"
                            rows="5"
                            maxlength="5000"
                            placeholder="Tambahkan catatan untuk submission tim..."
                        >{{ old('another', $submission['additional_text'] ?? '') }}</textarea>
                        @error('another')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button auth-submit" type="submit">
                        <span data-submission-submit-label>{{ $submission ? 'Simpan perubahan' : 'Kirim submission' }}</span>
                        <span aria-hidden="true">→</span>
                    </button>
                    </fieldset>
                </form>
            </section>

            <aside class="submission-side">
                <section class="dashboard-card submission-guidance">
                    <span class="submission-side-label">Sebelum mengirim</span>
                    <h2>Pastikan semuanya siap.</h2>
                    <ul>
                        <li>Tautan GitHub bisa dibuka panitia.</li>
                        <li>Prototype menunjukkan alur utama solusi tim.</li>
                        <li>File gambar maksimal 5 MB, video maksimal 30 MB.</li>
                    </ul>
                </section>

                @if ($submission)
                    <section class="dashboard-card submission-status-card">
                        <span class="submission-side-label">Status submission</span>
                        <span class="submission-status-chip"><span aria-hidden="true"></span> Tersimpan</span>
                        <p>Submission dapat diperbarui dengan mengirim perubahan melalui formulir ini.</p>
                    </section>
                @endif
            </aside>
        </div>
    </main>
@endsection
