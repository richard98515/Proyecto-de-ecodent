<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_GET['id_paciente']) || !is_numeric($_GET['id_paciente'])) {
    echo json_encode([]);
    exit;
}

$id_paciente = $_GET['id_paciente'];

$stmt = $conexion->prepare("
    SELECT id_tratamiento, nombre_tratamiento, estado, saldo_pendiente, costo_total
    FROM tratamientos
    WHERE id_paciente = ? AND estado IN ('pendiente', 'en_progreso')
    ORDER BY fecha_creacion DESC
");
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$resultado = $stmt->get_result();

$tratamientos = [];
while ($row = $resultado->fetch_assoc()) {
    $tratamientos[] = $row;
}

echo json_encode($tratamientos);
?>