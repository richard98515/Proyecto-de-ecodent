<?php
// public/paciente/mis_citas.php
// Muestra las citas del paciente y permite cancelaciones

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

// Verificar autenticación
if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

// Obtener el rol del usuario
$es_admin = esAdmin();
$es_paciente = esPaciente();

// Verificar permisos: solo admin o paciente pueden acceder
if (!$es_admin && !$es_paciente) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirigir('/ecodent/public/dashboard.php');
}

$id_usuario = $_SESSION['id_usuario'];
$id_paciente = null;
$nombre_paciente = null;

// Solo si es paciente, obtener su ID
if ($es_paciente) {
    $stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $paciente_data = $resultado->fetch_assoc();
        $id_paciente = $paciente_data['id_paciente'];
    } else {
        $_SESSION['error'] = "No se encontró información del paciente. Contacte al administrador.";
        redirigir('/ecodent/public/dashboard.php');
    }
}

// Si es admin, puede ver citas de un paciente específico o de todos?
// Por ahora, si es admin y no se especifica paciente, mostrará todas las citas
if ($es_admin && isset($_GET['id_paciente']) && is_numeric($_GET['id_paciente'])) {
    $id_paciente = (int)$_GET['id_paciente'];
    // Obtener nombre del paciente para mostrar
    $stmt_nombre = $conexion->prepare("SELECT u.nombre_completo FROM pacientes p JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.id_paciente = ?");
    $stmt_nombre->bind_param("i", $id_paciente);
    $stmt_nombre->execute();
    $result_nombre = $stmt_nombre->get_result();
    if ($result_nombre->num_rows > 0) {
        $nombre_paciente = $result_nombre->fetch_assoc()['nombre_completo'];
    }
}

// =============================================
// FUNCIÓN PARA VERIFICAR SI PUEDE MODIFICAR/CANCELAR (24 HORAS)
// =============================================
function puedeModificarCancelar($fecha_cita, $hora_cita) {
    $fecha_hora_cita = strtotime($fecha_cita . ' ' . $hora_cita);
    $ahora = time();
    $diferencia_horas = ($fecha_hora_cita - $ahora) / 3600;
    return $diferencia_horas >= 24;
}

// =============================================
// FUNCIONES AUXILIARES PARA REPROGRAMACIÓN
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

// =============================================
// OBTENER CITAS
// =============================================

// Construir la consulta según el rol
if ($es_paciente && $id_paciente) {
    // Paciente ve sus propias citas
    $sql = "SELECT c.*, 
               u.nombre_completo as nombre_odontologo,
               o.especialidad_principal
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN odontologos o ON t.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE t.id_paciente = ?
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $citas = $stmt->get_result();
    
} elseif ($es_admin && $id_paciente) {
    // Admin viendo citas de un paciente específico
    $sql = "SELECT c.*, 
               u.nombre_completo as nombre_odontologo,
               o.especialidad_principal
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN odontologos o ON t.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE t.id_paciente = ?
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $citas = $stmt->get_result();
    
} elseif ($es_admin) {
    // Admin viendo TODAS las citas (para supervisión)
    $sql = "SELECT c.*, 
               u.nombre_completo as nombre_odontologo,
               o.especialidad_principal,
               p2.nombre_completo as nombre_paciente
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN odontologos o ON t.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios p2 ON p.id_usuario = p2.id_usuario
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC
        LIMIT 100";
    
    $citas = $conexion->query($sql);
    
} else {
    $citas = new mysqli_result();
    $citas->num_rows = 0;
}

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
        <h1><i class="bi bi-calendar-check"></i> 
            <?php 
            if ($es_admin && $nombre_paciente) {
                echo "Citas de: " . htmlspecialchars($nombre_paciente);
            } elseif ($es_admin) {
                echo "Todas las Citas del Sistema";
            } else {
                echo "Mis Citas";
            }
            ?>
        </h1>
        
        <?php if ($es_admin): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Vista de administrador - Mostrando todas las citas del sistema
                <?php if (!$nombre_paciente): ?>
                    <br><small>Para ver citas de un paciente específico, usa: mis_citas.php?id_paciente=X</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
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
            <?php if ($es_paciente): ?>
                <a href="agendar.php" class="btn btn-primary">
                    <i class="bi bi-calendar-plus"></i> Agendar Nueva Cita
                </a>
            <?php endif; ?>
            <?php if ($es_admin && $nombre_paciente): ?>
                <a href="mis_citas.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Ver todas las citas
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (!$citas || $citas->num_rows === 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <?php 
                if ($es_admin && $nombre_paciente) {
                    echo "Este paciente no tiene citas registradas.";
                } elseif ($es_admin) {
                    echo "No hay citas registradas en el sistema.";
                } else {
                    echo "No tienes citas agendadas. <a href='agendar.php'>¡Agenda tu primera cita ahora!</a>";
                }
                ?>
            </div>
        <?php else: ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        32
                            <th>Fecha</th>
                            <th>Hora</th>
                            <?php if ($es_admin && !$nombre_paciente): ?>
                                <th>Paciente</th>
                            <?php endif; ?>
                            <th>Odontólogo</th>
                            <th>Especialidad</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <?php if ($es_paciente): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cita = $citas->fetch_assoc()): ?>
                            <?php
                            // Determinar si esta cita es la original cancelada o una nueva
                            $es_cancelada_por_doctor = ($cita['estado'] == 'cancelada_doc');
                            $tiene_opciones_disponibles = $es_cancelada_por_doctor ? tieneOpcionesDisponibles($cita['id_cita'], $conexion) : false;
                            $ya_eligio_opcion = $es_cancelada_por_doctor ? yaEligioOpcion($cita['id_cita'], $conexion) : false;
                            $puede = puedeModificarCancelar($cita['fecha_cita'], $cita['hora_cita']);
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($cita['hora_cita'])).' - '.date('h:i A', strtotime($cita['hora_fin'])); ?></td>
                                
                                <?php if ($es_admin && !$nombre_paciente): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cita['nombre_paciente'] ?? 'N/A'); ?></strong>
                                    </td>
                                <?php endif; ?>
                                
                                <td><?php echo htmlspecialchars($cita['nombre_odontologo']); ?></td>
                                <td><?php echo htmlspecialchars($cita['especialidad_principal']); ?></td>
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
                                    if ($es_paciente && $es_cancelada_por_doctor && $tiene_opciones_disponibles && !$ya_eligio_opcion): 
                                    ?>
                                        <br>
                                        <a href="reprogramar.php?id_cita=<?php echo $cita['id_cita']; ?>" 
                                           class="btn btn-warning btn-sm mt-1">
                                            <i class="bi bi-calendar-plus"></i> Elegir nueva fecha
                                        </a>
                                    <?php endif; ?>

                                    <?php 
                                    // CASO 2: Cita cancelada por doctor PERO ya eligió una opción
                                    if ($es_paciente && $es_cancelada_por_doctor && $ya_eligio_opcion): 
                                    ?>
                                        <br>
                                        <span class="badge bg-info mt-1">
                                            <i class="bi bi-check-circle"></i> Reprogramada - Ya elegiste
                                        </span>
                                    <?php endif; ?>

                                    <?php 
                                    // CASO 3: Cita cancelada por doctor SIN opciones (error)
                                    if ($es_paciente && $es_cancelada_por_doctor && !$tiene_opciones_disponibles && !$ya_eligio_opcion): 
                                    ?>
                                        <br>
                                        <span class="badge bg-secondary mt-1">
                                            <i class="bi bi-exclamation-triangle"></i> Sin opciones
                                        </span>
                                    <?php endif; ?>
                                 </th>
                                
                                <?php if ($es_paciente): ?>
                                    <td>
                                        <?php if (($cita['estado'] == 'programada' || $cita['estado'] == 'confirmada') && $puede): ?>
                                            <a href="cancelar_cita.php?id_cita=<?php echo $cita['id_cita']; ?>" 
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
                                            
                                        <?php elseif (($cita['estado'] == 'programada' || $cita['estado'] == 'confirmada') && !$puede): ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-clock-history"></i> No disponible<br>
                                                <small>Faltan menos de 24h</small>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
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