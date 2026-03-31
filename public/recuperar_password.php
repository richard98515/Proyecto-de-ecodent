<?php
// public/recuperar_password.php
require_once '../includes/header.php';
require_once '../includes/funciones.php';
require_once '../config/database.php';
require_once '../includes/email.php';

if (estaLogueado()) redirigir('/ecodent/public/index.php');

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {

        $email = sanitizar($_POST['email']);

        $stmt = $conexion->prepare("
            SELECT id_usuario, nombre_completo, activo 
            FROM usuarios WHERE email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        // ✅ Mismo mensaje aunque no exista (seguridad — no revelar si el email está registrado)
        if ($usuario && $usuario['activo']) {
            $codigo     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $stmt2 = $conexion->prepare("
                UPDATE usuarios 
                SET codigo_verificacion = ?, codigo_expiracion = ?
                WHERE id_usuario = ?
            ");
            $stmt2->bind_param("ssi", $codigo, $expiracion, $usuario['id_usuario']);
            $stmt2->execute();

            // Enviar email con código
            $asunto = '🦷 EcoDent - Recuperar contraseña';
            $cuerpo = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:12px;overflow:hidden'>
                <div style='background:#dc3545;padding:24px;text-align:center'>
                    <h2 style='color:white;margin:0'>🦷 EcoDent</h2>
                </div>
                <div style='padding:32px'>
                    <h3 style='margin-top:0'>Hola, {$usuario['nombre_completo']}!</h3>
                    <p>Recibimos una solicitud para restablecer tu contraseña.</p>
                    <p>Tu código de recuperación es:</p>
                    <div style='text-align:center;background:#fff0f0;border-radius:10px;padding:24px;margin:24px 0'>
                        <span style='font-size:44px;font-weight:bold;letter-spacing:12px;color:#dc3545'>{$codigo}</span>
                    </div>
                    <p style='color:#888;font-size:13px'>⏰ Expira en <strong>15 minutos</strong>.</p>
                    <p style='color:#888;font-size:13px'>Si no solicitaste esto, ignora este mensaje. Tu contraseña no cambiará.</p>
                </div>
                <div style='background:#f8f9fa;padding:14px;text-align:center'>
                    <small style='color:#aaa'>EcoDent · Sistema de Citas Dentales</small>
                </div>
            </div>";

            enviarEmail($email, $usuario['nombre_completo'], $asunto, $cuerpo);

            $_SESSION['recuperar_email'] = $email;
            redirigir('/ecodent/public/nuevo_password.php');
        }

        // Mismo mensaje para cualquier caso
        $exito = "✅ Si ese correo está registrado, recibirás un código en breve.";
    }
}

$token_csrf = generarTokenCSRF();
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-danger text-white text-center py-3">
                <h4 class="mb-0">🔐 Recuperar contraseña</h4>
            </div>
            <div class="card-body p-4">

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="alert alert-success"><?php echo $exito; ?></div>
                <?php endif; ?>

                <p class="text-muted text-center">
                    Ingresa tu email y te enviaremos un código para restablecer tu contraseña.
                </p>

                <form method="POST">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" required autofocus>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">
                            📧 Enviar código
                        </button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-0">
                    <a href="login.php">← Volver al login</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>