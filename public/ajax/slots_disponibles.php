<?php
require_once '../../config/database.php';
require_once '../../includes/slots.php';

$odontologo_id = (int)$_GET['odontologo'];
$fecha         = $_GET['fecha'];
$excluir_cita  = isset($_GET['excluir_cita']) ? (int)$_GET['excluir_cita'] : 0;

$slots = generarSlotsDisponibles($odontologo_id, $fecha, $conexion, $excluir_cita);

header('Content-Type: application/json');
echo json_encode($slots);
?>