<?php
// public/odontologo/paciente_nuevo.php
// Registrar nuevo paciente

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];
$errores = [];
$exito = false;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = sanitizar($_POST['nombre']);
    $email = sanitizar($_POST['email']);
    $telefono = sanitizar($_POST['telefono']);
    $contrasena = $_POST['contrasena'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $direccion = sanitizar($_POST['direccion']);
    
    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre completo es obligatorio.";
    }
    
    if (empty($email)) {
        $errores[] = "El email es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email no es válido.";
    }
    
    if (empty($telefono)) {
        $errores[] = "El teléfono es obligatorio.";
    }
    
    if (empty($contrasena)) {
        $errores[] = "La contraseña es obligatoria.";
    } elseif (strlen($contrasena) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    }
    
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
            $stmt_usuario = $conexion->prepare("INSERT INTO usuarios (email, contrasena_hash, nombre_completo, telefono, rol, email_verificado, activo) VALUES (?, ?, ?, ?, 'paciente', 1, 1)");
            $stmt_usuario->bind_param("ssss", $email, $contrasena_hash, $nombre, $telefono);
            $stmt_usuario->execute();
            $id_usuario_nuevo = $conexion->insert_id;
            
            // Insertar paciente
            $stmt_paciente = $conexion->prepare("INSERT INTO pacientes (id_usuario, fecha_nacimiento, direccion, estado_cuenta, puede_agendar) VALUES (?, ?, ?, 'normal', 1)");
            $stmt_paciente->bind_param("iss", $id_usuario_nuevo, $fecha_nacimiento, $direccion);
            $stmt_paciente->execute();
            
            $conexion->commit();
            $exito = true;
            $_SESSION['exito'] = "Paciente registrado correctamente.";
            redirigir('/ecodent/public/odontologo/pacientes.php');
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al registrar: " . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
?>

<style>
.form-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-person-plus-fill text-primary"></i> Nuevo Paciente</h1>
        <p class="lead">Registra un nuevo paciente en el sistema.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="pacientes.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver a pacientes
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
    <div class="form-section">
        <h5><i class="bi bi-person-badge me-2"></i> Datos personales</h5>
        <hr>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nombre completo *</label>
                <input type="text" class="form-control" name="nombre" required 
                       value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de nacimiento</label>
                <input type="date" class="form-control" name="fecha_nacimiento"
                       value="<?php echo isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : ''; ?>">
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Email *</label>
                <input type="email" class="form-control" name="email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Teléfono *</label>
                <input type="tel" class="form-control" name="telefono" required
                       value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label">Contraseña *</label>
                <input type="password" class="form-control" name="contrasena" required>
                <small class="text-muted">Mínimo 6 caracteres. El paciente podrá cambiar su contraseña después.</small>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Dirección</label>
            <textarea class="form-control" name="direccion" rows="3"><?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?></textarea>
        </div>
    </div>
    
    <div class="text-end">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save"></i> Registrar paciente
        </button>
        <a href="pacientes.php" class="btn btn-secondary btn-lg">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </div>
</form>

<?php
require_once '../../includes/footer.php';
?>