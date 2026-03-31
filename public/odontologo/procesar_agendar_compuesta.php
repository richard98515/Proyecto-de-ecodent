<?php
// public/odontologo/procesar_agendar_compuesta.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

session_start();
requerirRol('odontologo');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/ecodent/public/odontologo/agendar_cita_compuesta.php');
}

// Verificar token CSRF
if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
    $_SESSION['error'] = 'Error de seguridad. Intente nuevamente.';
    redirigir('/ecodent/public/odontologo/agendar_cita_compuesta.php?fecha=' . $_POST['fecha']);
}

// Obtener datos del formulario
$fecha = $_POST['fecha'];
$id_paciente = $_POST['id_paciente'];
$motivo = sanitizar($_POST['motivo']);
$hora_inicio = $_POST['hora_inicio'];
$hora_fin = $_POST['hora_fin'];
$slots_ocupados_json = $_POST['slots_ocupados'];
$slots_ocupados = json_decode($slots_ocupados_json, true);

// Obtener id_odontologo
$id_usuario = $_SESSION['id_usuario'];
$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Verificar que TODOS los slots estén disponibles
    $todos_disponibles = true;
    $errores = [];
    
    foreach ($slots_ocupados as $hora_slot) {
        $sql_verificar = "SELECT c.id_cita FROM citas c
                          WHERE c.id_odontologo = ? AND c.fecha_cita = ? AND c.hora_cita = ?
                          AND c.estado IN ('programada', 'confirmada')
                          UNION
                          SELECT sb.id_bloqueo FROM slots_bloqueados sb
                          WHERE sb.id_odontologo = ? AND sb.fecha = ? AND sb.hora_inicio = ?";
        $stmt_verificar = $conexion->prepare($sql_verificar);
        $stmt_verificar->bind_param("ississ", $id_odontologo, $fecha, $hora_slot, $id_odontologo, $fecha, $hora_slot);
        $stmt_verificar->execute();
        $resultado_verificar = $stmt_verificar->get_result();

        if ($resultado_verificar->num_rows > 0) {
            $todos_disponibles = false;
            $errores[] = "El slot $hora_slot ya no está disponible";
        }
    }
    
    if (!$todos_disponibles) {
        throw new Exception("Algunos slots ya no están disponibles: " . implode(", ", $errores));
    }
    
    // Insertar UNA SOLA cita que cubre todo el rango
    $sql_insertar = "INSERT INTO citas (id_odontologo, id_paciente, fecha_cita, hora_cita, hora_fin, estado, motivo)
                     VALUES (?, ?, ?, ?, ?, 'programada', ?)";
    $stmt_insertar = $conexion->prepare($sql_insertar);
    $stmt_insertar->bind_param("iissss", $id_odontologo, $id_paciente, $fecha, $hora_inicio, $hora_fin, $motivo);
    
    if (!$stmt_insertar->execute()) {
        throw new Exception("Error al crear la cita: " . $conexion->error);
    }
    
    $id_cita = $conexion->insert_id;
    
    // Confirmar transacción
    $conexion->commit();
    
    $_SESSION['exito'] = "¡Procedimiento agendado correctamente! Ocupa " . count($slots_ocupados) . " slots.";
    
} catch (Exception $e) {
    $conexion->rollback();
    $_SESSION['error'] = "Error al agendar: " . $e->getMessage();
}

redirigir('/ecodent/public/odontologo/calendario.php');
?>