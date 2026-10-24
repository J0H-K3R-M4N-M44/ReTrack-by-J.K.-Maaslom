<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RepairTrack keeps computer repair check-ins, service updates, and store teams in one clear place.">
    <meta name="theme-color" content="#111b22">
    <title>RepairTrack | Clear updates for every repair</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="landing-body">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <a class="site-brand" href="#top" aria-label="RepairTrack home">
            <span class="brand-mark"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
            <span>RepairTrack</span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
            <span class="sr-only">Open navigation</span>
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Main navigation">
            <a href="#how-it-works">How it works</a>
            <a href="#for-everyone">For customers and teams</a>
            <a class="nav-cta" href="admin/login.php">Staff sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </nav>
    </header>

    <div class="scroll-progress" aria-hidden="true"><span></span></div>

    <main id="main">
        <section class="hero" id="top" aria-labelledby="hero-title">
            <video class="hero-video" autoplay muted loop playsinline preload="metadata" poster="assets/admin-repair-illustration.jpg" aria-hidden="true">
                <source src="assets/login-bg.mp4" type="video/mp4">
            </video>
            <div class="hero-shade" aria-hidden="true"></div>
            <div class="hero-content">
                <p class="eyebrow"><span></span> One repair, one clear record</p>
                <h1 id="hero-title">Know where every repair stands.</h1>
                <p class="hero-lede">A calmer check-in for customers. A clearer work queue for the people who repair their devices.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="#how-it-works">See how it works <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
                    <a class="button button-quiet" href="admin/login.php">Open staff workspace</a>
                </div>
                <div class="hero-meta"><span class="live-dot"></span> Built for repair teams with one shop or many</div>
            </div>
            <a class="scroll-cue" href="#how-it-works" aria-label="Scroll to how it works"><span></span></a>
        </section>

        <section class="section process-section" id="how-it-works" aria-labelledby="process-title">
            <div class="section-heading reveal">
                <p class="eyebrow">A simple service path</p>
                <h2 id="process-title">From check-in to pickup, the next step stays clear.</h2>
                <p>Each handoff keeps the customer, device, issue, and repair status together.</p>
            </div>
            <div class="process-grid">
                <article class="process-card reveal">
                    <span class="step-number">01</span>
                    <div class="process-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></div>
                    <h3>Check in</h3>
                    <p>A staff member records the customer, device, reported problem, and visible condition.</p>
                </article>
                <article class="process-card reveal">
                    <span class="step-number">02</span>
                    <div class="process-icon"><i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i></div>
                    <h3>Review and diagnose</h3>
                    <p>The repair team reviews the intake and records what they find before work begins.</p>
                </article>
                <article class="process-card reveal">
                    <span class="step-number">03</span>
                    <div class="process-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></div>
                    <h3>Share an update</h3>
                    <p>Customers get a plain-language update when the repair reaches an important stage.</p>
                </article>
                <article class="process-card reveal">
                    <span class="step-number">04</span>
                    <div class="process-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
                    <h3>Hand it back</h3>
                    <p>Staff record the final test and pickup so the repair closes with a complete history.</p>
                </article>
            </div>
        </section>

        <section class="section audience-section" id="for-everyone" aria-labelledby="audience-title">
            <div class="section-heading reveal">
                <p class="eyebrow">One shared record</p>
                <h2 id="audience-title">A useful view for every person in the repair.</h2>
            </div>
            <div class="audience-grid">
                <article class="audience-card customer-card reveal">
                    <div class="audience-heading"><span class="audience-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>For customers</span></div>
                    <h3>Know what to expect.</h3>
                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Bring the device and any related accessories.</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Describe what happened in your own words.</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Keep the repair reference given at check-in.</li>
                    </ul>
                    <a class="text-link" href="#service-flow">View the check-in steps <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
                <article class="audience-card team-card reveal">
                    <div class="audience-heading"><span class="audience-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span><span>For the service team</span></div>
                    <h3>Keep handoffs in one place.</h3>
                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Counter staff capture a consistent intake.</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Technicians get a focused repair queue.</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Managers can see branch activity at a glance.</li>
                    </ul>
                    <a class="text-link" href="admin/login.php">Go to staff sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </section>

        <section class="section service-flow" id="service-flow" aria-labelledby="service-title">
            <div class="service-copy reveal">
                <p class="eyebrow">A better check-in</p>
                <h2 id="service-title">Start with the details that help the repair.</h2>
                <p>At the counter, an officer can find an existing customer or add a new one, record the device and reported issue, then save a draft for review. Extra checks stay with the staff issue process instead of slowing down the first conversation.</p>
                <a class="button button-primary" href="admin/login.php">Staff: start an intake <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="service-preview reveal" aria-label="Intake flow preview">
                <div class="preview-top"><span><i class="fa-solid fa-circle"></i><i class="fa-solid fa-circle"></i><i class="fa-solid fa-circle"></i></span><small>Repair intake</small></div>
                <div class="preview-progress"><span class="is-done">1 <small>Customer</small></span><i></i><span>2 <small>Device</small></span><i></i><span>3 <small>Review</small></span></div>
                <div class="preview-line preview-line-strong"></div>
                <div class="preview-line"></div>
                <div class="preview-fields"><span></span><span></span></div>
                <div class="preview-line preview-line-short"></div>
                <div class="preview-button">Save draft</div>
            </div>
        </section>

        <section class="closing-cta reveal" aria-labelledby="closing-title">
            <div>
                <p class="eyebrow">Repair work, made easier to follow</p>
                <h2 id="closing-title">Ready to open the workbench?</h2>
            </div>
            <a class="button button-primary" href="admin/login.php">Sign in to RepairTrack <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </section>
    </main>

    <footer class="site-footer">
        <a class="site-brand" href="#top"><span class="brand-mark"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span><span>RepairTrack</span></a>
        <p>Computer repair tracking for people and service teams.</p>
        <a href="#top">Back to top <i class="fa-solid fa-arrow-up" aria-hidden="true"></i></a>
    </footer>
    <script src="js/landing.js"></script>
</body>
</html>
