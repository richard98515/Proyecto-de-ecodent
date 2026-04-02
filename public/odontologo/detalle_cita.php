<?php
// public/odontologo/detalle_cita.php
// Muestra el detalle de una cita específica

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
// PROCESAR CAMBIO DE ESTADO (solo odontólogos)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado']) && $es_odontologo) {
    
    $nuevo_estado = $_POST['estado'];
    $actualizar = false;
    
    if ($nuevo_estado == 'completada' && $cita['estado'] != 'completada') {
        // Paciente asistió
        $sql_update = "UPDATE citas SET estado = 'completada' WHERE id_cita = ?";
        $actualizar = true;
        $mensaje = "Cita marcada como completada";
        
    } elseif ($nuevo_estado == 'ausente' && $cita['estado'] != 'ausente') {
        // Paciente no asistió
        $sql_update = "UPDATE citas SET estado = 'ausente' WHERE id_cita = ?";
        $actualizar = true;
        $mensaje = "Paciente marcado como ausente";
        
        // Actualizar contador de ausencias en paciente
        $sql_ausencia = "UPDATE pacientes SET ausencias_sin_aviso = ausencias_sin_aviso + 1,
                         fecha_ultima_ausencia = CURDATE()
                         WHERE id_paciente = ?";
        $stmt_ausencia = $conexion->prepare($sql_ausencia);
        $stmt_ausencia->bind_param("i", $cita['id_paciente']);
        $stmt_ausencia->execute();
        
    } elseif ($nuevo_estado == 'llego_tarde' && $cita['estado'] == 'programada') {
        // Paciente llegó tarde
        $minutos_tarde = (int)$_POST['minutos_tarde'];
        $sql_update = "UPDATE citas SET estado = 'completada', llego_tarde = TRUE, minutos_tarde = ? 
                       WHERE id_cita = ?";
        $actualizar = true;
        $mensaje = "Paciente marcado como llegó tarde ($minutos_tarde minutos)";
        
        // Actualizar contador de llegadas tarde
        $sql_tarde = "UPDATE pacientes SET llegadas_tarde = llegadas_tarde + 1
                      WHERE id_paciente = ?";
        $stmt_tarde = $conexion->prepare($sql_tarde);
        $stmt_tarde->bind_param("i", $cita['id_paciente']);
        $stmt_tarde->execute();
    }
    
    if ($actualizar) {
        $stmt_update = $conexion->prepare($sql_update);
        
        // Si es llego_tarde, necesita 2 parámetros
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

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-calendar-check"></i> Detalle de Cita</h1>
        <hr>
        
        <?php if (isset($exito)): ?>
            <div class="alert alert-success"><?php echo $exito; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($es_admin): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Vista de administrador - <?php echo isset($cita['nombre_odontologo']) ? 'Odontólogo: ' . htmlspecialchars($cita['nombre_odontologo']) : ''; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <!-- Información de la cita -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de la Cita</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Fecha:</th>
                        <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    </tr>
                    <tr>
                        <th>Hora:</th>
                        <td><?php echo date('h:i A', strtotime($cita['hora_cita']))." - Hasta - ".date('h:i A', strtotime($cita['hora_fin'])); ?></td>
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
                                'cancelada_pac' => 'Cancelada (Paciente)',
                                'cancelada_doc' => 'Cancelada (Doctor)',
                                'ausente' => 'No Asistió'
                            ];
                            $clase = $estados[$cita['estado']] ?? 'badge bg-secondary';
                            ?>
                            <span class="<?php echo $clase; ?>"><?php echo $estado_texto[$cita['estado']] ?? $cita['estado']; ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Motivo:</th>
                        <td><?php echo $cita['motivo'] ?? 'No especificado'; ?></td>
                    </tr>
                    <?php if ($cita['llego_tarde']): ?>
                        <tr>
                            <th>Llegó tarde:</th>
                            <td class="text-danger">Sí (<?php echo $cita['minutos_tarde']; ?> minutos)</td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($cita['fecha_cancelacion']): ?>
                        <tr>
                            <th>Cancelada el:</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($cita['fecha_cancelacion'])); ?></td>
                        </tr>
                        <tr>
                            <th>Motivo cancelación:</th>
                            <td><?php echo $cita['motivo_cancelacion'] ?? 'No especificado'; ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Acciones según estado - Solo para odontólogos -->
        <?php if ($es_odontologo && ($cita['estado'] == 'programada' || $cita['estado'] == 'confirmada')): ?>
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Registrar Asistencia</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">¿El paciente asistió?</label>
                            <div class="d-grid gap-2">
                                <button type="submit" name="cambiar_estado" value="1" 
                                        class="btn btn-success"
                                        onclick="return confirm('¿Marcar cita como COMPLETADA?')">
                                    <i class="bi bi-check-circle"></i> Sí, asistió
                                </button>
                                
                                <button type="button" class="btn btn-warning" 
                                        data-bs-toggle="collapse" data-bs-target="#llegoTardeForm">
                                    <i class="bi bi-clock"></i> Llegó tarde
                                </button>
                                
                                <div class="collapse mt-2" id="llegoTardeForm">
                                    <div class="card card-body">
                                        <div class="mb-3">
                                            <label for="minutos_tarde">Minutos de retraso:</label>
                                            <input type="number" class="form-control" id="minutos_tarde" 
                                                   name="minutos_tarde" min="1" max="120" value="15">
                                        </div>
                                        <button type="submit" name="cambiar_estado" value="2" 
                                                class="btn btn-warning">
                                            Confirmar llegada tarde
                                        </button>
                                    </div>
                                </div>
                                
                                <button type="submit" name="cambiar_estado" value="3" 
                                        class="btn btn-danger"
                                        onclick="return confirm('¿Marcar cita como AUSENTE?\n\nEsto afectará el estado de cuenta del paciente.')">
                                    <i class="bi bi-x-circle"></i> No asistió (ausente)
                                </button>
                            </div>
                            <input type="hidden" name="estado" id="estado_input">
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Botón para cancelar - Solo para odontólogos -->
        <?php if ($es_odontologo && ($cita['estado'] == 'programada' || $cita['estado'] == 'confirmada')): ?>
            <div class="d-grid gap-2">
                <a href="cancelar_cita.php?id_cita=<?php echo $id_cita; ?>" 
                   class="btn btn-danger">
                    <i class="bi bi-x-circle"></i> Cancelar Cita
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Botón para volver al calendario del odontólogo correspondiente -->
        <div class="mt-3">
            <?php if ($es_admin && isset($cita['id_odontologo'])): ?>
                <a href="calendario.php?ver_odontologo=<?php echo $cita['id_odontologo']; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Calendario del Odontólogo
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
                <h5 class="mb-0">Información del Paciente</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Nombre:</th>
                        <td><?php echo htmlspecialchars($cita['nombre_paciente']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($cita['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Teléfono:</th>
                        <td><?php echo $cita['telefono'] ?? 'No registrado'; ?></td>
                    </tr>
                    <tr>
                        <th>Paciente desde:</th>
                        <td><?php echo date('d/m/Y', strtotime($cita['paciente_desde'])); ?></td>
                    </tr>
                    <tr>
                        <th>Total citas:</th>
                        <td><?php echo $cita['total_citas_paciente']; ?></td>
                    </tr>
                </table>
                
                <div class="d-grid gap-2">
                    <a href="historial_paciente.php?id_paciente=<?php echo $cita['id_paciente']; ?>" 
                       class="btn btn-outline-primary">
                        <i class="bi bi-clock-history"></i> Ver historial del paciente
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Manejar los diferentes botones de estado
document.querySelectorAll('button[type="submit"][name="cambiar_estado"]').forEach(button => {
    button.addEventListener('click', function(e) {
        let estadoInput = document.getElementById('estado_input');
        if (this.value == '1') {
            estadoInput.value = 'completada';
        } else if (this.value == '2') {
            estadoInput.value = 'llego_tarde';
        } else if (this.value == '3') {
            estadoInput.value = 'ausente';
        }
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>