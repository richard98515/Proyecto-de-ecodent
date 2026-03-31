<?php
// public/registro.php
// Página de registro de nuevos usuarios

// Incluimos archivos necesarios
require_once '../includes/header.php';
require_once '../includes/funciones.php';
require_once '../config/database.php';

// Si ya está logueado, redirigir al dashboard correspondiente
if (estaLogueado()) {
    if (esOdontologo()) {
        redirigir('/ecodent/public/odontologo/dashboard.php');
    } else {
        redirigir('/ecodent/public/paciente/dashboard.php');
    }
}

// Variables para el formulario
$nombre = $email = $telefono = '';
$error = '';
$exito = '';

// Procesar el formulario cuando se envíe (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar token CSRF para seguridad
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {
        // Sanitizar y obtener los datos del formulario
        $nombre = sanitizar($_POST['nombre_completo']);
        $email = sanitizar($_POST['email']);
        $telefono = sanitizar($_POST['telefono']);
        $contrasena = $_POST['contrasena'];
        $confirmar_contrasena = $_POST['confirmar_contrasena'];
        $rol = 'paciente'; // Por ahora solo registramos pacientes desde aquí
        
        // === VALIDACIONES ===
        $errores = [];
        
        // Validar nombre (mínimo 3 caracteres)
        if (strlen($nombre) < 3) {
            $errores[] = 'El nombre debe tener al menos 3 caracteres';
        }
        
        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido';
        }
        
        // Validar teléfono (opcional, pero si viene debe tener formato)
        if (!empty($telefono) && !preg_match('/^[0-9]{7,10}$/', $telefono)) {
            $errores[] = 'El teléfono debe tener entre 7 y 10 dígitos';
        }
        
        // Validar contraseña (mínimo 8 caracteres)
        if (strlen($contrasena) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres';
        }
        
        // Validar que las contraseñas coincidan
        if ($contrasena !== $confirmar_contrasena) {
            $errores[] = 'Las contraseñas no coinciden';
        }
        
        // Si no hay errores, proceder a guardar en BD
        if (empty($errores)) {
            // Verificar si el email ya existe
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows > 0) {
                $errores[] = 'Este correo electrónico ya está registrado';
            } else {
                // Hash de la contraseña (bcrypt)
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                
                // Iniciar transacción (TODO O NADA)
                $conexion->begin_transaction();
                
                try {
                    // =============================================
                    // PASO 1: Insertar en tabla usuarios
                    // =============================================
                    $stmt = $conexion->prepare("
                        INSERT INTO usuarios 
                        (email, contrasena_hash, nombre_completo, telefono, rol, email_verificado) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    // NOTA: Ponemos email_verificado=1 para evitar complicaciones con verificación
                    // En un sistema real, enviarías un email de confirmación
                    
                    $stmt->bind_param(
                        "sssss", 
                        $email, 
                        $contrasena_hash, 
                        $nombre, 
                        $telefono, 
                        $rol
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error al crear usuario: " . $stmt->error);
                    }
                    
                    $id_usuario = $conexion->insert_id; // Obtener el ID generado
                    
                    // =============================================
                    // PASO 2: Insertar en tabla pacientes
                    // =============================================
                    $stmt2 = $conexion->prepare("
                        INSERT INTO pacientes (id_usuario, estado_cuenta, puede_agendar, limite_citas_simultaneas) 
                        VALUES (?, 'normal', 1, 3)
                    ");
                    $stmt2->bind_param("i", $id_usuario);
                    
                    if (!$stmt2->execute()) {
                        throw new Exception("Error al crear perfil de paciente: " . $stmt2->error);
                    }
                    
                    // =============================================
                    // PASO 3: INICIAR SESIÓN AUTOMÁTICAMENTE
                    // =============================================
                    $_SESSION['id_usuario'] = $id_usuario;
                    $_SESSION['nombre_completo'] = $nombre;
                    $_SESSION['email'] = $email;
                    $_SESSION['rol'] = 'paciente';
                    
                    // Actualizar último acceso
                    $stmt3 = $conexion->prepare("
                        UPDATE usuarios SET ultimo_acceso = NOW() 
                        WHERE id_usuario = ?
                    ");
                    $stmt3->bind_param("i", $id_usuario);
                    $stmt3->execute();
                    
                    // Confirmar transacción
                    $conexion->commit();
                    
                    // =============================================
                    // PASO 4: REDIRIGIR AL DASHBOARD DE PACIENTE
                    // =============================================
                    $_SESSION['exito'] = '¡Registro exitoso! Bienvenido a ECO-DENT.';
                    redirigir('/ecodent/public/paciente/dashboard.php');
                    
                } catch (Exception $e) {
                    // Si algo sale mal, deshacer todo
                    $conexion->rollback();
                    $errores[] = 'Error al registrar: ' . $e->getMessage();
                }
            }
        }
        
        // Si hay errores, mostrarlos
        if (!empty($errores)) {
            $error = implode('<br>', $errores);
        }
    }
}

// Generar token CSRF para el formulario
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
                    <!-- Token CSRF (seguridad) -->
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    
                    <!-- Nombre completo -->
                    <div class="mb-3">
                        <label for="nombre_completo" class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre_completo" 
                               name="nombre_completo" value="<?php echo $nombre; ?>" required>
                        <div class="form-text">Ej: Juan Pérez Mamani</div>
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico *</label>
                        <input type="email" class="form-control" id="email" 
                               name="email" value="<?php echo $email; ?>" required>
                    </div>
                    
                    <!-- Teléfono -->
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" 
                               name="telefono" value="<?php echo $telefono; ?>">
                        <div class="form-text">Ej: 77112233 (solo números)</div>
                    </div>
                    
                    <!-- Contraseña -->
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" id="contrasena" 
                               name="contrasena" required minlength="8">
                        <div class="form-text">Mínimo 8 caracteres</div>
                    </div>
                    
                    <!-- Confirmar contraseña -->
                    <div class="mb-3">
                        <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña *</label>
                        <input type="password" class="form-control" id="confirmar_contrasena" 
                               name="confirmar_contrasena" required>
                    </div>
                    
                    <!-- Términos y condiciones -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terminos" required>
                        <label class="form-check-label" for="terminos">
                            Acepto los términos y condiciones
                        </label>
                    </div>
                    
                    <!-- Botón de registro -->
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check"></i> Registrarse
                    </button>
                </form>
                
                <hr>
                
                <p class="text-center mb-0">
                    ¿Ya tienes cuenta? 
                    <a href="login.php">Inicia Sesión aquí</a>
                </p>
            </div>
        </div>
        
        <!-- Datos de prueba -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Datos de Prueba
            </div>
            <div class="card-body">
                <p><strong>Odontólogo:</strong> carlos.mamani@ecodent.com / password</p>
                <p><strong>Paciente existente:</strong> juan.perez@email.com / password</p>
                <p class="mb-0 text-success">Al registrarte, entrarás automáticamente al sistema</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>