<?php
// includes/email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'richardocsachoqueherrera985@gmail.com');  // ← tu gmail aquí
define('SMTP_PASS', 'fzhl nxuc rnrj upet');   // ← clave de app 16 dígitos
define('SMTP_PORT', 587);
define('SMTP_NAME', 'EcoDent');

function enviarEmail($destinatario, $nombre, $asunto, $cuerpo_html) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_USER, SMTP_NAME);
        $mail->addAddress($destinatario, $nombre);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo_html;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function enviarCodigoVerificacion($email, $nombre, $codigo) {
    $asunto = '🦷 EcoDent - Código de verificación';
    $cuerpo = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
        <div style='background:#0d6efd;padding:24px;text-align:center'>
            <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
        </div>
        <div style='padding:32px'>
            <h3 style='margin-top:0'>Hola, {$nombre}!</h3>
            <p>Para completar tu registro ingresa este código:</p>
            <div style='text-align:center;background:#f0f4ff;border-radius:10px;padding:24px;margin:24px 0'>
                <span style='font-size:44px;font-weight:bold;letter-spacing:12px;color:#0d6efd'>{$codigo}</span>
            </div>
            <p style='color:#888;font-size:13px'>⏰ Expira en <strong>15 minutos</strong>.</p>
            <p style='color:#888;font-size:13px'>Si no creaste esta cuenta, ignora este mensaje.</p>
        </div>
        <div style='background:#f8f9fa;padding:14px;text-align:center'>
            <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales</small>
        </div>
    </div>";
    return enviarEmail($email, $nombre, $asunto, $cuerpo);
}
?>