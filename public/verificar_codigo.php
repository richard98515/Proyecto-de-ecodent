<?php
// public/verificar_codigo.php
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/email.php';
require_once '../includes/header.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['verificar_email'])) {
    redirigir('/ecodent/public/login.php');
}

$email  = $_SESSION['verificar_email'];
$nombre = $_SESSION['verificar_nombre'];
$error  = '';
$exito  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Reenviar código ──────────────────────────────
    if (isset($_POST['reenviar'])) {
        $nuevo_codigo     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $nueva_expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt = $conexion->prepare("
            UPDATE usuarios 
            SET codigo_verificacion = ?, codigo_expiracion = ?
            WHERE email = ?
        ");
        $stmt->bind_param("sss", $nuevo_codigo, $nueva_expiracion, $email);
        $stmt->execute();

        if (enviarCodigoVerificacion($email, $nombre, $nuevo_codigo)) {
            $exito = "✅ Código reenviado. Revisa tu bandeja de entrada.";
        } else {
            $error = "❌ Error al reenviar. Intenta de nuevo.";
        }

    // ── Verificar código ─────────────────────────────
    } else {
        $codigo_ingresado = trim($_POST['codigo']);

        $stmt = $conexion->prepare("
            SELECT id_usuario, codigo_verificacion, codigo_expiracion
            FROM usuarios WHERE email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if (!$usuario) {
            $error = "Usuario no encontrado.";
        } elseif ($usuario['codigo_verificacion'] !== $codigo_ingresado) {
            $error = "❌ Código incorrecto. Revisa tu email.";
        } elseif (strtotime($usuario['codigo_expiracion']) < time()) {
            $error = "⏰ El código expiró. Solicita uno nuevo abajo.";
        } else {
            // ✅ Marcar como verificado
            $stmt2 = $conexion->prepare("
                UPDATE usuarios 
                SET email_verificado = 1, 
                    codigo_verificacion = NULL, 
                    codigo_expiracion = NULL,
                    ultimo_acceso = NOW()
                WHERE id_usuario = ?
            ");
            $stmt2->bind_param("i", $usuario['id_usuario']);
            $stmt2->execute();

            // Iniciar sesión automáticamente
            $_SESSION['id_usuario']      = $usuario['id_usuario'];
            $_SESSION['nombre_completo'] = $nombre;
            $_SESSION['email']           = $email;
            $_SESSION['rol']             = 'paciente';

            unset($_SESSION['verificar_email'], $_SESSION['verificar_nombre']);

            $_SESSION['exito'] = '🎉 ¡Cuenta verificada! Bienvenido a EcoDent.';
            redirigir('/ecodent/public/paciente/dashboard.php');
        }
    }
}
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-success text-white text-center py-3">
                <h4 class="mb-0">📧 Verifica tu cuenta</h4>
            </div>
            <div class="card-body p-4 text-center">
                
                <p class="text-muted">Enviamos un código de 6 dígitos a:</p>
                <p><strong><?php echo htmlspecialchars($email); ?></strong></p>
                <p class="text-muted small">Revisa también tu carpeta de spam.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="alert alert-success"><?php echo $exito; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <input type="text" name="codigo" 
                               class="form-control form-control-lg text-center fw-bold"
                               maxlength="6" placeholder="000000"
                               style="font-size:36px;letter-spacing:12px" 
                               autofocus required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-success btn-lg">
                            ✅ Verificar cuenta
                        </button>
                    </div>
                </form>

                <hr>

                <form method="POST">
                    <button type="submit" name="reenviar" value="1" 
                            class="btn btn-link text-muted text-decoration-none">
                        🔄 ¿No llegó el código? Reenviar
                    </button>
                </form>

                <a href="registro.php" class="btn btn-link text-muted text-decoration-none d-block mt-1">
                    ← Volver al registro
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>