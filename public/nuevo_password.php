<?php
// public/nuevo_password.php
require_once '../includes/header.php';
require_once '../includes/funciones.php';
require_once '../config/database.php';
require_once '../includes/email.php';

if (estaLogueado()) redirigir('/ecodent/public/index.php');

if (empty($_SESSION['recuperar_email'])) {
    redirigir('/ecodent/public/recuperar_password.php');
}

$email = $_SESSION['recuperar_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {

        // ── Reenviar código ──────────────────────────────
        if (isset($_POST['reenviar'])) {
            $nuevo_codigo     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $nueva_expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $stmt = $conexion->prepare("
                UPDATE usuarios SET codigo_verificacion = ?, codigo_expiracion = ?
                WHERE email = ?
            ");
            $stmt->bind_param("sss", $nuevo_codigo, $nueva_expiracion, $email);
            $stmt->execute();

            $stmt2 = $conexion->prepare("SELECT nombre_completo FROM usuarios WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $u = $stmt2->get_result()->fetch_assoc();

            $asunto = '🦷 EcoDent - Nuevo código de recuperación';
            $cuerpo = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
                <div style='background:#dc3545;padding:24px;text-align:center'>
                    <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
                </div>
                <div style='padding:32px'>
                    <h3>Hola, {$u['nombre_completo']}!</h3>
                    <p>Tu nuevo código de recuperación es:</p>
                    <div style='text-align:center;background:#fff0f0;border-radius:10px;padding:24px;margin:24px 0'>
                        <span style='font-size:44px;font-weight:bold;letter-spacing:12px;color:#dc3545'>{$nuevo_codigo}</span>
                    </div>
                    <p style='color:#888;font-size:13px'>⏰ Expira en <strong>15 minutos</strong>.</p>
                </div>
                <div style='background:#f8f9fa;padding:14px;text-align:center'>
                    <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales</small>
                </div>
            </div>";

            enviarEmail($email, $u['nombre_completo'], $asunto, $cuerpo);
            $_SESSION['exito_reenvio'] = "✅ Código reenviado. Revisa tu bandeja.";
            redirigir('/ecodent/public/nuevo_password.php');

        // ── Cambiar contraseña ───────────────────────────
        } else {
            $codigo       = trim($_POST['codigo']);
            $nueva_pass   = $_POST['nueva_password'];
            $confirmar    = $_POST['confirmar_password'];

            if (strlen($nueva_pass) < 8) {
                $error = "La contraseña debe tener al menos 8 caracteres.";
            } elseif ($nueva_pass !== $confirmar) {
                $error = "Las contraseñas no coinciden.";
            } else {
                $stmt = $conexion->prepare("
                    SELECT id_usuario, codigo_verificacion, codigo_expiracion
                    FROM usuarios WHERE email = ?
                ");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $usuario = $stmt->get_result()->fetch_assoc();

                if (!$usuario) {
                    $error = "Usuario no encontrado.";
                } elseif ($usuario['codigo_verificacion'] !== $codigo) {
                    $error = "❌ Código incorrecto.";
                } elseif (strtotime($usuario['codigo_expiracion']) < time()) {
                    $error = "⏰ El código expiró. Solicita uno nuevo abajo.";
                } else {
                    // ✅ Actualizar contraseña
                    $nuevo_hash = password_hash($nueva_pass, PASSWORD_DEFAULT);

                    $stmt2 = $conexion->prepare("
                        UPDATE usuarios 
                        SET contrasena_hash = ?, 
                            codigo_verificacion = NULL, 
                            codigo_expiracion = NULL
                        WHERE id_usuario = ?
                    ");
                    $stmt2->bind_param("si", $nuevo_hash, $usuario['id_usuario']);
                    $stmt2->execute();

                    unset($_SESSION['recuperar_email']);
                    $_SESSION['exito'] = "✅ Contraseña actualizada. Ya puedes iniciar sesión.";
                    redirigir('/ecodent/public/login.php');
                }
            }
        }
    }
}

$token_csrf = generarTokenCSRF();
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-danger text-white text-center py-3">
                <h4 class="mb-0">🔑 Nueva contraseña</h4>
            </div>
            <div class="card-body p-4">

                <p class="text-muted text-center">
                    Ingresa el código enviado a:<br>
                    <strong><?php echo htmlspecialchars($email); ?></strong>
                </p>

                <?php if (isset($_SESSION['exito_reenvio'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['exito_reenvio']; unset($_SESSION['exito_reenvio']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Código de verificación</label>
                        <input type="text" name="codigo" 
                               class="form-control form-control-lg text-center fw-bold"
                               maxlength="6" placeholder="000000"
                               style="font-size:32px;letter-spacing:10px"
                               autofocus required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nueva contraseña</label>
                        <input type="password" name="nueva_password" 
                               class="form-control" minlength="8" required>
                        <div class="form-text">Mínimo 8 caracteres</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirmar contraseña</label>
                        <input type="password" name="confirmar_password" 
                               class="form-control" required>
                    </div>
                    <div class="d-grid mb-2">
                        <button type="submit" class="btn btn-danger btn-lg">
                            🔑 Cambiar contraseña
                        </button>
                    </div>
                </form>

                <hr>

                <form method="POST">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    <button type="submit" name="reenviar" value="1"
                            class="btn btn-link text-muted text-decoration-none w-100">
                        🔄 ¿No llegó el código? Reenviar
                    </button>
                </form>

                <a href="recuperar_password.php" 
                   class="btn btn-link text-muted text-decoration-none d-block text-center">
                    ← Cambiar email
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>