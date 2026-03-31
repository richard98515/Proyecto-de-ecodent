<?php
// public/odontologo/tratamiento_detalle.php
// Ver detalles completos del tratamiento con historial de pagos

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

// Verificar que se haya pasado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de tratamiento no válido.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_tratamiento = $_GET['id'];

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
    $_SESSION['error'] = "Tratamiento no encontrado.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

// Obtener pagos del tratamiento
$stmt_pagos = $conexion->prepare("
    SELECT p.*, u.nombre_completo as registrado_por
    FROM pagos p
    JOIN usuarios u ON p.id_usuario_registro = u.id_usuario
    WHERE p.id_tratamiento = ?
    ORDER BY p.fecha_pago DESC
");
$stmt_pagos->bind_param("i", $id_tratamiento);
$stmt_pagos->execute();
$pagos = $stmt_pagos->get_result();

// Calcular porcentaje completado
$porcentaje = 0;
if ($tratamiento['costo_total'] > 0) {
    $porcentaje = ($tratamiento['total_pagado'] / $tratamiento['costo_total']) * 100;
}

// Procesar actualización de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado'])) {
    $nuevo_estado = $_POST['estado'];
    $notas = sanitizar($_POST['notas']);
    
    $stmt_update = $conexion->prepare("
        UPDATE tratamientos 
        SET estado = ?, notas = ?
        WHERE id_tratamiento = ?
    ");
    $stmt_update->bind_param("ssi", $nuevo_estado, $notas, $id_tratamiento);
    
    if ($stmt_update->execute()) {
        $_SESSION['exito'] = "Estado del tratamiento actualizado correctamente.";
        redirigir('/ecodent/public/odontologo/tratamiento_detalle.php?id=' . $id_tratamiento);
    } else {
        $error = "Error al actualizar el estado.";
    }
}

require_once '../../includes/header.php';

if (isset($_SESSION['exito'])) {
    echo '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill"></i> ' . $_SESSION['exito'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['exito']);
}
if (isset($error)) {
    echo '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill"></i> ' . $error . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
?>

<style>
.tratamiento-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
}

.progress-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(#06d6a0 0deg, #e0e0e0 0deg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circle-inner {
    width: 90px;
    height: 90px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.pago-card {
    transition: all 0.2s;
    border-left: 4px solid #06d6a0;
    margin-bottom: 10px;
}

.pago-card:hover {
    transform: translateX(5px);
    background: #f8f9fa;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
}

.info-label {
    font-weight: 600;
    color: #666;
}

.info-value {
    color: #333;
}
</style>

<div class="tratamiento-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2 class="mb-2"><?php echo htmlspecialchars($tratamiento['nombre_tratamiento']); ?></h2>
            <div class="d-flex flex-wrap gap-3">
                <span><i class="bi bi-person"></i> Paciente: <?php echo htmlspecialchars($tratamiento['paciente_nombre']); ?></span>
                <span><i class="bi bi-calendar"></i> Inicio: <?php echo $tratamiento['fecha_inicio'] ? date('d/m/Y', strtotime($tratamiento['fecha_inicio'])) : 'No definida'; ?></span>
                <?php if ($tratamiento['fecha_fin']): ?>
                    <span><i class="bi bi-calendar-check"></i> Fin estimado: <?php echo date('d/m/Y', strtotime($tratamiento['fecha_fin'])); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <a href="paciente_detalle.php?id=<?php echo $tratamiento['id_paciente']; ?>" class="btn btn-light me-2">
                <i class="bi bi-arrow-left"></i> Volver al paciente
            </a>
            <a href="pago_nuevo.php?tratamiento=<?php echo $id_tratamiento; ?>&paciente=<?php echo $tratamiento['id_paciente']; ?>" class="btn btn-warning">
                <i class="bi bi-cash-stack"></i> Registrar pago
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Resumen financiero -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-calculator me-2 text-success"></i> Resumen financiero</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="progress-circle" id="progressCircle">
                        <div class="progress-circle-inner">
                            <div class="fs-2 fw-bold text-success"><?php echo round($porcentaje); ?>%</div>
                            <small class="text-muted">pagado</small>
                        </div>
                    </div>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Costo total:</span>
                    <span class="info-value fw-bold">S/. <?php echo number_format($tratamiento['costo_total'], 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total pagado:</span>
                    <span class="info-value text-success fw-bold">S/. <?php echo number_format($tratamiento['total_pagado'], 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Saldo pendiente:</span>
                    <span class="info-value text-danger fw-bold">S/. <?php echo number_format($tratamiento['saldo_pendiente'], 2); ?></span>
                </div>
                
                <div class="mt-3">
                    <div class="progress-bar-custom">
                        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%; background: linear-gradient(90deg, #06d6a0, #11998e);"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Estado actual -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-tag me-2 text-primary"></i> Estado del tratamiento</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php
                    $badge_class = 'secondary';
                    if ($tratamiento['estado'] == 'pendiente') $badge_class = 'warning';
                    if ($tratamiento['estado'] == 'en_progreso') $badge_class = 'info';
                    if ($tratamiento['estado'] == 'completado') $badge_class = 'success';
                    if ($tratamiento['estado'] == 'cancelado') $badge_class = 'danger';
                    ?>
                    <span class="badge bg-<?php echo $badge_class; ?> fs-5 px-3 py-2">
                        <?php echo ucfirst(str_replace('_', ' ', $tratamiento['estado'])); ?>
                    </span>
                </div>
                
                <?php if ($tratamiento['notas']): ?>
                    <div class="mt-3">
                        <small class="text-muted">Notas:</small>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($tratamiento['notas'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <hr>
                
                <form method="POST" action="">
                    <label class="form-label">Actualizar estado:</label>
                    <select class="form-select mb-2" name="estado">
                        <option value="pendiente" <?php echo $tratamiento['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="en_progreso" <?php echo $tratamiento['estado'] == 'en_progreso' ? 'selected' : ''; ?>>En progreso</option>
                        <option value="completado" <?php echo $tratamiento['estado'] == 'completado' ? 'selected' : ''; ?>>Completado</option>
                        <option value="cancelado" <?php echo $tratamiento['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                    
                    <label class="form-label mt-2">Notas adicionales:</label>
                    <textarea class="form-control mb-2" name="notas" rows="2"><?php echo htmlspecialchars($tratamiento['notas'] ?? ''); ?></textarea>
                    
                    <button type="submit" name="actualizar_estado" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Actualizar estado
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Descripción del tratamiento -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-file-text me-2 text-primary"></i> Descripción del tratamiento</h5>
            </div>
            <div class="card-body">
                <p><?php echo $tratamiento['descripcion'] ? nl2br(htmlspecialchars($tratamiento['descripcion'])) : 'Sin descripción registrada.'; ?></p>
            </div>
        </div>
        
        <!-- Historial de pagos -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i> Historial de pagos</h5>
                <a href="pago_nuevo.php?tratamiento=<?php echo $id_tratamiento; ?>&paciente=<?php echo $tratamiento['id_paciente']; ?>" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-circle"></i> Registrar pago
                </a>
            </div>
            <div class="card-body">
                <?php if ($pagos->num_rows > 0): ?>
                    <?php while ($pago = $pagos->fetch_assoc()): ?>
                        <div class="pago-card p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">S/. <?php echo number_format($pago['monto'], 2); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($pago['concepto']); ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> Registrado por: <?php echo htmlspecialchars($pago['registrado_por']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-info"><?php echo ucfirst($pago['metodo_pago']); ?></div>
                                    <div class="mt-1">
                                        <small><?php echo date('d/m/Y H:i', strtotime($pago['fecha_pago'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php if ($pago['observaciones']): ?>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-chat"></i> <?php echo htmlspecialchars($pago['observaciones']); ?>
                                </small>
                            <?php endif; ?>
                            
                            <?php if ($pago['foto_comprobante']): ?>
                                <a href="<?php echo $pago['foto_comprobante']; ?>" target="_blank" class="btn btn-sm btn-link p-0 mt-1">
                                    <i class="bi bi-image"></i> Ver comprobante
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cash-stack fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No hay pagos registrados para este tratamiento.</p>
                        <a href="pago_nuevo.php?tratamiento=<?php echo $id_tratamiento; ?>&paciente=<?php echo $tratamiento['id_paciente']; ?>" class="btn btn-sm btn-success">
                            Registrar primer pago
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Actualizar el círculo de progreso
const porcentaje = <?php echo $porcentaje; ?>;
const circle = document.getElementById('progressCircle');
const deg = (porcentaje / 100) * 360;
circle.style.background = `conic-gradient(#06d6a0 ${deg}deg, #e0e0e0 ${deg}deg)`;

function verComprobante(ruta) {
    window.open(ruta, '_blank', 'width=800,height=600');
}
</script>

<?php
require_once '../../includes/footer.php';
?>