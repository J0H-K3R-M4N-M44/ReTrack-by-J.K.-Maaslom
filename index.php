<!DOCTYPE html>
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
            <span aria-hidden="true">☰</span>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Main navigation">
            <a href="track.php">Track a repair</a>
            <a href="#how-it-works">How it works</a>
            <a href="#prepare">Before your visit</a>
            <a href="#questions">Questions</a>
            <a href="learn.php">Customer guide</a>
            <a class="nav-cta" href="admin/login.php">Staff sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </nav>
    </header>

    <div class="scroll-progress" aria-hidden="true"><span></span></div>

    <main id="main">
        <section class="hero" id="top" aria-labelledby="hero-title">
            <video class="hero-video" muted loop playsinline preload="none" poster="assets/admin-repair-illustration.jpg" aria-hidden="true">
                <source data-src="assets/login-bg.mp4" type="video/mp4">
            </video>
            <div class="hero-shade" aria-hidden="true"></div>
            <div class="hero-content">
                <p class="eyebrow"><span></span> One repair, one clear record</p>
                <h1 id="hero-title">Know where every repair stands.</h1>
                <p class="hero-lede">Follow your device from check-in to collection. View the latest repair stage, confirmed custody and updates from your service team.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="track.php">Track your repair <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    <a class="button button-quiet" href="#prepare">Before your visit</a>
                </div>
                <div class="hero-meta"><span class="live-dot"></span> Built for repair teams with one shop or many</div>
            </div>
            <a class="scroll-cue" href="#how-it-works" aria-label="Scroll to how it works"><span></span></a>
        </section>

        <section class="section" id="services" aria-labelledby="services-title">
            <div class="section-heading"><p class="eyebrow">Help for your everyday devices</p><h2 id="services-title">Start with the problem. We help with the next step.</h2><p>Bring your device for assessment. Availability, parts and final costs are confirmed by your branch.</p></div>
            <div class="process-grid">
                <article class="process-card"><h3>Laptops and desktops</h3><p>Power problems, slow performance, startup failures and overheating.</p></article>
                <article class="process-card"><h3>Device diagnostics</h3><p>Describe the symptoms and let the technician inspect the cause.</p></article>
                <article class="process-card"><h3>Parts and upgrades</h3><p>Ask about compatible memory, storage and replacement components.</p></article>
                <article class="process-card"><h3>Repair updates</h3><p>Use your tracking code and phone PIN to check progress and messages from the service team.</p><a href="track.php">Open tracking →</a></article>
            </div>
        </section>
        <section class="section process-section" id="how-it-works" aria-labelledby="process-title">
            <div class="section-heading reveal">
                <p class="eyebrow">A simple service path</p>
                <h2 id="process-title">A clear record starts at the counter.</h2>
                <p>The current intake workflow keeps the customer, device, condition and branch location together.</p>
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
                    <h3>Review the details</h3>
                    <p>Review the reported problem, device identifiers and visible condition together before the draft is saved.</p>
                </article>
                <article class="process-card reveal">
                    <span class="step-number">03</span>
                    <div class="process-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></div>
                    <h3>Complete intake checks</h3>
                    <p>An administrator records accessories, a condition photo, signed intake paperwork and where the device is stored.</p>
                </article>
                <article class="process-card reveal">
                    <span class="step-number">04</span>
                    <div class="process-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
                    <h3>Keep your reference</h3>
                    <p>An issued intake has a unique reference. Keep your copy and quote it when asking the branch about your device.</p>
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
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Administrators capture and review a consistent intake.</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Staff members use individual accounts and assigned branches.</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Administrators review issued records and manage staff access.</li>
                    </ul>
                    <a class="text-link" href="admin/login.php">Go to staff sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </section>

        <section class="section service-flow" id="service-flow" aria-labelledby="service-title">
            <div class="service-copy reveal">
                <p class="eyebrow">A better check-in</p>
                <h2 id="service-title">Start with the details that help the repair.</h2>
                <p>An administrator can find an existing customer or add a new one, record the device and reported issue, then save a draft. Condition evidence, accessories and signed paperwork are checked before a ticket reference is issued. A draft is not yet a confirmed intake ticket.</p>
                <a class="button button-primary" href="admin/login.php">Admin: start an intake <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
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

        <section class="section preparation-section" id="prepare" aria-labelledby="prepare-title">
            <div class="section-heading reveal"><p class="eyebrow">Before your visit</p><h2 id="prepare-title">A few details make check-in easier.</h2><p>You do not need to diagnose the fault yourself. Describe what you noticed and let the team record it accurately.</p></div>
            <div class="preparation-grid">
                <article class="preparation-card reveal"><span class="step-number">01</span><h3>Bring the device</h3><p>Bring the charger or accessories related to the problem. Review the received-accessory list with the person accepting your device.</p></article>
                <article class="preparation-card reveal"><span class="step-number">02</span><h3>Explain what happened</h3><p>Note when the issue started, error messages and anything that makes it happen again. Mention drops, liquid exposure or recent changes.</p></article>
                <article class="preparation-card reveal"><span class="step-number">03</span><h3>Prepare your information</h3><p>Have one reliable phone number or email ready. If the device is usable, back up important files before handing it over.</p></article>
                <article class="preparation-card reveal"><span class="step-number">04</span><h3>Check the record together</h3><p>Review visible damage, device identifiers, accessories and the branch’s intake terms. Ask about access to your files and any information the technician actually needs.</p></article>
            </div>
        </section>
        <section class="section capability-section" id="available" aria-labelledby="available-title">
            <div class="section-heading reveal"><p class="eyebrow">What this version supports</p><h2 id="available-title">Clear expectations, from the start.</h2></div>
            <div class="capability-grid">
                <article class="capability-card reveal"><h3>Available now</h3><ul><li>Private customer tracking with repair status, custody and published updates.</li><li>Authorized staff intake, quick menus, review and draft editing.</li><li>Issued intake references and printable records.</li><li>Private intake evidence and recorded storage locations.</li><li>Staff account creation, activation and branch assignments.</li><li>Role-specific queues, technician assignments and repair-stage updates.</li><li>Searchable workbench, customer summaries and status totals.</li></ul></article>
                <article class="capability-card reveal"><h3>Still being built</h3><ul><li>QR scanning and customer requests.</li><li>Automated SMS/email repair alerts and customer chat.</li><li>Quotes, cashier/COD records and inventory actions.</li></ul><p>Use private tracking for saved repair updates. Contact the branch for appointments, estimates, collection and payment questions.</p></article>
            </div>
        </section>
        <section class="section faq-section" id="questions" aria-labelledby="questions-title">
            <div class="section-heading reveal"><p class="eyebrow">Common questions</p><h2 id="questions-title">Know what happens next.</h2></div>
            <div class="faq-list">
                <details><summary>Do I need an account to bring in a device?</summary><p>No customer account is needed for the current counter-intake workflow. An administrator records your details and links your device to the intake.</p></details>
                <details><summary>Can I track a repair online yet?</summary><p>Yes. Ask your branch for a private tracking code, then choose Track your repair and enter the last four digits of your recorded phone number as your PIN. Your receipt number alone does not grant access. Tracking shows saved staff updates; it does not follow a courier by GPS.</p></details>
                <details><summary>What if I cannot find the serial number?</summary><p>Tell the person accepting the device. They can record other identifying marks or specifications before issuing the intake. Do not guess a serial number.</p></details>
                <details><summary>Is an intake reference a repair quote or payment receipt?</summary><p>No. It identifies the accepted device and intake record. Ask the branch about assessment, pricing, payment arrangements and approval before repair work. This version does not collect online payments.</p></details>
                <details><summary>How long will the repair take?</summary><p>The branch needs to assess the fault, parts availability and workload before giving an estimate. The website does not promise a fixed turnaround time.</p></details>
                <details><summary>How do new staff get access?</summary><p>An administrator creates the account and privately shares a one-use activation link. The staff member chooses their own password. A staff role does not automatically grant administrator access.</p></details>
                <details><summary>Where can I find branch hours and contact details?</summary><p>Confirm these directly with the branch accepting your device. Verified public branch listings and opening hours have not been published here yet.</p></details>
            </div>
        </section>

        <section class="closing-cta reveal" aria-labelledby="closing-title">
            <div>
                <p class="eyebrow">Repair work, made easier to follow</p>
                <h2 id="closing-title">Ready to check your repair?</h2>
            </div>
            <a class="button button-primary" href="track.php">Track your repair <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
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

