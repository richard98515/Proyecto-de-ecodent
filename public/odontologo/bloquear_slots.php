<?php
// public/odontologo/bloquear_slots.php
// Módulo para que el odontólogo pueda bloquear/desbloquear múltiples slots fácilmente
// Útil para emergencias, reuniones, días libres, etc.

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/slots.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

// Verificar que solo odontólogos puedan acceder
requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_odontologo
$stmt = $conexion->prepare("SELECT id_odontologo, duracion_cita_min FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];
$duracion_slot = $odontologo['duracion_cita_min']; // 40 minutos por defecto

// =============================================
// PROCESAR BLOQUEO DE SLOTS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bloquear'])) {
    
    // Verificar token CSRF
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $_SESSION['error'] = 'Error de seguridad. Intente nuevamente.';
    } else {
        
        $fecha = $_POST['fecha'];
        $motivo = sanitizar($_POST['motivo']);
        $tipo_bloqueo = $_POST['tipo_bloqueo']; // 'dia_completo', 'rango_horas', 'slots_especificos'
        
        $bloqueos_realizados = 0;
        $errores = [];
        
        // INICIAR TRANSACCIÓN
        $conexion->begin_transaction();
        
        try {
            
            if ($tipo_bloqueo === 'dia_completo') {
                // =============================================
                // CASO 1: BLOQUEAR DÍA COMPLETO
                // =============================================
                // Obtener horario del odontólogo para ese día
                $dia_semana = strtolower(date('l', strtotime($fecha)));
                $dias_es = [
                    'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miercoles',
                    'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sabado', 'sunday' => 'domingo'
                ];
                $dia_es = $dias_es[$dia_semana];
                
                $sql_horario = "SELECT hora_inicio, hora_fin FROM horarios_odontologos 
                                WHERE id_odontologo = ? AND dia_semana = ? AND activo = 1";
                $stmt_horario = $conexion->prepare($sql_horario);
                $stmt_horario->bind_param("is", $id_odontologo, $dia_es);
                $stmt_horario->execute();
                $horario = $stmt_horario->get_result()->fetch_assoc();
                
                if ($horario) {
                    // Generar todos los slots del día y bloquearlos
                    $inicio = strtotime($fecha . ' ' . $horario['hora_inicio']);
                    $fin = strtotime($fecha . ' ' . $horario['hora_fin']);
                    $paso = $duracion_slot * 60;
                    
                    $hora_actual = $inicio;
                    while ($hora_actual + $paso <= $fin) {
                        $hora_inicio = date('H:i:s', $hora_actual);
                        $hora_fin = date('H:i:s', $hora_actual + $paso);
                        
                        // Verificar que no haya cita en ese horario
                        $sql_verificar = "SELECT id_cita FROM citas 
                                         WHERE id_odontologo = ? AND fecha_cita = ? AND hora_cita = ?
                                         AND estado IN ('programada', 'confirmada')";
                        $stmt_verificar = $conexion->prepare($sql_verificar);
                        $stmt_verificar->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
                        $stmt_verificar->execute();
                        $cita_existente = $stmt_verificar->get_result()->fetch_assoc();
                        
                        if (!$cita_existente) {
                            // Verificar que no esté ya bloqueado
                            $sql_verificar_bloqueo = "SELECT id_bloqueo FROM slots_bloqueados 
                                                     WHERE id_odontologo = ? AND fecha = ? AND hora_inicio = ?";
                            $stmt_vb = $conexion->prepare($sql_verificar_bloqueo);
                            $stmt_vb->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
                            $stmt_vb->execute();
                            $bloqueo_existente = $stmt_vb->get_result()->fetch_assoc();
                            
                            if (!$bloqueo_existente) {
                                // Bloquear slot
                                $sql_bloquear = "INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo)
                                                 VALUES (?, ?, ?, ?, ?)";
                                $stmt_bloquear = $conexion->prepare($sql_bloquear);
                                $motivo_completo = "Día completo: " . $motivo;
                                $stmt_bloquear->bind_param("issss", $id_odontologo, $fecha, $hora_inicio, $hora_fin, $motivo_completo);
                                $stmt_bloquear->execute();
                                $bloqueos_realizados++;
                            }
                        }
                        
                        $hora_actual += $paso;
                    }
                }
                
            } elseif ($tipo_bloqueo === 'rango_horas') {
                // =============================================
                // CASO 2: BLOQUEAR RANGO DE HORAS
                // =============================================
                $hora_inicio_rango = $_POST['hora_inicio'];
                $hora_fin_rango = $_POST['hora_fin'];
                
                // Convertir a timestamp
                $inicio_rango = strtotime($fecha . ' ' . $hora_inicio_rango);
                $fin_rango = strtotime($fecha . ' ' . $hora_fin_rango);
                $paso = $duracion_slot * 60;
                
                $hora_actual = $inicio_rango;
                while ($hora_actual + $paso <= $fin_rango) {
                    $hora_inicio = date('H:i:s', $hora_actual);
                    $hora_fin = date('H:i:s', $hora_actual + $paso);
                    
                    // Verificar que no haya cita
                    $sql_verificar = "SELECT id_cita FROM citas 
                                     WHERE id_odontologo = ? AND fecha_cita = ? AND hora_cita = ?
                                     AND estado IN ('programada', 'confirmada')";
                    $stmt_verificar = $conexion->prepare($sql_verificar);
                    $stmt_verificar->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
                    $stmt_verificar->execute();
                    $cita_existente = $stmt_verificar->get_result()->fetch_assoc();
                    
                    if (!$cita_existente) {
                        // Verificar que no esté ya bloqueado
                        $sql_verificar_bloqueo = "SELECT id_bloqueo FROM slots_bloqueados 
                                                 WHERE id_odontologo = ? AND fecha = ? AND hora_inicio = ?";
                        $stmt_vb = $conexion->prepare($sql_verificar_bloqueo);
                        $stmt_vb->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
                        $stmt_vb->execute();
                        $bloqueo_existente = $stmt_vb->get_result()->fetch_assoc();
                        
                        if (!$bloqueo_existente) {
                            // Bloquear slot
                            $sql_bloquear = "INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo)
                                             VALUES (?, ?, ?, ?, ?)";
                            $stmt_bloquear = $conexion->prepare($sql_bloquear);
                            $motivo_completo = "Rango de horas: " . $motivo;
                            $stmt_bloquear->bind_param("issss", $id_odontologo, $fecha, $hora_inicio, $hora_fin, $motivo_completo);
                            $stmt_bloquear->execute();
                            $bloqueos_realizados++;
                        }
                    }
                    
                    $hora_actual += $paso;
                }
                
            } elseif ($tipo_bloqueo === 'slots_especificos' && isset($_POST['slots'])) {
                // =============================================
                // CASO 3: BLOQUEAR SLOTS ESPECÍFICOS
                // =============================================
                foreach ($_POST['slots'] as $hora_inicio) {
                    // Calcular hora fin
                    $hora_fin = date('H:i:s', strtotime($hora_inicio) + ($duracion_slot * 60));
                    
                    // Verificar que no haya cita
                    $sql_verificar = "SELECT id_cita FROM citas 
                                     WHERE id_odontologo = ? AND fecha_cita = ? AND hora_cita = ?
                                     AND estado IN ('programada', 'confirmada')";
                    $stmt_verificar = $conexion->prepare($sql_verificar);
                    $stmt_verificar->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
                    $stmt_verificar->execute();
                    $cita_existente = $stmt_verificar->get_result()->fetch_assoc();
                    
                    if (!$cita_existente) {
                        // Verificar que no esté ya bloqueado
                        $sql_verificar_bloqueo = "SELECT id_bloqueo FROM slots_bloqueados 
                                                 WHERE id_odontologo = ? AND fecha = ? AND hora_inicio = ?";
                        $stmt_vb = $conexion->prepare($sql_verificar_bloqueo);
                        $stmt_vb->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
                        $stmt_vb->execute();
                        $bloqueo_existente = $stmt_vb->get_result()->fetch_assoc();
                        
                        if (!$bloqueo_existente) {
                            // Bloquear slot
                            $sql_bloquear = "INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo)
                                             VALUES (?, ?, ?, ?, ?)";
                            $stmt_bloquear = $conexion->prepare($sql_bloquear);
                            $stmt_bloquear->bind_param("issss", $id_odontologo, $fecha, $hora_inicio, $hora_fin, $motivo);
                            $stmt_bloquear->execute();
                            $bloqueos_realizados++;
                        }
                    }
                }
            }
            
            // CONFIRMAR TRANSACCIÓN
            $conexion->commit();
            
            if ($bloqueos_realizados > 0) {
                $_SESSION['exito'] = "¡Listo! Se bloquearon $bloqueos_realizados slots correctamente.";
            } else {
                $_SESSION['error'] = "No se pudo bloquear ningún slot. Verifica que no haya citas programadas.";
            }
            
        } catch (Exception $e) {
            $conexion->rollback();
            $_SESSION['error'] = "Error al bloquear slots: " . $e->getMessage();
        }
        
        redirigir('/ecodent/public/odontologo/bloquear_slots.php?fecha=' . $fecha);
    }
}

// =============================================
// PROCESAR DESBLOQUEO DE SLOTS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['desbloquear'])) {
    
    // Verificar token CSRF
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $_SESSION['error'] = 'Error de seguridad. Intente nuevamente.';
    } else {
        
        $fecha = $_POST['fecha'];
        $desbloquear_ids = $_POST['desbloquear_ids'] ?? [];
        
        if (empty($desbloquear_ids)) {
            $_SESSION['error'] = 'No seleccionaste ningún slot para desbloquear.';
        } else {
            
            $desbloqueos_realizados = 0;
            
            // INICIAR TRANSACCIÓN
            $conexion->begin_transaction();
            
            try {
                // Eliminar los slots bloqueados seleccionados
                $placeholders = implode(',', array_fill(0, count($desbloquear_ids), '?'));
                $sql_desbloquear = "DELETE FROM slots_bloqueados 
                                    WHERE id_bloqueo IN ($placeholders) 
                                    AND id_odontologo = ?";
                $stmt_desbloquear = $conexion->prepare($sql_desbloquear);
                
                // Crear array de parámetros: primero los IDs, luego el id_odontologo
                $tipos = str_repeat('i', count($desbloquear_ids)) . 'i';
                $params = array_merge($desbloquear_ids, [$id_odontologo]);
                $stmt_desbloquear->bind_param($tipos, ...$params);
                $stmt_desbloquear->execute();
                $desbloqueos_realizados = $stmt_desbloquear->affected_rows;
                
                // CONFIRMAR TRANSACCIÓN
                $conexion->commit();
                
                if ($desbloqueos_realizados > 0) {
                    $_SESSION['exito'] = "¡Listo! Se desbloquearon $desbloqueos_realizados slots correctamente.";
                } else {
                    $_SESSION['error'] = "No se pudo desbloquear ningún slot.";
                }
                
            } catch (Exception $e) {
                $conexion->rollback();
                $_SESSION['error'] = "Error al desbloquear slots: " . $e->getMessage();
            }
        }
        
        redirigir('/ecodent/public/odontologo/bloquear_slots.php?fecha=' . $fecha);
    }
}

// =============================================
// OBTENER DATOS PARA EL FORMULARIO
// =============================================
// Generar token CSRF
$token_csrf = generarTokenCSRF();

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';

// Mostrar mensajes
if (isset($_SESSION['exito'])) {
    $exito = $_SESSION['exito'];
    unset($_SESSION['exito']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<style>
.bloqueo-card {
    border-left: 5px solid #dc3545;
    transition: all 0.3s;
}
.bloqueo-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.slot-checkbox {
    transform: scale(1.2);
    margin-right: 10px;
}
.slot-item {
    padding: 8px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    margin-bottom: 5px;
    transition: all 0.2s;
}
.slot-item:hover {
    background-color: #f8f9fa;
    border-color: #dc3545;
}
.slot-item.seleccionado {
    background-color: #f8d7da;
    border-color: #dc3545;
}
.bloqueado-item {
    border-left: 4px solid #dc3545;
    background-color: #fff3f3;
}
.bloqueado-item:hover {
    background-color: #ffe6e6;
}
.desbloquear-checkbox {
    transform: scale(1.2);
    margin-right: 10px;
}
.btn-desbloquear {
    background-color: #28a745;
    color: white;
}
.btn-desbloquear:hover {
    background-color: #218838;
    color: white;
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-lock-fill text-danger"></i> Gestionar Slots</h1>
        <p class="lead">Bloquea o desbloquea slots cuando tengas emergencias, reuniones o días libres.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="calendario.php" class="btn btn-primary">
            <i class="bi bi-calendar-week"></i> Ver Calendario
        </a>
    </div>
</div>

<?php if (isset($exito)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $exito; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <!-- Tarjeta de información importante -->
        <div class="alert alert-warning">
            <div class="d-flex">
                <div class="me-3">
                    <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                </div>
                <div>
                    <h5>Importante sobre la gestión de slots:</h5>
                    <ul class="mb-0">
                        <li>Los slots que ya tienen citas <strong>NO se pueden bloquear</strong> (debes cancelar la cita primero)</li>
                        <li>Los slots bloqueados <strong>no serán visibles</strong> para los pacientes</li>
                        <li>Puedes <strong>desbloquear</strong> slots cuando lo necesites</li>
                        <li>El bloqueo/desbloqueo es <strong>inmediato</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Panel de selección de fecha -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-calendar"></i> 1. Seleccionar Fecha</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="formFecha">
                    <div class="mb-3">
                        <label for="fecha" class="form-label">Fecha a gestionar:</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" 
                               value="<?php echo isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d'); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Ver Slots del Día
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Tips rápidos -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Tips rápidos</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success"></i>
                        <strong>Emergencia:</strong> Bloquea todo el día
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success"></i>
                        <strong>Reunión:</strong> Bloquea un rango de horas
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success"></i>
                        <strong>Salir temprano:</strong> Bloquea slots específicos
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-arrow-repeat text-primary"></i>
                        <strong>Desbloquear:</strong> Ve a la pestaña "Desbloquear slots"
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if (isset($_GET['fecha'])): 
            $fecha_seleccionada = $_GET['fecha'];
            
            // Obtener slots disponibles y ocupados para esa fecha
            $slots_disponibles = generarSlotsDisponibles($id_odontologo, $fecha_seleccionada, $conexion);
            
            // Obtener slots ya bloqueados
            $sql_bloqueados = "SELECT hora_inicio FROM slots_bloqueados 
                              WHERE id_odontologo = ? AND fecha = ?";
            $stmt_bloqueados = $conexion->prepare($sql_bloqueados);
            $stmt_bloqueados->bind_param("is", $id_odontologo, $fecha_seleccionada);
            $stmt_bloqueados->execute();
            $bloqueados_existentes = $stmt_bloqueados->get_result();
            $horas_bloqueadas = [];
            while ($b = $bloqueados_existentes->fetch_assoc()) {
                $horas_bloqueadas[] = $b['hora_inicio'];
            }
            
            // Obtener citas programadas
            $sql_citas = "SELECT hora_cita, nombre_completo 
                         FROM citas c
                         JOIN pacientes p ON c.id_paciente = p.id_paciente
                         JOIN usuarios u ON p.id_usuario = u.id_usuario
                         WHERE c.id_odontologo = ? AND c.fecha_cita = ? 
                         AND c.estado IN ('programada', 'confirmada')";
            $stmt_citas = $conexion->prepare($sql_citas);
            $stmt_citas->bind_param("is", $id_odontologo, $fecha_seleccionada);
            $stmt_citas->execute();
            $citas_dia = $stmt_citas->get_result();
            $citas_por_hora = [];
            while ($c = $citas_dia->fetch_assoc()) {
                $citas_por_hora[$c['hora_cita']] = $c['nombre_completo'];
            }
            
            // Generar todos los slots del día (incluyendo ocupados)
            $dia_semana = strtolower(date('l', strtotime($fecha_seleccionada)));
            $dias_es = [
                'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miercoles',
                'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sabado', 'sunday' => 'domingo'
            ];
            $dia_es = $dias_es[$dia_semana];
            
            $sql_horario = "SELECT hora_inicio, hora_fin FROM horarios_odontologos 
                            WHERE id_odontologo = ? AND dia_semana = ? AND activo = 1";
            $stmt_horario = $conexion->prepare($sql_horario);
            $stmt_horario->bind_param("is", $id_odontologo, $dia_es);
            $stmt_horario->execute();
            $horario = $stmt_horario->get_result()->fetch_assoc();
            
            $todos_slots = [];
            if ($horario) {
                $inicio = strtotime($fecha_seleccionada . ' ' . $horario['hora_inicio']);
                $fin = strtotime($fecha_seleccionada . ' ' . $horario['hora_fin']);
                $paso = $duracion_slot * 60;
                
                $hora_actual = $inicio;
                while ($hora_actual + $paso <= $fin) {
                    $hora_inicio = date('H:i:s', $hora_actual);
                    $hora_fin = date('H:i:s', $hora_actual + $paso);
                    
                    $todos_slots[] = [
                        'hora_inicio' => $hora_inicio,
                        'hora_fin' => $hora_fin,
                        'hora_inicio_formato' => date('h:i A', $hora_actual),
                        'hora_fin_formato' => date('h:i A', $hora_actual + $paso),
                        'disponible' => !isset($citas_por_hora[$hora_inicio]) && !in_array($hora_inicio, $horas_bloqueadas),
                        'ocupado' => isset($citas_por_hora[$hora_inicio]),
                        'bloqueado' => in_array($hora_inicio, $horas_bloqueadas),
                        'paciente' => $citas_por_hora[$hora_inicio] ?? null
                    ];
                    
                    $hora_actual += $paso;
                }
            }
            
            // Obtener slots bloqueados con detalles para la pestaña de desbloquear
            $sql_bloqueados_detalle = "SELECT id_bloqueo, hora_inicio, hora_fin, motivo, fecha_registro 
                                      FROM slots_bloqueados 
                                      WHERE id_odontologo = ? AND fecha = ? 
                                      ORDER BY hora_inicio";
            $stmt_bloqueados_detalle = $conexion->prepare($sql_bloqueados_detalle);
            $stmt_bloqueados_detalle->bind_param("is", $id_odontologo, $fecha_seleccionada);
            $stmt_bloqueados_detalle->execute();
            $bloqueados_detalle = $stmt_bloqueados_detalle->get_result();
        ?>
        
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-day"></i> 
                    Slots para el <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?>
                </h5>
            </div>
            <div class="card-body">
                
                <!-- Pestañas para tipos de gestión -->
                <ul class="nav nav-tabs mb-3" id="gestionTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dia-completo-tab" data-bs-toggle="tab" 
                                data-bs-target="#dia-completo" type="button" role="tab">
                            <i class="bi bi-calendar-x"></i> Día completo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rango-horas-tab" data-bs-toggle="tab" 
                                data-bs-target="#rango-horas" type="button" role="tab">
                            <i class="bi bi-clock"></i> Rango de horas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="slots-especificos-tab" data-bs-toggle="tab" 
                                data-bs-target="#slots-especificos" type="button" role="tab">
                            <i class="bi bi-list-check"></i> Slots específicos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="desbloquear-tab" data-bs-toggle="tab" 
                                data-bs-target="#desbloquear" type="button" role="tab">
                            <i class="bi bi-unlock-fill"></i> Desbloquear slots
                            <?php if ($bloqueados_detalle->num_rows > 0): ?>
                                <span class="badge bg-warning ms-1"><?php echo $bloqueados_detalle->num_rows; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
                
                <!-- Contenido de las pestañas -->
                <div class="tab-content" id="gestionTabContent">
                    
                    <!-- CASO 1: DÍA COMPLETO -->
                    <div class="tab-pane fade show active" id="dia-completo" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                            <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                            <input type="hidden" name="tipo_bloqueo" value="dia_completo">
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Vas a bloquear <strong>TODO el día <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></strong>.
                                Los pacientes no podrán agendar ninguna cita en este día.
                            </div>
                            
                            <div class="mb-3">
                                <label for="motivo_dia" class="form-label">Motivo del bloqueo:</label>
                                <input type="text" class="form-control" id="motivo_dia" name="motivo" 
                                       placeholder="Ej: Emergencia personal, Capacitación, Día libre, etc." required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Resumen del día:</label>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total de slots en el día
                                        <span class="badge bg-primary rounded-pill"><?php echo count($todos_slots); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Slots con citas (no se bloquearán)
                                        <span class="badge bg-warning rounded-pill">
                                            <?php echo count(array_filter($todos_slots, function($s) { return $s['ocupado']; })); ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Slots disponibles para bloquear
                                        <span class="badge bg-success rounded-pill">
                                            <?php echo count(array_filter($todos_slots, function($s) { return $s['disponible']; })); ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            
                            <button type="submit" name="bloquear" class="btn btn-danger w-100"
                                    onclick="return confirm('¿Bloquear TODO el día? Los slots disponibles dejarán de mostrarse a los pacientes.')">
                                <i class="bi bi-lock-fill"></i> Bloquear Día Completo
                            </button>
                        </form>
                    </div>
                    
                    <!-- CASO 2: RANGO DE HORAS -->
                    <div class="tab-pane fade" id="rango-horas" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                            <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                            <input type="hidden" name="tipo_bloqueo" value="rango_horas">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hora_inicio" class="form-label">Hora de inicio:</label>
                                        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hora_fin" class="form-label">Hora de fin:</label>
                                        <input type="time" class="form-control" id="hora_fin" name="hora_fin" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="motivo_rango" class="form-label">Motivo del bloqueo:</label>
                                <input type="text" class="form-control" id="motivo_rango" name="motivo" 
                                       placeholder="Ej: Reunión de personal, Almuerzo extendido, etc." required>
                            </div>
                            
                            <button type="submit" name="bloquear" class="btn btn-danger w-100">
                                <i class="bi bi-clock"></i> Bloquear Rango de Horas
                            </button>
                        </form>
                    </div>
                    
                    <!-- CASO 3: SLOTS ESPECÍFICOS -->
                    <div class="tab-pane fade" id="slots-especificos" role="tabpanel">
                        <form method="POST" action="" id="formSlotsEspecificos">
                            <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                            <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                            <input type="hidden" name="tipo_bloqueo" value="slots_especificos">
                            
                            <div class="mb-3">
                                <label class="form-label">Selecciona los slots a bloquear:</label>
                                <div class="row">
                                    <?php foreach ($todos_slots as $index => $slot): ?>
                                        <div class="col-md-6">
                                            <div class="slot-item <?php echo $slot['ocupado'] ? 'bg-light' : ($slot['bloqueado'] ? 'bg-secondary bg-opacity-25' : ''); ?>" 
                                                 id="slot-<?php echo $index; ?>">
                                                <div class="form-check">
                                                    <input class="form-check-input slot-checkbox" 
                                                           type="checkbox" 
                                                           name="slots[]" 
                                                           value="<?php echo $slot['hora_inicio']; ?>"
                                                           id="slot-check-<?php echo $index; ?>"
                                                           <?php echo ($slot['ocupado'] || $slot['bloqueado']) ? 'disabled' : ''; ?>>
                                                    <label class="form-check-label" for="slot-check-<?php echo $index; ?>">
                                                        <strong><?php echo $slot['hora_inicio_formato']; ?> - <?php echo $slot['hora_fin_formato']; ?></strong>
                                                        <?php if ($slot['ocupado']): ?>
                                                            <br>
                                                            <span class="badge bg-warning">
                                                                <i class="bi bi-person"></i> <?php echo $slot['paciente']; ?>
                                                            </span>
                                                            <small class="text-muted">(tiene cita)</small>
                                                        <?php elseif ($slot['bloqueado']): ?>
                                                            <br>
                                                            <span class="badge bg-secondary">
                                                                <i class="bi bi-lock"></i> Ya bloqueado
                                                            </span>
                                                        <?php else: ?>
                                                            <br>
                                                            <span class="badge bg-success">Disponible</span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="motivo_especifico" class="form-label">Motivo del bloqueo:</label>
                                <input type="text" class="form-control" id="motivo_especifico" name="motivo" 
                                       placeholder="Ej: Tengo una reunión, Salgo temprano, etc." required>
                            </div>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="seleccionarTodosDisponibles()">
                                    Seleccionar todos disponibles
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deseleccionarTodos()">
                                    Deseleccionar todos
                                </button>
                            </div>
                            
                            <button type="submit" name="bloquear" class="btn btn-danger w-100">
                                <i class="bi bi-lock-fill"></i> Bloquear Slots Seleccionados
                            </button>
                        </form>
                    </div>
                    
                    <!-- CASO 4: DESBLOQUEAR SLOTS -->
                    <div class="tab-pane fade" id="desbloquear" role="tabpanel">
                        <form method="POST" action="" id="formDesbloquear">
                            <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                            <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                            
                            <?php if ($bloqueados_detalle->num_rows > 0): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Selecciona los slots que deseas desbloquear. Estos volverán a estar disponibles para los pacientes.
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Slots bloqueados para este día:</label>
                                    <div class="row">
                                        <?php while ($bloqueado = $bloqueados_detalle->fetch_assoc()): ?>
                                            <div class="col-md-6">
                                                <div class="slot-item bloqueado-item" id="bloqueado-<?php echo $bloqueado['id_bloqueo']; ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input desbloquear-checkbox" 
                                                               type="checkbox" 
                                                               name="desbloquear_ids[]" 
                                                               value="<?php echo $bloqueado['id_bloqueo']; ?>"
                                                               id="desbloquear-<?php echo $bloqueado['id_bloqueo']; ?>">
                                                        <label class="form-check-label" for="desbloquear-<?php echo $bloqueado['id_bloqueo']; ?>">
                                                            <strong>
                                                                <?php echo date('h:i A', strtotime($bloqueado['hora_inicio'])); ?> - 
                                                                <?php echo date('h:i A', strtotime($bloqueado['hora_fin'])); ?>
                                                            </strong>
                                                            <br>
                                                            <span class="badge bg-secondary">Motivo: <?php echo htmlspecialchars($bloqueado['motivo']); ?></span>
                                                            <br>
                                                            <small class="text-muted">Bloqueado: <?php echo date('d/m/Y H:i', strtotime($bloqueado['fecha_registro'])); ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="seleccionarTodosBloqueados()">
                                        Seleccionar todos
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deseleccionarTodosBloqueados()">
                                        Deseleccionar todos
                                    </button>
                                </div>
                                
                                <button type="submit" name="desbloquear" class="btn btn-success w-100"
                                        onclick="return confirm('¿Estás seguro de desbloquear los slots seleccionados? Los pacientes podrán verlos nuevamente.')">
                                    <i class="bi bi-unlock-fill"></i> Desbloquear Slots Seleccionados
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i>
                                    No hay slots bloqueados para este día. ¡Todo está disponible!
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<!-- Script para manejar selección de slots -->
<script>
function seleccionarTodosDisponibles() {
    document.querySelectorAll('#slots-especificos .slot-checkbox:not(:disabled)').forEach(checkbox => {
        checkbox.checked = true;
        let slotItem = checkbox.closest('.slot-item');
        if (slotItem) slotItem.classList.add('seleccionado');
    });
}

function deseleccionarTodos() {
    document.querySelectorAll('#slots-especificos .slot-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        let slotItem = checkbox.closest('.slot-item');
        if (slotItem) slotItem.classList.remove('seleccionado');
    });
}

function seleccionarTodosBloqueados() {
    document.querySelectorAll('#desbloquear .desbloquear-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deseleccionarTodosBloqueados() {
    document.querySelectorAll('#desbloquear .desbloquear-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Resaltar slots seleccionados (para bloqueo de slots específicos)
document.querySelectorAll('#slots-especificos .slot-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let slotItem = this.closest('.slot-item');
        if (this.checked) {
            slotItem.classList.add('seleccionado');
        } else {
            slotItem.classList.remove('seleccionado');
        }
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>