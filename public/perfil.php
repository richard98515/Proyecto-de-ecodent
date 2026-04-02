<?php
// public/perfil.php
// Perfil de usuario - Funciona para paciente, odontólogo y admin

require_once '../config/database.php';
require_once '../includes/funciones.php';

if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();
$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

// Obtener datos básicos del usuario
$stmt = $conexion->prepare("
    SELECT email, nombre_completo, telefono, fecha_registro
    FROM usuarios WHERE id_usuario = ?
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Obtener datos específicos según el rol
$datos_extra = [];
if ($rol == 'paciente') {
    $stmt2 = $conexion->prepare("
        SELECT fecha_nacimiento, direccion, ausencias_sin_aviso, llegadas_tarde, estado_cuenta
        FROM pacientes WHERE id_usuario = ?
    ");
    $stmt2->bind_param("i", $id_usuario);
    $stmt2->execute();
    $datos_extra = $stmt2->get_result()->fetch_assoc();
} elseif ($rol == 'odontologo') {
    $stmt2 = $conexion->prepare("
        SELECT especialidad_principal, especialidades_adicionales, duracion_cita_min, max_citas_dia, color_calendario
        FROM odontologos WHERE id_usuario = ?
    ");
    $stmt2->bind_param("i", $id_usuario);
    $stmt2->execute();
    $datos_extra = $stmt2->get_result()->fetch_assoc();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitizar($_POST['nombre']);
    $telefono = sanitizar($_POST['telefono']);
    
    // Actualizar usuario
    $update = $conexion->prepare("
        UPDATE usuarios SET nombre_completo = ?, telefono = ? WHERE id_usuario = ?
    ");
    $update->bind_param("ssi", $nombre, $telefono, $id_usuario);
    $update->execute();
    
    // Actualizar datos específicos según rol
    if ($rol == 'paciente' && isset($_POST['fecha_nacimiento'])) {
        $fecha_nac = $_POST['fecha_nacimiento'];
        $direccion = sanitizar($_POST['direccion']);
        $update2 = $conexion->prepare("
            UPDATE pacientes SET fecha_nacimiento = ?, direccion = ? WHERE id_usuario = ?
        ");
        $update2->bind_param("ssi", $fecha_nac, $direccion, $id_usuario);
        $update2->execute();
    }
    
    if ($rol == 'odontologo' && isset($_POST['especialidad'])) {
        $especialidad = sanitizar($_POST['especialidad']);
        $duracion = (int)$_POST['duracion_cita'];
        $max_citas = (int)$_POST['max_citas'];
        $update2 = $conexion->prepare("
            UPDATE odontologos 
            SET especialidad_principal = ?, duracion_cita_min = ?, max_citas_dia = ? 
            WHERE id_usuario = ?
        ");
        $update2->bind_param("siii", $especialidad, $duracion, $max_citas, $id_usuario);
        $update2->execute();
    }
    
    $_SESSION['nombre_completo'] = $nombre;
    $_SESSION['mensaje'] = "Perfil actualizado correctamente";
    redirigir('/ecodent/public/perfil.php');
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-person-circle"></i> Mi Perfil
                        <span class="badge bg-light text-dark float-end"><?php echo ucfirst($rol); ?></span>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['mensaje'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <h5 class="mb-3">Información personal</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Nombre completo</label>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled>
                                <small class="text-muted">El email no se puede cambiar</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" class="form-control" 
                                       value="<?php echo htmlspecialchars($usuario['telefono']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Miembro desde</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?>" disabled>
                            </div>
                        </div>
                        
                        <?php if ($rol == 'paciente'): ?>
                        <hr>
                        <h5 class="mb-3">Datos del paciente</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Fecha de nacimiento</label>
                                <input type="date" name="fecha_nacimiento" class="form-control" 
                                       value="<?php echo $datos_extra['fecha_nacimiento']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Dirección</label>
                                <input type="text" name="direccion" class="form-control" 
                                       value="<?php echo htmlspecialchars($datos_extra['direccion']); ?>">
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-4">
                                    <small>Ausencias:</small>
                                    <strong><?php echo $datos_extra['ausencias_sin_aviso']; ?></strong>
                                </div>
                                <div class="col-4">
                                    <small>Llegadas tarde:</small>
                                    <strong><?php echo $datos_extra['llegadas_tarde']; ?></strong>
                                </div>
                                <div class="col-4">
                                    <small>Estado:</small>
                                    <span class="badge bg-<?php 
                                        echo $datos_extra['estado_cuenta'] == 'normal' ? 'success' : 
                                            ($datos_extra['estado_cuenta'] == 'observacion' ? 'warning' : 'danger');
                                    ?>">
                                        <?php echo ucfirst($datos_extra['estado_cuenta']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($rol == 'odontologo'): ?>
                        <hr>
                        <h5 class="mb-3">Datos profesionales</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Especialidad principal</label>
                                <input type="text" name="especialidad" class="form-control" 
                                       value="<?php echo htmlspecialchars($datos_extra['especialidad_principal']); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Duración cita (min)</label>
                                <input type="number" name="duracion_cita" class="form-control" 
                                       value="<?php echo $datos_extra['duracion_cita_min']; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Máx. citas/día</label>
                                <input type="number" name="max_citas" class="form-control" 
                                       value="<?php echo $datos_extra['max_citas_dia']; ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar cambios
                            </button>
                            <a href="<?php echo ($rol == 'admin') ? '/ecodent/public/admin/dashboard.php' : (($rol == 'odontologo') ? '/ecodent/public/odontologo/calendario.php' : '/ecodent/public/paciente/dashboard.php'); ?>" 
                               class="btn btn-secondary">
                                Volver
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Cambiar contraseña -->
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-key"></i> Cambiar contraseña</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="cambiar_password.php">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <input type="password" name="actual" class="form-control" placeholder="Contraseña actual" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <input type="password" name="nueva" class="form-control" placeholder="Nueva contraseña" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <input type="password" name="confirmar" class="form-control" placeholder="Confirmar nueva" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">Actualizar contraseña</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>