<?php
// public/odontologo/detalle_cita.php
// Muestra el detalle de una cita específica
// ADMIN y ODONTÓLOGO pueden: ver, marcar asistencia, registrar tarde, marcar ausente, cancelar

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz');

// Verificar autenticación
if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

// Obtener el rol del usuario
$es_admin = esAdmin();
$es_odontologo = esOdontologo();

// Verificar permisos: solo admin u odontólogo pueden acceder
if (!$es_admin && !$es_odontologo) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirigir('/ecodent/public/dashboard.php');
}

$id_usuario = $_SESSION['id_usuario'];
$id_odontologo = null;

// Solo si es odontólogo, obtener su ID
if ($es_odontologo) {
    $stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $odontologo_data = $resultado->fetch_assoc();
        $id_odontologo = $odontologo_data['id_odontologo'];
    } else {
        $_SESSION['error'] = "No se encontró información del odontólogo. Contacte al administrador.";
        redirigir('/ecodent/public/dashboard.php');
    }
}

// =============================================
// VERIFICAR QUE VIENE UN ID DE CITA VÁLIDO
// =============================================
if (!isset($_GET['id_cita'])) {
    $_SESSION['error'] = 'No se especificó la cita';
    redirigir('/ecodent/public/odontologo/calendario.php');
}

$id_cita = (int)$_GET['id_cita'];

// =============================================
// OBTENER DATOS DE LA CITA
// =============================================
if ($es_odontologo && $id_odontologo) {
    // Odontólogo ve solo sus citas
    $sql = "SELECT c.*, 
                   p.id_paciente,
                   u.nombre_completo as nombre_paciente,
                   u.email,
                   u.telefono,
                   u.fecha_registro as paciente_desde,
                   (SELECT COUNT(*) FROM citas WHERE id_paciente = c.id_paciente AND id_odontologo = ?) as total_citas_paciente
            FROM citas c
            JOIN pacientes p ON c.id_paciente = p.id_paciente
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE c.id_cita = ? AND c.id_odontologo = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iii", $id_odontologo, $id_cita, $id_odontologo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cita = $resultado->fetch_assoc();
} else {
    // Admin puede ver cualquier cita
    $sql = "SELECT c.*, 
                   p.id_paciente,
                   u.nombre_completo as nombre_paciente,
                   u.email,
                   u.telefono,
                   u.fecha_registro as paciente_desde,
                   o.id_odontologo,
                   od.nombre_completo as nombre_odontologo,
                   (SELECT COUNT(*) FROM citas WHERE id_paciente = c.id_paciente) as total_citas_paciente
            FROM citas c
            JOIN pacientes p ON c.id_paciente = p.id_paciente
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            JOIN odontologos o ON c.id_odontologo = o.id_odontologo
            JOIN usuarios od ON o.id_usuario = od.id_usuario
            WHERE c.id_cita = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cita = $resultado->fetch_assoc();
}

if (!$cita) {
    $_SESSION['error'] = 'Cita no encontrada o no tienes permisos para verla';
    redirigir('/ecodent/public/odontologo/calendario.php');
}

// =============================================
// PROCESAR CAMBIO DE ESTADO (Admin y Odontólogo)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    
    // Verificar permisos: admin o el odontólogo dueño de la cita
    $permiso = false;
    if ($es_admin) {
        $permiso = true;
    } elseif ($es_odontologo && isset($cita['id_odontologo']) && $cita['id_odontologo'] == $id_odontologo) {
        $permiso = true;
    }
    
    if (!$permiso) {
        $_SESSION['error'] = "No tienes permisos para modificar esta cita";
        redirigir('/ecodent/public/odontologo/detalle_cita.php?id_cita=' . $id_cita);
    }
    
    $nuevo_estado = $_POST['estado'];
    $actualizar = false;
    
    if ($nuevo_estado == 'completada' && $cita['estado'] != 'completada') {
        $sql_update = "UPDATE citas SET estado = 'completada' WHERE id_cita = ?";
        $actualizar = true;
        $mensaje = "✅ Cita marcada como completada";
        
    } elseif ($nuevo_estado == 'ausente' && $cita['estado'] != 'ausente') {
        $sql_update = "UPDATE citas SET estado = 'ausente' WHERE id_cita = ?";
        $actualizar = true;
        $mensaje = "⚠️ Paciente marcado como ausente";
        
        // Actualizar contador de ausencias en paciente
        $sql_ausencia = "UPDATE pacientes SET ausencias_sin_aviso = ausencias_sin_aviso + 1,
                         fecha_ultima_ausencia = CURDATE()
                         WHERE id_paciente = ?";
        $stmt_ausencia = $conexion->prepare($sql_ausencia);
        $stmt_ausencia->bind_param("i", $cita['id_paciente']);
        $stmt_ausencia->execute();
        
        // Ejecutar procedimiento para actualizar estado de cuenta
        $stmt_proc = $conexion->prepare("CALL verificar_estado_cuenta(?)");
        $stmt_proc->bind_param("i", $cita['id_paciente']);
        $stmt_proc->execute();
        
    } elseif ($nuevo_estado == 'llego_tarde' && in_array($cita['estado'], ['programada', 'confirmada'])) {
        $minutos_tarde = (int)$_POST['minutos_tarde'];
        $sql_update = "UPDATE citas SET estado = 'completada', llego_tarde = TRUE, minutos_tarde = ? 
                       WHERE id_cita = ?";
        $actualizar = true;
        $mensaje = "⏰ Paciente marcado como llegó tarde ($minutos_tarde minutos)";
        
        $sql_tarde = "UPDATE pacientes SET llegadas_tarde = llegadas_tarde + 1
                      WHERE id_paciente = ?";
        $stmt_tarde = $conexion->prepare($sql_tarde);
        $stmt_tarde->bind_param("i", $cita['id_paciente']);
        $stmt_tarde->execute();
        
        // Ejecutar procedimiento para actualizar estado de cuenta
        $stmt_proc = $conexion->prepare("CALL verificar_estado_cuenta(?)");
        $stmt_proc->bind_param("i", $cita['id_paciente']);
        $stmt_proc->execute();
    }
    
    if ($actualizar) {
        $stmt_update = $conexion->prepare($sql_update);
        
        if ($nuevo_estado == 'llego_tarde') {
            $stmt_update->bind_param("ii", $minutos_tarde, $id_cita);
        } else {
            $stmt_update->bind_param("i", $id_cita);
        }
        
        if ($stmt_update->execute()) {
            $_SESSION['exito'] = $mensaje;
            redirigir('/ecodent/public/odontologo/detalle_cita.php?id_cita=' . $id_cita);
        }
    }
}

// =============================================
// PROCESAR CANCELACIÓN (Admin y Odontólogo)
// =============================================
if (isset($_POST['cancelar_cita']) && ($es_admin || $es_odontologo)) {
    
    $motivo = sanitizar($_POST['motivo_cancelacion']);
    $quien_cancela = $es_admin ? 'admin' : 'odontologo';
    $id_quien_cancela = $es_admin ? $_SESSION['id_usuario'] : $id_odontologo;
    
    // Para admin, el slot queda disponible (no se bloquea)
    // Para odontólogo, el slot se bloquea automáticamente
    $nuevo_estado = ($es_admin) ? 'cancelada_pac' : 'cancelada_doc';
    
    $conexion->begin_transaction();
    
    try {
        // Actualizar cita
        $stmt = $conexion->prepare("
            UPDATE citas 
            SET estado = ?, 
                cancelado_por = ?, 
                fecha_cancelacion = NOW(), 
                motivo_cancelacion = ?
            WHERE id_cita = ?
        ");
        $stmt->bind_param("sisi", $nuevo_estado, $id_quien_cancela, $motivo, $id_cita);
        $stmt->execute();
        
        // Si es odontólogo quien cancela, bloquear el slot
        if (!$es_admin) {
            $hora_fin = date('H:i:s', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']) + (40 * 60));
            $stmt_bloq = $conexion->prepare("
                INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo)
                VALUES (?, ?, ?, ?, ?)
            ");
            $motivo_bloqueo = "Cancelación por odontólogo: " . $motivo;
            $stmt_bloq->bind_param("issss", $cita['id_odontologo'], $cita['fecha_cita'], $cita['hora_cita'], $hora_fin, $motivo_bloqueo);
            $stmt_bloq->execute();
        }
        
        $conexion->commit();
        $_SESSION['exito'] = "🗑️ Cita cancelada exitosamente";
        redirigir('/ecodent/public/odontologo/calendario.php');
        
    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['error'] = "Error al cancelar: " . $e->getMessage();
        redirigir('/ecodent/public/odontologo/detalle_cita.php?id_cita=' . $id_cita);
    }
}

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';

// Mostrar mensajes
if (isset($_SESSION['exito'])) {
    echo '<div class="alert alert-success alert-dismissible fade show m-3">
            <i class="bi bi-check-circle-fill"></i> ' . $_SESSION['exito'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['exito']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show m-3">
            <i class="bi bi-exclamation-triangle-fill"></i> ' . $_SESSION['error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error']);
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1><i class="bi bi-calendar-check text-primary"></i> Detalle de Cita</h1>
                <?php if ($es_admin): ?>
                    <span class="badge bg-danger fs-6 p-2">
                        <i class="bi bi-shield-shaded"></i> Modo Administrador
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($es_admin && isset($cita['nombre_odontologo'])): ?>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Vista de administrador</strong> - Odontólogo: Dr. <?php echo htmlspecialchars($cita['nombre_odontologo']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <!-- Información de la cita -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información de la Cita</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="35%">Fecha:</th>
                            <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                        </tr>
                        <tr>
                            <th>Hora:</th>
                            <td>
                                <strong><?php echo date('h:i A', strtotime($cita['hora_cita'])); ?></strong> 
                                - 
                                <strong><?php echo date('h:i A', strtotime($cita['hora_fin'])); ?></strong>
                                <span class="badge bg-secondary ms-2">40 min</span>
                            </td>
                        </tr>
                        <?php if ($es_admin && isset($cita['nombre_odontologo'])): ?>
                        <tr>
                            <th>Odontólogo:</th>
                            <td><i class="bi bi-hospital"></i> Dr. <?php echo htmlspecialchars($cita['nombre_odontologo']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Estado:</th>
                            <td>
                                <?php
                                $estados = [
                                    'programada' => ['badge bg-primary', '📅 Programada'],
                                    'confirmada' => ['badge bg-success', '✅ Confirmada'],
                                    'completada' => ['badge bg-secondary', '✔️ Completada'],
                                    'cancelada_pac' => ['badge bg-danger', '❌ Cancelada (Paciente)'],
                                    'cancelada_doc' => ['badge bg-danger', '❌ Cancelada (Doctor)'],
                                    'ausente' => ['badge bg-warning', '⚠️ No Asistió']
                                ];
                                $estado_info = $estados[$cita['estado']] ?? ['badge bg-secondary', $cita['estado']];
                                ?>
                                <span class="<?php echo $estado_info[0]; ?>"><?php echo $estado_info[1]; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th>Motivo:</th>
                            <td><?php echo htmlspecialchars($cita['motivo'] ?? 'No especificado'); ?></td>
                        </tr>
                        <?php if ($cita['llego_tarde']): ?>
                        <tr class="table-warning">
                            <th>Llegó tarde:</th>
                            <td class="text-danger">
                                <i class="bi bi-clock-history"></i> Sí (<?php echo $cita['minutos_tarde']; ?> minutos de retraso)
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($cita['fecha_cancelacion']): ?>
                        <tr class="table-danger">
                            <th>Cancelada el:</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($cita['fecha_cancelacion'])); ?></td>
                        </tr>
                        <tr class="table-danger">
                            <th>Motivo cancelación:</th>
                            <td><?php echo htmlspecialchars($cita['motivo_cancelacion'] ?? 'No especificado'); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- Acciones según estado - Admin y Odontólogo -->
            <?php if (($es_admin || $es_odontologo) && in_array($cita['estado'], ['programada', 'confirmada'])): ?>
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clipboard-check"></i> Registrar Asistencia
                            <?php if ($es_admin): ?>
                                <span class="badge bg-light text-dark ms-2">Admin</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formAsistencia">
                            <div class="mb-3">
                                <label class="form-label fw-bold">¿El paciente asistió a la cita?</label>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-success btn-lg" id="btnAsistio">
                                        <i class="bi bi-check-circle-fill"></i> Sí, asistió
                                    </button>
                                    
                                    <button type="button" class="btn btn-warning btn-lg" data-bs-toggle="collapse" data-bs-target="#llegoTardeForm">
                                        <i class="bi bi-clock-history"></i> Llegó tarde
                                    </button>
                                    
                                    <div class="collapse mt-2" id="llegoTardeForm">
                                        <div class="card card-body bg-light">
                                            <div class="mb-3">
                                                <label for="minutos_tarde" class="form-label">Minutos de retraso:</label>
                                                <input type="number" class="form-control" id="minutos_tarde" 
                                                       name="minutos_tarde" min="1" max="120" value="15">
                                            </div>
                                            <button type="button" class="btn btn-warning" id="btnLlegoTarde">
                                                <i class="bi bi-clock"></i> Confirmar llegada tarde
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-danger btn-lg" id="btnAusente">
                                        <i class="bi bi-x-circle-fill"></i> No asistió (ausente)
                                    </button>
                                </div>
                                <input type="hidden" name="estado" id="estado_input">
                                <input type="hidden" name="cambiar_estado" value="1">
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Botón Cancelar Cita -->
                <div class="card mb-3 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Cancelar Cita</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" onsubmit="return confirm('¿Estás seguro de cancelar esta cita?\n\nEsta acción no se puede deshacer.')">
                            <div class="mb-3">
                                <label class="form-label">Motivo de cancelación:</label>
                                <select name="motivo_cancelacion" class="form-select" required>
                                    <option value="">Seleccionar motivo...</option>
                                    <option value="paciente_no_pudo_asistir">Paciente no pudo asistir</option>
                                    <option value="emergencia_paciente">Emergencia del paciente</option>
                                    <?php if (!$es_admin): ?>
                                    <option value="emergencia_doctor">Emergencia del doctor</option>
                                    <option value="capacitacion">Capacitación / Curso</option>
                                    <option value="problemas_tecnicos">Problemas técnicos</option>
                                    <?php endif; ?>
                                    <option value="otro">Otro motivo</option>
                                </select>
                            </div>
                            <button type="submit" name="cancelar_cita" class="btn btn-danger w-100">
                                <i class="bi bi-x-circle"></i> Cancelar Cita
                            </button>
                        </form>
                        <?php if (!$es_admin): ?>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Al cancelar, este horario quedará BLOQUEADO automáticamente.
                            </small>
                        <?php else: ?>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Como admin, el slot quedará DISPONIBLE para otros pacientes.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Botón para volver -->
            <div class="mt-3">
                <?php if ($es_admin && isset($cita['id_odontologo'])): ?>
                    <a href="calendario.php?ver_odontologo=<?php echo $cita['id_odontologo']; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Calendario
                    </a>
                <?php else: ?>
                    <a href="calendario.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Calendario
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Información del paciente -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-person-circle"></i> Información del Paciente</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">Nombre:</th>
                            <td><strong><?php echo htmlspecialchars($cita['nombre_paciente']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($cita['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Teléfono:</th>
                            <td><i class="bi bi-phone"></i> <?php echo $cita['telefono'] ?? 'No registrado'; ?></td>
                        </tr>
                        <tr>
                            <th>Paciente desde:</th>
                            <td><?php echo date('d/m/Y', strtotime($cita['paciente_desde'])); ?></td>
                        </tr>
                        <tr>
                            <th>Total citas:</th>
                            <td><span class="badge bg-primary"><?php echo $cita['total_citas_paciente']; ?> citas</span></td>
                        </tr>
                    </table>
                    
                    <div class="d-grid gap-2">
                        <a href="historial_paciente.php?id_paciente=<?php echo $cita['id_paciente']; ?>" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-clock-history"></i> Ver historial completo del paciente
                        </a>
                        <?php if ($es_admin): ?>
                            <a href="paciente_detalle.php?id=<?php echo $cita['id_paciente']; ?>" 
                               class="btn btn-outline-info">
                                <i class="bi bi-eye"></i> Ver ficha del paciente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Historial de modificaciones -->
            <?php
            $stmt_historial = $conexion->prepare("
                SELECT * FROM historial_modificaciones_citas 
                WHERE id_cita = ? 
                ORDER BY fecha_modificacion DESC 
                LIMIT 5
            ");
            $stmt_historial->bind_param("i", $id_cita);
            $stmt_historial->execute();
            $historial = $stmt_historial->get_result();
            ?>
            
            <?php if ($historial->num_rows > 0): ?>
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Modificaciones</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while($h = $historial->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <small>
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('d/m/Y', strtotime($h['fecha_anterior'])); ?> 
                                        → 
                                        <?php echo date('d/m/Y', strtotime($h['fecha_nueva'])); ?>
                                    </small>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($h['fecha_modificacion'])); ?></small>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <small>
                                        <i class="bi bi-clock"></i> 
                                        <?php echo substr($h['hora_anterior'], 0, 5); ?> 
                                        → 
                                        <?php echo substr($h['hora_nueva'], 0, 5); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Manejar los botones de asistencia
document.getElementById('btnAsistio')?.addEventListener('click', function() {
    if (confirm('¿Marcar cita como COMPLETADA?')) {
        document.getElementById('estado_input').value = 'completada';
        document.getElementById('formAsistencia').submit();
    }
});

document.getElementById('btnLlegoTarde')?.addEventListener('click', function() {
    var minutos = document.getElementById('minutos_tarde').value;
    if (confirm('¿Marcar cita como LLEGÓ TARDE con ' + minutos + ' minutos de retraso?')) {
        document.getElementById('estado_input').value = 'llego_tarde';
        document.getElementById('formAsistencia').submit();
    }
});

document.getElementById('btnAusente')?.addEventListener('click', function() {
    if (confirm('¿Marcar cita como AUSENTE?\n\nEsto afectará el estado de cuenta del paciente.')) {
        document.getElementById('estado_input').value = 'ausente';
        document.getElementById('formAsistencia').submit();
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>