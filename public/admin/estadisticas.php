<?php
// public/admin/estadisticas.php
// Reportes y estadísticas globales del sistema

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish');
if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

// Obtener estadísticas por mes
$mes_actual = date('m');
$anio_actual = date('Y');

// Estadísticas de citas por mes
$citas_por_mes = $conexion->query("
    SELECT 
        MONTH(c.fecha_cita) as mes,
        COUNT(*) as total,
        SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN c.estado = 'ausente' THEN 1 ELSE 0 END) as ausentes,
        SUM(CASE WHEN c.estado = 'cancelada_pac' THEN 1 ELSE 0 END) as canceladas_pac,
        SUM(CASE WHEN c.estado = 'cancelada_doc' THEN 1 ELSE 0 END) as canceladas_doc
    FROM citas c
    WHERE YEAR(c.fecha_cita) = $anio_actual
    GROUP BY MONTH(c.fecha_cita)
    ORDER BY mes
");

// Ingresos por mes
$ingresos_por_mes = $conexion->query("
    SELECT 
        MONTH(fecha_pago) as mes,
        SUM(monto) as total
    FROM pagos
    WHERE YEAR(fecha_pago) = $anio_actual
    GROUP BY MONTH(fecha_pago)
    ORDER BY mes
");

// Top odontólogos por citas atendidas
$top_odontologos = $conexion->query("
    SELECT 
        u.nombre_completo,
        COUNT(c.id_cita) as total_citas,
        SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as atendidas,
        SUM(CASE WHEN c.estado = 'ausente' THEN 1 ELSE 0 END) as ausencias
    FROM odontologos o
    JOIN usuarios u ON o.id_usuario = u.id_usuario
    LEFT JOIN citas c ON c.id_tratamiento IN (
        SELECT id_tratamiento FROM tratamientos WHERE id_odontologo = o.id_odontologo
    ) AND YEAR(c.fecha_cita) = $anio_actual
    WHERE o.activo = 1
    GROUP BY o.id_odontologo
    ORDER BY atendidas DESC
    LIMIT 10
");

// Pacientes con problemas
$pacientes_problema = $conexion->query("
    SELECT 
        u.nombre_completo,
        p.ausencias_sin_aviso,
        p.llegadas_tarde,
        p.estado_cuenta
    FROM pacientes p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.estado_cuenta IN ('observacion', 'restringida', 'bloqueada')
    ORDER BY p.ausencias_sin_aviso DESC
    LIMIT 20
");

// Tratamientos más comunes
$tratamientos_top = $conexion->query("
    SELECT 
        nombre_tratamiento,
        COUNT(*) as total,
        AVG(costo_total) as costo_promedio
    FROM tratamientos
    GROUP BY nombre_tratamiento
    ORDER BY total DESC
    LIMIT 10
");

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <h1><i class="bi bi-graph-up"></i> Estadísticas Globales</h1>
    <p class="text-muted">Reportes del año <?php echo $anio_actual; ?></p>
    
    <!-- Resumen rápido -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php 
                        $total = $conexion->query("SELECT COUNT(*) as t FROM citas c WHERE YEAR(c.fecha_cita) = $anio_actual")->fetch_assoc()['t'];
                        echo $total;
                    ?></h3>
                    <p>Total Citas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php 
                        $total = $conexion->query("SELECT COALESCE(SUM(monto),0) as t FROM pagos WHERE YEAR(fecha_pago) = $anio_actual")->fetch_assoc()['t'];
                        echo "Bs. " . number_format($total, 0);
                    ?></h3>
                    <p>Ingresos Totales</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h3><?php 
                        $total = $conexion->query("SELECT COUNT(*) as t FROM pacientes WHERE estado_cuenta != 'normal'")->fetch_assoc()['t'];
                        echo $total;
                    ?></h3>
                    <p>Pacientes con Problemas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?php 
                        $total = $conexion->query("SELECT COUNT(*) as t FROM odontologos WHERE activo = 1")->fetch_assoc()['t'];
                        echo $total;
                    ?></h3>
                    <p>Odontólogos Activos</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Citas por mes -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5><i class="bi bi-calendar"></i> Citas por Mes</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Total</th>
                                <th>Completadas</th>
                                <th>Ausentes</th>
                                <th>Canceladas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $citas_por_mes->fetch_assoc()): ?>
                            <tr>
                                <<td><?php echo ucfirst(strftime('%B', mktime(0,0,0,$row['mes'],1))); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td class="text-success"><?php echo $row['completadas']; ?></td>
                                <td class="text-danger"><?php echo $row['ausentes']; ?></td>
                                <td class="text-warning"><?php echo $row['canceladas_pac'] + $row['canceladas_doc']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Ingresos por mes -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5><i class="bi bi-cash-stack"></i> Ingresos por Mes (Bs.)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $ingresos_por_mes->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst(strftime('%B', mktime(0,0,0,$row['mes'],1))); ?></td>
                                <td class="fw-bold">Bs. <?php echo number_format($row['total'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Top Odontólogos -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5><i class="bi bi-hospital"></i> Top Odontólogos (Citas Atendidas)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Odontólogo</th>
                                <th>Atendidas</th>
                                <th>Ausencias</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $top_odontologos->fetch_assoc()): ?>
                            <tr>
                                <td>Dr. <?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                                <td class="text-success"><?php echo $row['atendidas']; ?></td>
                                <td class="text-danger"><?php echo $row['ausencias']; ?></td>
                                <td><?php echo $row['total_citas']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Pacientes con Problemas -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5><i class="bi bi-exclamation-triangle text-danger"></i> Pacientes con Problemas</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Ausencias</th>
                                <th>Llegadas tarde</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $pacientes_problema->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                                <td class="text-danger"><?php echo $row['ausencias_sin_aviso']; ?></td>
                                <td class="text-warning"><?php echo $row['llegadas_tarde']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $row['estado_cuenta'] == 'bloqueada' ? 'danger' : 
                                            ($row['estado_cuenta'] == 'restringida' ? 'warning' : 'info');
                                    ?>">
                                        <?php echo $row['estado_cuenta']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tratamientos más comunes -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5><i class="bi bi-file-medical"></i> Tratamientos Más Realizados</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Tratamiento</th>
                        <th>Veces realizado</th>
                        <th>Costo promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $tratamientos_top->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombre_tratamiento']); ?></td>
                        <td><?php echo $row['total']; ?></td>
                        <td>Bs. <?php echo number_format($row['costo_promedio'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>