<?php
// public/odontologo/odontologos.php
// Módulo para gestionar odontólogos (solo usuarios con rol odontólogo pueden ver, pero solo admin puede crear/editar)

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// Verificar si es administrador (podemos verificar por email o crear un campo admin)
// Por ahora, solo permitimos crear odontólogos si el usuario es el primer odontólogo (ID 1)
$es_admin = ($id_usuario == 1); // El usuario con ID 1 es el administrador principal

// =============================================
// PROCESAR CRUD DE ODONTÓLOGOS
// =============================================

// Eliminar odontólogo
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar']) && $es_admin) {
    $id_odontologo = $_GET['eliminar'];
    
    // Verificar que no tenga citas
    // Verificar que no tenga citas (CORREGIDO: usando tratamientos)
        $stmt_verificar = $conexion->prepare("
            SELECT COUNT(*) as total 
            FROM citas c
            JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
            WHERE t.id_odontologo = ?
        ");
        $stmt_verificar->bind_param("i", $id_odontologo);
        $stmt_verificar->execute();
        $total_citas = $stmt_verificar->get_result()->fetch_assoc()['total'];
    
    if ($total_citas > 0) {
        $_SESSION['error'] = "No se puede eliminar el odontólogo porque tiene $total_citas cita(s) registrada(s).";
    } else {
        $stmt_eliminar = $conexion->prepare("DELETE FROM odontologos WHERE id_odontologo = ?");
        $stmt_eliminar->bind_param("i", $id_odontologo);
        
        if ($stmt_eliminar->execute()) {
            $_SESSION['exito'] = "Odontólogo eliminado correctamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar el odontólogo.";
        }
    }
    
    redirigir('/ecodent/public/odontologo/odontologos.php');
}

// =============================================
// OBTENER LISTA DE ODONTÓLOGOS
// =============================================

$sql = "SELECT o.id_odontologo, u.id_usuario, u.nombre_completo, u.email, u.telefono, 
               o.especialidad_principal, o.duracion_cita_min, o.max_citas_dia, o.activo
        FROM odontologos o
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        ORDER BY u.nombre_completo ASC";
$odontologos = $conexion->query($sql);

require_once '../../includes/header.php';

if (isset($_SESSION['exito'])) {
    echo '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill"></i> ' . $_SESSION['exito'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['exito']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill"></i> ' . $_SESSION['error'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error']);
}
?>

<style>
.odontologo-card {
    transition: all 0.3s ease;
    border-left: 4px solid #28a745;
}

.odontologo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.table-odontologos tbody tr:hover {
    background: linear-gradient(90deg, #e8f0fe 0%, #f0f8ff 100%);
    transform: scale(1.01);
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-person-badge-fill text-success"></i> Odontólogos</h1>
        <p class="lead">Lista de odontólogos registrados en el sistema.</p>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($es_admin): ?>
            <a href="odontologo_nuevo.php" class="btn btn-success">
                <i class="bi bi-person-plus-fill"></i> Nuevo Odontólogo
            </a>
        <?php endif; ?>
        <a href="pacientes.php" class="btn btn-outline-primary">
            <i class="bi bi-people"></i> Ver Pacientes
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">
            <i class="bi bi-list-ul me-2 text-success"></i>
            Lista de odontólogos
            <span class="badge bg-success ms-2"><?php echo $odontologos->num_rows; ?> total</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-odontologos table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Especialidad</th>
                        <th>Duración cita</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($odontologo = $odontologos->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-2">
                                        <i class="bi bi-person-circle fs-4 text-success"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($odontologo['nombre_completo']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <i class="bi bi-envelope me-1 text-muted"></i>
                                <?php echo htmlspecialchars($odontologo['email']); ?>
                            </td>
                            <td>
                                <i class="bi bi-telephone me-1 text-muted"></i>
                                <?php echo $odontologo['telefono'] ? htmlspecialchars($odontologo['telefono']) : 'No registrado'; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($odontologo['especialidad_principal']); ?></span>
                            </td>
                            <td>
                                <i class="bi bi-clock me-1"></i>
                                <?php echo $odontologo['duracion_cita_min']; ?> minutos
                            </td>
                            <td>
                                <?php if ($odontologo['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="odontologo_editar.php?id=<?php echo $odontologo['id_odontologo']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($es_admin && $odontologo['id_odontologo'] != 1): // No eliminar al admin principal ?>
                                    <a href="javascript:void(0)" 
                                       onclick="confirmarEliminacion(<?php echo $odontologo['id_odontologo']; ?>, '<?php echo addslashes($odontologo['nombre_completo']); ?>')"
                                       class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
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

<script>
function confirmarEliminacion(id, nombre) {
    if (confirm(`¿Estás seguro de eliminar al odontólogo "${nombre}"?\n\nEsta acción eliminará todos sus datos.`)) {
        window.location.href = `odontologos.php?eliminar=${id}`;
    }
}
</script>

<?php
require_once '../../includes/footer.php';
?>