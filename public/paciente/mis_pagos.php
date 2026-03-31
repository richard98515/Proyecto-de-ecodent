<?php
// public/paciente/mis_pagos.php
require_once '../../includes/header.php';
require_once '../../includes/funciones.php';
require_once '../../config/database.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];

$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

// RESUMEN
$stmt_resumen = $conexion->prepare("
    SELECT 
        COALESCE((SELECT SUM(monto) FROM pagos WHERE id_paciente = ?), 0)        AS total_pagado,
        COALESCE((SELECT SUM(saldo_pendiente) FROM tratamientos 
                  WHERE id_paciente = ? AND estado != 'cancelado'), 0)            AS total_pendiente,
        (SELECT COUNT(*) FROM pagos WHERE id_paciente = ?)                        AS num_pagos,
        (SELECT COUNT(*) FROM tratamientos WHERE id_paciente = ?)                 AS num_tratamientos
");
$stmt_resumen->bind_param("iiii", $id_paciente, $id_paciente, $id_paciente, $id_paciente);
$stmt_resumen->execute();
$resumen = $stmt_resumen->get_result()->fetch_assoc();

// TRATAMIENTOS
$stmt_trat = $conexion->prepare("
    SELECT t.*, u.nombre_completo AS odontologo,
           (SELECT COUNT(*) FROM pagos p WHERE p.id_tratamiento = t.id_tratamiento) AS num_pagos
    FROM tratamientos t
    JOIN odontologos o ON t.id_odontologo = o.id_odontologo
    JOIN usuarios u    ON o.id_usuario = u.id_usuario
    WHERE t.id_paciente = ?
    ORDER BY t.fecha_creacion DESC
");
$stmt_trat->bind_param("i", $id_paciente);
$stmt_trat->execute();
$tratamientos = $stmt_trat->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-cash-coin text-success"></i> Mis Pagos</h1>
        <p class="lead">Resumen de tus tratamientos y pagos.</p>
        <hr>
    </div>
</div>

<!-- TARJETAS RESUMEN -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success text-center">
            <div class="card-body py-3">
                <i class="bi bi-cash-stack fs-2"></i>
                <h4 class="mt-1 mb-0">Bs. <?php echo number_format($resumen['total_pagado'], 2); ?></h4>
                <small>Total pagado</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white text-center <?php echo $resumen['total_pendiente'] > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
            <div class="card-body py-3">
                <i class="bi bi-hourglass-split fs-2"></i>
                <h4 class="mt-1 mb-0">Bs. <?php echo number_format($resumen['total_pendiente'], 2); ?></h4>
                <small>Saldo pendiente</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-primary text-center">
            <div class="card-body py-3">
                <i class="bi bi-receipt fs-2"></i>
                <h4 class="mt-1 mb-0"><?php echo $resumen['num_pagos']; ?></h4>
                <small>Pagos realizados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info text-center">
            <div class="card-body py-3">
                <i class="bi bi-clipboard-pulse fs-2"></i>
                <h4 class="mt-1 mb-0"><?php echo $resumen['num_tratamientos']; ?></h4>
                <small>Tratamientos</small>
            </div>
        </div>
    </div>
</div>

<!-- LISTA DE TRATAMIENTOS -->
<?php if (empty($tratamientos)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-clipboard-x fs-1"></i>
            <p class="mt-3 mb-0">No tienes tratamientos registrados.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($tratamientos as $t):
            $porcentaje = $t['costo_total'] > 0
                ? min(100, round(($t['total_pagado'] / $t['costo_total']) * 100))
                : 0;

            [$badge_class, $badge_texto] = match($t['estado']) {
                'pendiente'   => ['bg-warning text-dark', '⏳ Pendiente'],
                'en_progreso' => ['bg-primary',           '🔄 En progreso'],
                'completado'  => ['bg-success',           '✅ Completado'],
                'cancelado'   => ['bg-secondary',         '❌ Cancelado'],
                default       => ['bg-secondary',          $t['estado']]
            };

            $barra_color = $porcentaje == 100 ? 'bg-success' : ($porcentaje >= 50 ? 'bg-primary' : 'bg-warning');
        ?>
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm">

                <!-- Header de la tarjeta -->
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-clipboard-pulse"></i> <?php echo htmlspecialchars($t['nombre_tratamiento']); ?></strong>
                    <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_texto; ?></span>
                </div>

                <div class="card-body">

                    <!-- Odontólogo y fecha -->
                    <p class="text-muted small mb-2">
                        <i class="bi bi-person-badge"></i> Dr(a). <?php echo htmlspecialchars($t['odontologo']); ?>
                        <?php if ($t['fecha_inicio']): ?>
                            &nbsp;·&nbsp;
                            <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($t['fecha_inicio'])); ?>
                        <?php endif; ?>
                    </p>

                    <?php if ($t['descripcion']): ?>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($t['descripcion']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Montos -->
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="small text-muted">Costo total</div>
                            <div class="fw-bold">Bs. <?php echo number_format($t['costo_total'], 2); ?></div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Pagado</div>
                            <div class="fw-bold text-success">Bs. <?php echo number_format($t['total_pagado'], 2); ?></div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Pendiente</div>
                            <div class="fw-bold <?php echo $t['saldo_pendiente'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                Bs. <?php echo number_format($t['saldo_pendiente'], 2); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de progreso -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Progreso de pago</span>
                            <span><?php echo $porcentaje; ?>%</span>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar <?php echo $barra_color; ?>"
                                 style="width:<?php echo $porcentaje; ?>%"></div>
                        </div>
                    </div>

                </div>

                <!-- Footer con botón -->
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-receipt"></i> <?php echo $t['num_pagos']; ?> pago(s) registrado(s)
                    </small>
                    <a href="detalle_tratamiento.php?id=<?php echo $t['id_tratamiento']; ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver detalle
                    </a>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>