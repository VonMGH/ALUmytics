<?php
// Alumni Landing Page for Alumytics
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="logo.png">
    <title>Alumni Portal - Alumytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />
    <style>
        :root{
            --primary:#222222; /* neutral dark instead of green */
            --primary-700:#111111;
            --muted:#6c757d;
            --card-bg: rgba(255,255,255,0.95);
            --glass: rgba(255,255,255,0.6);
            --max-width-card: 980px;
            --font: 'Montserrat', sans-serif;
        }

        html,body{height:100%;}
        body {
            font-family: var(--font);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0f1724;
            /* full-width aerial/campus image */
            background-image: linear-gradient(rgba(3,37,65,0.45), rgba(3,37,65,0.45)), url('assets/images/campus.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            color: #222;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .landing-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .landing-card {
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(2,6,23,0.6);
            padding: 0;
            max-width: var(--max-width-card);
            width: 92%;
            display: flex;
            gap: 0;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .landing-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s ease-in-out 1s;
        }
        
        @keyframes shimmer {
            to {
                left: 100%;
            }
        }
    .left-panel{padding:40px 40px;flex:1 1 420px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:12px}
    .logo-wrap{width:100%;display:flex;align-items:center;justify-content:center;margin-bottom:6px}
    .alumni-icon{font-size:48px;color:var(--primary);background:none;-webkit-text-fill-color:initial}
    .logo-img{max-width:140px;height:90px;border-radius:8px;display:block}
    .landing-title{font-size:28px;font-weight:700;color:#06223b;margin:0}
    .landing-subtitle{font-size:16px;color:var(--muted);margin:0}
    .landing-desc{color:var(--muted);margin-top:6px;margin-bottom:6px;font-size:15px;line-height:1.6}
    /* center heading and description in left panel */
    .left-panel .landing-title, .left-panel .landing-subtitle, .left-panel .landing-desc { text-align: center; width: 100%; }
        .right-panel{background:#fff;padding:28px;flex:0 0 380px;display:flex;flex-direction:column;justify-content:center;gap:14px}
        .login-box{background:var(--glass);padding:22px;border-radius:10px;border:1px solid rgba(0,0,0,0.04);box-shadow:0 8px 24px rgba(2,6,23,0.12)}
        .action-buttons{display:flex;flex-direction:column;gap:12px;width:100%}
        .action-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;background:var(--primary);color:#fff;border:none;border-radius:8px;padding:12px 16px;font-weight:700;text-decoration:none;width:100%;box-sizing:border-box}
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .action-btn.secondary{background:transparent;color:var(--primary);border:2px solid var(--primary);font-weight:600}
        
        .action-btn:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
        }
        
        .action-btn:hover::before {
            left: 100%;
        }
        
        .action-btn:active {
            transform: translateY(-2px) scale(0.98);
        }
        .action-btn.secondary:hover {
            background: #222222;
            color: #fff;
        }
        .action-btn i {
            font-size: 1.3em;
        }
        .back-link{position:absolute;top:20px;left:20px;color:#fff;text-decoration:none;font-size:14px;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:30px;background:rgba(0,0,0,0.25)}
        .back-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
        }
        .features{margin-top:18px;text-align:left}
        .feature-item{display:flex;align-items:center;gap:12px;margin-bottom:12px;color:var(--muted)}
        
        .feature-item:nth-child(1) { --delay: 0s; }
        .feature-item:nth-child(2) { --delay: 0.1s; }
        .feature-item:nth-child(3) { --delay: 0.2s; }
        .feature-item:nth-child(4) { --delay: 0.3s; }
        .feature-icon{color:var(--primary);font-size:1.1em;width:22px}
        .footer{position:fixed;bottom:12px;left:0;right:0;text-align:center;color:rgba(255,255,255,0.85);font-size:13px}
        /* Performance optimization for animations */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        @media (max-width: 880px){
            body{background-attachment:scroll}
            .landing-card{flex-direction:column}
            .right-panel{flex:unset;width:100%;padding:20px}
            .left-panel{padding:24px 20px;align-items:center}
            .left-panel .landing-title{font-size:24px}
        }
        @media (max-width:480px){
            .landing-container{padding:12px}
            .landing-card{width:100%}
            .logo-img{max-width:120px;height:auto}
            .landing-title{font-size:19px}
            .landing-desc{font-size:14px}
            .action-btn{
                padding:10px 12px;
                font-size:0.9rem;
                max-width:300px;
                margin:0 auto;
            }
            .footer{font-size:12px}
        }
    </style>
</head>
<body>
    
    
    <div class="landing-container">
        <div class="landing-card">
            <div class="left-panel">
                <div class="logo-wrap"><img src="staff/includes/logo.png" class="logo-img" alt="Alumytics logo"></div>
                <h1 class="landing-title">Alumni Portal</h1>
                <p class="landing-subtitle">Welcome Back, Graduate!</p>
                <p class="landing-desc">Access your alumni dashboard to update your profile, track your career progress, and stay connected with your alma mater and fellow graduates.</p>

                <div class="features">
                    <div class="feature-item"><i class="fas fa-chart-line feature-icon"></i><span>Track your career progress</span></div>
                    <div class="feature-item"><i class="fas fa-certificate feature-icon"></i><span>Upload certifications & awards</span></div>
                    <div class="feature-item"><i class="fas fa-network-wired feature-icon"></i><span>Connect with fellow alumni</span></div>
                    <div class="feature-item"><i class="fas fa-briefcase feature-icon"></i><span>Update employment information</span></div>
                </div>
            </div>

            <div class="right-panel">
                <div class="login-box">
                    <h3 style="margin:0 0 6px 0;color:#06223b">Sign in to your account</h3>
                    <p style="margin:0 0 12px 0;color:var(--muted)">Enter your institutional email and password</p>
                    <div class="action-buttons">
                        <a href="alumni/signin.php" class="action-btn"><i class="fas fa-sign-in-alt"></i> Sign In</a>
                        <a href="alumni/signup.php" class="action-btn secondary"><i class="fas fa-user-plus"></i> Create Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Alumytics Alumni Portal. Connecting graduates, building futures.</p>
    </footer>
</body>
</html>