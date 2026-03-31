<?php
// public/registro.php
require_once '../includes/header.php';
require_once '../includes/funciones.php';
require_once '../config/database.php';
require_once '../includes/email.php'; // ← NUEVO

if (estaLogueado()) {
    if (esOdontologo()) {
        redirigir('/ecodent/public/odontologo/dashboard.php');
    } else {
        redirigir('/ecodent/public/paciente/dashboard.php');
    }
}

$nombre = $email = $telefono = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {
        $nombre    = sanitizar($_POST['nombre_completo']);
        $email     = sanitizar($_POST['email']);
        $telefono  = sanitizar($_POST['telefono']);
        $contrasena          = $_POST['contrasena'];
        $confirmar_contrasena = $_POST['confirmar_contrasena'];
        
        $errores = [];
        
        if (strlen($nombre) < 3)
            $errores[] = 'El nombre debe tener al menos 3 caracteres';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errores[] = 'El correo electrónico no es válido';
        
        if (!empty($telefono) && !preg_match('/^[0-9]{7,10}$/', $telefono))
            $errores[] = 'El teléfono debe tener entre 7 y 10 dígitos';
        
        if (strlen($contrasena) < 8)
            $errores[] = 'La contraseña debe tener al menos 8 caracteres';
        
        if ($contrasena !== $confirmar_contrasena)
            $errores[] = 'Las contraseñas no coinciden';
        
        if (empty($errores)) {
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errores[] = 'Este correo electrónico ya está registrado';
            } else {
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                
                // ✅ Generar código de verificación
                $codigo     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $conexion->begin_transaction();
                try {
                    // ✅ email_verificado = 0, guardamos código
                    $stmt = $conexion->prepare("
                        INSERT INTO usuarios 
                        (email, contrasena_hash, nombre_completo, telefono, rol, 
                         email_verificado, codigo_verificacion, codigo_expiracion) 
                        VALUES (?, ?, ?, ?, 'paciente', 0, ?, ?)
                    ");
                    $stmt->bind_param("ssssss", $email, $contrasena_hash, $nombre, $telefono, $codigo, $expiracion);
                    
                    if (!$stmt->execute())
                        throw new Exception("Error al crear usuario: " . $stmt->error);
                    
                    $id_usuario = $conexion->insert_id;
                    
                    $stmt2 = $conexion->prepare("
                        INSERT INTO pacientes (id_usuario, estado_cuenta, puede_agendar, limite_citas_simultaneas) 
                        VALUES (?, 'normal', 1, 3)
                    ");
                    $stmt2->bind_param("i", $id_usuario);
                    
                    if (!$stmt2->execute())
                        throw new Exception("Error al crear perfil: " . $stmt2->error);
                    
                    // ✅ Enviar email con código
                    if (!enviarCodigoVerificacion($email, $nombre, $codigo))
                        throw new Exception("No se pudo enviar el email de verificación.");
                    
                    $conexion->commit();
                    
                    // ✅ Guardar en sesión y redirigir a verificación
                    $_SESSION['verificar_email']  = $email;
                    $_SESSION['verificar_nombre'] = $nombre;
                    redirigir('/ecodent/public/verificar_codigo.php');
                    
                } catch (Exception $e) {
                    $conexion->rollback();
                    $errores[] = $e->getMessage();
                }
            }
        }
        
        if (!empty($errores))
            $error = implode('<br>', $errores);
    }
}

$token_csrf = generarTokenCSRF();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-person-plus"></i> Registro de Paciente</h4>
            </div>
            <div class="card-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" name="nombre_completo" 
                               value="<?php echo $nombre; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico *</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo $email; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" name="telefono" 
                               value="<?php echo $telefono; ?>">
                        <div class="form-text">Ej: 77112233 (solo números)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" name="contrasena" 
                               required minlength="8">
                        <div class="form-text">Mínimo 8 caracteres</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Contraseña *</label>
                        <input type="password" class="form-control" name="confirmar_contrasena" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terminos" required>
                        <label class="form-check-label" for="terminos">
                            Acepto los términos y condiciones
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check"></i> Registrarse
                    </button>
                </form>
                
                <hr>
                <p class="text-center mb-0">
                    ¿Ya tienes cuenta? <a href="login.php">Inicia Sesión aquí</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>