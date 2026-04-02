<?php
// public/admin/horarios_odontologo.php
// Gestionar horarios de atención de cada odontólogo

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

// Verificar que se haya pasado un ID de odontólogo
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirigir('/ecodent/public/admin/gestion_odontologos.php');
}

$id_odontologo = (int)$_GET['id'];

// Obtener datos del odontólogo
$stmt = $conexion->prepare("
    SELECT o.*, u.nombre_completo, u.email, u.telefono
    FROM odontologos o
    JOIN usuarios u ON o.id_usuario = u.id_usuario
    WHERE o.id_odontologo = ?
");
$stmt->bind_param("i", $id_odontologo);
$stmt->execute();
$odontologo = $stmt->get_result()->fetch_assoc();

if (!$odontologo) {
    redirigir('/ecodent/public/admin/gestion_odontologos.php');
}

// Procesar actualización de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $id_horario = (int)$_POST['id_horario'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $stmt = $conexion->prepare("
        UPDATE horarios_odontologos 
        SET hora_inicio = ?, hora_fin = ?, activo = ?
        WHERE id_horario = ? AND id_odontologo = ?
    ");
    $stmt->bind_param("ssiii", $hora_inicio, $hora_fin, $activo, $id_horario, $id_odontologo);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Horario actualizado correctamente";
    } else {
        $_SESSION['error'] = "Error al actualizar: " . $conexion->error;
    }
    redirigir("/ecodent/public/admin/horarios_odontologo.php?id={$id_odontologo}");
}

// Procesar creación de nuevo horario
if (isset($_POST['nuevo_horario'])) {
    $dia = $_POST['dia_semana'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    
    $stmt = $conexion->prepare("
        INSERT INTO horarios_odontologos (id_odontologo, dia_semana, hora_inicio, hora_fin, activo)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("isss", $id_odontologo, $dia, $hora_inicio, $hora_fin);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Nuevo horario agregado correctamente";
    } else {
        $_SESSION['error'] = "Error al agregar: " . $conexion->error;
    }
    redirigir("/ecodent/public/admin/horarios_odontologo.php?id={$id_odontologo}");
}

// Procesar eliminación de horario
if (isset($_GET['eliminar'])) {
    $id_horario = (int)$_GET['eliminar'];
    $stmt = $conexion->prepare("
        DELETE FROM horarios_odontologos 
        WHERE id_horario = ? AND id_odontologo = ?
    ");
    $stmt->bind_param("ii", $id_horario, $id_odontologo);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Horario eliminado correctamente";
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $conexion->error;
    }
    redirigir("/ecodent/public/admin/horarios_odontologo.php?id={$id_odontologo}");
}

// Obtener horarios actuales
$horarios = $conexion->prepare("
    SELECT * FROM horarios_odontologos 
    WHERE id_odontologo = ? 
    ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')
");
$horarios->bind_param("i", $id_odontologo);
$horarios->execute();
$horarios_result = $horarios->get_result();

// Días de la semana en español
$dias_semana = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];

// Verificar qué días ya tienen horario
$dias_con_horario = [];
while ($h = $horarios_result->fetch_assoc()) {
    $dias_con_horario[] = $h['dia_semana'];
}
$horarios_result->data_seek(0); // Resetear el puntero

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <!-- Botón volver -->
    <div class="mb-3">
        <a href="gestion_odontologos.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver a Odontólogos
        </a>
    </div>
    
    <!-- Información del odontólogo -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <i class="bi bi-person-badge"></i> 
                <?php echo htmlspecialchars($odontologo['nombre_completo']); ?>
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted">Email:</small>
                    <p><?php echo htmlspecialchars($odontologo['email']); ?></p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Teléfono:</small>
                    <p><?php echo htmlspecialchars($odontologo['telefono'] ?: 'No registrado'); ?></p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Especialidad:</small>
                    <p><?php echo htmlspecialchars($odontologo['especialidad_principal']); ?></p>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Duración cita:</small>
                    <p><span class="badge bg-info"><?php echo $odontologo['duracion_cita_min']; ?> minutos</span></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Horarios actuales -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Horarios de Atención</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoHorario">
                    <i class="bi bi-plus-circle"></i> Agregar Horario
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Día</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Slots generados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($horarios_result->num_rows > 0): ?>
                        <?php while($horario = $horarios_result->fetch_assoc()): 
                            // Calcular cuántos slots de 40 min caben en este horario
                            $inicio = strtotime($horario['hora_inicio']);
                            $fin = strtotime($horario['hora_fin']);
                            $duracion = $odontologo['duracion_cita_min'];
                            $total_slots = floor(($fin - $inicio) / ($duracion * 60));
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo $dias_semana[$horario['dia_semana']]; ?></strong>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_horario" value="<?php echo $horario['id_horario']; ?>">
                                    <input type="time" name="hora_inicio" value="<?php echo $horario['hora_inicio']; ?>" 
                                           class="form-control form-control-sm d-inline-block w-auto" style="width: 100px;">
                            </td>
                            <td>
                                    <input type="time" name="hora_fin" value="<?php echo $horario['hora_fin']; ?>" 
                                           class="form-control form-control-sm d-inline-block w-auto" style="width: 100px;">
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $total_slots; ?> slots</span>
                                <small class="text-muted d-block">(cada <?php echo $duracion; ?> min)</small>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="activo" class="form-check-input" 
                                           onchange="this.form.submit()" <?php echo $horario['activo'] ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td>
                                <button type="submit" name="guardar" class="btn btn-sm btn-success">
                                    <i class="bi bi-save"></i>
                                </button>
                                </form>
                                <a href="?id=<?php echo $id_odontologo; ?>&eliminar=<?php echo $horario['id_horario']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Eliminar este horario?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                No hay horarios configurados para este odontólogo
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Información de slots -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> ¿Cómo funcionan los slots?</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>📅 Generación de slots:</h6>
                    <p class="small text-muted">
                        El sistema genera automáticamente los slots de <strong><?php echo $odontologo['duracion_cita_min']; ?> minutos</strong> 
                        basándose en el rango horario que definas. Por ejemplo:
                    </p>
                    <ul class="small">
                        <li>Horario: 08:00 - 18:00</li>
                        <li>Slots generados: 08:00, 08:40, 09:20, 10:00... (15 slots)</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>🔒 Bloqueo de slots:</h6>
                    <p class="small text-muted">
                        Los odontólogos pueden bloquear slots específicos desde su calendario:
                    </p>
                    <ul class="small">
                        <li>✅ <strong>Paciente cancela</strong> → Slot queda disponible</li>
                        <li>❌ <strong>Odontólogo cancela</strong> → Slot se bloquea automáticamente</li>
                        <li>🔓 El odontólogo puede desbloquear manualmente si se recupera</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Horario -->
<div class="modal fade" id="modalNuevoHorario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar Horario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Día de la semana *</label>
                        <select name="dia_semana" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($dias_semana as $key => $nombre): ?>
                                <?php if (!in_array($key, $dias_con_horario)): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $nombre; ?></option>
                                <?php else: ?>
                                    <option value="<?php echo $key; ?>" disabled>
                                        <?php echo $nombre; ?> (ya tiene horario)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Solo se muestran días sin horario asignado</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora de inicio *</label>
                            <input type="time" name="hora_inicio" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora de fin *</label>
                            <input type="time" name="hora_fin" class="form-control" value="18:00" required>
                        </div>
                    </div>
                    <div class="alert alert-info small">
                        <i class="bi bi-lightbulb"></i> Recomendación: El rango mínimo para generar al menos un slot es de <?php echo $odontologo['duracion_cita_min']; ?> minutos.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="nuevo_horario" class="btn btn-primary">Agregar Horario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['mensaje'])): ?>
<script>
    alert('<?php echo $_SESSION['mensaje']; ?>');
</script>
<?php unset($_SESSION['mensaje']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
    alert('Error: <?php echo $_SESSION['error']; ?>');
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php require_once '../../includes/footer.php'; ?>