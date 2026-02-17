<?php
// config/email_config.php

// INSTRUCTIONS:
// 1. For Gmail: Use 'smtp.gmail.com', Port 465, SSL
// 2. You MUST use an "App Password" if you have 2FA enabled (recommended).
// 3. Go to Google Account -> Security -> 2-Step Verification -> App Passwords
// 4. Generate one for "Mail" and "Other (Custom name: XAMPP)"
// 5. Paste it below.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); // 465 for SSL, 587 for TLS
define('SMTP_USER', 'vigilantappdetection@gmail.com'); 
define('SMTP_PASS', 'tioz ghgn tjhu qocd'); 
define('SMTP_FROM', 'vigilantappdetection@gmail.com');
define('SMTP_FROM_NAME', 'Vigilant Security');
?>
