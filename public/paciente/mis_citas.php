<?php
// public/paciente/mis_citas.php
// Muestra las citas del paciente y permite cancelaciones

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/cancelaciones.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_paciente
$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$paciente = $resultado->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

// =============================================
// PROCESAR CANCELACIÓN (si viene por GET)
// =============================================
if (isset($_GET['cancelar']) && isset($_GET['id_cita'])) {
    $id_cita = (int)$_GET['id_cita'];
    
    $resultado_cancelacion = cancelarCitaPaciente($id_cita, $id_paciente, $conexion);
    
    if ($resultado_cancelacion['exito']) {
        $_SESSION['exito'] = $resultado_cancelacion['mensaje'];
    } else {
        $_SESSION['error'] = $resultado_cancelacion['error'];
    }
    
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// =============================================
// FUNCIONES AUXILIARES
// =============================================

/**
 * Verifica si una cita cancelada por el doctor TIENE opciones disponibles
 * (que el paciente aún no ha elegido ninguna)
 */
function tieneOpcionesDisponibles($id_cita, $conexion) {
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total 
        FROM opciones_reprogramacion_cita 
        WHERE id_cita_original = ? AND seleccionada = FALSE
    ");
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    return $resultado['total'] > 0;
}

/**
 * Verifica si el paciente YA eligió UNA opción de reprogramación
 */
function yaEligioOpcion($id_cita, $conexion) {
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total 
        FROM opciones_reprogramacion_cita 
        WHERE id_cita_original = ? AND seleccionada = TRUE
    ");
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    return $resultado['total'] > 0;
}

/**
 * Obtiene la nueva cita reprogramada (si existe)
 */
function obtenerNuevaCitaReprogramada($id_cita_original, $conexion) {
    // Esta función requeriría un campo id_cita_origen en la tabla citas
    // Por ahora, no la usaremos
    return null;
}

// =============================================
// OBTENER CITAS DEL PACIENTE
// =============================================
$sql = "SELECT c.*, 
               u.nombre_completo as nombre_odontologo,
               o.especialidad_principal
        FROM citas c
        JOIN odontologos o ON c.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE c.id_paciente = ?
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$citas = $stmt->get_result();

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';

// Mostrar mensajes de sesión
if (isset($_SESSION['exito'])) {
    $exito = $_SESSION['exito'];
    unset($_SESSION['exito']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-calendar-check"></i> Mis Citas</h1>
        <hr>
        
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
        
        <div class="mb-3">
            <a href="agendar.php" class="btn btn-primary">
                <i class="bi bi-calendar-plus"></i> Agendar Nueva Cita
            </a>
        </div>
        
        <?php if ($citas->num_rows === 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No tienes citas agendadas. <a href="agendar.php">¡Agenda tu primera cita ahora!</a>
            </div>
        <?php else: ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Odontólogo</th>
                            <th>Especialidad</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cita = $citas->fetch_assoc()): ?>
                            <?php
                            // Determinar si esta cita es la original cancelada o una nueva
                            $es_cancelada_por_doctor = ($cita['estado'] == 'cancelada_doc');
                            $tiene_opciones_disponibles = $es_cancelada_por_doctor ? tieneOpcionesDisponibles($cita['id_cita'], $conexion) : false;
                            $ya_eligio_opcion = $es_cancelada_por_doctor ? yaEligioOpcion($cita['id_cita'], $conexion) : false;
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($cita['hora_cita'])).' - '.date('h:i A', strtotime($cita['hora_fin'])); ?></td>
                                <td><?php echo $cita['nombre_odontologo']; ?></td>
                                <td><?php echo $cita['especialidad_principal']; ?></td>
                                <td><?php echo $cita['motivo'] ?? '-'; ?></td>
                                <td>
                                    <?php
                                    $estados = [
                                        'programada' => 'badge bg-primary',
                                        'confirmada' => 'badge bg-success',
                                        'completada' => 'badge bg-secondary',
                                        'cancelada_pac' => 'badge bg-danger',
                                        'cancelada_doc' => 'badge bg-danger',
                                        'ausente' => 'badge bg-warning'
                                    ];
                                    
                                    $estado_texto = [
                                        'programada' => 'Programada',
                                        'confirmada' => 'Confirmada',
                                        'completada' => 'Completada',
                                        'cancelada_pac' => 'Cancelada (Tú)',
                                        'cancelada_doc' => 'Cancelada (Doctor)',
                                        'ausente' => 'No Asististe'
                                    ];
                                    
                                    $clase = $estados[$cita['estado']] ?? 'badge bg-secondary';
                                    ?>
                                    <span class="<?php echo $clase; ?>">
                                        <?php echo $estado_texto[$cita['estado']] ?? $cita['estado']; ?>
                                    </span>
                                    
                                    <?php 
                                    // CASO 1: Cita cancelada por doctor CON opciones disponibles (aún no elige)
                                    if ($es_cancelada_por_doctor && $tiene_opciones_disponibles && !$ya_eligio_opcion): 
                                    ?>
                                        <br>
                                        <a href="reprogramar.php?id_cita=<?php echo $cita['id_cita']; ?>" 
                                           class="btn btn-warning btn-sm mt-1">
                                            <i class="bi bi-calendar-plus"></i> Elegir nueva fecha
                                        </a>
                                    <?php endif; ?>

                                    <?php 
                                    // CASO 2: Cita cancelada por doctor PERO ya eligió una opción
                                    if ($es_cancelada_por_doctor && $ya_eligio_opcion): 
                                    ?>
                                        <br>
                                        <span class="badge bg-info mt-1">
                                            <i class="bi bi-check-circle"></i> Reprogramada - Ya elegiste
                                        </span>
                                    <?php endif; ?>

                                    <?php 
                                    // CASO 3: Cita cancelada por doctor SIN opciones (error)
                                    if ($es_cancelada_por_doctor && !$tiene_opciones_disponibles && !$ya_eligio_opcion): 
                                    ?>
                                        <br>
                                        <span class="badge bg-secondary mt-1">
                                            <i class="bi bi-exclamation-triangle"></i> Sin opciones
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cita['estado'] == 'programada' || $cita['estado'] == 'confirmada'): ?>
                                        <a href="mis_citas.php?cancelar=1&id_cita=<?php echo $cita['id_cita']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('¿Estás seguro de cancelar esta cita?\n\nRecuerda: Al cancelar, el horario quedará disponible para otros pacientes.')">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </a>
                                        
                                        <?php if (isset($cita['puede_modificar']) && $cita['puede_modificar'] && $cita['cambios_realizados'] < $cita['limite_cambios']): ?>
                                            <a href="modificar_cita.php?id_cita=<?php echo $cita['id_cita']; ?>" 
                                               class="btn btn-warning btn-sm mt-1">
                                                <i class="bi bi-pencil"></i> Modificar
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Leyenda de estados -->
            <div class="card mt-3">
                <div class="card-header bg-light">
                    <i class="bi bi-info-circle"></i> Leyenda de estados
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <span class="badge bg-primary">Programada</span> - Cita creada
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-success">Confirmada</span> - Confirmada por el sistema
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-secondary">Completada</span> - Cita realizada
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-danger">Cancelada (Tú)</span> - Tú cancelaste
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-danger">Cancelada (Doctor)</span> - El doctor canceló
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-warning">No Asististe</span> - No te presentaste
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>