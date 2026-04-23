<?php
// public/odontologo/paciente_detalle.php
// Ver detalles completos del paciente con historial de citas y tratamientos

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
        $odontologo = $resultado->fetch_assoc();
        $id_odontologo = $odontologo['id_odontologo'];
    } else {
        $_SESSION['error'] = "No se encontró información del odontólogo. Contacte al administrador.";
        redirigir('/ecodent/public/dashboard.php');
    }
}

// Verificar que se haya pasado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de paciente no válido.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_paciente = $_GET['id'];

// Obtener datos del paciente
$stmt_paciente = $conexion->prepare("
    SELECT p.id_paciente, u.id_usuario, u.nombre_completo, u.email, u.telefono, u.fecha_registro,
           p.fecha_nacimiento, p.direccion, p.ausencias_sin_aviso, p.llegadas_tarde,
           p.estado_cuenta, p.puede_agendar, p.fecha_ultima_ausencia, p.fecha_actualizacion_estado
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

// Obtener estadísticas completas - Si es admin, mostrar todas las citas del paciente (sin filtrar por odontólogo)
// Obtener estadísticas completas - Si es admin, mostrar todas las citas del paciente (sin filtrar por odontólogo)
if ($es_odontologo && $id_odontologo) {
    $stmt_stats = $conexion->prepare("
    SELECT 
        COUNT(*) as total_citas,
        SUM(CASE WHEN c.estado = 'programada' THEN 1 ELSE 0 END) as programadas,
        SUM(CASE WHEN c.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN c.estado = 'cancelada_pac' THEN 1 ELSE 0 END) as canceladas_paciente,
        SUM(CASE WHEN c.estado = 'cancelada_doc' THEN 1 ELSE 0 END) as canceladas_doctor,
        SUM(CASE WHEN c.estado = 'ausente' THEN 1 ELSE 0 END) as ausencias,
        MAX(c.fecha_cita) as ultima_cita,
        MIN(c.fecha_cita) as primera_cita
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    WHERE t.id_paciente = ? AND t.id_odontologo = ?
");
    $stmt_stats->bind_param("ii", $id_paciente, $id_odontologo);
    $stmt_stats->execute();
    $estadisticas = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->bind_param("ii", $id_paciente, $id_odontologo);
    $stmt_stats->execute();
    $estadisticas = $stmt_stats->get_result()->fetch_assoc();
    
    // Obtener todas las citas del paciente con este odontólogo
    $stmt_citas = $conexion->prepare("
    SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.hora_fin, c.motivo, c.estado,
           c.llego_tarde, c.minutos_tarde, c.fecha_creacion, c.fecha_actualizacion,
           c.motivo_cancelacion, c.fecha_cancelacion
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    WHERE t.id_paciente = ? AND t.id_odontologo = ?
    ORDER BY c.fecha_cita DESC, c.hora_cita DESC
");
    $stmt_citas->bind_param("ii", $id_paciente, $id_odontologo);
    $stmt_citas->execute();
    $citas = $stmt_citas->get_result();
    
    // Obtener tratamientos de este odontólogo
    $stmt_tratamientos = $conexion->prepare("
        SELECT t.id_tratamiento, t.nombre_tratamiento, t.descripcion, t.costo_total, t.total_pagado, 
               t.saldo_pendiente, t.fecha_inicio, t.fecha_fin, t.estado, t.notas, t.fecha_creacion,
               (SELECT SUM(monto) FROM pagos WHERE id_tratamiento = t.id_tratamiento) as total_pagos
        FROM tratamientos t
        WHERE t.id_paciente = ? AND t.id_odontologo = ?
        ORDER BY t.fecha_creacion DESC
    ");
    $stmt_tratamientos->bind_param("ii", $id_paciente, $id_odontologo);
    $stmt_tratamientos->execute();
    $tratamientos = $stmt_tratamientos->get_result();
} else {
    // Si es admin, mostrar todas las citas sin filtrar por odontólogo
    $stmt_stats = $conexion->prepare("
    SELECT 
        COUNT(*) as total_citas,
        SUM(CASE WHEN c.estado = 'programada' THEN 1 ELSE 0 END) as programadas,
        SUM(CASE WHEN c.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN c.estado = 'cancelada_pac' THEN 1 ELSE 0 END) as canceladas_paciente,
        SUM(CASE WHEN c.estado = 'cancelada_doc' THEN 1 ELSE 0 END) as canceladas_doctor,
        SUM(CASE WHEN c.estado = 'ausente' THEN 1 ELSE 0 END) as ausencias,
        MAX(c.fecha_cita) as ultima_cita,
        MIN(c.fecha_cita) as primera_cita
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    WHERE t.id_paciente = ?
");
    $stmt_stats->bind_param("i", $id_paciente);
    $stmt_stats->execute();
    $estadisticas = $stmt_stats->get_result()->fetch_assoc();
    
    // Obtener todas las citas del paciente (todos los odontólogos)
    $stmt_citas = $conexion->prepare("
    SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.hora_fin, c.motivo, c.estado,
           c.llego_tarde, c.minutos_tarde, c.fecha_creacion, c.fecha_actualizacion,
           c.motivo_cancelacion, c.fecha_cancelacion,
           u.nombre_completo as odontologo_nombre
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    JOIN odontologos o ON t.id_odontologo = o.id_odontologo
    JOIN usuarios u ON o.id_usuario = u.id_usuario
    WHERE t.id_paciente = ?
    ORDER BY c.fecha_cita DESC, c.hora_cita DESC
");
    $stmt_citas->bind_param("i", $id_paciente);
    $stmt_citas->execute();
    $citas = $stmt_citas->get_result();
    
    // Obtener todos los tratamientos (todos los odontólogos)
    $stmt_tratamientos = $conexion->prepare("
        SELECT t.id_tratamiento, t.nombre_tratamiento, t.descripcion, t.costo_total, t.total_pagado, 
               t.saldo_pendiente, t.fecha_inicio, t.fecha_fin, t.estado, t.notas, t.fecha_creacion,
               u.nombre_completo as odontologo_nombre,
               (SELECT SUM(monto) FROM pagos WHERE id_tratamiento = t.id_tratamiento) as total_pagos
        FROM tratamientos t
        JOIN odontologos o ON t.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE t.id_paciente = ?
        ORDER BY t.fecha_creacion DESC
    ");
    $stmt_tratamientos->bind_param("i", $id_paciente);
    $stmt_tratamientos->execute();
    $tratamientos = $stmt_tratamientos->get_result();
}

$stmt_pagos = $conexion->prepare("
    SELECT p.id_pago, p.monto, p.concepto, p.fecha_pago, p.metodo_pago, p.observaciones,
           t.nombre_tratamiento,
           u.nombre_completo as odontologo_nombre
    FROM pagos p
    INNER JOIN tratamientos t ON p.id_tratamiento = t.id_tratamiento
    LEFT JOIN odontologos o ON t.id_odontologo = o.id_odontologo
    LEFT JOIN usuarios u ON o.id_usuario = u.id_usuario
    WHERE t.id_paciente = ?
    ORDER BY p.fecha_pago DESC
    LIMIT 10
");
$stmt_pagos->bind_param("i", $id_paciente);
$stmt_pagos->execute();
$pagos = $stmt_pagos->get_result();

// Calcular total adeudado
$total_adeudado = 0;
$tratamientos->data_seek(0);
while ($tratamiento = $tratamientos->fetch_assoc()) {
    if ($tratamiento['estado'] != 'cancelado') {
        $total_adeudado += $tratamiento['saldo_pendiente'];
    }
}
$tratamientos->data_seek(0);

require_once '../../includes/header.php';
?>

<style>
.paciente-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
}

.paciente-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
}

.stat-card-detalle {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    height: 100%;
}

.stat-card-detalle:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.stat-number-detalle {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label-detalle {
    color: #666;
    font-size: 0.85rem;
}

.estado-badge-detalle {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-block;
}

.cita-row {
    transition: all 0.2s;
    cursor: pointer;
}

.cita-row:hover {
    background: #e8f0fe;
    transform: scale(1.01);
}

.tratamiento-card {
    transition: all 0.2s;
    border-left: 4px solid;
    margin-bottom: 15px;
}

.tratamiento-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.info-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.info-label {
    font-size: 0.8rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1rem;
    font-weight: 500;
    color: #333;
}

.progress-bar-custom {
    height: 8px;
    border-radius: 4px;
    background: #e0e0e0;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #06d6a0, #11998e);
    border-radius: 4px;
    transition: width 0.5s ease;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
    border-left: 2px solid #e0e0e0;
    padding-left: 20px;
    margin-left: 10px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -8px;
    top: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #4361ee;
}

.timeline-item:last-child {
    border-left: none;
}

.badge-admin {
    background: #667eea;
    color: white;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 20px;
    margin-left: 10px;
}
</style>

<div class="paciente-header">
    <div class="row align-items-center">
        <div class="col-md-1">
            <div class="paciente-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
        </div>
        <div class="col-md-7">
            <h2 class="mb-1">
                <?php echo htmlspecialchars($paciente['nombre_completo']); ?>
                <?php if ($es_admin): ?>
                    <span class="badge-admin"><i class="bi bi-shield-shaded"></i> Vista Admin</span>
                <?php endif; ?>
            </h2>
            <div class="d-flex flex-wrap gap-3">
                <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($paciente['email']); ?></span>
                <span><i class="bi bi-telephone"></i> <?php echo $paciente['telefono'] ? htmlspecialchars($paciente['telefono']) : 'No registrado'; ?></span>
                <?php if ($paciente['fecha_nacimiento']): ?>
                    <span><i class="bi bi-cake2"></i> <?php echo date('d/m/Y', strtotime($paciente['fecha_nacimiento'])); ?> 
                        (<?php echo date('Y') - date('Y', strtotime($paciente['fecha_nacimiento'])); ?> años)</span>
                <?php endif; ?>
                <span><i class="bi bi-calendar-plus"></i> Desde: <?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?></span>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($es_odontologo): ?>
                <a href="paciente_editar.php?id=<?php echo $id_paciente; ?>" class="btn btn-light me-2">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <a href="agendar_cita.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-success">
                    <i class="bi bi-calendar-plus"></i> Nueva cita
                </a>
            <?php else: ?>
                <a href="/ecodent/public/odontologo/pacientes.php" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Volver a pacientes
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($es_admin): ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i> Vista de administrador - Mostrando toda la información del paciente (todos los odontólogos)
    </div>
<?php endif; ?>

<!-- Tarjetas de estadísticas -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card-detalle">
            <div class="stat-number-detalle" style="color: #4361ee;"><?php echo $estadisticas['total_citas'] ?? 0; ?></div>
            <div class="stat-label-detalle">Total citas</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card-detalle">
            <div class="stat-number-detalle" style="color: #06d6a0;"><?php echo $estadisticas['completadas'] ?? 0; ?></div>
            <div class="stat-label-detalle">Completadas</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card-detalle">
            <div class="stat-number-detalle" style="color: #ffb703;"><?php echo $estadisticas['ausencias'] ?? 0; ?></div>
            <div class="stat-label-detalle">Ausencias</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card-detalle">
            <div class="stat-number-detalle" style="color: #f5576c;">S/. <?php echo number_format($total_adeudado, 2); ?></div>
            <div class="stat-label-detalle">Saldo pendiente</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Columna izquierda: Información personal -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Información personal</h5>
            </div>
            <div class="card-body">
                <div class="info-section">
                    <div class="info-label">Estado de cuenta</div>
                    <div class="info-value">
                        <?php
                        $estado_class = 'success';
                        $estado_texto = 'Normal';
                        if ($paciente['estado_cuenta'] == 'observacion') {
                            $estado_class = 'warning';
                            $estado_texto = 'En observación';
                        } elseif ($paciente['estado_cuenta'] == 'restringida') {
                            $estado_class = 'danger';
                            $estado_texto = 'Restringida';
                        } elseif ($paciente['estado_cuenta'] == 'bloqueada') {
                            $estado_class = 'dark';
                            $estado_texto = 'Bloqueada';
                        }
                        ?>
                        <span class="badge bg-<?php echo $estado_class; ?> fs-6"><?php echo $estado_texto; ?></span>
                        <?php if (!$paciente['puede_agendar']): ?>
                            <span class="badge bg-secondary ms-2"><i class="bi bi-ban"></i> No puede agendar</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Dirección</div>
                    <div class="info-value"><?php echo $paciente['direccion'] ? htmlspecialchars($paciente['direccion']) : 'No registrada'; ?></div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Comportamiento</div>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <div class="fs-3 text-warning"><?php echo $paciente['ausencias_sin_aviso']; ?></div>
                                <small class="text-muted">Ausencias</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <div class="fs-3 text-info"><?php echo $paciente['llegadas_tarde']; ?></div>
                                <small class="text-muted">Llegadas tarde</small>
                            </div>
                        </div>
                    </div>
                    <?php if ($paciente['fecha_ultima_ausencia']): ?>
                        <small class="text-muted d-block mt-2">
                            Última ausencia: <?php echo date('d/m/Y', strtotime($paciente['fecha_ultima_ausencia'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Resumen de citas</div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <small>Programadas</small>
                            <strong><?php echo $estadisticas['programadas'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small>Confirmadas</small>
                            <strong><?php echo $estadisticas['confirmadas'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small>Canceladas (paciente)</small>
                            <strong><?php echo $estadisticas['canceladas_paciente'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small>Canceladas (doctor)</small>
                            <strong><?php echo $estadisticas['canceladas_doctor'] ?? 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Últimos pagos -->
        <?php if ($pagos->num_rows > 0): ?>
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i> Últimos pagos</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php while ($pago = $pagos->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>S/. <?php echo number_format($pago['monto'], 2); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pago['concepto']); ?></small>
                                    <?php if ($pago['nombre_tratamiento']): ?>
                                        <br>
                                        <small class="text-primary"><?php echo htmlspecialchars($pago['nombre_tratamiento']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($es_admin && $pago['odontologo_nombre']): ?>
                                        <br>
                                        <small class="text-muted"><i class="bi bi-hospital"></i> Dr. <?php echo htmlspecialchars($pago['odontologo_nombre']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <div><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></div>
                                    <small class="text-muted"><?php echo ucfirst($pago['metodo_pago']); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Columna derecha: Historial de citas y tratamientos -->
    <div class="col-md-8">
        <!-- Tratamientos -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-medical me-2 text-success"></i> Tratamientos</h5>
                <?php if ($es_odontologo): ?>
                    <a href="tratamiento_nuevo.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle"></i> Nuevo tratamiento
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($tratamientos->num_rows > 0): ?>
                    <?php while ($tratamiento = $tratamientos->fetch_assoc()): 
                        $porcentaje = 0;
                        if ($tratamiento['costo_total'] > 0) {
                            $porcentaje = ($tratamiento['total_pagado'] / $tratamiento['costo_total']) * 100;
                        }
                        
                        $border_color = '#4361ee';
                        if ($tratamiento['estado'] == 'completado') $border_color = '#06d6a0';
                        if ($tratamiento['estado'] == 'cancelado') $border_color = '#dc3545';
                        if ($tratamiento['estado'] == 'pendiente') $border_color = '#ffb703';
                    ?>
                        <div class="tratamiento-card" style="border-left-color: <?php echo $border_color; ?>; padding: 15px; border: 1px solid #e0e0e0; border-left-width: 4px; border-radius: 8px;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($tratamiento['nombre_tratamiento']); ?>
                                        <span class="badge bg-<?php 
                                            if ($tratamiento['estado'] == 'completado') echo 'success';
                                            elseif ($tratamiento['estado'] == 'en_progreso') echo 'info';
                                            elseif ($tratamiento['estado'] == 'pendiente') echo 'warning';
                                            else echo 'secondary';
                                        ?> ms-2"><?php echo ucfirst($tratamiento['estado']); ?></span>
                                        <?php if ($es_admin && isset($tratamiento['odontologo_nombre'])): ?>
                                            <span class="badge bg-secondary ms-2">
                                                <i class="bi bi-hospital"></i> Dr. <?php echo htmlspecialchars($tratamiento['odontologo_nombre']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h6>
                                    <?php if ($tratamiento['descripcion']): ?>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($tratamiento['descripcion']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-2">
                                        <div class="col-md-4">
                                            <small class="text-muted">Costo total:</small>
                                            <div class="fw-bold">S/. <?php echo number_format($tratamiento['costo_total'], 2); ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Pagado:</small>
                                            <div class="fw-bold text-success">S/. <?php echo number_format($tratamiento['total_pagado'], 2); ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Saldo pendiente:</small>
                                            <div class="fw-bold text-danger">S/. <?php echo number_format($tratamiento['saldo_pendiente'], 2); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <div class="progress-bar-custom">
                                            <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($porcentaje, 1); ?>% completado</small>
                                    </div>
                                    
                                    <?php if ($tratamiento['fecha_inicio']): ?>
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-calendar"></i> Inicio: <?php echo date('d/m/Y', strtotime($tratamiento['fecha_inicio'])); ?>
                                            <?php if ($tratamiento['fecha_fin']): ?>
                                                | Fin: <?php echo date('d/m/Y', strtotime($tratamiento['fecha_fin'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <a href="tratamiento_detalle.php?id=<?php echo $tratamiento['id_tratamiento']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <?php if ($es_odontologo): ?>
                                        <a href="tratamiento_editar.php?id=<?php echo $tratamiento['id_tratamiento']; ?>" class="btn btn-sm btn-outline-warning mt-1">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <a href="pago_nuevo.php?tratamiento=<?php echo $tratamiento['id_tratamiento']; ?>&paciente=<?php echo $id_paciente; ?>" class="btn btn-sm btn-outline-success mt-1">
                                            <i class="bi bi-cash"></i> Registrar pago
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-file-medical fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No hay tratamientos registrados.</p>
                        <?php if ($es_odontologo): ?>
                            <a href="tratamiento_nuevo.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-sm btn-success">
                                Iniciar tratamiento
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Historial de citas -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2 text-primary"></i> Historial de citas</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($citas->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                32
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Motivo</th>
                                    <?php if ($es_admin): ?>
                                        <th>Odontólogo</th>
                                    <?php endif; ?>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($cita = $citas->fetch_assoc()): 
                                    $estado_badge = 'secondary';
                                    $estado_icono = 'bi-question-circle';
                                    if ($cita['estado'] == 'programada') {
                                        $estado_badge = 'warning';
                                        $estado_icono = 'bi-clock';
                                    } elseif ($cita['estado'] == 'confirmada') {
                                        $estado_badge = 'info';
                                        $estado_icono = 'bi-check-circle';
                                    } elseif ($cita['estado'] == 'completada') {
                                        $estado_badge = 'success';
                                        $estado_icono = 'bi-check-circle-fill';
                                    } elseif ($cita['estado'] == 'cancelada_pac' || $cita['estado'] == 'cancelada_doc') {
                                        $estado_badge = 'danger';
                                        $estado_icono = 'bi-x-circle';
                                    } elseif ($cita['estado'] == 'ausente') {
                                        $estado_badge = 'dark';
                                        $estado_icono = 'bi-person-x';
                                    }
                                ?>
                                    <tr class="cita-row" onclick="window.location.href='detalle_cita.php?id_cita=<?php echo $cita['id_cita']; ?>'">
                                        <td class="text-nowrap">
                                            <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?>
                                            <?php if ($cita['fecha_cita'] == date('Y-m-d')): ?>
                                                <span class="badge bg-primary ms-1">Hoy</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <strong><?php echo date('h:i A', strtotime($cita['hora_cita'])); ?></strong>
                                            <?php if ($cita['llego_tarde']): ?>
                                                <br>
                                                <small class="text-danger">(Llegó tarde <?php echo $cita['minutos_tarde']; ?> min)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cita['motivo'] ?? 'Sin motivo'); ?></td>
                                        <?php if ($es_admin && isset($cita['odontologo_nombre'])): ?>
                                            <td><i class="bi bi-hospital"></i> Dr. <?php echo htmlspecialchars($cita['odontologo_nombre']); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-<?php echo $estado_badge; ?>">
                                                <i class="bi <?php echo $estado_icono; ?>"></i>
                                                <?php 
                                                    $estado_texto = ucfirst(str_replace('_', ' ', $cita['estado']));
                                                    if ($cita['estado'] == 'cancelada_pac') $estado_texto = 'Cancelada (Paciente)';
                                                    if ($cita['estado'] == 'cancelada_doc') $estado_texto = 'Cancelada (Doctor)';
                                                    echo $estado_texto;
                                                ?>
                                            </span>
                                            <?php if ($cita['motivo_cancelacion']): ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($cita['motivo_cancelacion']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <i class="bi bi-chevron-right text-primary"></i>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No hay citas registradas con este paciente.</p>
                        <?php if ($es_odontologo): ?>
                            <a href="agendar_cita.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-sm btn-primary">
                                Agendar primera cita
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>