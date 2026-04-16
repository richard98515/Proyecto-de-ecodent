<?php
// public/odontologo/tratamiento_nuevo.php
// Registrar nuevo tratamiento para paciente

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

// Verificar que se haya pasado un ID de paciente
if (!isset($_GET['paciente']) || !is_numeric($_GET['paciente'])) {
    $_SESSION['error'] = "ID de paciente no válido.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_paciente = $_GET['paciente'];
$return_to = $_GET['return_to'] ?? null;
$fecha_retorno = $_GET['fecha'] ?? date('Y-m-d');

// Obtener datos del paciente
$stmt_paciente = $conexion->prepare("
    SELECT u.nombre_completo 
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
    
    // Si no hay errores, insertar
    if (empty($errores)) {
        $conexion->begin_transaction();
        
        try {
            $stmt_tratamiento = $conexion->prepare("
                INSERT INTO tratamientos (id_paciente, id_odontologo, nombre_tratamiento, descripcion, 
                                          costo_total, total_pagado, fecha_inicio, fecha_fin, estado, notas)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ");
            $stmt_tratamiento->bind_param("iissdssss", 
                $id_paciente, $id_odontologo, $nombre_tratamiento, $descripcion,
                $costo_total, $fecha_inicio, $fecha_fin, $estado, $notas
            );
            $stmt_tratamiento->execute();
            $id_tratamiento = $conexion->insert_id;
            
            $conexion->commit();
            
            // =============================================
            // REDIRECCIÓN SEGÚN PARÁMETRO return_to (CORREGIDO)
            // =============================================
            if ($return_to === 'agendar') {
                $_SESSION['exito'] = "✅ Tratamiento creado correctamente. Ahora puedes seleccionarlo para la cita.";
                redirigir('/ecodent/public/odontologo/agendar_cita.php?paciente=' . $id_paciente . '&fecha=' . $fecha_retorno);
            } elseif ($return_to === 'vincular') {
                $id_cita = $_GET['cita'] ?? 0;
                $_SESSION['nuevo_tratamiento_id'] = $id_tratamiento;
                redirigir('/ecodent/public/odontologo/vincular_tratamiento.php?id_cita=' . $id_cita . '&id_paciente=' . $id_paciente);
            } else {
                $_SESSION['exito'] = "Tratamiento registrado correctamente.";
                redirigir('/ecodent/public/odontologo/tratamiento_detalle.php?id=' . $id_tratamiento);
            }
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al registrar: " . $e->getMessage();
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

.badge-return {
    background: #28a745;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-file-medical-fill text-success"></i> Nuevo Tratamiento</h1>
        <p class="lead">Registra un nuevo tratamiento para el paciente.</p>
        <?php if ($return_to === 'agendar'): ?>
            <div class="alert alert-success mt-2">
                <i class="bi bi-info-circle"></i>
                Estás creando un tratamiento desde el agendamiento de cita.
                Al finalizar, volverás a la pantalla de agendar cita.
            </div>
        <?php elseif ($return_to === 'vincular'): ?>
            <div class="alert alert-info mt-2">
                <i class="bi bi-info-circle"></i>
                Estás creando un tratamiento para vincular a una cita existente.
                Al finalizar, volverás a la pantalla de vinculación.
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($return_to === 'agendar'): ?>
            <a href="agendar_cita.php?paciente=<?php echo $id_paciente; ?>&fecha=<?php echo $fecha_retorno; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a agendar cita
            </a>
        <?php elseif ($return_to === 'vincular'): ?>
            <a href="vincular_tratamiento.php?id_cita=<?php echo $_GET['cita'] ?? 0; ?>&id_paciente=<?php echo $id_paciente; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a vincular
            </a>
        <?php else: ?>
            <a href="paciente_detalle.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al paciente
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="paciente-info">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <i class="bi bi-person-circle fs-2 me-3"></i>
            <div>
                <h5 class="mb-0">Paciente: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h5>
                <small>ID: <?php echo $id_paciente; ?></small>
            </div>
        </div>
        <?php if ($return_to === 'agendar'): ?>
            <span class="badge-return">
                <i class="bi bi-calendar-plus"></i> Modo: Agendar cita
            </span>
        <?php elseif ($return_to === 'vincular'): ?>
            <span class="badge-return">
                <i class="bi bi-link"></i> Modo: Vincular a cita
            </span>
        <?php endif; ?>
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
                       value="<?php echo isset($_POST['nombre_tratamiento']) ? htmlspecialchars($_POST['nombre_tratamiento']) : ''; ?>">
            </div>
            
            <div class="col-md-4 mb-3">
                <label class="form-label">Costo total *</label>
                <div class="input-group">
                    <span class="input-group-text">S/.</span>
                    <input type="number" class="form-control" name="costo_total" step="0.01" required
                           value="<?php echo isset($_POST['costo_total']) ? $_POST['costo_total'] : ''; ?>">
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" rows="3" 
                      placeholder="Describe el tratamiento a realizar..."><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de inicio</label>
                <input type="date" class="form-control" name="fecha_inicio"
                       value="<?php echo isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d'); ?>">
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de fin (estimada)</label>
                <input type="date" class="form-control" name="fecha_fin"
                       value="<?php echo isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : ''; ?>">
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado inicial</label>
                <select class="form-select" name="estado">
                    <option value="pendiente">Pendiente</option>
                    <option value="en_progreso">En progreso</option>
                    <option value="completado">Completado</option>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Notas adicionales</label>
            <textarea class="form-control" name="notas" rows="2" 
                      placeholder="Información adicional sobre el tratamiento..."><?php echo isset($_POST['notas']) ? htmlspecialchars($_POST['notas']) : ''; ?></textarea>
        </div>
    </div>
    
    <div class="text-end">
        <?php if ($return_to === 'agendar'): ?>
            <a href="agendar_cita.php?paciente=<?php echo $id_paciente; ?>&fecha=<?php echo $fecha_retorno; ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        <?php elseif ($return_to === 'vincular'): ?>
            <a href="vincular_tratamiento.php?id_cita=<?php echo $_GET['cita'] ?? 0; ?>&id_paciente=<?php echo $id_paciente; ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        <?php else: ?>
            <a href="paciente_detalle.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        <?php endif; ?>
        <button type="submit" class="btn btn-success btn-lg">
            <i class="bi bi-save"></i> Registrar tratamiento
        </button>
    </div>
</form>

<?php
require_once '../../includes/footer.php';
?>