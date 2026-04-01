<?php
set_time_limit(300); // 5 minutos de límite
ini_set('max_execution_time', 300);
// =====================================================
// cron/cron_mensajes_pendientes.php
// Procesa los mensajes de cancelación acumulados en la tabla
// Ejecutar cada hora junto con cron_recordatorios.php
// =====================================================

require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../config/database.php'; // tu archivo de conexión

$conn = $conexion; // tu variable se llama $conexion

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
        case 'cancelacion':
            $asunto = '🦷 EcoDent - Tu cita fue cancelada';
            break;
        case 'confirmacion':
            $asunto = '🦷 EcoDent - Confirmación de cita';
            break;
        case 'reprogramacion':
            $asunto = '🦷 EcoDent - Tu cita fue reprogramada';
            break;
        default:
            // Tipo vacío '' o desconocido = cancelación por odontólogo
            $asunto = '🦷 EcoDent - Cita cancelada por el odontólogo';
            break;
    }

    // Construir HTML del email con el mensaje ya guardado
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
        // Marcar como enviado
        $upd = $conn->prepare("UPDATE mensajes_pendientes SET enviado = 1, fecha_envio = NOW() WHERE id_mensaje = ?");
        $upd->bind_param('i', $msg['id_mensaje']);
        $upd->execute();
        echo "[OK] Mensaje ID {$msg['id_mensaje']} enviado a {$email_final}\n";
        $enviados++;
    } else {
        echo "[ERROR] Mensaje ID {$msg['id_mensaje']} falló para {$email_final}\n";
        $errores++;
    }
}

echo "✅ Procesados: {$enviados} enviados, {$errores} errores.\n";
?>