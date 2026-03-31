<?php
// public/login.php
require_once '../includes/header.php';
require_once '../includes/funciones.php';
require_once '../config/database.php';
require_once '../includes/email.php'; // ← NUEVO

if (estaLogueado()) {
    redirigir('/ecodent/public/index.php');
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {
        
        $email      = sanitizar($_POST['email']);
        $contrasena = $_POST['contrasena'];
        
        // ✅ Ahora también traemos email_verificado y datos para reenviar código
        $stmt = $conexion->prepare("
            SELECT id_usuario, email, contrasena_hash, nombre_completo, 
                   rol, activo, email_verificado
            FROM usuarios 
            WHERE email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();
            
            if (!$usuario['activo']) {
                $error = 'Tu cuenta está desactivada. Contacta al administrador.';
            
            } elseif (password_verify($contrasena, $usuario['contrasena_hash'])) {
                
                // ✅ NUEVO: verificar si confirmó su email
                if (!$usuario['email_verificado']) {
                    // Reenviar código fresco por si expiró
                    $nuevo_codigo     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $nueva_expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $stmt2 = $conexion->prepare("
                        UPDATE usuarios 
                        SET codigo_verificacion = ?, codigo_expiracion = ?
                        WHERE id_usuario = ?
                    ");
                    $stmt2->bind_param("ssi", $nuevo_codigo, $nueva_expiracion, $usuario['id_usuario']);
                    $stmt2->execute();
                    
                    enviarCodigoVerificacion($usuario['email'], $usuario['nombre_completo'], $nuevo_codigo);
                    
                    // Mandar a verificar
                    $_SESSION['verificar_email']  = $usuario['email'];
                    $_SESSION['verificar_nombre'] = $usuario['nombre_completo'];
                    redirigir('/ecodent/public/verificar_codigo.php');
                }
                
                // ✅ Todo ok — iniciar sesión
                $_SESSION['id_usuario']      = $usuario['id_usuario'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                $_SESSION['email']           = $usuario['email'];
                $_SESSION['rol']             = $usuario['rol'];
                
                $stmt3 = $conexion->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
                $stmt3->bind_param("i", $usuario['id_usuario']);
                $stmt3->execute();
                
                if ($usuario['rol'] === 'odontologo') {
                    redirigir('/ecodent/public/odontologo/dashboard.php');
                } else {
                    redirigir('/ecodent/public/paciente/dashboard.php');
                }
                
            } else {
                $error = 'Contraseña incorrecta.';
            }
        } else {
            $error = 'No existe una cuenta con ese email.';
        }
    }
}

$token_csrf = generarTokenCSRF();
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión</h4>
            </div>
            <div class="card-body">
                
                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['exito']; unset($_SESSION['exito']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo $email; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" name="contrasena" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="recordar">
                        <label class="form-check-label" for="recordar">Recordarme</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                    </button>
                </form>
                
                <hr>
                
                <p class="text-center mb-2">
                    ¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a>
                </p>
                <p class="text-center mb-0">
                    <a href="recuperar_password.php">¿Olvidaste tu contraseña?</a>
                </p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Datos de Prueba
            </div>
            <div class="card-body">
                <p><strong>Odontólogo:</strong> carlos.mamani@ecodent.com / password</p>
                <p class="mb-0"><strong>Paciente:</strong> juan.perez@email.com / password</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>