<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Composer autoload

function sendVerificationEmail($email, $token) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = '38a71c42a65d97';
        $mail->Password   = 'PUT_YOUR_MAILTRAP_PASSWORD_HERE'; // ใส่รหัสจริงจาก Mailtrap SMTP
        $mail->Port       = 2525;

        // Recipients
        $mail->setFrom('noreply@animedule.com', 'AnimeDule');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'ยืนยันอีเมล AnimeDule';
        $mail->Body    = "กรุณาคลิกลิงก์นี้เพื่อยืนยันอีเมลของคุณ: 
            <a href='http://localhost/AnimeDule/verify.php?token=$token'>ยืนยันอีเมล</a>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}
