<?php
// public/paciente/perfil.php
require_once '../../includes/header.php';
require_once '../../includes/funciones.php';
require_once '../../config/database.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];
$error  = '';
$exito  = '';

// Obtener datos actuales
$stmt = $conexion->prepare("
    SELECT u.nombre_completo, u.email, u.telefono, u.fecha_registro,
           p.id_paciente, p.fecha_nacimiento, p.direccion,
           p.ausencias_sin_aviso, p.llegadas_tarde, p.estado_cuenta,
           p.limite_citas_simultaneas, p.puede_agendar
    FROM usuarios u
    JOIN pacientes p ON u.id_usuario = p.id_usuario
    WHERE u.id_usuario = ?
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$datos = $stmt->get_result()->fetch_assoc();

// Contar citas completadas
$stmt2 = $conexion->prepare("
    SELECT COUNT(*) as total FROM citas 
    WHERE id_paciente = ? AND estado = 'completada'
");
$stmt2->bind_param("i", $datos['id_paciente']);
$stmt2->execute();
$citas_completadas = $stmt2->get_result()->fetch_assoc()['total'];

// ══════════════════════════════════════
// PROCESAR FORMULARIOS
// ══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {

        // ── Actualizar datos personales ──────────────────
        if (isset($_POST['actualizar_perfil'])) {
            $nombre    = sanitizar($_POST['nombre_completo']);
            $telefono  = sanitizar($_POST['telefono']);
            $direccion = sanitizar($_POST['direccion']);
            $fecha_nac = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;

            $errores = [];

            if (strlen($nombre) < 3)
                $errores[] = 'El nombre debe tener al menos 3 caracteres.';

            if (!empty($telefono) && !preg_match('/^[0-9]{7,10}$/', $telefono))
                $errores[] = 'El teléfono debe tener entre 7 y 10 dígitos.';

            if ($fecha_nac && strtotime($fecha_nac) > time())
                $errores[] = 'La fecha de nacimiento no puede ser futura.';

            if (empty($errores)) {
                // Actualizar usuarios
                $stmt3 = $conexion->prepare("
                    UPDATE usuarios SET nombre_completo = ?, telefono = ?
                    WHERE id_usuario = ?
                ");
                $stmt3->bind_param("ssi", $nombre, $telefono, $id_usuario);
                $stmt3->execute();

                // Actualizar pacientes
                $stmt4 = $conexion->prepare("
                    UPDATE pacientes SET fecha_nacimiento = ?, direccion = ?
                    WHERE id_usuario = ?
                ");
                $stmt4->bind_param("ssi", $fecha_nac, $direccion, $id_usuario);
                $stmt4->execute();

                // Actualizar sesión
                $_SESSION['nombre_completo'] = $nombre;

                // Recargar datos
                $stmt->execute();
                $datos = $stmt->get_result()->fetch_assoc();

                $exito = '✅ Perfil actualizado correctamente.';
            } else {
                $error = implode('<br>', $errores);
            }
        }

        // ── Cambiar contraseña ───────────────────────────
        if (isset($_POST['cambiar_password'])) {
            $actual   = $_POST['password_actual'];
            $nueva    = $_POST['password_nueva'];
            $confirmar = $_POST['password_confirmar'];

            $errores = [];

            // Verificar contraseña actual
            $stmt5 = $conexion->prepare("SELECT contrasena_hash FROM usuarios WHERE id_usuario = ?");
            $stmt5->bind_param("i", $id_usuario);
            $stmt5->execute();
            $u = $stmt5->get_result()->fetch_assoc();

            if (!password_verify($actual, $u['contrasena_hash']))
                $errores[] = 'La contraseña actual es incorrecta.';

            if (strlen($nueva) < 8)
                $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';

            if ($nueva !== $confirmar)
                $errores[] = 'Las contraseñas nuevas no coinciden.';

            if (empty($errores)) {
                $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
                $stmt6 = $conexion->prepare("UPDATE usuarios SET contrasena_hash = ? WHERE id_usuario = ?");
                $stmt6->bind_param("si", $nuevo_hash, $id_usuario);
                $stmt6->execute();
                $exito = '✅ Contraseña cambiada correctamente.';
            } else {
                $error = implode('<br>', $errores);
            }
        }
    }
}

$token_csrf = generarTokenCSRF();

// Calcular edad si tiene fecha de nacimiento
$edad = null;
if ($datos['fecha_nacimiento']) {
    $edad = date_diff(date_create($datos['fecha_nacimiento']), date_create('today'))->y;
}
?>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-person-circle text-primary"></i> Mi Perfil</h1>
        <p class="lead">Gestiona tu información personal y contraseña.</p>
        <hr>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?></div>
<?php endif; ?>
<?php if ($exito): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $exito; ?></div>
<?php endif; ?>

<div class="row">

    <!-- ══ COLUMNA IZQUIERDA — Resumen ══ -->
    <div class="col-md-4">

        <!-- Tarjeta de perfil -->
        <div class="card mb-3 text-center">
            <div class="card-body py-4">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:90px;height:90px">
                    <span style="font-size:40px;color:white">
                        <?php echo mb_strtoupper(mb_substr($datos['nombre_completo'], 0, 1)); ?>
                    </span>
                </div>
                <h5 class="mb-1"><?php echo htmlspecialchars($datos['nombre_completo']); ?></h5>
                <p class="text-muted small mb-2"><?php echo htmlspecialchars($datos['email']); ?></p>

                <!-- Badge estado -->
                <?php
                $badge_class = match($datos['estado_cuenta']) {
                    'normal'      => 'bg-success',
                    'observacion' => 'bg-info',
                    'restringida' => 'bg-warning text-dark',
                    'bloqueada'   => 'bg-danger',
                    default       => 'bg-secondary'
                };
                $estado_texto = match($datos['estado_cuenta']) {
                    'normal'      => '✅ Normal',
                    'observacion' => '👁️ Observación',
                    'restringida' => '⚠️ Restringida',
                    'bloqueada'   => '🚫 Bloqueada',
                    default       => $datos['estado_cuenta']
                };
                ?>
                <span class="badge <?php echo $badge_class; ?> px-3 py-2">
                    <?php echo $estado_texto; ?>
                </span>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-bar-chart"></i> Estadísticas
            </div>
            <div class="card-body p-0">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td><i class="bi bi-calendar-check text-success"></i> Citas completadas</td>
                        <td class="text-end fw-bold"><?php echo $citas_completadas; ?></td>
                    </tr>
                    <tr class="<?php echo $datos['ausencias_sin_aviso'] >= 3 ? 'table-warning' : ''; ?>">
                        <td><i class="bi bi-person-x text-danger"></i> Ausencias</td>
                        <td class="text-end fw-bold"><?php echo $datos['ausencias_sin_aviso']; ?></td>
                    </tr>
                    <tr class="<?php echo $datos['llegadas_tarde'] >= 3 ? 'table-warning' : ''; ?>">
                        <td><i class="bi bi-clock text-warning"></i> Llegadas tarde</td>
                        <td class="text-end fw-bold"><?php echo $datos['llegadas_tarde']; ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-calendar-plus text-primary"></i> Citas permitidas</td>
                        <td class="text-end fw-bold"><?php echo $datos['limite_citas_simultaneas']; ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-calendar-date"></i> Miembro desde</td>
                        <td class="text-end small"><?php echo date('d/m/Y', strtotime($datos['fecha_registro'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Info cuenta -->
        <?php if ($datos['estado_cuenta'] != 'normal'): ?>
        <div class="alert alert-warning small">
            <i class="bi bi-info-circle"></i>
            Para mejorar tu estado: asiste puntualmente a tus citas y evita ausencias sin aviso.
            <?php if ($datos['estado_cuenta'] == 'bloqueada'): ?>
                <hr class="my-2">
                Contacta al consultorio: <strong>77112233</strong>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- ══ COLUMNA DERECHA — Formularios ══ -->
    <div class="col-md-8">

        <!-- Datos personales -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person"></i> Datos Personales</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    <input type="hidden" name="actualizar_perfil" value="1">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre completo *</label>
                            <input type="text" name="nombre_completo" class="form-control"
                                   value="<?php echo htmlspecialchars($datos['nombre_completo']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control"
                                   value="<?php echo htmlspecialchars($datos['email']); ?>" disabled>
                            <div class="form-text">El email no se puede cambiar.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control"
                                   value="<?php echo htmlspecialchars($datos['telefono'] ?? ''); ?>"
                                   placeholder="Ej: 77112233">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control"
                                   value="<?php echo $datos['fecha_nacimiento'] ?? ''; ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                            <?php if ($edad !== null): ?>
                                <div class="form-text"><?php echo $edad; ?> años</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Dirección</label>
                        <textarea name="direccion" class="form-control" rows="2"
                                  placeholder="Ej: Calle Potosí #123, La Paz"><?php echo htmlspecialchars($datos['direccion'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy"></i> Guardar cambios
                    </button>
                </form>
            </div>
        </div>

        <!-- Cambiar contraseña -->
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-lock"></i> Cambiar Contraseña</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    <input type="hidden" name="cambiar_password" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Contraseña actual *</label>
                        <input type="password" name="password_actual" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nueva contraseña *</label>
                            <input type="password" name="password_nueva" class="form-control"
                                   minlength="8" required>
                            <div class="form-text">Mínimo 8 caracteres.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Confirmar nueva contraseña *</label>
                            <input type="password" name="password_confirmar" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Cambiar contraseña
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>