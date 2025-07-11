<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // แก้ path ให้ถูกต้องตามที่เก็บไฟล์จริง

function sendVerificationEmail($to, $verify_link) {
    $mail = new PHPMailer(true);

    try {
        // ตั้งค่า SMTP ของ Mailtrap
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = '38a71c42a65d97';        // เปลี่ยนเป็น Username Mailtrap ของคุณ
        $mail->Password   = '48e87f0eedf238';        // เปลี่ยนเป็น Password Mailtrap ของคุณ
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // ตั้งค่าอีเมลผู้ส่งและผู้รับ
        $mail->setFrom('no-reply@animedule.com', 'AnimeDule');
        $mail->addAddress($to);

        // ตั้งค่าเนื้อหาอีเมล
        $mail->Subject = 'ยืนยันอีเมลสำหรับ AnimeDule';
        $mail->Body    = "สวัสดี!\n\nกรุณาคลิกที่ลิงก์ด้านล่างเพื่อยืนยันอีเมลของคุณ:\n$verify_link";

        // ส่งเมล
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        echo "<pre>Mailer Error: " . $mail->ErrorInfo . "</pre>";
        return false;
    }
}
