<?php
// public/admin/dashboard.php
// Panel de Administrador - Vista global del sistema

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

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
$stmt = $conexion->prepare("SELECT COUNT(*) as total FROM citas WHERE fecha_cita = ? AND estado IN ('programada', 'confirmada')");
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

// Próximas citas
$proximas_citas = $conexion->query("
    SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.hora_fin,
           u.nombre_completo as paciente,
           od.nombre_completo as odontologo
    FROM citas c
    JOIN pacientes p ON c.id_paciente = p.id_paciente
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN odontologos o ON c.id_odontologo = o.id_odontologo
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
        <div>
            <span class="badge bg-light text-dark p-2">
                <i class="bi bi-calendar-check"></i> <?php echo $citas_hoy; ?> citas hoy
            </span>
        </div>
    </div>
</div>

<!-- Tarjetas de estadísticas -->
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

<!-- Segunda fila -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
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
    
    <div class="col-md-6 mb-3">
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