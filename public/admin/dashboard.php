<?php
// public/admin/dashboard.php
// Panel de Administrador - Vista global del sistema

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz');

// Verificar que sea admin
if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

// Obtener la conexión
$conexion = conectarBD();
$hoy = date('Y-m-d');

// =============================================
// ESTADÍSTICAS GLOBALES
// =============================================

// Total de pacientes
$result = $conexion->query("SELECT COUNT(*) as total FROM pacientes");
$total_pacientes = $result ? $result->fetch_assoc()['total'] : 0;

// Total de odontólogos activos
$result = $conexion->query("SELECT COUNT(*) as total FROM odontologos WHERE activo = 1");
$total_odontologos = $result ? $result->fetch_assoc()['total'] : 0;

// Total de citas hoy
$stmt = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    WHERE c.fecha_cita = ? AND c.estado IN ('programada', 'confirmada')
");
$stmt->bind_param("s", $hoy);
$stmt->execute();
$result = $stmt->get_result();
$citas_hoy = $result ? $result->fetch_assoc()['total'] : 0;

// Pacientes con problemas
$result = $conexion->query("SELECT COUNT(*) as total FROM pacientes WHERE estado_cuenta IN ('observacion', 'restringida', 'bloqueada')");
$pacientes_problema = $result ? $result->fetch_assoc()['total'] : 0;

// Ingresos del mes actual
$result = $conexion->query("SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())");
$ingresos_mes = $result ? $result->fetch_assoc()['total'] : 0;

// =============================================
// SLOTS BLOQUEADOS - MONITOREO DE LIMPIEZA
// =============================================

// Slots bloqueados futuros (los que están vigentes)
$stmt_slots_futuros = $conexion->prepare("SELECT COUNT(*) as total FROM slots_bloqueados WHERE fecha >= CURDATE()");
$stmt_slots_futuros->execute();
$slots_futuros = $stmt_slots_futuros->get_result()->fetch_assoc()['total'];

// Slots bloqueados pasados (los que NO se limpiaron - deberían ser 0)
$stmt_slots_pasados = $conexion->prepare("SELECT COUNT(*) as total FROM slots_bloqueados WHERE fecha < CURDATE()");
$stmt_slots_pasados->execute();
$slots_pasados = $stmt_slots_pasados->get_result()->fetch_assoc()['total'];

$total_slots_bloqueados = $slots_futuros + $slots_pasados;

// =============================================
// ALERTAS - NOTIFICACIONES
// =============================================

// Contar alertas no leídas
$stmt_alertas = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM alertas 
    WHERE id_usuario = ? AND leida = 0
");
$stmt_alertas->bind_param("i", $_SESSION['id_usuario']);
$stmt_alertas->execute();
$alertas_no_leidas = $stmt_alertas->get_result()->fetch_assoc()['total'];

// Obtener últimas 5 alertas
$stmt_lista = $conexion->prepare("
    SELECT id_alerta, titulo, mensaje, tipo, fecha_creacion, leida
    FROM alertas 
    WHERE id_usuario = ?
    ORDER BY fecha_creacion DESC 
    LIMIT 5
");
$stmt_lista->bind_param("i", $_SESSION['id_usuario']);
$stmt_lista->execute();
$alertas_lista = $stmt_lista->get_result();

// Próximas citas
$proximas_citas = $conexion->query("
    SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.hora_fin,
           u.nombre_completo as paciente,
           od.nombre_completo as odontologo
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    JOIN pacientes p ON t.id_paciente = p.id_paciente
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN odontologos o ON t.id_odontologo = o.id_odontologo
    JOIN usuarios od ON o.id_usuario = od.id_usuario
    WHERE c.fecha_cita >= CURDATE()
    AND c.estado IN ('programada', 'confirmada')
    ORDER BY c.fecha_cita ASC, c.hora_cita ASC
    LIMIT 10
");

// Últimos backups
$backups = $conexion->query("
    SELECT * FROM backups 
    ORDER BY fecha_creacion DESC 
    LIMIT 5
");

require_once '../../includes/header.php';
?>

<style>
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
}
.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 8px;
}
.stat-label {
    color: #7f8c8d;
    font-size: 14px;
    font-weight: 500;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.action-card {
    text-align: center;
    padding: 20px;
    border-radius: 12px;
    background: #f8f9fa;
    transition: all 0.3s;
    text-decoration: none;
    display: block;
}
.action-card:hover {
    background: #e9ecef;
    transform: translateY(-3px);
    text-decoration: none;
}
.action-card i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}
.citas-table tbody tr {
    cursor: pointer;
    transition: background 0.2s;
}
.citas-table tbody tr:hover {
    background: #f8f9fa;
}
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
}
/* Estilos para el dropdown de alertas */
.alertas-dropdown {
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
}
.alertas-item {
    transition: background 0.2s;
}
.alertas-item:hover {
    background: #f8f9fa;
}
.alertas-item.no-leida {
    background: #fff3cd;
    border-left: 3px solid #ffc107;
}
</style>

<div class="admin-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-2">
                <i class="bi bi-shield-shaded"></i> Panel de Administración
            </h1>
            <p class="mb-0 opacity-75">
                Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Administrador'); ?> | 
                <?php echo strftime('%A, %d de %B de %Y', strtotime($hoy)); ?>
            </p>
        </div>
        <div class="d-flex align-items-center">
            <!-- CAMPANITA DE ALERTAS -->
            <div class="dropdown me-3">
                <a href="#" class="text-white position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell-fill fs-3"></i>
                    <?php if ($alertas_no_leidas > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $alertas_no_leidas; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end alertas-dropdown p-0">
                    <div class="dropdown-header bg-light py-2 px-3">
                        <strong><i class="bi bi-bell-fill me-1"></i> Notificaciones</strong>
                        <?php if ($alertas_no_leidas > 0): ?>
                            <a href="marcar_todas_alertas.php" class="float-end small text-decoration-none">
                                Marcar todas
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-divider m-0"></div>
                    <?php if ($alertas_lista->num_rows > 0): ?>
                        <?php while($alerta = $alertas_lista->fetch_assoc()): ?>
                            <a href="ver_alerta.php?id=<?php echo $alerta['id_alerta']; ?>" 
                               class="dropdown-item alertas-item <?php echo $alerta['leida'] ? '' : 'no-leida'; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="me-2 mt-1">
                                        <?php if ($alerta['tipo'] == 'danger'): ?>
                                            <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
                                        <?php elseif ($alerta['tipo'] == 'warning'): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                        <?php else: ?>
                                            <i class="bi bi-info-circle-fill text-info fs-5"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?php echo htmlspecialchars($alerta['titulo']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars(substr($alerta['mensaje'], 0, 60)); ?>...</div>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])); ?></small>
                                    </div>
                                    <?php if (!$alerta['leida']): ?>
                                        <span class="badge bg-primary rounded-pill ms-2 mt-1" style="font-size: 10px;">Nueva</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="dropdown-divider m-0"></div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="dropdown-item text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                            No hay alertas
                        </div>
                    <?php endif; ?>
                    <div class="dropdown-footer text-center p-2 bg-light">
                        <a href="ver_todas_alertas.php" class="small text-decoration-none">
                            Ver todas las alertas
                        </a>
                    </div>
                </div>
            </div>
            
            <span class="badge bg-light text-dark p-2">
                <i class="bi bi-calendar-check"></i> <?php echo $citas_hoy; ?> citas hoy
            </span>
        </div>
    </div>
</div>

<!-- Tarjetas de estadísticas - Fila 1 -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card" style="border-left-color: #4361ee;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $total_pacientes; ?></div>
                    <div class="stat-label">Pacientes Registrados</div>
                </div>
                <div class="stat-icon" style="background: #eef2ff; color: #4361ee;">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card" style="border-left-color: #06d6a0;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $total_odontologos; ?></div>
                    <div class="stat-label">Odontólogos Activos</div>
                </div>
                <div class="stat-icon" style="background: #e0faf3; color: #06d6a0;">
                    <i class="bi bi-hospital"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card" style="border-left-color: #ffb703;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $citas_hoy; ?></div>
                    <div class="stat-label">Citas Programadas Hoy</div>
                </div>
                <div class="stat-icon" style="background: #fff3e0; color: #ffb703;">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card" style="border-left-color: #e63946;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $pacientes_problema; ?></div>
                    <div class="stat-label">Pacientes con Problemas</div>
                </div>
                <div class="stat-icon" style="background: #ffe5e8; color: #e63946;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Segunda fila - 3 columnas (ingresos, backups, slots) -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="stat-card" style="border-left-color: #764ba2;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">Bs. <?php echo number_format($ingresos_mes, 2); ?></div>
                    <div class="stat-label">Ingresos del Mes</div>
                </div>
                <div class="stat-icon" style="background: #f0e6ff; color: #764ba2;">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="stat-card" style="border-left-color: #17a2b8;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $backups ? $backups->num_rows : 0; ?></div>
                    <div class="stat-label">Backups Realizados</div>
                </div>
                <div class="stat-icon" style="background: #e0f7fa; color: #17a2b8;">
                    <i class="bi bi-database"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="stat-card" style="border-left-color: <?php echo ($slots_pasados > 0) ? '#e63946' : '#06d6a0'; ?>;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">
                        <?php echo $slots_futuros; ?>
                        <?php if ($slots_pasados > 0): ?>
                            <span class="badge bg-danger ms-2" style="font-size: 14px;">+<?php echo $slots_pasados; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">
                        Slots Bloqueados
                        <?php if ($slots_pasados == 0 && $total_slots_bloqueados > 0): ?>
                            <i class="bi bi-check-circle text-success" title="Limpieza automática activa"></i>
                        <?php elseif ($slots_pasados > 0): ?>
                            <i class="bi bi-exclamation-triangle text-danger" title="Hay slots pasados sin limpiar"></i>
                        <?php endif; ?>
                    </div>
                    <?php if ($slots_pasados > 0): ?>
                        <small class="text-danger">
                            ⚠️ <?php echo $slots_pasados; ?> slots pasados (ejecuta .bat)
                        </small>
                    <?php else: ?>
                        <small class="text-muted">
                            <?php if ($total_slots_bloqueados == 0): ?>
                                Sin bloqueos activos
                            <?php else: ?>
                                ✅ Limpieza automática OK
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="stat-icon" style="background: <?php echo ($slots_pasados > 0) ? '#ffe5e8' : '#e0faf3'; ?>; color: <?php echo ($slots_pasados > 0) ? '#e63946' : '#06d6a0'; ?>;">
                    <i class="bi bi-lock"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones Rápidas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>
                    Acciones de Administración
                </h5>
            </div>
            <div class="card-body">
                <div class="action-grid">
                    <a href="/ecodent/public/admin/gestion_odontologos.php" class="action-card">
                        <i class="bi bi-person-plus text-primary"></i>
                        <strong>Gestionar Odontólogos</strong>
                        <small class="text-muted d-block">Crear, editar o desactivar</small>
                    </a>
                    
                    <a href="/ecodent/public/odontologo/calendario.php" class="action-card">
                        <i class="bi bi-calendar3 text-info"></i>
                        <strong>Ver Calendario</strong>
                        <small class="text-muted d-block">Como odontólogo</small>
                    </a>
                    
                    <a href="/ecodent/public/odontologo/pacientes.php" class="action-card">
                        <i class="bi bi-people text-success"></i>
                        <strong>Ver Pacientes</strong>
                        <small class="text-muted d-block">Todos los pacientes</small>
                    </a>
                    
                    <a href="/ecodent/public/admin/reglas_alertas.php" class="action-card">
                        <i class="bi bi-bell text-warning"></i>
                        <strong>Reglas de Alertas</strong>
                        <small class="text-muted d-block">Configurar alertas automáticas</small>
                    </a>
                    
                    <a href="/ecodent/public/admin/ver_backups.php" class="action-card">
                        <i class="bi bi-database text-secondary"></i>
                        <strong>Gestionar Backups</strong>
                        <small class="text-muted d-block">Respaldos automáticos</small>
                    </a>
                    
                    <a href="/ecodent/public/admin/estadisticas.php" class="action-card">
                        <i class="bi bi-graph-up text-danger"></i>
                        <strong>Estadísticas Globales</strong>
                        <small class="text-muted d-block">Reportes detallados</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Próximas Citas -->
<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-list-check me-2 text-primary"></i>
                    Próximas Citas - Todos los Odontólogos
                </h5>
            </div>
            <div class="card-body p-0">
                <table class="citas-table table mb-0">
                    <thead>
                        32
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>Odontólogo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proximas_citas && $proximas_citas->num_rows > 0): ?>
                            <?php while($cita = $proximas_citas->fetch_assoc()): ?>
                            <tr onclick="window.location.href='/ecodent/public/odontologo/detalle_cita.php?id_cita=<?php echo $cita['id_cita']; ?>'">
                                <td>
                                    <?php 
                                    if ($cita['fecha_cita'] == $hoy) {
                                        echo '<span class="badge bg-primary">Hoy</span>';
                                    } else {
                                        echo date('d/m/Y', strtotime($cita['fecha_cita']));
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('h:i A', strtotime($cita['hora_cita'])); ?> - <?php echo date('h:i A', strtotime($cita['hora_fin'])); ?></td>
                                <td><?php echo htmlspecialchars($cita['paciente']); ?></td>
                                <td><i class="bi bi-hospital"></i> Dr. <?php echo htmlspecialchars($cita['odontologo']); ?></td>
                                <td class="text-end"><i class="bi bi-chevron-right text-primary"></i></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No hay citas programadas
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Últimos Backups -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-database me-2 text-secondary"></i>
                    Últimos Backups
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($backups && $backups->num_rows > 0): ?>
                        <?php while($backup = $backups->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-zip text-secondary"></i>
                                    <small><?php echo htmlspecialchars($backup['nombre_archivo']); ?></small>
                                </div>
                                <span class="badge bg-success"><?php echo htmlspecialchars($backup['estado']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo $backup['fecha_creacion']; ?></small>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="bi bi-database-slash fs-3 d-block mb-2"></i>
                            No hay backups registrados
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white">
                <a href="/ecodent/public/admin/ver_backups.php" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-database"></i> Gestionar Backups
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.citas-table tbody tr').forEach(row => {
    if (row.querySelector('td[colspan]')) return;
    row.addEventListener('click', function() {
        var onclickAttr = this.getAttribute('onclick');
        if (onclickAttr) {
            var match = onclickAttr.match(/'(.*?)'/);
            if (match && match[1]) {
                window.location.href = match[1];
            }
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>