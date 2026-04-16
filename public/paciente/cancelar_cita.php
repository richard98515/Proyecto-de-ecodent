<?php
// public/paciente/cancelar_cita.php
// Cancelar cita por parte del paciente

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];

// Obtener ID del paciente
$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

// Verificar ID de cita
if (!isset($_GET['id_cita']) || !is_numeric($_GET['id_cita'])) {
    $_SESSION['error'] = "ID de cita no válido.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

$id_cita = (int)$_GET['id_cita'];

// Obtener datos de la cita
$stmt = $conexion->prepare("
    SELECT c.*, c.fecha_cita, c.hora_cita, c.estado
    FROM citas c
    WHERE c.id_cita = ? AND c.id_paciente = ?
");
$stmt->bind_param("ii", $id_cita, $id_paciente);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();

if (!$cita) {
    $_SESSION['error'] = "Cita no encontrada.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// Validar estado
if (!in_array($cita['estado'], ['programada', 'confirmada'])) {
    $_SESSION['error'] = "Esta cita ya no puede cancelarse.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// =============================================
// VALIDAR 24 HORAS DE ANTELACIÓN
// =============================================
$fecha_hora_cita = strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']);
$ahora = time();
$diferencia_horas = ($fecha_hora_cita - $ahora) / 3600;

if ($diferencia_horas < 24) {
    $faltan = round($diferencia_horas, 1);
    $_SESSION['error'] = "⏰ No puedes cancelar la cita. Faltan solo {$faltan} horas para la cita. Debes cancelar con al menos 24 horas de anticipación.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// Cancelar la cita
$stmt = $conexion->prepare("
    UPDATE citas 
    SET estado = 'cancelada_pac', 
        fecha_cancelacion = NOW(),
        motivo_cancelacion = 'Cancelada por el paciente'
    WHERE id_cita = ? AND id_paciente = ?
");
$stmt->bind_param("ii", $id_cita, $id_paciente);

if ($stmt->execute()) {
    $_SESSION['exito'] = "✅ Cita cancelada exitosamente. El horario quedará disponible para otros pacientes.";
} else {
    $_SESSION['error'] = "Error al cancelar la cita. Intenta nuevamente.";
}

redirigir('/ecodent/public/paciente/mis_citas.php');
?>