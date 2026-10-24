<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>

    <link rel="stylesheet" href="../css/login.css">
</head>

<body class="page-fade">
    <video class="bg-video" autoplay muted loop playsinline>
        <source src="../assets/login-bg.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <div class="login-scene">
        <div class="login-shell">
            <div class="brand-wrap">
                <div class="brand-tag">Account Recovery</div>
                <h1 class="brand-title">RepairTrack</h1>
            </div>

            <div class="login-content">
                <div class="login-panel reset-panel">
                    <h2>Reset Password</h2>
                    <p class="reset-copy">
                        Password reset is not automated in this local demo. Contact the administrator to reset your account.
                    </p>

                    <div class="reset-box">
                        <p>For account safety, password resets are handled by the administrator.</p>
                    </div>

                    <form action="login.php" method="GET">
                        <button type="submit" class="login-btn">Back to Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/trans.js"></script>
</body>

</html>