<?php
// public/odontologo/procesar_agendar_compuesta.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz');

session_start();

// Verificar autenticación
if (!estaLogueado()) {
    $_SESSION['error'] = 'Debes iniciar sesión';
    redirigir('/ecodent/public/login.php');
}

// Obtener el rol del usuario
$es_admin = esAdmin();
$es_odontologo = esOdontologo();

// Verificar permisos: solo admin u odontólogo pueden acceder
if (!$es_admin && !$es_odontologo) {
    $_SESSION['error'] = 'No tienes permisos para realizar esta acción';
    redirigir('/ecodent/public/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/ecodent/public/odontologo/agendar_cita.php');
}

// Verificar token CSRF
if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
    $_SESSION['error'] = 'Error de seguridad. Intente nuevamente.';
    redirigir('/ecodent/public/odontologo/agendar_cita.php?fecha=' . ($_POST['fecha'] ?? date('Y-m-d')));
}

// Obtener datos del formulario
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$id_paciente = $_POST['id_paciente'] ?? 0;
$motivo = sanitizar($_POST['motivo'] ?? '');
$hora_inicio = $_POST['hora_inicio'] ?? '';
$hora_fin = $_POST['hora_fin'] ?? '';
$slots_ocupados_json = $_POST['slots_ocupados'] ?? '[]';
$slots_ocupados = json_decode($slots_ocupados_json, true);

// Validar datos básicos
if (!$id_paciente) {
    $_SESSION['error'] = 'Debes seleccionar un paciente';
    redirigir('/ecodent/public/odontologo/agendar_cita.php?fecha=' . $fecha);
}

if (!$motivo) {
    $_SESSION['error'] = 'Debes especificar el motivo del procedimiento';
    redirigir('/ecodent/public/odontologo/agendar_cita.php?fecha=' . $fecha);
}

if (!$hora_inicio || !$hora_fin) {
    $_SESSION['error'] = 'Horario no válido';
    redirigir('/ecodent/public/odontologo/agendar_cita.php?fecha=' . $fecha);
}

// Obtener id_odontologo
$id_odontologo = null;

// Si es admin, puede venir el id_odontologo en POST
if ($es_admin && isset($_POST['id_odontologo']) && is_numeric($_POST['id_odontologo'])) {
    $id_odontologo = (int)$_POST['id_odontologo'];
    // Verificar que el odontólogo existe
    $check = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_odontologo = ? AND activo = 1");
    $check->bind_param("i", $id_odontologo);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $_SESSION['error'] = 'El odontólogo seleccionado no existe o está inactivo';
        redirigir('/ecodent/public/odontologo/calendario.php');
    }
} else {
    // Si es odontólogo, obtener su ID de la sesión
    $id_usuario = $_SESSION['id_usuario'];
    $stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        $_SESSION['error'] = 'No se encontró información del odontólogo';
        redirigir('/ecodent/public/dashboard.php');
    }
    
    $odontologo = $resultado->fetch_assoc();
    $id_odontologo = $odontologo['id_odontologo'];
}

if (!$id_odontologo) {
    $_SESSION['error'] = 'No se pudo identificar al odontólogo';
    redirigir('/ecodent/public/odontologo/agendar_cita.php?fecha=' . $fecha);
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Verificar que TODOS los slots estén disponibles
    $todos_disponibles = true;
    $errores = [];
    
    if (!empty($slots_ocupados) && is_array($slots_ocupados)) {
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
    
    $total_slots = is_array($slots_ocupados) ? count($slots_ocupados) : 0;
    $_SESSION['exito'] = "¡Procedimiento agendado correctamente! Ocupa " . $total_slots . " slot" . ($total_slots != 1 ? 's' : '') . ".";
    
} catch (Exception $e) {
    $conexion->rollback();
    $_SESSION['error'] = "Error al agendar: " . $e->getMessage();
}

// Redirigir al calendario correspondiente
if ($es_admin && $id_odontologo) {
    redirigir('/ecodent/public/odontologo/calendario.php?ver_odontologo=' . $id_odontologo);
} else {
    redirigir('/ecodent/public/odontologo/calendario.php');
}
?>