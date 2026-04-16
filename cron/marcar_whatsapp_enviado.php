<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_GET['id_cita'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$id_cita = (int)$_GET['id_cita'];

$conexion->prepare("
    UPDATE mensajes_pendientes 
    SET enviado = 1, fecha_envio = NOW() 
    WHERE id_cita = ? AND canal = 'whatsapp' AND enviado = 0
")->execute() ? null : null;

// Necesitamos bind_param
$stmt = $conexion->prepare("
    UPDATE mensajes_pendientes 
    SET enviado = 1, fecha_envio = NOW() 
    WHERE id_cita = ? AND canal = 'whatsapp' AND enviado = 0
");
$stmt->bind_param("i", $id_cita);
$stmt->execute();

echo json_encode(['ok' => true]);
?>