<?php
// SMTP email configuration for ALUMytics alumni module.
// TODO: Replace placeholder values with your real Gmail + App Password as described in SMTP-EMAIL-SETUP.md.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'alumyticsg@gmail.com');        // Your Gmail
define('SMTP_PASSWORD', 'yifbwttfjwymkncd');  // Gmail App Password
define('FROM_EMAIL', 'alumyticsg@gmail.com');           // Same as SMTP_USERNAME
define('FROM_NAME', 'ALUMytics System');
define('REPLY_TO', 'alumyticsg@gmail.com');

// Force real email sending even on localhost (after XAMPP sendmail is configured)
define('FORCE_EMAIL_SEND', true);
