<?php
set_time_limit(300);
ini_set('max_execution_time', 300);
// =====================================================
// cron/cron_mensajes_pendientes.php
// Procesa los mensajes pendientes de la tabla mensajes_pendientes
// Se ejecuta desde: C:\xampp\htdocs\ecodent\cron\ejecutar_todos.bat
// =====================================================

require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../config/database.php';

$conn = $conexion;

// Obtener todos los mensajes de email pendientes (enviado = 0)
$sql = "
    SELECT 
        mp.id_mensaje,
        mp.tipo,
        mp.mensaje,
        mp.email_destino,
        u.email,
        u.nombre_completo
    FROM mensajes_pendientes mp
    JOIN usuarios u ON mp.id_usuario = u.id_usuario
    WHERE mp.enviado = 0
      AND mp.canal = 'email'
    ORDER BY mp.fecha_registro ASC
    LIMIT 50
";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "Sin mensajes pendientes.\n";
    exit;
}

$enviados = 0;
$errores  = 0;

while ($msg = $result->fetch_assoc()) {

    // Usar email_destino si existe, si no usar el del usuario
    $email_final = !empty($msg['email_destino']) ? $msg['email_destino'] : $msg['email'];

    // Determinar asunto según tipo
    switch ($msg['tipo']) {
        case 'confirmacion':
            $asunto = '🦷 EcoDent - Confirmación de cita';
            break;
        case 'recordatorio_24h':
            $asunto = '🦷 EcoDent - Recordatorio: tu cita es mañana';
            break;
        case 'recordatorio_1h':
            $asunto = '🦷 EcoDent - Tu cita es en 1 hora';
            break;
        case 'cancelacion':
            $asunto = '🦷 EcoDent - Tu cita fue cancelada';
            break;
        case 'cancelacion_doctor':
            $asunto = '🦷 EcoDent - Cita cancelada por el odontólogo';
            break;
        case 'reprogramacion':
            $asunto = '🦷 EcoDent - Tu cita fue reprogramada';
            break;
        default:
            // Tipo vacío '' o desconocido = registros viejos
            $asunto = '🦷 EcoDent - Cita cancelada por el odontólogo';
            break;
    }

    // Construir HTML del email con el mensaje ya guardado en la BD
    $cuerpo = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
        <div style='background:#0d6efd;padding:24px;text-align:center'>
            <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
        </div>
        <div style='padding:32px'>
            <h3 style='margin-top:0'>Hola, {$msg['nombre_completo']}!</h3>
            <p>" . nl2br(htmlspecialchars($msg['mensaje'])) . "</p>
        </div>
        <div style='background:#f8f9fa;padding:14px;text-align:center'>
            <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales · Tel: 77112233</small>
        </div>
    </div>";

    $ok = enviarEmail($email_final, $msg['nombre_completo'], $asunto, $cuerpo);

    if ($ok) {
        $upd = $conn->prepare("UPDATE mensajes_pendientes SET enviado = 1, fecha_envio = NOW() WHERE id_mensaje = ?");
        $upd->bind_param('i', $msg['id_mensaje']);
        $upd->execute();
        echo "[OK] ID {$msg['id_mensaje']} | tipo: {$msg['tipo']} | enviado a: {$email_final}\n";
        $enviados++;
    } else {
        echo "[ERROR] ID {$msg['id_mensaje']} | tipo: {$msg['tipo']} | fallo para: {$email_final}\n";
        $errores++;
    }
}

echo "\n✅ Total: {$enviados} enviados, {$errores} errores.\n";
?>