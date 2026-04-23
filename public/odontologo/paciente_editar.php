<?php
// public/odontologo/paciente_editar.php
// Editar información del paciente

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_odontologo
$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

// Verificar que se haya pasado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de paciente no válido.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_paciente = $_GET['id'];

// Obtener datos del paciente
$stmt_paciente = $conexion->prepare("
    SELECT p.id_paciente, u.id_usuario, u.nombre_completo, u.email, u.telefono, 
           p.fecha_nacimiento, p.direccion, p.ausencias_sin_aviso, p.llegadas_tarde,
           p.estado_cuenta, p.puede_agendar, p.fecha_ultima_ausencia
    FROM pacientes p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_paciente = ?
");
$stmt_paciente->bind_param("i", $id_paciente);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();

if (!$paciente) {
    $_SESSION['error'] = "Paciente no encontrado.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

// Procesar actualización
$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = sanitizar($_POST['nombre']);
    $email = sanitizar($_POST['email']);
    $telefono = sanitizar($_POST['telefono']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
    $direccion = sanitizar($_POST['direccion']);
    $estado_cuenta = $_POST['estado_cuenta'];
    $puede_agendar = isset($_POST['puede_agendar']) ? 1 : 0;
    
    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre completo es obligatorio.";
    }
    
    if (empty($email)) {
        $errores[] = "El email es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email no es válido.";
    }
    
    if (empty($telefono)) {
        $errores[] = "El teléfono es obligatorio.";
    }
    
    // Verificar email único (excepto el mismo paciente)
    if (empty($errores) && $email != $paciente['email']) {
        $stmt_check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt_check->bind_param("si", $email, $paciente['id_usuario']);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $errores[] = "Este email ya está registrado por otro usuario.";
        }
    }
    
    // Si no hay errores, actualizar
    if (empty($errores)) {
        $conexion->begin_transaction();
        
        try {
            // Actualizar usuario
            $stmt_usuario = $conexion->prepare("
                UPDATE usuarios 
                SET nombre_completo = ?, email = ?, telefono = ?
                WHERE id_usuario = ?
            ");
            $stmt_usuario->bind_param("sssi", $nombre, $email, $telefono, $paciente['id_usuario']);
            $stmt_usuario->execute();
            
            // Actualizar paciente
            $stmt_paciente_update = $conexion->prepare("
                UPDATE pacientes 
                SET fecha_nacimiento = ?, direccion = ?, estado_cuenta = ?, puede_agendar = ?
                WHERE id_paciente = ?
            ");
            $stmt_paciente_update->bind_param("sssii", $fecha_nacimiento, $direccion, $estado_cuenta, $puede_agendar, $id_paciente);
            $stmt_paciente_update->execute();
            
            $conexion->commit();
            $_SESSION['exito'] = "Paciente actualizado correctamente.";
            redirigir('/ecodent/public/odontologo/paciente_detalle.php?id=' . $id_paciente);
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Obtener estadísticas del paciente
$stmt_citas = $conexion->prepare("
    SELECT 
        COUNT(*) as total_citas,
        SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN c.estado = 'cancelada_pac' THEN 1 ELSE 0 END) as canceladas_paciente,
        SUM(CASE WHEN c.estado = 'cancelada_doc' THEN 1 ELSE 0 END) as canceladas_doctor,
        SUM(CASE WHEN c.estado = 'ausente' THEN 1 ELSE 0 END) as ausencias,
        MAX(c.fecha_cita) as ultima_cita
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    WHERE t.id_paciente = ? AND t.id_odontologo = ?
");
$stmt_citas->bind_param("ii", $id_paciente, $id_odontologo);
$stmt_citas->execute();
$estadisticas = $stmt_citas->get_result()->fetch_assoc();

// Obtener tratamientos activos
$stmt_tratamientos = $conexion->prepare("
    SELECT id_tratamiento, nombre_tratamiento, estado, costo_total, total_pagado, saldo_pendiente
    FROM tratamientos
    WHERE id_paciente = ? AND id_odontologo = ? AND estado != 'cancelado'
    ORDER BY fecha_creacion DESC
    LIMIT 5
");
$stmt_tratamientos->bind_param("ii", $id_paciente, $id_odontologo);
$stmt_tratamientos->execute();
$tratamientos = $stmt_tratamientos->get_result();

require_once '../../includes/header.php';
?>

<style>
.form-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.info-badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.info-badge i {
    margin-right: 8px;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.stats-number {
    font-size: 2rem;
    font-weight: bold;
}

.stats-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.estado-selector {
    border-radius: 10px;
    padding: 10px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s;
}

.estado-selector:hover {
    border-color: #4361ee;
}

.estado-option {
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.estado-option:hover {
    background: #f8f9fa;
}

.estado-option.selected {
    background: #e3f2fd;
    border-left: 3px solid #4361ee;
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-pencil-square text-primary"></i> Editar Paciente</h1>
        <p class="lead">Actualiza la información del paciente y gestiona su estado.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="paciente_detalle.php?id=<?php echo $id_paciente; ?>" class="btn btn-info">
            <i class="bi bi-eye"></i> Ver detalles
        </a>
        <a href="pacientes.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0 mt-2">
            <?php foreach ($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tarjetas de estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stats-number"><?php echo $estadisticas['total_citas'] ?? 0; ?></div>
            <div class="stats-label">Total de citas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <div class="stats-number"><?php echo $estadisticas['completadas'] ?? 0; ?></div>
            <div class="stats-label">Citas completadas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="stats-number"><?php echo $estadisticas['ausencias'] ?? 0; ?></div>
            <div class="stats-label">Ausencias</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="stats-number"><?php echo $estadisticas['canceladas_paciente'] ?? 0; ?></div>
            <div class="stats-label">Cancelaciones</div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <div class="row">
        <div class="col-md-7">
            <!-- Datos personales -->
            <div class="form-section">
                <h5><i class="bi bi-person-badge me-2 text-primary"></i> Datos personales</h5>
                <hr>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" class="form-control" name="nombre" required 
                               value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required
                               value="<?php echo htmlspecialchars($paciente['email']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono *</label>
                        <input type="tel" class="form-control" name="telefono" required
                               value="<?php echo htmlspecialchars($paciente['telefono']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" class="form-control" name="fecha_nacimiento"
                               value="<?php echo $paciente['fecha_nacimiento']; ?>">
                        <?php if ($paciente['fecha_nacimiento']): ?>
                            <small class="text-muted">
                                Edad: <?php echo date('Y') - date('Y', strtotime($paciente['fecha_nacimiento'])); ?> años
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea class="form-control" name="direccion" rows="3"><?php echo htmlspecialchars($paciente['direccion'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Estado de cuenta -->
            <div class="form-section">
                <h5><i class="bi bi-shield-check me-2 text-warning"></i> Estado de cuenta</h5>
                <hr>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Estado de cuenta</label>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <div class="estado-option <?php echo $paciente['estado_cuenta'] == 'normal' ? 'selected' : ''; ?>" 
                                     onclick="document.getElementById('estado_normal').checked = true; seleccionarEstado(this)">
                                    <input type="radio" name="estado_cuenta" id="estado_normal" value="normal" 
                                           <?php echo $paciente['estado_cuenta'] == 'normal' ? 'checked' : ''; ?> hidden>
                                    <div class="text-center">
                                        <i class="bi bi-check-circle-fill text-success fs-2"></i>
                                        <div>Normal</div>
                                        <small class="text-muted">Puede agendar</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="estado-option <?php echo $paciente['estado_cuenta'] == 'observacion' ? 'selected' : ''; ?>"
                                     onclick="document.getElementById('estado_observacion').checked = true; seleccionarEstado(this)">
                                    <input type="radio" name="estado_cuenta" id="estado_observacion" value="observacion"
                                           <?php echo $paciente['estado_cuenta'] == 'observacion' ? 'checked' : ''; ?> hidden>
                                    <div class="text-center">
                                        <i class="bi bi-eye-fill text-warning fs-2"></i>
                                        <div>Observación</div>
                                        <small class="text-muted">Requiere atención</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="estado-option <?php echo $paciente['estado_cuenta'] == 'restringida' ? 'selected' : ''; ?>"
                                     onclick="document.getElementById('estado_restringida').checked = true; seleccionarEstado(this)">
                                    <input type="radio" name="estado_cuenta" id="estado_restringida" value="restringida"
                                           <?php echo $paciente['estado_cuenta'] == 'restringida' ? 'checked' : ''; ?> hidden>
                                    <div class="text-center">
                                        <i class="bi bi-exclamation-triangle-fill text-danger fs-2"></i>
                                        <div>Restringida</div>
                                        <small class="text-muted">Límite de citas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="estado-option <?php echo $paciente['estado_cuenta'] == 'bloqueada' ? 'selected' : ''; ?>"
                                     onclick="document.getElementById('estado_bloqueada').checked = true; seleccionarEstado(this)">
                                    <input type="radio" name="estado_cuenta" id="estado_bloqueada" value="bloqueada"
                                           <?php echo $paciente['estado_cuenta'] == 'bloqueada' ? 'checked' : ''; ?> hidden>
                                    <div class="text-center">
                                        <i class="bi bi-ban-fill text-dark fs-2"></i>
                                        <div>Bloqueada</div>
                                        <small class="text-muted">No puede agendar</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="puede_agendar" id="puede_agendar" 
                                   value="1" <?php echo $paciente['puede_agendar'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="puede_agendar">
                                <i class="bi bi-calendar-check"></i> El paciente puede agendar citas
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-5">
            <!-- Información adicional -->
            <div class="form-section">
                <h5><i class="bi bi-info-circle me-2 text-info"></i> Información adicional</h5>
                <hr>
                
                <div class="mb-3">
                    <label class="form-label">Ausencias sin aviso</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-x"></i></span>
                        <input type="number" class="form-control" value="<?php echo $paciente['ausencias_sin_aviso']; ?>" readonly disabled>
                    </div>
                    <small class="text-muted">Se actualiza automáticamente cuando el paciente no asiste</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Llegadas tarde</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                        <input type="number" class="form-control" value="<?php echo $paciente['llegadas_tarde']; ?>" readonly disabled>
                    </div>
                    <small class="text-muted">Se actualiza automáticamente cuando llega tarde</small>
                </div>
                
                <?php if ($paciente['fecha_ultima_ausencia']): ?>
                    <div class="mb-3">
                        <label class="form-label">Última ausencia</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar-x"></i></span>
                            <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($paciente['fecha_ultima_ausencia'])); ?>" readonly disabled>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label">Última cita</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                        <input type="text" class="form-control" value="<?php echo $estadisticas['ultima_cita'] ? date('d/m/Y', strtotime($estadisticas['ultima_cita'])) : 'Sin citas'; ?>" readonly disabled>
                    </div>
                </div>
            </div>
            
            <!-- Tratamientos recientes -->
            <?php if ($tratamientos->num_rows > 0): ?>
                <div class="form-section">
                    <h5><i class="bi bi-file-medical me-2 text-success"></i> Tratamientos recientes</h5>
                    <hr>
                    
                    <div class="list-group">
                        <?php while ($tratamiento = $tratamientos->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($tratamiento['nombre_tratamiento']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Estado: 
                                            <?php
                                            $badge_class = 'secondary';
                                            if ($tratamiento['estado'] == 'pendiente') $badge_class = 'warning';
                                            if ($tratamiento['estado'] == 'en_progreso') $badge_class = 'info';
                                            if ($tratamiento['estado'] == 'completado') $badge_class = 'success';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($tratamiento['estado']); ?></span>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div>S/. <?php echo number_format($tratamiento['saldo_pendiente'], 2); ?></div>
                                        <small class="text-muted">Pendiente</small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="mt-3">
                        <a href="tratamientos.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-plus-circle"></i> Ver todos los tratamientos
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
                <a href="paciente_detalle.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Guardar cambios
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function seleccionarEstado(elemento) {
    // Remover clase selected de todos
    document.querySelectorAll('.estado-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    // Agregar clase selected al elemento clickeado
    elemento.classList.add('selected');
}

// Mantener el estado seleccionado al cargar
document.querySelectorAll('.estado-option').forEach(option => {
    const radio = option.querySelector('input[type="radio"]');
    if (radio && radio.checked) {
        option.classList.add('selected');
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>