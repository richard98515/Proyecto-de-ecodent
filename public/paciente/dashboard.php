<?php
// public/paciente/dashboard.php
// Panel principal del paciente - CON VALIDACIÓN DE ESTADO

require_once '../../includes/header.php';
require_once '../../includes/funciones.php';
require_once '../../config/database.php';

// Verificar que solo pacientes puedan acceder
requerirRol('paciente');

// Obtener datos del paciente desde la base de datos
$id_usuario = $_SESSION['id_usuario'];

// Consultar información del paciente
$stmt = $conexion->prepare("
    SELECT p.*, u.nombre_completo, u.email, u.telefono, u.fecha_registro
    FROM pacientes p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_usuario = ?
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$paciente = $resultado->fetch_assoc();

// Consultar próximas citas
$stmt2 = $conexion->prepare("
    SELECT c.*, o.especialidad_principal, u2.nombre_completo as nombre_odontologo
    FROM citas c
    JOIN odontologos o ON c.id_odontologo = o.id_odontologo
    JOIN usuarios u2 ON o.id_usuario = u2.id_usuario
    WHERE c.id_paciente = ? 
      AND c.fecha_cita >= CURDATE() 
      AND c.estado IN ('programada', 'confirmada')
    ORDER BY c.fecha_cita ASC, c.hora_cita ASC
    LIMIT 1
");
$stmt2->bind_param("i", $paciente['id_paciente']);
$stmt2->execute();
$proxima_cita = $stmt2->get_result()->fetch_assoc();

// Contar citas activas
$stmt_citas_activas = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM citas 
    WHERE id_paciente = ? 
    AND fecha_cita >= CURDATE() 
    AND estado IN ('programada', 'confirmada')
");
$stmt_citas_activas->bind_param("i", $paciente['id_paciente']);
$stmt_citas_activas->execute();
$citas_activas = $stmt_citas_activas->get_result()->fetch_assoc()['total'];

// Mostrar mensaje de éxito si existe
if (isset($_SESSION['exito'])) {
    $exito = $_SESSION['exito'];
    unset($_SESSION['exito']);
}
?>

<style>
.estado-badge {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
}
.estado-normal { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
.estado-observacion { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
.estado-restringida { background: #ffe5d0; color: #e67e22; border-left: 4px solid #fd7e14; }
.estado-bloqueada { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
</style>

<div class="row">
    <div class="col-md-12">
        <?php if (isset($exito)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo $exito; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <h1>¡Bienvenido a ECO-DENT!</h1>
        <p class="lead">Hola <strong><?php echo $_SESSION['nombre_completo']; ?></strong>, estamos felices de tenerte con nosotros.</p>
    </div>
</div>

<!-- ALERTAS SEGÚN ESTADO DE CUENTA -->
<?php if ($paciente['estado_cuenta'] == 'bloqueada'): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-ban fs-1 me-3"></i>
            <div>
                <h5 class="mb-1">⚠️ Cuenta Bloqueada</h5>
                <p class="mb-0">
                    Tu cuenta está <strong>BLOQUEADA</strong>. No puedes agendar nuevas citas por el sistema.<br>
                    Por favor, comunícate al <strong class="fs-5">77112233</strong> para más información.
                </p>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($paciente['estado_cuenta'] == 'restringida'): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle fs-1 me-3"></i>
            <div>
                <h5 class="mb-1">⚠️ Cuenta Restringida</h5>
                <p class="mb-0">
                    Tu cuenta está en estado <strong>RESTRINGIDO</strong>.<br>
                    Solo puedes tener <strong>1 cita activa</strong> a la vez.
                    <?php if ($citas_activas >= 1): ?>
                        <span class="badge bg-danger">Tienes <?php echo $citas_activas; ?> cita(s) activa(s)</span>
                    <?php endif; ?>
                </p>
                <small class="mt-1 d-block">
                    Ausencias: <?php echo $paciente['ausencias_sin_aviso']; ?> | 
                    Llegadas tarde: <?php echo $paciente['llegadas_tarde']; ?>
                </small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($paciente['estado_cuenta'] == 'observacion'): ?>
    <div class="alert alert-info alert-dismissible fade show mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-eye fs-1 me-3"></i>
            <div>
                <h5 class="mb-1">📋 Cuenta en Observación</h5>
                <p class="mb-0">
                    Tu cuenta está en <strong>OBSERVACIÓN</strong>.<br>
                    Puedes tener hasta <strong>2 citas activas</strong>.
                    <?php if ($citas_activas >= 2): ?>
                        <span class="badge bg-warning">Has alcanzado el límite de <?php echo $citas_activas; ?>/2 citas</span>
                    <?php endif; ?>
                </p>
                <small class="mt-1 d-block">
                    Ausencias: <?php echo $paciente['ausencias_sin_aviso']; ?> | 
                    Llegadas tarde: <?php echo $paciente['llegadas_tarde']; ?>
                </small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-header">
                <i class="bi bi-calendar-check"></i> Próxima Cita
            </div>
            <div class="card-body">
                <?php if ($proxima_cita): ?>
                    <h5 class="card-title">
                        <?php echo date('d/m/Y', strtotime($proxima_cita['fecha_cita'])); ?>
                    </h5>
                    <p class="card-text">
                        <?php echo date('H:i', strtotime($proxima_cita['hora_cita']))." - ". date('H:i',strtotime($proxima_cita['hora_fin'])); ?> hrs<br>
                        Dr(a). <?php echo $proxima_cita['nombre_odontologo']; ?>
                    </p>
                <?php else: ?>
                    <h5 class="card-title">No tienes citas</h5>
                    <p class="card-text">Agenda tu primera cita ahora</p>
                    <?php if ($paciente['estado_cuenta'] != 'bloqueada' && $paciente['puede_agendar']): ?>
                        <a href="agendar.php" class="btn btn-light btn-sm">Agendar</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled>Cuenta bloqueada</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-header">
                <i class="bi bi-cash-coin"></i> Estado de Pagos
            </div>
            <div class="card-body">
                <h5 class="card-title">Al día</h5>
                <p class="card-text">No tienes deudas pendientes</p>
                <a href="mis_pagos.php" class="btn btn-light btn-sm">Ver historial</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-shield-check"></i> Estado de Cuenta
            </div>
            <div class="card-body">
                <div class="estado-badge estado-<?php echo $paciente['estado_cuenta']; ?> mb-2">
                    <i class="bi <?php 
                        if ($paciente['estado_cuenta'] == 'normal') echo 'bi-check-circle';
                        elseif ($paciente['estado_cuenta'] == 'observacion') echo 'bi-eye';
                        elseif ($paciente['estado_cuenta'] == 'restringida') echo 'bi-exclamation-triangle';
                        else echo 'bi-ban';
                    ?>"></i>
                    <?php 
                    $estado_texto = ucfirst($paciente['estado_cuenta']);
                    if ($estado_texto == 'Observacion') $estado_texto = 'Observación';
                    if ($estado_texto == 'Restringida') $estado_texto = 'Restringida';
                    if ($estado_texto == 'Bloqueada') $estado_texto = 'Bloqueada';
                    echo $estado_texto;
                    ?>
                </div>
                <p class="mb-0">
                    <strong>Citas activas:</strong> <?php echo $citas_activas; ?> / <?php echo $paciente['limite_citas_simultaneas']; ?>
                </p>
                <p class="mb-0 small text-muted">
                    <i class="bi bi-person-x"></i> Ausencias: <?php echo $paciente['ausencias_sin_aviso']; ?> | 
                    <i class="bi bi-clock"></i> Llegadas tarde: <?php echo $paciente['llegadas_tarde']; ?>
                </p>
                <?php if ($paciente['estado_cuenta'] == 'bloqueada'): ?>
                    <div class="mt-2 text-danger small">
                        <i class="bi bi-telephone"></i> Contacta al consultorio: <strong>77112233</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-lightning-charge"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($paciente['estado_cuenta'] != 'bloqueada' && $paciente['puede_agendar']): ?>
                        <?php if ($citas_activas < $paciente['limite_citas_simultaneas']): ?>
                            <a href="agendar.php" class="btn btn-primary">
                                <i class="bi bi-calendar-plus"></i> Agendar Nueva Cita
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="bi bi-exclamation-triangle"></i> Límite de citas alcanzado (<?php echo $citas_activas; ?>/<?php echo $paciente['limite_citas_simultaneas']; ?>)
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-danger" disabled>
                            <i class="bi bi-ban"></i> Cuenta Bloqueada - No puedes agendar
                        </button>
                    <?php endif; ?>
                    
                    <a href="mis_citas.php" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-check"></i> Ver Mis Citas
                    </a>
                    <a href="mis_pagos.php" class="btn btn-outline-success">
                        <i class="bi bi-cash-coin"></i> Historial de Pagos
                    </a>
                    <a href="perfil.php" class="btn btn-outline-secondary">
                        <i class="bi bi-person"></i> Mi Perfil
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="bi bi-robot"></i> Asistente Virtual
            </div>
            <div class="card-body text-center">
                <p>¿Necesitas ayuda? Nuestro asistente virtual está disponible 24/7</p>
                <button class="btn btn-success" onclick="abrirChat()">
                    <i class="bi bi-chat-dots"></i> Hablar con Asistente
                </button>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-warning">
                <i class="bi bi-clock-history"></i> ¿Sabías que...?
            </div>
            <div class="card-body">
                <p class="mb-0">
                    <i class="bi bi-check-circle text-success"></i> 
                    Las citas tienen duración de <strong>40 minutos</strong>
                </p>
                <p class="mb-0">
                    <i class="bi bi-check-circle text-success"></i> 
                    Puedes modificar tu cita hasta 3 veces
                </p>
                <p class="mb-0">
                    <i class="bi bi-check-circle text-success"></i> 
                    Recibirás recordatorios 24h y 1h antes
                </p>
                <?php if ($paciente['estado_cuenta'] != 'normal'): ?>
                    <hr>
                    <p class="mb-0 text-warning small">
                        <i class="bi bi-info-circle"></i> 
                        Para recuperar tu estado normal, evita ausencias y llega a tiempo a tus citas.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function abrirChat() {
    alert('El asistente virtual estará disponible en la siguiente etapa del desarrollo');
}
</script>

<?php
require_once '../../includes/footer.php';
?>