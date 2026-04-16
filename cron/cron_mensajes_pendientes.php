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

// =============================================
// 1. PROCESAR MENSAJES DE EMAIL
// =============================================
$sql_email = "
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

$result = $conn->query($sql_email);

if ($result && $result->num_rows > 0) {
    $enviados = 0;
    $errores = 0;

    while ($msg = $result->fetch_assoc()) {
        $email_final = !empty($msg['email_destino']) ? $msg['email_destino'] : $msg['email'];

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
                $asunto = '🦷 EcoDent - Cita cancelada por el odontólogo';
                break;
        }

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
            echo "[EMAIL OK] ID {$msg['id_mensaje']} | tipo: {$msg['tipo']} | enviado a: {$email_final}\n";
            $enviados++;
        } else {
            echo "[EMAIL ERROR] ID {$msg['id_mensaje']} | tipo: {$msg['tipo']} | fallo para: {$email_final}\n";
            $errores++;
        }
    }
    echo "\n📧 EMAIL: {$enviados} enviados, {$errores} errores.\n";
} else {
    echo "📧 Sin mensajes de EMAIL pendientes.\n";
}

// =============================================
// 2. PROCESAR MENSAJES DE WHATSAPP (SOLO MOSTRAR LINKS)
// =============================================
echo "\n========================================\n";
echo "📱 WHATSAPP PENDIENTES...\n";
echo "========================================\n";

$sql_whatsapp = "
    SELECT 
        mp.id_mensaje,
        mp.tipo,
        mp.mensaje,
        mp.telefono_destino,
        u.telefono,
        u.nombre_completo,
        mp.id_cita
    FROM mensajes_pendientes mp
    JOIN usuarios u ON mp.id_usuario = u.id_usuario
    WHERE mp.enviado = 0
      AND mp.canal = 'whatsapp'
    ORDER BY mp.fecha_registro ASC
    LIMIT 50
";

$result_wa = $conn->query($sql_whatsapp);

if ($result_wa && $result_wa->num_rows > 0) {
    $pendientes_wa = 0;
    
    while ($msg = $result_wa->fetch_assoc()) {
        $telefono_final = !empty($msg['telefono_destino']) ? $msg['telefono_destino'] : $msg['telefono'];
        
        $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono_final);
        if (substr($telefono_limpio, 0, 1) == '0') {
            $telefono_limpio = '591' . substr($telefono_limpio, 1);
        } elseif (substr($telefono_limpio, 0, 2) != '59') {
            $telefono_limpio = '591' . $telefono_limpio;
        }
        
        $mensaje_codificado = urlencode($msg['mensaje']);
        $whatsapp_link = "https://wa.me/{$telefono_limpio}?text={$mensaje_codificado}";
        
        echo "[WHATSAPP PENDIENTE] ID {$msg['id_mensaje']} | Cita ID: {$msg['id_cita']}\n";
        echo "   📱 Link: {$whatsapp_link}\n";
        echo "   👤 Paciente: {$msg['nombre_completo']}\n\n";
        $pendientes_wa++;
    }
    echo "\n📱 WHATSAPP: {$pendientes_wa} mensajes pendientes de envío manual.\n";
    echo "💡 El odontólogo debe presionar el botón de WhatsApp para enviarlos.\n";
} else {
    echo "📱 Sin mensajes de WHATSAPP pendientes.\n";
}

echo "\n✅ Proceso completado.\n";
?>