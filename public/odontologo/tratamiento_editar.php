<?php
// public/odontologo/tratamiento_editar.php
// Editar tratamiento existente

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_odontologo
$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

// Verificar que se haya pasado un ID de tratamiento
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de tratamiento no válido.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_tratamiento = $_GET['id'];
$from = $_GET['from'] ?? null;
$id_cita = $_GET['id_cita'] ?? null;

// Obtener datos del tratamiento
$stmt_tratamiento = $conexion->prepare("
    SELECT t.*, u.nombre_completo as paciente_nombre, p.id_paciente
    FROM tratamientos t
    JOIN pacientes p ON t.id_paciente = p.id_paciente
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE t.id_tratamiento = ? AND t.id_odontologo = ?
");
$stmt_tratamiento->bind_param("ii", $id_tratamiento, $id_odontologo);
$stmt_tratamiento->execute();
$tratamiento = $stmt_tratamiento->get_result()->fetch_assoc();

if (!$tratamiento) {
    $_SESSION['error'] = "Tratamiento no encontrado o no tienes permisos para editarlo.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_paciente = $tratamiento['id_paciente'];
$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre_tratamiento = sanitizar($_POST['nombre_tratamiento']);
    $descripcion = sanitizar($_POST['descripcion']);
    $costo_total = (float)$_POST['costo_total'];
    $fecha_inicio = $_POST['fecha_inicio'] ?: null;
    $fecha_fin = $_POST['fecha_fin'] ?: null;
    $estado = $_POST['estado'];
    $notas = sanitizar($_POST['notas']);
    
    // Validaciones
    if (empty($nombre_tratamiento)) {
        $errores[] = "El nombre del tratamiento es obligatorio.";
    }
    
    if ($costo_total <= 0) {
        $errores[] = "El costo total debe ser mayor a 0.";
    }
    
    if ($fecha_inicio && $fecha_fin && strtotime($fecha_fin) < strtotime($fecha_inicio)) {
        $errores[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    }
    
    // Si no hay errores, actualizar
    if (empty($errores)) {
        $conexion->begin_transaction();
        
        try {
            $stmt_update = $conexion->prepare("
                UPDATE tratamientos 
                SET nombre_tratamiento = ?, 
                    descripcion = ?, 
                    costo_total = ?, 
                    fecha_inicio = ?, 
                    fecha_fin = ?, 
                    estado = ?, 
                    notas = ?
                WHERE id_tratamiento = ? AND id_odontologo = ?
            ");
            $stmt_update->bind_param("ssdssssii", 
                $nombre_tratamiento, $descripcion, $costo_total,
                $fecha_inicio, $fecha_fin, $estado, $notas,
                $id_tratamiento, $id_odontologo
            );
            $stmt_update->execute();
            
            $conexion->commit();
            
            $_SESSION['exito'] = "Tratamiento actualizado correctamente.";
            
            // Redirigir según origen
            if ($from === 'cita' && $id_cita) {
                redirigir('/ecodent/public/odontologo/detalle_cita.php?id_cita=' . $id_cita);
            } else {
                redirigir('/ecodent/public/odontologo/tratamiento_detalle.php?id=' . $id_tratamiento);
            }
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

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

.paciente-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-pencil-square text-warning"></i> Editar Tratamiento</h1>
        <p class="lead">Actualiza la información del tratamiento.</p>
        <?php if ($from === 'cita'): ?>
            <div class="alert alert-warning mt-2">
                <i class="bi bi-info-circle"></i>
                Este tratamiento fue creado automáticamente al agendar la cita.
                Complétalo con el nombre real y el costo.
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($from === 'cita' && $id_cita): ?>
            <a href="detalle_cita.php?id_cita=<?php echo $id_cita; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a la cita
            </a>
        <?php else: ?>
            <a href="tratamiento_detalle.php?id=<?php echo $id_tratamiento; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al tratamiento
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="paciente-info">
    <div class="d-flex align-items-center">
        <i class="bi bi-person-circle fs-2 me-3"></i>
        <div>
            <h5 class="mb-0">Paciente: <?php echo htmlspecialchars($tratamiento['paciente_nombre']); ?></h5>
            <small>ID: <?php echo $id_paciente; ?></small>
        </div>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0 mt-2">
            <?php foreach ($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <div class="form-section">
        <h5><i class="bi bi-info-circle me-2 text-primary"></i> Información del tratamiento</h5>
        <hr>
        
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Nombre del tratamiento *</label>
                <input type="text" class="form-control" name="nombre_tratamiento" required
                       placeholder="Ej: Ortodoncia, Implante dental, Endodoncia..."
                       value="<?php echo htmlspecialchars($tratamiento['nombre_tratamiento']); ?>">
            </div>
            
            <div class="col-md-4 mb-3">
                <label class="form-label">Costo total *</label>
                <div class="input-group">
                    <span class="input-group-text">S/.</span>
                    <input type="number" class="form-control" name="costo_total" step="0.01" required
                           value="<?php echo $tratamiento['costo_total']; ?>">
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" rows="3" 
                      placeholder="Describe el tratamiento..."><?php echo htmlspecialchars($tratamiento['descripcion'] ?? ''); ?></textarea>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de inicio</label>
                <input type="date" class="form-control" name="fecha_inicio"
                       value="<?php echo $tratamiento['fecha_inicio']; ?>">
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de fin (estimada)</label>
                <input type="date" class="form-control" name="fecha_fin"
                       value="<?php echo $tratamiento['fecha_fin']; ?>">
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="pendiente" <?php echo $tratamiento['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="en_progreso" <?php echo $tratamiento['estado'] == 'en_progreso' ? 'selected' : ''; ?>>En progreso</option>
                    <option value="completado" <?php echo $tratamiento['estado'] == 'completado' ? 'selected' : ''; ?>>Completado</option>
                    <option value="cancelado" <?php echo $tratamiento['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Notas adicionales</label>
            <textarea class="form-control" name="notas" rows="2" 
                      placeholder="Información adicional sobre el tratamiento..."><?php echo htmlspecialchars($tratamiento['notas'] ?? ''); ?></textarea>
        </div>
    </div>
    
    <div class="text-end">
        <?php if ($from === 'cita' && $id_cita): ?>
            <a href="detalle_cita.php?id_cita=<?php echo $id_cita; ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        <?php else: ?>
            <a href="tratamiento_detalle.php?id=<?php echo $id_tratamiento; ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save"></i> Guardar cambios
        </button>
    </div>
</form>

<?php
require_once '../../includes/footer.php';
?>