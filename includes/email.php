<?php
// includes/email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'herreraocsachoquerichard985@gmail.com');  // ← tu gmail aquí
define('SMTP_PASS', 'tvzn vbtp yact arqk');   // ← clave de app 16 dígitos
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

//para las funciones de recordatorio de citas, cancelaciones, etc. se pueden crear funciones similares a enviarCodigoVerificacion pero con diferentes asuntos y cuerpos de email, adaptados a cada caso específico.
function enviarRecordatorio24h($email, $nombre_paciente, $fecha_cita, $hora_cita, $nombre_odontologo) {
    $fecha_formato = date('d/m/Y', strtotime($fecha_cita));
    $hora_formato  = date('H:i', strtotime($hora_cita));
 
    $asunto = '🦷 EcoDent - Recordatorio: tu cita es mañana';
    $cuerpo = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
        <div style='background:#0d6efd;padding:24px;text-align:center'>
            <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
        </div>
        <div style='padding:32px'>
            <h3 style='margin-top:0'>Hola, {$nombre_paciente}!</h3>
            <p>Te recordamos que tienes una cita <strong>mañana</strong>:</p>
            <div style='background:#f0f4ff;border-radius:10px;padding:20px;margin:20px 0'>
                <p style='margin:6px 0'>📅 <strong>Fecha:</strong> {$fecha_formato}</p>
                <p style='margin:6px 0'>🕐 <strong>Hora:</strong> {$hora_formato}</p>
                <p style='margin:6px 0'>👨‍⚕️ <strong>Odontólogo:</strong> {$nombre_odontologo}</p>
            </div>
            <p style='color:#888;font-size:13px'>Por favor asiste puntualmente. Si no puedes ir, cancela con anticipación desde el sistema.</p>
        </div>
        <div style='background:#f8f9fa;padding:14px;text-align:center'>
            <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales · Tel: 77112233</small>
        </div>
    </div>";
 
    return enviarEmail($email, $nombre_paciente, $asunto, $cuerpo);
}
 
// ─────────────────────────────────────────────
// RECORDATORIO 1 HORA ANTES
// ─────────────────────────────────────────────
function enviarRecordatorio1h($email, $nombre_paciente, $fecha_cita, $hora_cita, $nombre_odontologo) {
    $fecha_formato = date('d/m/Y', strtotime($fecha_cita));
    $hora_formato  = date('H:i', strtotime($hora_cita));
 
    $asunto = '🦷 EcoDent - Tu cita es en 1 hora';
    $cuerpo = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
        <div style='background:#198754;padding:24px;text-align:center'>
            <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
        </div>
        <div style='padding:32px'>
            <h3 style='margin-top:0'>Hola, {$nombre_paciente}!</h3>
            <p>⏰ Tu cita es <strong>en aproximadamente 1 hora</strong>:</p>
            <div style='background:#f0fff4;border-radius:10px;padding:20px;margin:20px 0'>
                <p style='margin:6px 0'>📅 <strong>Fecha:</strong> {$fecha_formato}</p>
                <p style='margin:6px 0'>🕐 <strong>Hora:</strong> {$hora_formato}</p>
                <p style='margin:6px 0'>👨‍⚕️ <strong>Odontólogo:</strong> {$nombre_odontologo}</p>
            </div>
            <p style='color:#888;font-size:13px'>¡Te esperamos! Llega unos minutos antes para completar el registro.</p>
        </div>
        <div style='background:#f8f9fa;padding:14px;text-align:center'>
            <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales · Tel: 77112233</small>
        </div>
    </div>";
 
    return enviarEmail($email, $nombre_paciente, $asunto, $cuerpo);
}
 
// ─────────────────────────────────────────────
// EMAIL DE CANCELACION (para mensajes pendientes acumulados)
// ─────────────────────────────────────────────
function enviarEmailCancelacion($email, $nombre_paciente, $tipo_cancelacion, $motivo = null) {
    if ($tipo_cancelacion === 'cancelacion_pac') {
        $asunto = '🦷 EcoDent - Tu cita fue cancelada';
        $mensaje_body = "Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees desde el sistema.";
        $color_header = '#6c757d';
    } else {
        // Cancelada por odontólogo
        $asunto = '🦷 EcoDent - Cita cancelada por el odontólogo';
        $motivo_texto = $motivo ? "Motivo: <strong>{$motivo}</strong>" : "";
        $mensaje_body = "Tu cita ha sido cancelada por el odontólogo. {$motivo_texto}<br><br>Por favor ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.";
        $color_header = '#dc3545';
    }
 
    $cuerpo = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
        <div style='background:{$color_header};padding:24px;text-align:center'>
            <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
        </div>
        <div style='padding:32px'>
            <h3 style='margin-top:0'>Hola, {$nombre_paciente}!</h3>
            <p>{$mensaje_body}</p>
        </div>
        <div style='background:#f8f9fa;padding:14px;text-align:center'>
            <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales · Tel: 77112233</small>
        </div>
    </div>";
 
    return enviarEmail($email, $nombre_paciente, $asunto, $cuerpo);
}
?>