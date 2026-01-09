<?php
/**
 * FDTV - Marketing Landing Page
 * Beautiful, Interactive, Modern Landing Experience
 */
require_once 'config/database.php';
require_once 'config/settings.php';

// Get some stats for the landing page
try {
    $stats = [
        'stations' => $conn->query("SELECT COUNT(*) FROM stations WHERE status = 'active'")->fetchColumn() ?: 0,
        'videos' => $conn->query("SELECT COUNT(*) FROM videos")->fetchColumn() ?: 0,
        'radio' => $conn->query("SELECT COUNT(*) FROM radio_stations WHERE status = 'active'")->fetchColumn() ?: 0
    ];
} catch (Exception $e) {
    $stats = ['stations' => 25, 'videos' => 500, 'radio' => 15];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Launch your own TV or Radio station with FDTV. Stream video and audio content 24/7, manage your schedule, and reach your audience worldwide.">
    <meta name="keywords" content="TV station, radio station, streaming, broadcast, video platform, audio streaming, IPTV, internet radio">
    <meta name="theme-color" content="#6366f1">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FDTV">
    <link rel="apple-touch-icon" href="/assets/images/icons/icon-192.png">

    <!-- Open Graph -->
    <meta property="og:title" content="FDTV - Your Own TV & Radio Station">
    <meta property="og:description" content="Launch and manage your own 24/7 TV or Radio station with ease">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">

    <title>FDTV - Launch Your Own TV & Radio Station | 24/7 Streaming Platform</title>

    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="/" class="nav-logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                        <polygon points="10,8 10,12 14,10" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <span class="logo-text">FDTV</span>
            </a>

            <div class="nav-links" id="navLinks">
                <a href="#features" class="nav-link">Features</a>
                <a href="#how-it-works" class="nav-link">How It Works</a>
                <a href="#showcase" class="nav-link">Showcase</a>
                <a href="#pricing" class="nav-link">Pricing</a>
                <a href="auth/login.php" class="nav-link">Login</a>
                <a href="auth/register.php" class="btn btn-primary btn-glow">Get Started</a>
            </div>

            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="#features" class="mobile-menu-link">Features</a>
        <a href="#how-it-works" class="mobile-menu-link">How It Works</a>
        <a href="#showcase" class="mobile-menu-link">Showcase</a>
        <a href="#pricing" class="mobile-menu-link">Pricing</a>
        <a href="auth/login.php" class="mobile-menu-link">Login</a>
        <a href="auth/register.php" class="btn btn-primary btn-block">Get Started Free</a>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-bg">
            <!-- Video Background -->
            <video class="hero-video-bg" autoplay muted loop playsinline>
                <source src="assets/videos/hero-bg.mp4" type="video/mp4">
                <!-- Fallback to animated background if video fails -->
            </video>
            <div class="hero-video-overlay"></div>

            <div class="floating-orb orb-1"></div>
            <div class="floating-orb orb-2"></div>
            <div class="floating-orb orb-3"></div>
            <div class="grid-pattern"></div>
        </div>

        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="badge-dot"></span>
                    Trusted by <?php echo $stats['stations']; ?>+ Broadcasters
                </div>

                <h1 class="hero-title">
                    Launch Your Own
                    <span class="gradient-text">TV Station</span>
                    in Minutes
                </h1>

                <p class="hero-subtitle">
                    Stream your content 24/7, manage schedules, add radio stations,
                    and reach your audience worldwide. No technical expertise required.
                </p>

                <div class="hero-cta">
                    <a href="auth/register.php" class="btn btn-primary btn-lg btn-glow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                        </svg>
                        Start Broadcasting Free
                    </a>
                    <a href="#how-it-works" class="btn btn-ghost btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/>
                        </svg>
                        Watch Demo
                    </a>
                </div>

                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-value" data-count="<?php echo $stats['stations']; ?>"><?php echo $stats['stations']; ?>+</span>
                        <span class="stat-label">Active Stations</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-value" data-count="<?php echo $stats['videos']; ?>"><?php echo $stats['videos']; ?>+</span>
                        <span class="stat-label">Videos Streamed</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-value">99.9%</span>
                        <span class="stat-label">Uptime</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="tv-mockup">
                    <div class="tv-screen">
                        <div class="tv-content">
                            <div class="tv-header">
                                <div class="live-badge">
                                    <span class="live-dot"></span>
                                    LIVE
                                </div>
                                <span class="channel-name">Your Station</span>
                            </div>
                            <div class="tv-video-placeholder">
                                <div class="play-button">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="tv-ticker">
                                <div class="ticker-content">
                                    Breaking News: Your content streaming 24/7 worldwide...
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tv-stand"></div>
                </div>

                <!-- Floating Feature Cards -->
                <div class="floating-card card-schedule">
                    <div class="floating-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="floating-card-text">
                        <span class="floating-card-title">Smart Scheduling</span>
                        <span class="floating-card-subtitle">Automate your broadcasts</span>
                    </div>
                </div>

                <div class="floating-card card-analytics">
                    <div class="floating-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <div class="floating-card-text">
                        <span class="floating-card-title">Live Analytics</span>
                        <span class="floating-card-subtitle">Track your viewers</span>
                    </div>
                </div>

                <div class="floating-card card-radio">
                    <div class="floating-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="2"/>
                            <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>
                        </svg>
                    </div>
                    <div class="floating-card-text">
                        <span class="floating-card-title">Internet Radio</span>
                        <span class="floating-card-subtitle">Audio streaming too</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-wave">
            <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
                <path d="M0,64 C480,150 960,-20 1440,64 L1440,120 L0,120 Z" fill="currentColor"/>
            </svg>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header reveal">
                <span class="section-badge">Features</span>
                <h2 class="section-title">Everything You Need to <span class="gradient-text">Broadcast</span></h2>
                <p class="section-subtitle">Powerful tools designed for content creators, businesses, and broadcasters of all sizes.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Your Own TV Station</h3>
                    <p class="feature-description">Get a dedicated TV station with your own custom URL. Brand it your way with custom logos and colors.</p>
                    <div class="feature-tag">Unlimited Viewers</div>
                </div>

                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Easy Upload</h3>
                    <p class="feature-description">Upload up to 20 videos with support for all major formats. Drag, drop, and start streaming.</p>
                    <div class="feature-tag">500MB per video</div>
                </div>

                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Smart Scheduling</h3>
                    <p class="feature-description">Create weekly schedules for automated 24/7 playback. Set it and forget it.</p>
                    <div class="feature-tag">24/7 Automation</div>
                </div>

                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="2"/>
                            <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Internet Radio</h3>
                    <p class="feature-description">Add audio streaming with your own internet radio station. Perfect for music and podcasts.</p>
                    <div class="feature-tag">Audio Streaming</div>
                </div>

                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Real-time Analytics</h3>
                    <p class="feature-description">Track viewers, watch time, and engagement with detailed analytics dashboards.</p>
                    <div class="feature-tag">Live Stats</div>
                </div>

                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">News Ticker</h3>
                    <p class="feature-description">Add scrolling news tickers to your broadcast. Keep your audience informed in real-time.</p>
                    <div class="feature-tag">Live Updates</div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-header reveal">
                <span class="section-badge">How It Works</span>
                <h2 class="section-title">Launch in <span class="gradient-text">3 Simple Steps</span></h2>
                <p class="section-subtitle">Get your TV station up and running in minutes, not months.</p>
            </div>

            <div class="steps-container">
                <div class="step-card reveal">
                    <div class="step-number">01</div>
                    <div class="step-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                    </div>
                    <h3 class="step-title">Create Account</h3>
                    <p class="step-description">Sign up in seconds with your email. Choose your station name and customize your branding.</p>
                </div>

                <div class="step-connector">
                    <svg viewBox="0 0 100 20">
                        <path d="M0,10 Q50,10 100,10" stroke="currentColor" stroke-width="2" stroke-dasharray="5,5" fill="none"/>
                        <polygon points="95,5 100,10 95,15" fill="currentColor"/>
                    </svg>
                </div>

                <div class="step-card reveal">
                    <div class="step-number">02</div>
                    <div class="step-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                    </div>
                    <h3 class="step-title">Upload Content</h3>
                    <p class="step-description">Upload your videos, add jingles, and organize your content library. We handle all the encoding.</p>
                </div>

                <div class="step-connector">
                    <svg viewBox="0 0 100 20">
                        <path d="M0,10 Q50,10 100,10" stroke="currentColor" stroke-width="2" stroke-dasharray="5,5" fill="none"/>
                        <polygon points="95,5 100,10 95,15" fill="currentColor"/>
                    </svg>
                </div>

                <div class="step-card reveal">
                    <div class="step-number">03</div>
                    <div class="step-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                        </svg>
                    </div>
                    <h3 class="step-title">Go Live!</h3>
                    <p class="step-description">Hit publish and your station goes live instantly. Share your unique URL with the world.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Showcase Section -->
    <section class="showcase" id="showcase">
        <div class="container">
            <div class="showcase-content">
                <div class="showcase-text reveal">
                    <span class="section-badge">TV Showcase</span>
                    <h2 class="section-title">Professional <span class="gradient-text">Broadcasting</span> Made Simple</h2>
                    <p class="showcase-description">
                        Our platform gives you everything you need to run a professional TV station.
                        From automated scheduling to live news tickers, we've got you covered.
                    </p>

                    <ul class="showcase-features">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Full HD video streaming quality</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Automatic content rotation</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Custom branding and overlays</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Jingle insertion between programs</span>
                        </li>
                    </ul>

                    <a href="auth/register.php" class="btn btn-primary btn-lg">Start Your TV Station</a>
                </div>

                <div class="showcase-visual reveal">
                    <div class="showcase-player">
                        <div class="player-header">
                            <div class="player-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <span class="player-title">My Station - Live</span>
                        </div>
                        <div class="player-screen">
                            <div class="player-overlay">
                                <div class="station-logo">FDTV</div>
                                <div class="live-indicator">
                                    <span class="live-dot"></span>
                                    LIVE
                                </div>
                            </div>
                            <div class="play-overlay">
                                <div class="play-btn-large">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="player-controls">
                            <div class="control-btn">
                                <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <div class="control-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Radio Showcase -->
    <section class="showcase showcase-alt">
        <div class="container">
            <div class="showcase-content reverse">
                <div class="showcase-visual reveal">
                    <div class="radio-player">
                        <div class="radio-visual">
                            <div class="sound-wave">
                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <div class="radio-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="2"/>
                                    <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>
                                </svg>
                            </div>
                        </div>
                        <div class="now-playing">
                            <span class="np-label">Now Playing</span>
                            <span class="np-title">Your Internet Radio</span>
                            <span class="np-artist">Live Streaming 24/7</span>
                        </div>
                        <div class="radio-controls">
                            <button class="radio-btn"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2"/></svg></button>
                            <button class="radio-btn play"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>
                            <button class="radio-btn"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2"/></svg></button>
                        </div>
                    </div>
                </div>

                <div class="showcase-text reveal">
                    <span class="section-badge">Radio Feature</span>
                    <h2 class="section-title">Internet <span class="gradient-text">Radio</span> Included</h2>
                    <p class="showcase-description">
                        Extend your reach with audio streaming. Our integrated internet radio feature
                        lets you broadcast audio content alongside your TV station.
                    </p>

                    <ul class="showcase-features">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>High-quality audio streaming</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Auto DJ functionality</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Playlist management</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Mobile-friendly player</span>
                        </li>
                    </ul>

                    <a href="auth/register.php" class="btn btn-primary btn-lg">Add Radio to Your Station</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="container">
            <div class="section-header reveal">
                <span class="section-badge">Pricing</span>
                <h2 class="section-title">Simple, <span class="gradient-text">Affordable</span> Pricing</h2>
                <p class="section-subtitle">No hidden fees. No surprises. Just powerful broadcasting tools.</p>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card reveal">
                    <div class="pricing-header">
                        <h3 class="pricing-name">Starter</h3>
                        <div class="pricing-price">
                            <span class="currency">N</span>
                            <span class="amount">40,000</span>
                            <span class="period">/month</span>
                        </div>
                        <p class="pricing-description">Perfect for getting started</p>
                    </div>
                    <ul class="pricing-features">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 1 TV Station</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Up to 20 Videos</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 500MB per video</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Weekly Scheduling</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Basic Analytics</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> News Ticker</li>
                        <li class="disabled"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Internet Radio</li>
                    </ul>
                    <a href="auth/register.php" class="btn btn-outline btn-block">Get Started</a>
                </div>

                <div class="pricing-card featured reveal">
                    <div class="pricing-badge">Most Popular</div>
                    <div class="pricing-header">
                        <h3 class="pricing-name">Professional</h3>
                        <div class="pricing-price">
                            <span class="currency">N</span>
                            <span class="amount">75,000</span>
                            <span class="period">/month</span>
                        </div>
                        <p class="pricing-description">For serious broadcasters</p>
                    </div>
                    <ul class="pricing-features">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 1 TV Station</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Up to 50 Videos</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 1GB per video</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Advanced Scheduling</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Full Analytics</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> News Ticker</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 1 Internet Radio</li>
                    </ul>
                    <a href="auth/register.php" class="btn btn-primary btn-block btn-glow">Get Started</a>
                </div>

                <div class="pricing-card reveal">
                    <div class="pricing-header">
                        <h3 class="pricing-name">Enterprise</h3>
                        <div class="pricing-price">
                            <span class="currency">N</span>
                            <span class="amount">150,000</span>
                            <span class="period">/month</span>
                        </div>
                        <p class="pricing-description">For large organizations</p>
                    </div>
                    <ul class="pricing-features">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 3 TV Stations</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Unlimited Videos</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 2GB per video</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Priority Scheduling</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Advanced Analytics</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Multiple Tickers</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> 3 Internet Radios</li>
                    </ul>
                    <a href="auth/register.php" class="btn btn-outline btn-block">Contact Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <div class="section-header reveal">
                <span class="section-badge">Testimonials</span>
                <h2 class="section-title">Loved by <span class="gradient-text">Broadcasters</span></h2>
                <p class="section-subtitle">See what our customers have to say about FDTV</p>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card reveal">
                    <div class="testimonial-content">
                        <div class="quote-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                            </svg>
                        </div>
                        <p class="testimonial-text">"FDTV transformed our church's outreach. We now broadcast our services 24/7 and reach members who can't attend in person. The setup was incredibly easy!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <span>JO</span>
                        </div>
                        <div class="author-info">
                            <span class="author-name">Pastor James Okonkwo</span>
                            <span class="author-title">Grace Chapel Ministry</span>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card reveal">
                    <div class="testimonial-content">
                        <div class="quote-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                            </svg>
                        </div>
                        <p class="testimonial-text">"As a media company, we needed a reliable platform for our clients. FDTV delivers consistently with 99.9% uptime. The analytics help us show real results."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <span>CA</span>
                        </div>
                        <div class="author-info">
                            <span class="author-name">Chioma Adeyemi</span>
                            <span class="author-title">CEO, MediaPro Nigeria</span>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card reveal">
                    <div class="testimonial-content">
                        <div class="quote-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                            </svg>
                        </div>
                        <p class="testimonial-text">"The internet radio feature was exactly what we needed. Now our community radio station reaches listeners worldwide. The support team is amazing!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <span>EM</span>
                        </div>
                        <div class="author-info">
                            <span class="author-name">Emmanuel Mensah</span>
                            <span class="author-title">Urban Vibes Radio</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-bg">
            <div class="cta-orb orb-1"></div>
            <div class="cta-orb orb-2"></div>
        </div>
        <div class="container">
            <div class="cta-content reveal">
                <h2 class="cta-title">Ready to Start <span class="gradient-text">Broadcasting</span>?</h2>
                <p class="cta-subtitle">Join hundreds of broadcasters who trust FDTV for their streaming needs. Launch your station today.</p>
                <div class="cta-buttons">
                    <a href="auth/register.php" class="btn btn-white btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                        </svg>
                        Get Started Free
                    </a>
                    <a href="#pricing" class="btn btn-ghost-white btn-lg">View Pricing</a>
                </div>
                <p class="cta-note">No credit card required. Cancel anytime.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="/" class="footer-logo">
                        <div class="logo-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                <line x1="8" y1="21" x2="16" y2="21"/>
                                <line x1="12" y1="17" x2="12" y2="21"/>
                                <polygon points="10,8 10,12 14,10" fill="currentColor" stroke="none"/>
                            </svg>
                        </div>
                        <span>FDTV</span>
                    </a>
                    <p class="footer-description">Your complete TV and radio broadcasting platform. Launch, manage, and grow your streaming channels with ease.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="Twitter">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="YouTube">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/>
                                <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="white"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="footer-links">
                    <h4>Product</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#showcase">Showcase</a></li>
                        <li><a href="#">Documentation</a></li>
                    </ul>
                </div>

                <div class="footer-links">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>

                <div class="footer-links">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Cookie Policy</a></li>
                        <li><a href="#">DMCA</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> FDTV. All rights reserved.</p>
                <p>Made with passion in Nigeria</p>
            </div>
        </div>
    </footer>

    <!-- PWA Install Button (Floating) -->
    <button id="floatingInstallBtn" class="floating-install-btn" style="display: none;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Install App
    </button>

    <script src="assets/js/app.js"></script>
    <script>
        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenu = document.getElementById('mobileMenu');

        mobileMenuToggle.addEventListener('click', () => {
            mobileMenuToggle.classList.toggle('active');
            mobileMenu.classList.toggle('open');
        });

        // Close mobile menu on link click
        document.querySelectorAll('.mobile-menu-link').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenuToggle.classList.remove('active');
                mobileMenu.classList.remove('open');
            });
        });

        // Scroll reveal animation
        const revealElements = document.querySelectorAll('.reveal');

        const revealOnScroll = () => {
            revealElements.forEach(el => {
                const elementTop = el.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;

                if (elementTop < windowHeight - 100) {
                    el.classList.add('revealed');
                }
            });
        };

        window.addEventListener('scroll', revealOnScroll);
        revealOnScroll(); // Initial check

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const navHeight = navbar.offsetHeight;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Animated counter for stats
        const animateCounter = (element, target) => {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target + '+';
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current) + '+';
                }
            }, 30);
        };

        // Trigger counter animation when stats are visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counters = entry.target.querySelectorAll('[data-count]');
                    counters.forEach(counter => {
                        const target = parseInt(counter.dataset.count);
                        if (target) animateCounter(counter, target);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        });

        const heroStats = document.querySelector('.hero-stats');
        if (heroStats) statsObserver.observe(heroStats);
    </script>
</body>
</html>
