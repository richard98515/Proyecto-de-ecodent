<?php
// public/paciente/detalle_tratamiento.php
require_once '../../includes/header.php';
require_once '../../includes/funciones.php';
require_once '../../config/database.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];
$id_tratamiento = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_tratamiento <= 0) {
    $_SESSION['error'] = "Tratamiento no válido.";
    redirigir('/ecodent/public/paciente/mis_pagos.php');
}

// Obtener id_paciente
$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

// Obtener tratamiento — verificando que sea del paciente
$stmt_trat = $conexion->prepare("
    SELECT t.*, u.nombre_completo AS odontologo, o.especialidad_principal
    FROM tratamientos t
    JOIN odontologos o ON t.id_odontologo = o.id_odontologo
    JOIN usuarios u    ON o.id_usuario = u.id_usuario
    WHERE t.id_tratamiento = ? AND t.id_paciente = ?
");
$stmt_trat->bind_param("ii", $id_tratamiento, $id_paciente);
$stmt_trat->execute();
$trat = $stmt_trat->get_result()->fetch_assoc();

if (!$trat) {
    $_SESSION['error'] = "Tratamiento no encontrado.";
    redirigir('/ecodent/public/paciente/mis_pagos.php');
}

// Obtener pagos del tratamiento
$stmt_pagos = $conexion->prepare("
    SELECT p.*, u.nombre_completo AS registrado_por
    FROM pagos p
    JOIN usuarios u ON p.id_usuario_registro = u.id_usuario
    WHERE p.id_tratamiento = ?
    ORDER BY p.fecha_pago ASC
");
$stmt_pagos->bind_param("i", $id_tratamiento);
$stmt_pagos->execute();
$pagos = $stmt_pagos->get_result()->fetch_all(MYSQLI_ASSOC);

$porcentaje = $trat['costo_total'] > 0
    ? min(100, round(($trat['total_pagado'] / $trat['costo_total']) * 100))
    : 0;

[$badge_class, $badge_texto] = match($trat['estado']) {
    'pendiente'   => ['bg-warning text-dark', '⏳ Pendiente'],
    'en_progreso' => ['bg-primary',           '🔄 En progreso'],
    'completado'  => ['bg-success',           '✅ Completado'],
    'cancelado'   => ['bg-secondary',         '❌ Cancelado'],
    default       => ['bg-secondary',          $trat['estado']]
};
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="mis_pagos.php">Mis Pagos</a></li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($trat['nombre_tratamiento']); ?></li>
    </ol>
</nav>

<div class="row">

    <!-- COLUMNA IZQUIERDA — Info del tratamiento -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-clipboard-pulse"></i> Tratamiento</h5>
            </div>
            <div class="card-body">
                <h5><?php echo htmlspecialchars($trat['nombre_tratamiento']); ?></h5>
                <span class="badge <?php echo $badge_class; ?> mb-3"><?php echo $badge_texto; ?></span>

                <?php if ($trat['descripcion']): ?>
                    <p class="text-muted small"><?php echo htmlspecialchars($trat['descripcion']); ?></p>
                <?php endif; ?>

                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted">Odontólogo</td>
                        <td><strong>Dr(a). <?php echo htmlspecialchars($trat['odontologo']); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Especialidad</td>
                        <td><?php echo htmlspecialchars($trat['especialidad_principal']); ?></td>
                    </tr>
                    <?php if ($trat['fecha_inicio']): ?>
                    <tr>
                        <td class="text-muted">Inicio</td>
                        <td><?php echo date('d/m/Y', strtotime($trat['fecha_inicio'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($trat['fecha_fin']): ?>
                    <tr>
                        <td class="text-muted">Fin</td>
                        <td><?php echo date('d/m/Y', strtotime($trat['fecha_fin'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($trat['notas']): ?>
                    <tr>
                        <td class="text-muted">Notas</td>
                        <td><small><?php echo htmlspecialchars($trat['notas']); ?></small></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Resumen de pagos -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Resumen</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Costo total</span>
                    <strong>Bs. <?php echo number_format($trat['costo_total'], 2); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total pagado</span>
                    <strong class="text-success">Bs. <?php echo number_format($trat['total_pagado'], 2); ?></strong>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Saldo pendiente</span>
                    <strong class="<?php echo $trat['saldo_pendiente'] > 0 ? 'text-danger' : 'text-success'; ?>">
                        Bs. <?php echo number_format($trat['saldo_pendiente'], 2); ?>
                    </strong>
                </div>

                <!-- Barra de progreso -->
                <div class="mb-1 d-flex justify-content-between small">
                    <span>Progreso</span><span><?php echo $porcentaje; ?>%</span>
                </div>
                <div class="progress mb-2" style="height:12px">
                    <div class="progress-bar <?php echo $porcentaje == 100 ? 'bg-success' : 'bg-primary'; ?>"
                         style="width:<?php echo $porcentaje; ?>%"></div>
                </div>

                <?php if ($porcentaje == 100): ?>
                    <div class="alert alert-success py-2 mb-0 text-center small">
                        ✅ ¡Tratamiento completamente pagado!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA — Historial de pagos -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Historial de pagos</h5>
                <span class="badge bg-light text-dark"><?php echo count($pagos); ?> pago(s)</span>
            </div>
            <div class="card-body p-0">

                <?php if (empty($pagos)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-cash-coin fs-1"></i>
                        <p class="mt-2">No hay pagos registrados para este tratamiento.</p>
                    </div>
                <?php else: ?>

                    <!-- Timeline de pagos -->
                    <div class="p-3">
                        <?php foreach ($pagos as $i => $p): ?>
                        <div class="d-flex gap-3 mb-3">

                            <!-- Número de pago -->
                            <div class="text-center" style="min-width:40px">
                                <div class="bg-success text-white rounded-circle d-flex align-items-center 
                                            justify-content-center mx-auto"
                                     style="width:36px;height:36px;font-weight:bold">
                                    <?php echo $i + 1; ?>
                                </div>
                                <?php if ($i < count($pagos) - 1): ?>
                                    <div class="border-start border-2 border-success mx-auto mt-1"
                                         style="height:30px;width:0"></div>
                                <?php endif; ?>
                            </div>

                            <!-- Detalle del pago -->
                            <div class="card flex-grow-1 border-success border-opacity-25">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($p['concepto']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                📅 <?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?>
                                                &nbsp;·&nbsp;
                                                <?php echo match($p['metodo_pago']) {
                                                    'efectivo'      => '💵 Efectivo',
                                                    'tarjeta'       => '💳 Tarjeta',
                                                    'transferencia' => '🏦 Transferencia',
                                                    default         => '📋 Otro'
                                                }; ?>
                                                &nbsp;·&nbsp;
                                                Registrado por: <?php echo htmlspecialchars($p['registrado_por']); ?>
                                            </small>
                                            <?php if ($p['observaciones']): ?>
                                                <br>
                                                <small class="text-muted fst-italic">
                                                    📝 <?php echo htmlspecialchars($p['observaciones']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end ms-3">
                                            <span class="fs-5 fw-bold text-success">
                                                Bs. <?php echo number_format($p['monto'], 2); ?>
                                            </span>
                                            <?php if ($p['foto_comprobante']): ?>
                                                <br>
                                                <a href="<?php echo htmlspecialchars($p['foto_comprobante']); ?>"
                                                   target="_blank"
                                                   class="btn btn-outline-primary btn-sm mt-1">
                                                    <i class="bi bi-image"></i> Comprobante
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <?php endforeach; ?>

                        <!-- Total -->
                        <div class="d-flex justify-content-end mt-2">
                            <div class="card bg-success text-white px-4 py-2">
                                <strong>Total pagado: Bs. <?php echo number_format($trat['total_pagado'], 2); ?></strong>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3">
            <a href="mis_pagos.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Mis Pagos
            </a>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>