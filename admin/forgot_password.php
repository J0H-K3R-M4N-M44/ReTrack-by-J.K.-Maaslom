<?php
// ========================================
// PASSWORD RECOVERY PAGE (DEMO)
// ========================================

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="../css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body>
    <!-- ========================================
         BACKGROUND & OVERLAY
         ======================================== -->
    
    <video class="bg-video" autoplay muted loop playsinline>
        <source src="../assets/login-bg.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <!-- ========================================
         RECOVERY CARD
         ======================================== -->
    
    <div class="login-scene">
        <div class="login-shell">
            <!-- Brand Header -->
            <div class="brand-wrap">
                <div class="brand-tag">Account Recovery</div>\n                <h1 class="brand-title\">RepairTrack</h1>\n            </div>\n\n            <!-- Recovery Content -->
            <div class="login-content">
                <div class="login-panel reset-panel">
                    <h2>Reset Password</h2>
                    <p class="reset-copy\">
                        This is a demo admin account. Use the default credentials below to login.
                    </p>

                    <!-- Demo Credentials -->
                    <div class="reset-box">
                        <p><strong>Username:</strong> admin</p>
                        <p><strong>Password:</strong> 123</p>
                    </div>

                    <!-- Return to Login -->
                    <form id="reset-form" action="login.php" method="GET">
                        <button type="submit" class="login-btn">Back to Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================
         SCRIPTS
         ======================================== -->
    
    <script src="../js/login.js"></script>
</body>
</html>
