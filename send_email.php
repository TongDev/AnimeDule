<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // ถ้าใช้ Composer

function sendVerificationEmail($to, $verify_link) {
    $mail = new PHPMailer(true);

    try {
        // Mailtrap SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = '38a71c42a65d97';          // <== เปลี่ยนให้ตรงกับ Mailtrap ของคุณ
        $mail->Password   = '48e87f0eedf238';  // <== เปลี่ยนให้ตรงกับ Mailtrap ของคุณ
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('no-reply@animedule.com', 'AnimeDule');
        $mail->addAddress($to);
        $mail->Subject = 'ยืนยันอีเมลสำหรับ AnimeDule';
        $mail->Body    = "สวัสดี!\n\nกรุณาคลิกลิงก์ด้านล่างเพื่อยืนยันอีเมลของคุณ:\n$verify_link";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        echo "<pre>Mailer Error: " . $mail->ErrorInfo . "</pre>";
        return false;
    }
}
