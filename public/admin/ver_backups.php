<?php
// public/admin/ver_backups.php
// Ver y gestionar backups del sistema

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

// Realizar backup manual
if (isset($_GET['backup_manual'])) {
    $tipo = $_GET['backup_manual'];
    $backup_dir = $_SERVER['DOCUMENT_ROOT'] . '/ecodent/backups/';
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $fecha = date('Y-m-d_H-i-s');
    $nombre = "ecodent_backup_{$tipo}_{$fecha}.sql";
    $ruta = $backup_dir . $nombre;
    
    $comando = "mysqldump --host=localhost --user=root --password= ecodent > \"{$ruta}\" 2>&1";
    exec($comando, $output, $return);
    
    if ($return === 0 && file_exists($ruta)) {
        $tamano = filesize($ruta);
        $stmt = $conexion->prepare("
            INSERT INTO backups (nombre_archivo, tipo, tamano, ruta, estado) 
            VALUES (?, ?, ?, ?, 'exitoso')
        ");
        $stmt->bind_param("ssis", $nombre, $tipo, $tamano, $ruta);
        $stmt->execute();
        $mensaje = "Backup {$tipo} realizado exitosamente";
    } else {
        $error = "Error al realizar backup: " . implode("\n", $output);
    }
    redirigir('/ecodent/public/admin/ver_backups.php?msg=' . urlencode($mensaje ?? '') . '&err=' . urlencode($error ?? ''));
}

$backups = $conexion->query("
    SELECT * FROM backups 
    ORDER BY fecha_creacion DESC 
    LIMIT 50
");

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-database"></i> Gestión de Backups</h1>
        <div>
            <a href="?backup_manual=diario" class="btn btn-primary" onclick="return confirm('¿Realizar backup diario?')">
                <i class="bi bi-plus-circle"></i> Backup Diario
            </a>
            <a href="?backup_manual=semanal" class="btn btn-warning" onclick="return confirm('¿Realizar backup semanal?')">
                <i class="bi bi-plus-circle"></i> Backup Semanal
            </a>
            <a href="?backup_manual=mes" class="btn btn-info" onclick="return confirm('¿Realizar backup mensual?')">
                <i class="bi bi-plus-circle"></i> Backup Mensual
            </a>
        </div>
    </div>
    
    <?php if (isset($_GET['msg']) && $_GET['msg']): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['err']) && $_GET['err']): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['err']); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($backups && $backups->num_rows > 0): ?>
                        <?php while($b = $backups->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $b['id_backup']; ?></td>
                            <td><?php echo htmlspecialchars($b['nombre_archivo']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo $b['tipo']; ?></span></td>
                            <td><?php echo number_format($b['tamano'] / 1024, 2); ?> KB</td>
                            <td>
                                <span class="badge bg-<?php echo $b['estado'] == 'exitoso' ? 'success' : 'danger'; ?>">
                                    <?php echo $b['estado']; ?>
                                </span>
                            </td>
                            <td><?php echo $b['fecha_creacion']; ?></td>
                            <td>
                                <a href="<?php echo $b['ruta']; ?>" class="btn btn-sm btn-success" download>
                                    <i class="bi bi-download"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-database-slash fs-1 d-block mb-2"></i>
                                No hay backups registrados
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