<?php
// Management Landing Page for Alumytics
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="logo.png">
    <title>Management Portal - Alumytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />
    <style>
    :root{--primary:#222222;--muted:#6c757d;--card-bg:rgba(255,255,255,0.95);--glass:rgba(255,255,255,0.6);--max-width-card:1000px}
        html,body{height:100%}
        body {
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: linear-gradient(rgba(2,20,60,0.45), rgba(2,20,60,0.45)), url('assets/images/campus.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            color: #fff;
        }
    .portal-card{background:var(--card-bg);backdrop-filter:blur(8px);border-radius:12px;box-shadow:0 20px 50px rgba(2,6,23,0.6);padding:0;max-width:var(--max-width-card);width:92%;display:flex;gap:0;overflow:hidden;border:1px solid rgba(255,255,255,0.08)}
    .portal-container{min-height:calc(100vh - 48px);display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box}
    .portal-left{padding:40px 40px;flex:1 1 420px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:12px}
    .logo-wrap{width:100%;display:flex;align-items:center;justify-content:center;margin-bottom:6px}
    .logo-img{max-width:160px;height:auto;border-radius:8px;display:block}
    .portal-title{font-size:28px;margin:0 0 8px 0;color:#06223b}
    /* ensure the title centers within the left panel */
    .portal-left .portal-title{width:100%;text-align:center}
        .portal-subtitle{color:var(--muted);margin-bottom:12px}
    .portal-right{background:#fff;padding:24px;flex:0 0 380px;display:flex;flex-direction:column;justify-content:center;gap:14px}
    /* Features visibility */
    .features{margin-top:18px;text-align:left}
    .feature-item{display:flex;align-items:center;gap:12px;margin-bottom:12px;color:var(--muted)}
    .feature-icon{color:#222222;font-size:1.1em;width:22px}
        .role-badges{display:flex;gap:10px;margin-bottom:12px}
    .role-badge{background:transparent;padding:8px 14px;border-radius:999px;color:#222222;font-weight:700;border:1px solid rgba(0,0,0,0.12)}
        .login-actions{display:flex;flex-direction:column;gap:12px;width:100%;margin-bottom:30px;margin-top:30px}
    .login-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:10px 14px;border-radius:8px;text-decoration:none;background:#222222;color:#fff;font-weight:700;width:100%;box-sizing:border-box}
    .portal-footer{position:fixed;bottom:12px;left:0;right:0;text-align:center;color:rgba(0,0,0,0.75);font-size:13px}
    /* ensure old .footer (used in markup) is anchored bottom center */
    .footer{position:fixed;left:0;right:0;bottom:8px;text-align:center;color:rgba(0,0,0,0.7);font-size:13px}
        @media (max-width:880px){
            body{background-attachment:scroll}
            .portal-card{flex-direction:column}
            .portal-right{flex:unset;width:100%;padding:20px}
            .portal-left{padding:24px 20px;align-items:center}
            .portal-left .portal-title{font-size:24px}
        }
        @media (max-width:480px){
            .portal-container{padding:12px}
            .portal-card{width:100%}
            .logo-img{max-width:120px}
            .portal-title{font-size:19px}
            .features{font-size:0.9rem}
            .login-btn{
                padding:7px 10px;
                font-size:0.8rem;
                max-width:300px;
                margin:0 auto;
            }
            .login-btn i{font-size:1rem}
            .footer{font-size:12px}
        }
    </style>
</head>
<body>
    
    
    <div class="portal-container">
        <div class="portal-card">
            <div class="portal-left">
                <div class="logo-wrap"><img src="staff/includes/logo.png" class="logo-img" alt="Alumytics logo"></div>
                    <h1 class="portal-title">Management Portal</h1>
                <div class="features">
                    <div class="feature-item"><i class="fas fa-chart-bar feature-icon"></i><span>Comprehensive alumni analytics and reporting</span></div>
                    <div class="feature-item"><i class="fas fa-users-cog feature-icon"></i><span>User management and role-based access control</span></div>
                    <div class="feature-item"><i class="fas fa-map-marked-alt feature-icon"></i><span>Geographic distribution tracking</span></div>
                    <div class="feature-item"><i class="fas fa-file-export feature-icon"></i><span>Data export and backup capabilities</span></div>
                    <div class="feature-item"><i class="fas fa-graduation-cap feature-icon"></i><span>Campus and program-specific insights</span></div>
                </div>
            </div>
            <div class="portal-right">
                <div style="width:100%">
                    <p class="portal-title">Administrative Dashboard</p>
                    <div class="login-actions">
                        <a href="staff/login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Access Management Dashboard</a>
                        <a href="#help" class="login-btn" style="background:transparent;border:2px solid rgba(0,0,0,0.12);color:var(--primary);">Need Help?</a>
                    </div>
                    <p style="color:var(--muted);margin-top:10px">Access comprehensive analytics, manage alumni data, generate reports, and oversee the entire alumni tracking system with powerful administrative tools.</p>
                    
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Alumytics Management Portal. Empowering data-driven decisions.</p>
    </footer>

    <script>
        // Handle help button click
        document.addEventListener('DOMContentLoaded', function() {
            const helpBtn = document.querySelector('a[href="#help"]');
            if (helpBtn) {
                helpBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('For technical support, please contact your system administrator or IT department.\n\nFor access issues, verify your role and credentials with the administration office.');
                });
            }
        });
    </script>
</body>
</html>