<?php
// public/admin/gestion_odontologos.php
// Gestión de odontólogos (crear, editar, activar/desactivar)

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || $_SESSION['rol'] !== 'admin') {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();
$mensaje = '';
$error = '';

// Procesar eliminación/desactivación
if (isset($_GET['desactivar']) && is_numeric($_GET['desactivar'])) {
    $id = $_GET['desactivar'];
    $stmt = $conexion->prepare("UPDATE odontologos SET activo = 0 WHERE id_odontologo = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mensaje = "Odontólogo desactivado correctamente";
    } else {
        $error = "Error al desactivar odontólogo";
    }
}

if (isset($_GET['activar']) && is_numeric($_GET['activar'])) {
    $id = $_GET['activar'];
    $stmt = $conexion->prepare("UPDATE odontologos SET activo = 1 WHERE id_odontologo = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mensaje = "Odontólogo activado correctamente";
    } else {
        $error = "Error al activar odontólogo";
    }
}

// Obtener lista de odontólogos
$odontologos = $conexion->query("
    SELECT o.id_odontologo, o.especialidad_principal, o.activo,
           u.id_usuario, u.nombre_completo, u.email, u.telefono
    FROM odontologos o
    JOIN usuarios u ON o.id_usuario = u.id_usuario
    ORDER BY o.activo DESC, u.nombre_completo ASC
");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-people"></i> Gestionar Odontólogos
        </h1>
        <a href="crear_odontologo.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Nuevo Odontólogo
        </a>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Especialidad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($odontologo = $odontologos->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $odontologo['id_odontologo']; ?></td>
                            <td>
                                <strong><?php echo $odontologo['nombre_completo']; ?></strong>
                            </td>
                            <td><?php echo $odontologo['email']; ?></td>
                            <td><?php echo $odontologo['telefono'] ?? '—'; ?></td>
                            <td><?php echo $odontologo['especialidad_principal']; ?></td>
                            <td>
                                <?php if ($odontologo['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar_odontologo.php?id=<?php echo $odontologo['id_odontologo']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                
                                <?php if ($odontologo['activo']): ?>
                                    <a href="?desactivar=<?php echo $odontologo['id_odontologo']; ?>" 
                                       class="btn btn-sm btn-outline-warning"
                                       onclick="return confirm('¿Desactivar este odontólogo? No podrá atender citas.')">
                                        <i class="bi bi-ban"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?activar=<?php echo $odontologo['id_odontologo']; ?>" 
                                       class="btn btn-sm btn-outline-success"
                                       onclick="return confirm('¿Activar este odontólogo?')">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>