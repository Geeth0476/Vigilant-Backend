<?php
// utils/EmailSender.php
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/SMTPClient.php'; 

class EmailSender {
    public static function send($to, $toName, $subject, $body) {
        $smtp = new SMTPClient(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS);
        return $smtp->send($to, $subject, $body, SMTP_FROM, SMTP_FROM_NAME);
    }
}
?>
