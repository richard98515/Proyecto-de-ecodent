<?php
// public/odontologo/odontologo_nuevo.php
// Registrar nuevo odontólogo (solo administrador)

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// Verificar si es administrador
$es_admin = ($id_usuario == 1);

if (!$es_admin) {
    $_SESSION['error'] = "No tienes permisos para crear odontólogos.";
    redirigir('/ecodent/public/odontologo/odontologos.php');
}

$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = sanitizar($_POST['nombre']);
    $email = sanitizar($_POST['email']);
    $telefono = sanitizar($_POST['telefono']);
    $contrasena = $_POST['contrasena'];
    $especialidad = sanitizar($_POST['especialidad']);
    $duracion_cita = (int)$_POST['duracion_cita'];
    $max_citas_dia = (int)$_POST['max_citas_dia'];
    
    // Validaciones
    if (empty($nombre)) $errores[] = "El nombre completo es obligatorio.";
    if (empty($email)) $errores[] = "El email es obligatorio.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El email no es válido.";
    if (empty($telefono)) $errores[] = "El teléfono es obligatorio.";
    if (empty($contrasena)) $errores[] = "La contraseña es obligatoria.";
    if (strlen($contrasena) < 6) $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    if (empty($especialidad)) $errores[] = "La especialidad es obligatoria.";
    
    // Verificar email único
    if (empty($errores)) {
        $stmt_check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $errores[] = "Este email ya está registrado.";
        }
    }
    
    // Si no hay errores, insertar
    if (empty($errores)) {
        $conexion->begin_transaction();
        
        try {
            // Insertar usuario
            $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt_usuario = $conexion->prepare("INSERT INTO usuarios (email, contrasena_hash, nombre_completo, telefono, rol, email_verificado, activo) VALUES (?, ?, ?, ?, 'odontologo', 1, 1)");
            $stmt_usuario->bind_param("ssss", $email, $contrasena_hash, $nombre, $telefono);
            $stmt_usuario->execute();
            $id_usuario_nuevo = $conexion->insert_id;
            
            // Insertar odontólogo
            $stmt_odontologo = $conexion->prepare("INSERT INTO odontologos (id_usuario, especialidad_principal, duracion_cita_min, max_citas_dia, activo) VALUES (?, ?, ?, ?, 1)");
            $stmt_odontologo->bind_param("isii", $id_usuario_nuevo, $especialidad, $duracion_cita, $max_citas_dia);
            $stmt_odontologo->execute();
            
            $conexion->commit();
            $_SESSION['exito'] = "Odontólogo registrado correctamente.";
            redirigir('/ecodent/public/odontologo/odontologos.php');
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al registrar: " . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-person-plus-fill text-success"></i> Nuevo Odontólogo</h1>
        <p class="lead">Registra un nuevo odontólogo en el sistema.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="odontologos.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0 mt-2">
            <?php foreach ($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre completo *</label>
                    <input type="text" class="form-control" name="nombre" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono *</label>
                    <input type="tel" class="form-control" name="telefono" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraseña *</label>
                    <input type="password" class="form-control" name="contrasena" required>
                    <small class="text-muted">Mínimo 6 caracteres.</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Especialidad principal *</label>
                    <input type="text" class="form-control" name="especialidad" 
                           placeholder="Ej: Ortodoncia, Endodoncia, Cirugía Oral..." required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">Duración cita (minutos)</label>
                    <input type="number" class="form-control" name="duracion_cita" value="40" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">Máx. citas por día</label>
                    <input type="number" class="form-control" name="max_citas_dia" value="8" required>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> Registrar Odontólogo
            </button>
            <a href="odontologos.php" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </div>
</form>

<?php
require_once '../../includes/footer.php';
?>