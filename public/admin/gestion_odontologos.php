<?php
// public/admin/gestion_odontologos.php
// Gestionar odontólogos: crear, editar, activar/desactivar

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();
$mensaje = '';
$error = '';

// Procesar creación de nuevo odontólogo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $email = sanitizar($_POST['email']);
    $nombre = sanitizar($_POST['nombre']);
    $telefono = sanitizar($_POST['telefono']);
    $especialidad = sanitizar($_POST['especialidad']);
    $duracion_cita = (int)$_POST['duracion_cita'];
    $max_citas = (int)$_POST['max_citas'];
    $color = sanitizar($_POST['color']);
    
    // Verificar si email ya existe
    $check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "El email ya está registrado";
    } else {
        $conexion->begin_transaction();
        try {
            // Crear usuario
            $password_hash = password_hash('123456', PASSWORD_DEFAULT); // Contraseña temporal
            $stmt = $conexion->prepare("
                INSERT INTO usuarios (email, contrasena_hash, nombre_completo, telefono, rol, email_verificado, activo)
                VALUES (?, ?, ?, ?, 'odontologo', 1, 1)
            ");
            $stmt->bind_param("ssss", $email, $password_hash, $nombre, $telefono);
            $stmt->execute();
            $id_usuario = $conexion->insert_id;
            
            // Crear odontólogo
            $stmt2 = $conexion->prepare("
                INSERT INTO odontologos (id_usuario, especialidad_principal, duracion_cita_min, max_citas_dia, color_calendario, activo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt2->bind_param("isiss", $id_usuario, $especialidad, $duracion_cita, $max_citas, $color);
            $stmt2->execute();
            $id_odontologo = $conexion->insert_id;
            
            // Crear horarios por defecto (Lunes a Viernes 8:00-18:00)
            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
            $stmt3 = $conexion->prepare("
                INSERT INTO horarios_odontologos (id_odontologo, dia_semana, hora_inicio, hora_fin, activo)
                VALUES (?, ?, '08:00:00', '18:00:00', 1)
            ");
            foreach ($dias as $dia) {
                $stmt3->bind_param("is", $id_odontologo, $dia);
                $stmt3->execute();
            }
            
            $conexion->commit();
            $mensaje = "Odontólogo creado exitosamente. Contraseña temporal: 123456";
        } catch (Exception $e) {
            $conexion->rollback();
            $error = "Error al crear: " . $e->getMessage();
        }
    }
}

// Procesar toggle activo/inactivo
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conexion->prepare("UPDATE odontologos SET activo = NOT activo WHERE id_odontologo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    redirigir('/ecodent/public/admin/gestion_odontologos.php');
}

// Obtener lista de odontólogos
$odontologos = $conexion->query("
    SELECT o.*, u.email, u.nombre_completo, u.telefono
    FROM odontologos o
    JOIN usuarios u ON o.id_usuario = u.id_usuario
    ORDER BY o.id_odontologo
");

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-person-badge"></i> Gestionar Odontólogos</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo">
            <i class="bi bi-plus-circle"></i> Nuevo Odontólogo
        </button>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Especialidad</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($odonto = $odontologos->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $odonto['id_odontologo']; ?></td>
                        <td><?php echo htmlspecialchars($odonto['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($odonto['email']); ?></td>
                        <td><?php echo htmlspecialchars($odonto['telefono']); ?></td>
                        <td><?php echo htmlspecialchars($odonto['especialidad_principal']); ?></td>
                        <td><?php echo $odonto['duracion_cita_min']; ?> min</td>
                        <td>
                            <span class="badge bg-<?php echo $odonto['activo'] ? 'success' : 'danger'; ?>">
                                <?php echo $odonto['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?toggle=<?php echo $odonto['id_odontologo']; ?>" 
                               class="btn btn-sm btn-<?php echo $odonto['activo'] ? 'warning' : 'success'; ?>"
                               onclick="return confirm('¿Cambiar estado?')">
                                <i class="bi bi-<?php echo $odonto['activo'] ? 'pause-circle' : 'play-circle'; ?>"></i>
                            </a>
                            <button class="btn btn-sm btn-info" onclick="verHorarios(<?php echo $odonto['id_odontologo']; ?>)">
                                <i class="bi bi-clock"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Odontólogo -->
<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Nuevo Odontólogo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nombre completo *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                        <small class="text-muted">Contraseña temporal: 123456</small>
                    </div>
                    <div class="mb-3">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Especialidad principal *</label>
                        <input type="text" name="especialidad" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Duración cita (minutos)</label>
                            <input type="number" name="duracion_cita" class="form-control" value="40" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Máx. citas/día</label>
                            <input type="number" name="max_citas" class="form-control" value="8" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Color en calendario</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#2E75B6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear" class="btn btn-primary">Crear Odontólogo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verHorarios(id) {
    window.location.href = 'horarios_odontologo.php?id=' + id;
}
</script>

<?php require_once '../../includes/footer.php'; ?>