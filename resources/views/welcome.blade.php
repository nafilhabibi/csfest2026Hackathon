@extends('layouts.app')

@section('title', 'Hackathon untuk kampus yang lebih baik')

@section('content')
    <div class="announcement">
        <span class="announcement-tag">CSFEST2026</span>
        <span>Hackathon untuk masa depan kehidupan kampus yang lebih baik.</span>
    </div>

    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="CSFEST2026 Beranda">
            <img class="brand-logo" src="{{ asset('images/csfest2026-logo.png') }}" alt="">
        </a>
        <nav class="nav-links" aria-label="Navigasi utama">
            <a href="#tentang">Tentang</a>
            <a href="#tema">Tema</a>
            <button class="button button-outline button-small theme-toggle" type="button" data-theme-toggle aria-pressed="false">
                <span aria-hidden="true" data-theme-icon>☾</span>
                <span data-theme-label>Mode malam</span>
            </button>
            <a class="button button-outline button-small" href="{{ route('login') }}">Masuk</a>
        </nav>
    </header>

    <main class="landing-page">
        <section class="hero" id="tentang">
            <div class="hero-copy">
                <h1>Build a better campus, <em>together.</em></h1>
                <p class="hero-description" id="tema">
                    Innovating Integrated Digital Solutions for Better Campus Life.
                    Satukan ide, teknologi, dan kolaborasi untuk menjawab tantangan nyata di kampus.
                </p>
                <div class="hero-actions">
                    <a class="button" href="{{ route('login') }}">Masuk ke portal <span aria-hidden="true">→</span></a>
                    <a class="button button-outline" href="#tentang-event">Kenali CSFEST2026</a>
                </div>
                <p class="hero-footnote">Satu hackathon. Banyak kemungkinan untuk kampus.</p>
            </div>

            <div class="hero-art" aria-label="Ilustrasi ekosistem solusi digital kampus" role="img">
                <div class="orbit"></div>
                <div class="campus-stack">
                    <div class="stack-layer layer-top">
                        <span>SMART CAMPUS</span>
                        <small>IDEAS IN MOTION</small>
                    </div>
                    <div class="stack-layer layer-tools">
                        <span>⌘ &nbsp; CONNECTED SOLUTIONS</span>
                        <small>01 / 03</small>
                    </div>
                    <div class="stack-layer layer-main">
                        <small>CSFEST · HACKATHON 2026</small>
                        <strong>Campus, connected.</strong>
                    </div>
                    <div class="stack-layer layer-campus">
                        <span>BETTER CAMPUS LIFE</span>
                        <small>BUILD WHAT MATTERS</small>
                    </div>
                </div>
                <div class="float-note note-one">
                    <span class="note-icon" aria-hidden="true">✦</span>
                    <span><b>Ideas first</b> <span>Berawal dari kebutuhan</span></span>
                </div>
                <div class="float-note note-two">
                    <span class="note-icon" aria-hidden="true">↗</span>
                    <span><b>Built together</b> <span>Dampak lewat kolaborasi</span></span>
                </div>
            </div>
        </section>

        <section class="info-strip" id="tentang-event" aria-label="Tentang event">
            <div class="info-item">
                <span class="info-symbol" aria-hidden="true">⌘</span>
                <span><strong>Solusi terintegrasi</strong><span>Teknologi yang saling terhubung</span></span>
            </div>
            <div class="info-item">
                <span class="info-symbol" aria-hidden="true">✳</span>
                <span><strong>Berpusat pada mahasiswa</strong><span>Mulai dari tantangan sehari-hari</span></span>
            </div>
            <div class="info-item">
                <span class="info-symbol" aria-hidden="true">↗</span>
                <span><strong>Untuk kampus lebih baik</strong><span>Ide yang dirancang untuk berdampak</span></span>
            </div>
        </section>
    </main>

    <footer class="page-footer">
        <span>© 2026 CSFEST · Computer Science Fest</span>
        <span>Innovating campus life, one idea at a time.</span>
    </footer>
@endsection
