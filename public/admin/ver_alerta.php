<?php
// public/admin/ver_alerta.php
// Ver detalle de una alerta específica y marcarla como leída

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

// Verificar que se haya pasado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirigir('/ecodent/public/admin/ver_todas_alertas.php');
}

$id_alerta = (int)$_GET['id'];

// Obtener datos de la alerta
$stmt = $conexion->prepare("
    SELECT a.*, r.nombre as regla_nombre, r.condicion
    FROM alertas a
    LEFT JOIN reglas_alertas r ON a.id_regla = r.id_regla
    WHERE a.id_alerta = ? AND a.id_usuario = ?
");
$stmt->bind_param("ii", $id_alerta, $_SESSION['id_usuario']);
$stmt->execute();
$alerta = $stmt->get_result()->fetch_assoc();

if (!$alerta) {
    $_SESSION['error'] = "Alerta no encontrada";
    redirigir('/ecodent/public/admin/ver_todas_alertas.php');
}

// Marcar como leída si no lo está
if ($alerta['leida'] == 0) {
    $update = $conexion->prepare("UPDATE alertas SET leida = 1 WHERE id_alerta = ?");
    $update->bind_param("i", $id_alerta);
    $update->execute();
    $alerta['leida'] = 1;
}

// Obtener alertas relacionadas (misma regla)
$relacionadas = $conexion->prepare("
    SELECT id_alerta, titulo, fecha_creacion, leida
    FROM alertas 
    WHERE id_regla = ? AND id_alerta != ? AND id_usuario = ?
    ORDER BY fecha_creacion DESC
    LIMIT 5
");
$relacionadas->bind_param("iii", $alerta['id_regla'], $id_alerta, $_SESSION['id_usuario']);
$relacionadas->execute();
$alertas_relacionadas = $relacionadas->get_result();

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- Botón volver -->
            <div class="mb-3">
                <a href="ver_todas_alertas.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Centro de Alertas
                </a>
            </div>
            
            <!-- Tarjeta de la alerta -->
            <div class="card">
                <div class="card-header <?php 
                    echo $alerta['tipo'] == 'danger' ? 'bg-danger' : ($alerta['tipo'] == 'warning' ? 'bg-warning' : 'bg-info');
                ?> text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <?php if ($alerta['tipo'] == 'danger'): ?>
                                <i class="bi bi-exclamation-octagon-fill me-2"></i>
                            <?php elseif ($alerta['tipo'] == 'warning'): ?>
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php else: ?>
                                <i class="bi bi-info-circle-fill me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($alerta['titulo']); ?>
                        </h4>
                        <span class="badge bg-light text-dark">
                            <?php echo $alerta['leida'] ? '✓ Leída' : '📌 Nueva'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Fecha -->
                    <div class="text-muted mb-3">
                        <i class="bi bi-calendar3 me-1"></i> 
                        <?php echo date('d/m/Y H:i:s', strtotime($alerta['fecha_creacion'])); ?>
                    </div>
                    
                    <!-- Mensaje -->
                    <div class="alert alert-light border">
                        <p class="mb-0 fs-5"><?php echo nl2br(htmlspecialchars($alerta['mensaje'])); ?></p>
                    </div>
                    
                    <!-- Información adicional -->
                    <?php if ($alerta['regla_nombre']): ?>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <small class="text-muted">Regla que generó esta alerta:</small>
                                <p class="mb-0"><strong><?php echo htmlspecialchars($alerta['regla_nombre']); ?></strong></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Condición:</small>
                                <p class="mb-0"><code><?php echo htmlspecialchars($alerta['condicion']); ?></code></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <a href="ver_todas_alertas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list"></i> Ver todas
                        </a>
                        <?php if ($alerta['id_regla']): ?>
                            <a href="reglas_alertas.php" class="btn btn-outline-info">
                                <i class="bi bi-gear"></i> Configurar regla
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Alertas relacionadas -->
            <?php if ($alertas_relacionadas->num_rows > 0): ?>
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bell me-2"></i>
                        Alertas relacionadas (misma regla)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while($rel = $alertas_relacionadas->fetch_assoc()): ?>
                            <a href="ver_alerta.php?id=<?php echo $rel['id_alerta']; ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-bell-fill text-warning me-2"></i>
                                    <?php echo htmlspecialchars($rel['titulo']); ?>
                                    <?php if (!$rel['leida']): ?>
                                        <span class="badge bg-primary ms-2">Nueva</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($rel['fecha_creacion'])); ?>
                                    <i class="bi bi-chevron-right ms-2"></i>
                                </small>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>