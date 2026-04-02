<?php
// public/admin/ver_todas_alertas.php
// Listado completo de alertas

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

// Marcar alerta como leída
if (isset($_GET['marcar'])) {
    $id = (int)$_GET['marcar'];
    $stmt = $conexion->prepare("UPDATE alertas SET leida = 1 WHERE id_alerta = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id, $_SESSION['id_usuario']);
    $stmt->execute();
    redirigir('/ecodent/public/admin/ver_todas_alertas.php');
}

// Marcar todas como leídas
if (isset($_GET['marcar_todas'])) {
    $stmt = $conexion->prepare("UPDATE alertas SET leida = 1 WHERE id_usuario = ?");
    $stmt->bind_param("i", $_SESSION['id_usuario']);
    $stmt->execute();
    redirigir('/ecodent/public/admin/ver_todas_alertas.php');
}

// Obtener todas las alertas
$alertas = $conexion->prepare("
    SELECT a.*, r.nombre as regla_nombre
    FROM alertas a
    LEFT JOIN reglas_alertas r ON a.id_regla = r.id_regla
    WHERE a.id_usuario = ?
    ORDER BY a.fecha_creacion DESC
");
$alertas->bind_param("i", $_SESSION['id_usuario']);
$alertas->execute();
$alertas_lista = $alertas->get_result();

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-bell-fill text-warning"></i> Centro de Alertas</h1>
        <a href="?marcar_todas=1" class="btn btn-secondary" onclick="return confirm('¿Marcar todas como leídas?')">
            <i class="bi bi-check2-all"></i> Marcar todas como leídas
        </a>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Estado</th>
                        <th>Tipo</th>
                        <th>Título</th>
                        <th>Mensaje</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alertas_lista->num_rows > 0): ?>
                        <?php while($alerta = $alertas_lista->fetch_assoc()): ?>
                        <tr>
                            <td>>
                                <?php if ($alerta['leida']): ?>
                                    <i class="bi bi-check-circle text-success fs-5" title="Leída"></i>
                                <?php else: ?>
                                    <i class="bi bi-circle-fill text-primary fs-5" title="No leída"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $icono = 'info-circle';
                                $color = 'info';
                                if ($alerta['tipo'] == 'danger') {
                                    $icono = 'exclamation-octagon';
                                    $color = 'danger';
                                } elseif ($alerta['tipo'] == 'warning') {
                                    $icono = 'exclamation-triangle';
                                    $color = 'warning';
                                }
                                ?>
                                <i class="bi bi-<?php echo $icono; ?>-fill text-<?php echo $color; ?> fs-5"></i>
                            </td>
                            <td><strong><?php echo htmlspecialchars($alerta['titulo']); ?></strong></td>
                            <td><?php echo htmlspecialchars($alerta['mensaje']); ?></td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])); ?></small></td>
                            <td>
                                <?php if (!$alerta['leida']): ?>
                                    <a href="?marcar=<?php echo $alerta['id_alerta']; ?>" class="btn btn-sm btn-outline-success">
                                        Marcar leída
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                                No hay alertas registradas
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>