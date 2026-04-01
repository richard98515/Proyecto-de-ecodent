<?php
// =====================================================
// cron/cron_recordatorios.php
// Ejecutar cada hora con el Programador de Tareas de Windows
// =====================================================

require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../config/database.php'; // tu archivo de conexión

$conn = $conexion; // tu variable se llama $conexion

$ahora = new DateTime();

// ─────────────────────────────────────────────
// RECORDATORIO 24 HORAS ANTES
// Busca citas programadas/confirmadas para mañana
// que aún no tienen recordatorio de 24h enviado
// ─────────────────────────────────────────────
$manana = (new DateTime('+1 day'))->format('Y-m-d');

$sql_24h = "
    SELECT 
        c.id_cita,
        c.fecha_cita,
        c.hora_cita,
        u.email,
        u.nombre_completo AS nombre_paciente,
        u2.nombre_completo AS nombre_odontologo
    FROM citas c
    JOIN pacientes p ON c.id_paciente = p.id_paciente
    JOIN usuarios u  ON p.id_usuario = u.id_usuario
    JOIN odontologos o ON c.id_odontologo = o.id_odontologo
    JOIN usuarios u2   ON o.id_usuario = u2.id_usuario
    WHERE c.fecha_cita = ?
      AND c.estado IN ('programada', 'confirmada')
      AND c.fecha_recordatorio_24h IS NULL
";

$stmt = $conn->prepare($sql_24h);
$stmt->bind_param('s', $manana);
$stmt->execute();
$citas_24h = $stmt->get_result();

while ($cita = $citas_24h->fetch_assoc()) {
    $enviado = enviarRecordatorio24h(
        $cita['email'],
        $cita['nombre_paciente'],
        $cita['fecha_cita'],
        $cita['hora_cita'],
        $cita['nombre_odontologo']
    );

    if ($enviado) {
        // Marcar que el recordatorio de 24h fue enviado
        $upd = $conn->prepare("UPDATE citas SET fecha_recordatorio_24h = NOW() WHERE id_cita = ?");
        $upd->bind_param('i', $cita['id_cita']);
        $upd->execute();
        echo "[24h] Enviado a: " . $cita['email'] . " - Cita ID: " . $cita['id_cita'] . "\n";
    } else {
        echo "[24h] ERROR al enviar a: " . $cita['email'] . " - Cita ID: " . $cita['id_cita'] . "\n";
    }
}

// ─────────────────────────────────────────────
// RECORDATORIO 1 HORA ANTES
// Busca citas para HOY en la próxima 1 hora
// que aún no tienen recordatorio de 1h enviado
// ─────────────────────────────────────────────
$hoy        = $ahora->format('Y-m-d');
$en_1h      = (clone $ahora)->modify('+60 minutes')->format('H:i:s');
$en_90min   = (clone $ahora)->modify('+90 minutes')->format('H:i:s'); // ventana de 30 min

$sql_1h = "
    SELECT 
        c.id_cita,
        c.fecha_cita,
        c.hora_cita,
        u.email,
        u.nombre_completo AS nombre_paciente,
        u2.nombre_completo AS nombre_odontologo
    FROM citas c
    JOIN pacientes p ON c.id_paciente = p.id_paciente
    JOIN usuarios u  ON p.id_usuario = u.id_usuario
    JOIN odontologos o ON c.id_odontologo = o.id_odontologo
    JOIN usuarios u2   ON o.id_usuario = u2.id_usuario
    WHERE c.fecha_cita = ?
      AND c.hora_cita BETWEEN ? AND ?
      AND c.estado IN ('programada', 'confirmada')
      AND c.fecha_recordatorio_1h IS NULL
";

$stmt2 = $conn->prepare($sql_1h);
$stmt2->bind_param('sss', $hoy, $en_1h, $en_90min);
$stmt2->execute();
$citas_1h = $stmt2->get_result();

while ($cita = $citas_1h->fetch_assoc()) {
    $enviado = enviarRecordatorio1h(
        $cita['email'],
        $cita['nombre_paciente'],
        $cita['fecha_cita'],
        $cita['hora_cita'],
        $cita['nombre_odontologo']
    );

    if ($enviado) {
        $upd2 = $conn->prepare("UPDATE citas SET fecha_recordatorio_1h = NOW() WHERE id_cita = ?");
        $upd2->bind_param('i', $cita['id_cita']);
        $upd2->execute();
        echo "[1h] Enviado a: " . $cita['email'] . " - Cita ID: " . $cita['id_cita'] . "\n";
    } else {
        echo "[1h] ERROR al enviar a: " . $cita['email'] . " - Cita ID: " . $cita['id_cita'] . "\n";
    }
}

echo "✅ Cron de recordatorios finalizado: " . $ahora->format('Y-m-d H:i:s') . "\n";
?>