<?php
// cron/marcar_whatsapp_enviado.php

require_once __DIR__ . '/../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['id_cita'])) {
    echo json_encode(['ok' => false, 'error' => 'ID de cita no proporcionado']);
    exit;
}

$id_cita = (int)$_GET['id_cita'];

// Verificar que la cita existe
$check = $conexion->prepare("SELECT id_cita FROM citas WHERE id_cita = ?");
$check->bind_param("i", $id_cita);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'La cita no existe']);
    exit;
}
$check->close();

// Marcar mensajes como enviados
$stmt = $conexion->prepare("
    UPDATE mensajes_pendientes 
    SET enviado = 1, fecha_envio = NOW() 
    WHERE id_cita = ? AND canal = 'whatsapp' AND enviado = 0
");
$stmt->bind_param("i", $id_cita);
$success = $stmt->execute();
$afectadas = $stmt->affected_rows;
$stmt->close();

echo json_encode([
    'ok' => $success,
    'afectadas' => $afectadas,
    'mensaje' => $afectadas > 0 ? 'Mensaje(s) marcado(s) como enviado' : 'No había mensajes pendientes'
]);
?>