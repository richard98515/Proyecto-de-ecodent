<?php
// public/asistente/chat_api.php
// API Endpoint para procesar mensajes del chat (AJAX)

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos del cuerpo de la petición
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['mensaje']) || !isset($data['id_paciente'])) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

$mensaje = trim($data['mensaje']);
$id_paciente = (int)$data['id_paciente'];

if (empty($mensaje)) {
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

$conexion = conectarBD();

// Obtener datos del paciente
$stmt = $conexion->prepare("
    SELECT p.*, u.nombre_completo, u.email, u.telefono
    FROM pacientes p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_paciente = ?
");
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();

if (!$paciente) {
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit;
}

// =============================================
// DETECCIÓN DE INTENCIONES
// =============================================

$mensaje_lower = mb_strtolower($mensaje, 'UTF-8');

// 1. SALUDO
if (preg_match('/hola|buenos dias|buenas tardes|buenas noches|hey|saludos|qué tal/i', $mensaje_lower)) {
    $respuesta = "¡Hola " . htmlspecialchars($paciente['nombre_completo']) . "! 👋\n\n" .
                 "Soy el asistente virtual de ECO-DENT. ¿En qué puedo ayudarte hoy?\n\n" .
                 "📌 *Opciones disponibles:*\n" .
                 "• 📅 Agendar una cita\n" .
                 "• 👀 Ver mis próximas citas\n" .
                 "• ❌ Cancelar una cita\n" .
                 "• 💰 Ver mi historial de pagos\n" .
                 "• 📋 Ver mis tratamientos\n\n" .
                 "¿Qué deseas hacer?";
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 2. AGENDAR CITA
if (preg_match('/agendar|reservar|nueva cita|programar cita|quiero.*cita|sacar cita/i', $mensaje_lower)) {
    
    // Verificar si el paciente puede agendar
    if ($paciente['estado_cuenta'] == 'bloqueada') {
        $respuesta = "⚠️ *Lo siento, no puedes agendar citas*\n\n" .
                     "Tu cuenta está **BLOQUEADA** debido a ausencias repetidas.\n\n" .
                     "📞 Por favor, comunícate al **77112233** para regularizar tu situación.\n\n" .
                     "_Una vez regularizada, podrás volver a agendar normalmente._";
        echo json_encode(['respuesta' => $respuesta]);
        exit;
    }
    
    // Contar citas activas
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM citas 
        WHERE id_paciente = ? AND fecha_cita >= CURDATE() 
        AND estado IN ('programada', 'confirmada')
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $citas_activas = $stmt->get_result()->fetch_assoc()['total'];
    
    if ($citas_activas >= $paciente['limite_citas_simultaneas']) {
        $respuesta = "⚠️ *Has alcanzado el límite de citas simultáneas*\n\n" .
                     "Tienes " . $paciente['limite_citas_simultaneas'] . " cita(s) activa(s).\n\n" .
                     "Para agendar una nueva, primero debes asistir o cancelar alguna de tus citas actuales.\n\n" .
                     "👉 ¿Quieres ver tus citas actuales?";
        echo json_encode(['respuesta' => $respuesta]);
        exit;
    }
    
    $respuesta = "📅 *Vamos a agendar tu cita*\n\n" .
                 "Por favor, elige el odontólogo:\n\n" .
                 "1️⃣ Dr. Carlos Mamani (Ortodoncia)\n" .
                 "2️⃣ Dra. María Quispe (Endodoncia)\n\n" .
                 "Responde con el número o el nombre del odontólogo.\n\n" .
                 "👉 _Ejemplo: 'Dr. Mamani' o '1'_";
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 3. VER CITAS
if (preg_match('/mis citas|ver citas|proximas citas|que citas tengo|mis próximas citas/i', $mensaje_lower)) {
    
    $stmt = $conexion->prepare("
        SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.hora_fin, c.estado,
               u.nombre_completo as odontologo, o.especialidad_principal
        FROM citas c
        JOIN odontologos o ON c.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE c.id_paciente = ? AND c.fecha_cita >= CURDATE()
        AND c.estado IN ('programada', 'confirmada')
        ORDER BY c.fecha_cita ASC, c.hora_cita ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $citas = $stmt->get_result();
    
    if ($citas->num_rows > 0) {
        $respuesta = "📋 *Tus próximas citas:*\n\n";
        while ($cita = $citas->fetch_assoc()) {
            $fecha = date('d/m/Y', strtotime($cita['fecha_cita']));
            $hora = date('h:i A', strtotime($cita['hora_cita']));
            $hora_fin = date('h:i A', strtotime($cita['hora_fin']));
            $respuesta .= "🦷 *" . $fecha . "* - " . $hora . " a " . $hora_fin . "\n";
            $respuesta .= "   Dr. " . $cita['odontologo'] . " (" . $cita['especialidad_principal'] . ")\n";
            $respuesta .= "   Estado: " . ucfirst($cita['estado']) . "\n\n";
        }
        $respuesta .= "¿Necesitas cancelar o modificar alguna cita?";
    } else {
        $respuesta = "📭 *No tienes citas programadas*\n\n" .
                     "¿Quieres agendar una cita ahora? Responde 'Agendar cita' y te ayudo.";
    }
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 4. CANCELAR CITA
if (preg_match('/cancelar|cancelar cita|eliminar cita|borrar cita/i', $mensaje_lower)) {
    
    $stmt = $conexion->prepare("
        SELECT c.id_cita, c.fecha_cita, c.hora_cita, u.nombre_completo as odontologo
        FROM citas c
        JOIN odontologos o ON c.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE c.id_paciente = ? AND c.fecha_cita >= CURDATE()
        AND c.estado IN ('programada', 'confirmada')
        LIMIT 3
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $citas = $stmt->get_result();
    
    if ($citas->num_rows > 0) {
        $respuesta = "❌ *Cancelar cita*\n\n" .
                     "Tienes estas citas programadas:\n\n";
        while ($cita = $citas->fetch_assoc()) {
            $fecha = date('d/m/Y', strtotime($cita['fecha_cita']));
            $hora = date('h:i A', strtotime($cita['hora_cita']));
            $respuesta .= "• ID: {$cita['id_cita']} - {$fecha} a las {$hora} con Dr. {$cita['odontologo']}\n";
        }
        $respuesta .= "\n*Para cancelar, ingresa al sistema y ve a 'Mis Citas'*\n" .
                      "🔗 <a href='/ecodent/public/paciente/mis_citas.php'>Ir a Mis Citas</a>";
    } else {
        $respuesta = "No tienes citas programadas para cancelar.\n\n¿Quieres agendar una cita?";
    }
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 5. VER PAGOS
if (preg_match('/pagos|mis pagos|historial de pagos|debo|saldo|cuanto debo/i', $mensaje_lower)) {
    
    $stmt = $conexion->prepare("
        SELECT COALESCE(SUM(saldo_pendiente), 0) as total_saldo
        FROM tratamientos
        WHERE id_paciente = ? AND estado IN ('pendiente', 'en_progreso')
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $saldo = $stmt->get_result()->fetch_assoc()['total_saldo'];
    
    $stmt2 = $conexion->prepare("
        SELECT COUNT(*) as total_pagos, COALESCE(SUM(monto), 0) as total_monto
        FROM pagos
        WHERE id_paciente = ?
    ");
    $stmt2->bind_param("i", $id_paciente);
    $stmt2->execute();
    $pagos = $stmt2->get_result()->fetch_assoc();
    
    $respuesta = "💰 *Resumen de pagos*\n\n" .
                 "• Total pagado: Bs. " . number_format($pagos['total_monto'], 2) . "\n" .
                 "• Saldo pendiente: Bs. " . number_format($saldo, 2) . "\n" .
                 "• Número de pagos: " . $pagos['total_pagos'] . "\n\n";
    
    if ($saldo > 0) {
        $respuesta .= "⚠️ Tienes un saldo pendiente de Bs. " . number_format($saldo, 2) . "\n" .
                      "Por favor, regulariza tu situación.\n\n";
    } else {
        $respuesta .= "✅ ¡Estás al día con tus pagos!\n\n";
    }
    
    $respuesta .= "🔗 <a href='/ecodent/public/paciente/mis_pagos.php'>Ver historial completo</a>";
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 6. HORARIOS
if (preg_match('/horario|horarios|atencion|que horarios tienen|cuando atienden/i', $mensaje_lower)) {
    $respuesta = "🕐 *Horarios de atención ECO-DENT*\n\n" .
                 "📅 *Lunes a Viernes:* 08:00 - 18:00\n" .
                 "📅 *Sábados:* 09:00 - 13:00\n" .
                 "📅 *Domingos:* Cerrado\n\n" .
                 "⏰ *Cada cita dura 40 minutos*\n\n" .
                 "📞 *Emergencias:* 77112233\n\n" .
                 "¿Quieres agendar una cita? Responde 'Agendar cita'";
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 7. TRATAMIENTOS
if (preg_match('/tratamiento|tratamientos|que tratamientos hacen|servicios/i', $mensaje_lower)) {
    
    $stmt = $conexion->prepare("
        SELECT nombre_tratamiento, estado, fecha_inicio, costo_total, total_pagado, saldo_pendiente
        FROM tratamientos
        WHERE id_paciente = ?
        ORDER BY fecha_creacion DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $tratamientos = $stmt->get_result();
    
    if ($tratamientos->num_rows > 0) {
        $respuesta = "📋 *Tus tratamientos:*\n\n";
        while ($t = $tratamientos->fetch_assoc()) {
            $respuesta .= "• *{$t['nombre_tratamiento']}*\n";
            $respuesta .= "  Estado: " . ucfirst($t['estado']) . "\n";
            $respuesta .= "  Costo: Bs. " . number_format($t['costo_total'], 2) . "\n";
            $respuesta .= "  Pagado: Bs. " . number_format($t['total_pagado'], 2) . "\n\n";
        }
    } else {
        $respuesta = "No tienes tratamientos registrados.\n\n" .
                     "¿Quieres agendar una cita para iniciar un tratamiento?";
    }
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 8. AYUDA
if (preg_match('/ayuda|help|que puedes hacer|como funciona|no entiendo/i', $mensaje_lower)) {
    $respuesta = "🤖 *¿Qué puedo hacer por ti?*\n\n" .
                 "Soy el asistente virtual de ECO-DENT. Estas son mis funciones:\n\n" .
                 "📅 **Agendar cita** - Te guío para agendar una cita con tu odontólogo\n" .
                 "👀 **Ver mis citas** - Te muestro tus próximas citas\n" .
                 "❌ **Cancelar cita** - Cancela una cita existente\n" .
                 "💰 **Ver mis pagos** - Consulta tu historial y saldo pendiente\n" .
                 "📋 **Ver tratamientos** - Información de tus tratamientos\n" .
                 "🕐 **Horarios** - Te muestro los horarios de atención\n\n" .
                 "📞 **Contacto directo:** 77112233\n\n" .
                 "👉 *¿Qué deseas hacer?*";
    
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}

// 9. RESPUESTA POR DEFECTO
$respuesta = "🤔 *No entendí tu mensaje*\n\n" .
             "Puedes intentar con alguna de estas opciones:\n\n" .
             "• 📅 'Agendar cita'\n" .
             "• 👀 'Ver mis citas'\n" .
             "• ❌ 'Cancelar cita'\n" .
             "• 💰 'Ver mis pagos'\n" .
             "• 📋 'Ver tratamientos'\n" .
             "• 🕐 'Horarios'\n" .
             "• ❓ 'Ayuda'\n\n" .
             "¿Cómo puedo ayudarte?";

echo json_encode(['respuesta' => $respuesta]);
?>