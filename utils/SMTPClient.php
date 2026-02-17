<?php
// utils/SMTPClient.php

class SMTPClient {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $debug = false;

    public function __construct($host, $port, $user, $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send($to, $subject, $body, $from, $fromName, $debug = false) {
        $this->debug = $debug;
        $socket = fsockopen("ssl://" . $this->host, $this->port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("SMTP Error: Could not connect to host. $errno - $errstr");
            return false;
        }

        if (!$this->expect($socket, "220")) return false;

        $this->cmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
        if (!$this->expect($socket, "250")) return false;

        $this->cmd($socket, "AUTH LOGIN");
        if (!$this->expect($socket, "334")) return false;

        $this->cmd($socket, base64_encode($this->user));
        if (!$this->expect($socket, "334")) return false;

        $this->cmd($socket, base64_encode($this->pass));
        if (!$this->expect($socket, "235")) {
             error_log("SMTP Auth Failed");
             return false;
        }

        $this->cmd($socket, "MAIL FROM: <$from>");
        if (!$this->expect($socket, "250")) return false;

        $this->cmd($socket, "RCPT TO: <$to>");
        if (!$this->expect($socket, "250")) return false;

        $this->cmd($socket, "DATA");
        if (!$this->expect($socket, "354")) return false;

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";

        $this->cmd($socket, "$headers\r\n$body\r\n.");
        if (!$this->expect($socket, "250")) return false;

        $this->cmd($socket, "QUIT");
        fclose($socket);
        return true;
    }

    private function cmd($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
    }

    private function expect($socket, $code) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        if ($this->debug) {
            error_log("SMTP: " . $response);
        }
        
        // Check if response code matches expected
        if (substr($response, 0, 3) !== $code) {
             error_log("SMTP Error: Expected $code, got $response");
             return false;
        }
        return true;
    }
}
?>
