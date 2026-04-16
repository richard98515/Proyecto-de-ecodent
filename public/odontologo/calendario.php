<?php
// public/odontologo/calendario.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/slots.php';
require_once '../../includes/cancelaciones.php';
date_default_timezone_set('America/La_Paz');

// Verificar autenticación
if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

// PERMITIR ACCESO A ADMIN Y ODONTÓLOGO
$es_admin = esAdmin();
$es_odontologo = esOdontologo();

if (!$es_admin && !$es_odontologo) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirigir('/ecodent/public/dashboard.php');
}

$id_usuario = $_SESSION['id_usuario'];

// OBTENER INFORMACIÓN DEL ODONTÓLOGO (incluyendo nombre completo)
$stmt = $conexion->prepare("
    SELECT o.id_odontologo, o.duracion_cita_min, o.color_calendario, o.activo, o.especialidad_principal, u.nombre_completo 
    FROM odontologos o 
    JOIN usuarios u ON o.id_usuario = u.id_usuario 
    WHERE o.id_usuario = ?
");
if (!$stmt) {
    die("Error en la consulta: " . $conexion->error);
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

// Variable para almacenar el odontólogo seleccionado
$odontologo = null;
$id_odontologo = null;
$nombre_odontologo = null;

// Si es ADMIN y no tiene odontólogo asociado, mostrar mensaje especial
if ($resultado->num_rows === 0) {
    if ($es_admin) {
        // Procesar la selección del odontólogo
        if (isset($_GET['ver_odontologo']) && !empty($_GET['ver_odontologo'])) {
            $id_odontologo_seleccionado = (int)$_GET['ver_odontologo'];
            
            // Verificar que el odontólogo existe
            $check = $conexion->prepare("
                SELECT o.id_odontologo, o.duracion_cita_min, o.color_calendario, o.activo, o.especialidad_principal, u.nombre_completo 
                FROM odontologos o 
                JOIN usuarios u ON o.id_usuario = u.id_usuario 
                WHERE o.id_odontologo = ? AND o.activo = 1
            ");
            $check->bind_param("i", $id_odontologo_seleccionado);
            $check->execute();
            $check_result = $check->get_result();
            
            if ($check_result->num_rows > 0) {
                $odontologo = $check_result->fetch_assoc();
                $id_odontologo = $odontologo['id_odontologo'];
                $nombre_odontologo = $odontologo['nombre_completo'];
                $_SESSION['admin_viendo_odontologo'] = $id_odontologo;
                // Saltar a mostrar calendario
                goto mostrar_calendario;
            } else {
                $error_seleccion = "El odontólogo seleccionado no existe o está inactivo.";
            }
        }
        
        // Mostrar mensaje amigable para admin
        require_once '../../includes/header.php';
        ?>
        <div class="container mt-5">
            <div class="alert alert-warning">
                <h4><i class="bi bi-exclamation-triangle"></i> No tienes un perfil de odontólogo asociado</h4>
                <p>Como administrador, puedes:</p>
                <ul>
                    <li><strong>Opción 1:</strong> <a href="/ecodent/public/admin/gestion_odontologos.php" class="alert-link">Crear un odontólogo</a> con tu usuario actual (ID Usuario: <?php echo $id_usuario; ?>)</li>
                    <li><strong>Opción 2:</strong> Ver el calendario de otro odontólogo seleccionándolo a continuación:</li>
                </ul>
                
                <?php if (isset($error_seleccion)): ?>
                    <div class="alert alert-danger"><?php echo $error_seleccion; ?></div>
                <?php endif; ?>
                
                <form method="GET" action="" class="mt-3">
                    <div class="row">
                        <div class="col-md-8">
                            <select name="ver_odontologo" class="form-select" required>
                                <option value="">Selecciona un odontólogo para ver su calendario</option>
                                <?php
                                $odontologos_lista = $conexion->query("
                                    SELECT o.id_odontologo, u.nombre_completo, o.especialidad_principal 
                                    FROM odontologos o 
                                    JOIN usuarios u ON o.id_usuario = u.id_usuario 
                                    WHERE o.activo = 1
                                    ORDER BY u.nombre_completo
                                ");
                                while($od = $odontologos_lista->fetch_assoc()) {
                                    $selected = (isset($_GET['ver_odontologo']) && $_GET['ver_odontologo'] == $od['id_odontologo']) ? 'selected' : '';
                                    echo "<option value='{$od['id_odontologo']}' $selected>{$od['nombre_completo']} - {$od['especialidad_principal']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Ver Calendario</button>
                        </div>
                    </div>
                </form>
                
                <hr>
                <a href="/ecodent/public/admin/dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
        <?php
        require_once '../../includes/footer.php';
        exit;
    } else {
        $_SESSION['error'] = "No se encontró información del odontólogo. Contacte al administrador.";
        redirigir('/ecodent/public/dashboard.php');
    }
}

// Si llegamos aquí, el usuario tiene odontólogo asociado
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];
$nombre_odontologo = $odontologo['nombre_completo'];

mostrar_calendario:

// Si es ADMIN y está viendo otro odontólogo
if ($es_admin && isset($_GET['ver_odontologo']) && is_numeric($_GET['ver_odontologo'])) {
    $id_odontologo_temp = (int)$_GET['ver_odontologo'];
    // Verificar que existe
    $check = $conexion->prepare("
        SELECT 
            o.id_odontologo,
            o.duracion_cita_min,
            o.color_calendario,
            o.activo,
            o.especialidad_principal,
            u.nombre_completo
        FROM odontologos o
        INNER JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE o.id_odontologo = ?
    ");
    $check->bind_param("i", $id_odontologo_temp);
    $check->execute();
    $check_result = $check->get_result();
    if ($check_result->num_rows > 0) {
        $odontologo_ver = $check_result->fetch_assoc();
        $id_odontologo = $odontologo_ver['id_odontologo'];
        $nombre_odontologo = $odontologo_ver['nombre_completo'];
        $odontologo = $odontologo_ver;
        $_SESSION['admin_viendo_odontologo'] = $id_odontologo;
    } else {
        $id_odontologo = $odontologo['id_odontologo'];
    }
} else if ($es_admin && isset($_SESSION['admin_viendo_odontologo'])) {
    // Si ya tenía seleccionado un odontólogo anteriormente
    $id_odontologo_temp = $_SESSION['admin_viendo_odontologo'];
    $check = $conexion->prepare("
        SELECT o.id_odontologo, o.duracion_cita_min, o.color_calendario, o.activo, o.especialidad_principal, u.nombre_completo 
        FROM odontologos o 
        JOIN usuarios u ON o.id_usuario = u.id_usuario 
        WHERE o.id_odontologo = ?
    ");
    $check->bind_param("i", $id_odontologo_temp);
    $check->execute();
    $check_result = $check->get_result();
    if ($check_result->num_rows > 0) {
        $odontologo_ver = $check_result->fetch_assoc();
        $id_odontologo = $odontologo_ver['id_odontologo'];
        $nombre_odontologo = $odontologo_ver['nombre_completo'];
        $odontologo = $odontologo_ver;
    } else {
        $id_odontologo = $odontologo['id_odontologo'];
        unset($_SESSION['admin_viendo_odontologo']);
    }
} else {
    $id_odontologo = $odontologo['id_odontologo'];
}

// Verificar si el odontólogo está activo
if ($odontologo['activo'] != 1 && !$es_admin) {
    $_SESSION['error'] = "Tu cuenta de odontólogo está desactivada. Contacta al administrador.";
    redirigir('/ecodent/public/dashboard.php');
}

// Si el admin quiere resetear y ver su propio calendario (si tiene)
if ($es_admin && isset($_GET['reset']) && $_GET['reset'] == 1) {
    unset($_SESSION['admin_viendo_odontologo']);
    redirigir('/ecodent/public/odontologo/calendario.php');
}

// DESBLOQUEO DE SLOT
if (isset($_GET['desbloquear']) && isset($_GET['fecha']) && isset($_GET['hora'])) {
    $fecha = $_GET['fecha'];
    $hora  = $_GET['hora'];
    if (function_exists('desbloquearSlot')) {
        if (desbloquearSlot($id_odontologo, $fecha, $hora, $conexion)) {
            $_SESSION['exito'] = 'Slot desbloqueado exitosamente.';
        } else {
            $_SESSION['error'] = 'Error al desbloquear el slot.';
        }
    } else {
        $_SESSION['error'] = 'Función desbloquearSlot no disponible.';
    }
    redirigir('/ecodent/public/odontologo/calendario.php');
}

// PARÁMETROS DE FECHA
$mes_actual  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio_actual = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

if ($mes_actual < 1)  $mes_actual = 12;
if ($mes_actual > 12) $mes_actual = 1;

$mes_anterior  = $mes_actual - 1;
$anio_anterior = $anio_actual;
if ($mes_anterior < 1)  { $mes_anterior = 12; $anio_anterior--; }

$mes_siguiente  = $mes_actual + 1;
$anio_siguiente = $anio_actual;
if ($mes_siguiente > 12) { $mes_siguiente = 1; $anio_siguiente++; }

$meses = [
    1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril',
    5=>'Mayo',  6=>'Junio',   7=>'Julio', 8=>'Agosto',
    9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
];
$nombre_mes = $meses[$mes_actual];

// CITAS DEL MES
$primer_dia = sprintf('%04d-%02d-01', $anio_actual, $mes_actual);
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));

$stmt_citas = $conexion->prepare(
    "SELECT c.estado, DAY(c.fecha_cita) as dia
     FROM citas c
     WHERE c.id_odontologo = ? AND c.fecha_cita BETWEEN ? AND ?
     ORDER BY c.hora_cita ASC"
);
if ($stmt_citas) {
    $stmt_citas->bind_param("iss", $id_odontologo, $primer_dia, $ultimo_dia);
    $stmt_citas->execute();
    $res_citas = $stmt_citas->get_result();

    $citas_por_dia = [];
    while ($row = $res_citas->fetch_assoc()) {
        $d = (int)$row['dia'];
        if (!isset($citas_por_dia[$d])) $citas_por_dia[$d] = ['total' => 0, 'activas' => 0];
        $citas_por_dia[$d]['total']++;
        if (in_array($row['estado'], ['programada', 'confirmada'])) {
            $citas_por_dia[$d]['activas']++;
        }
    }
} else {
    $citas_por_dia = [];
}

// BLOQUEOS DEL MES
$stmt_bloqueos = $conexion->prepare(
    "SELECT DAY(fecha) as dia, COUNT(*) as total
     FROM slots_bloqueados
     WHERE id_odontologo = ? AND fecha BETWEEN ? AND ?
     GROUP BY DAY(fecha)"
);
if ($stmt_bloqueos) {
    $stmt_bloqueos->bind_param("iss", $id_odontologo, $primer_dia, $ultimo_dia);
    $stmt_bloqueos->execute();
    $res_bloqueos = $stmt_bloqueos->get_result();

    $bloqueos_por_dia = [];
    while ($row = $res_bloqueos->fetch_assoc()) {
        $bloqueos_por_dia[(int)$row['dia']] = (int)$row['total'];
    }
} else {
    $bloqueos_por_dia = [];
}

// GENERAR CALENDARIO
$primer_dia_timestamp = strtotime($primer_dia);
$dia_semana_inicio    = (int)date('N', $primer_dia_timestamp);
$total_dias_mes       = (int)date('t', $primer_dia_timestamp);
$dias_semana          = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$hoy                  = date('Y-m-d');

require_once '../../includes/header.php';

// Mostrar mensaje de WhatsApp pendiente después de cancelar
if (isset($_SESSION['whatsapp_link']) && isset($_GET['whatsapp_pendiente'])) {
    $whatsapp_link = $_SESSION['whatsapp_link'];
    $whatsapp_telefono = $_SESSION['whatsapp_telefono'];
    ?>
    <div class="alert whatsapp-card alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <i class="bi bi-whatsapp fs-2 me-2"></i>
                <strong>📱 ¡Cita cancelada correctamente!</strong>
                <p class="mb-0 small mt-1">Se ha enviado un email al paciente. También puedes notificarle por WhatsApp:</p>
            </div>
            <a href="<?php echo $whatsapp_link; ?>" 
            target="_blank" 
            class="btn btn-light btn-whatsapp"
            onclick="fetch('../../cron/marcar_whatsapp_enviado.php?id_cita=<?php echo $id_cita_pendiente; ?>')">
                <i class="bi bi-whatsapp"></i> Enviar WhatsApp ahora
            </a>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
    <?php
    unset($_SESSION['whatsapp_link']);
    unset($_SESSION['whatsapp_telefono']);
}

// Mensajes de sesión
if (isset($_SESSION['exito'])) { 
    $exito = $_SESSION['exito']; 
    unset($_SESSION['exito']); 
}
if (isset($_SESSION['error'])) { 
    $error = $_SESSION['error']; 
    unset($_SESSION['error']); 
}

// Mostrar banner si admin está viendo otro odontólogo
if ($es_admin && isset($_SESSION['admin_viendo_odontologo']) && $_SESSION['admin_viendo_odontologo'] != $odontologo['id_odontologo']) {
    $stmt_nombre = $conexion->prepare("
        SELECT u.nombre_completo 
        FROM odontologos o 
        JOIN usuarios u ON o.id_usuario = u.id_usuario 
        WHERE o.id_odontologo = ?
    ");
    $stmt_nombre->bind_param("i", $_SESSION['admin_viendo_odontologo']);
    $stmt_nombre->execute();
    $nombre_result = $stmt_nombre->get_result();
    if ($nombre_odontologo_temp = $nombre_result->fetch_assoc()) {
        ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-eye"></i> Estás viendo el calendario de: <strong><?php echo htmlspecialchars($nombre_odontologo_temp['nombre_completo']); ?></strong>
            <a href="/ecodent/public/odontologo/calendario.php?reset=1" class="float-end">Ver mi calendario</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
    }
}
?>

<style>
.calendario-dia {
    height: 110px;
    border: 1px solid #dee2e6;
    padding: 6px;
    background: #fff;
    cursor: pointer;
    transition: background .15s, box-shadow .15s;
    position: relative;
    vertical-align: top;
}
.calendario-dia:hover {
    background: #f0f7ff;
    box-shadow: inset 0 0 0 2px #90caf9;
    z-index: 5;
}
.calendario-dia.otro-mes {
    background: #f8f9fa;
    cursor: default;
}
.calendario-dia.otro-mes:hover {
    background: #f8f9fa;
    box-shadow: none;
}
.calendario-dia.hoy {
    background: #e3f2fd !important;
    border: 2px solid #1976d2 !important;
    box-shadow: 0 0 8px rgba(25,118,210,.3) !important;
}
.calendario-dia.hoy:hover { background: #bbdefb !important; }
.calendario-dia.hoy .dia-num {
    background: #1976d2;
    color: #fff;
    border-radius: 50%;
    width: 26px; height: 26px;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: .85rem; font-weight: 700;
}
.dia-num {
    font-weight: 700;
    font-size: .9rem;
    color: #343a40;
    line-height: 1;
}
.celda-body {
    margin-top: 4px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.badge-citas {
    font-size: .7rem;
    padding: 2px 7px;
    border-radius: 20px;
    background: #0d6efd;
    color: #fff;
    display: inline-block;
    width: fit-content;
}
.badge-bloqueos {
    font-size: .7rem;
    padding: 2px 7px;
    border-radius: 20px;
    background: #dc3545;
    color: #fff;
    display: inline-block;
    width: fit-content;
}
.btn-ver-citas {
    font-size: .68rem;
    padding: 3px 8px;
    border-radius: 20px;
    border: 1px solid #0d6efd;
    color: #0d6efd;
    background: transparent;
    cursor: pointer;
    transition: all .15s;
    width: 100%;
    text-align: center;
    margin-top: 2px;
}
.btn-ver-citas:hover {
    background: #0d6efd;
    color: #fff;
}
.modal-citas-header { background: linear-gradient(135deg,#1565c0,#1976d2); }
.slot-row {
    display: flex;
    align-items: stretch;
    border-bottom: 1px solid #f0f0f0;
    min-height: 52px;
    transition: background .1s;
}
.slot-row:hover { background: #fafafa; }
.slot-hora {
    width: 72px;
    min-width: 72px;
    padding: 6px 8px;
    font-size: .78rem;
    font-weight: 600;
    color: #555;
    border-right: 2px solid #e9ecef;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: right;
    line-height: 1.3;
}
.slot-contenido {
    flex: 1;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.slot-libre {
    color: #adb5bd;
    font-size: .8rem;
    font-style: italic;
}
.slot-cita-card {
    flex: 1;
    border-left: 4px solid;
    padding: 5px 10px;
    border-radius: 4px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.slot-cita-card.programada  { border-left-color: #0d6efd; background:#f0f4ff; }
.slot-cita-card.confirmada  { border-left-color: #198754; background:#f0fff4; }
.slot-cita-card.completada  { border-left-color: #6c757d; background:#f4f4f4; }
.slot-cita-card.ausente     { border-left-color: #ffc107; background:#fffdf0; }
.slot-cita-card.cancelada   { border-left-color: #dc3545; background:#fff5f5; opacity:.8; }
.slot-bloqueado-card {
    flex: 1;
    border-left: 4px solid #dc3545;
    padding: 5px 10px;
    border-radius: 4px;
    background: #fff5f5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: #dc3545;
    font-size: .82rem;
}
.slot-paciente-nombre {
    font-weight: 600;
    font-size: .85rem;
    color: #212529;
}
.slot-motivo {
    font-size: .75rem;
    color: #6c757d;
}
.slot-estado-badge {
    font-size: .68rem;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
</style>

<!-- Encabezado -->
<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-calendar-week"></i> Mi Calendario — <?php echo date('d/m/Y'); ?></h1>
        
        <p class="mb-0">
        Bienvenido, 
        <strong>
        <?php 
        if ($es_admin && isset($nombre_odontologo)) {
            echo htmlspecialchars("Administrador - Calendario del odontólogo: " . $nombre_odontologo);
        } else {
            echo htmlspecialchars($odontologo['nombre_completo'] ?? 'Odontólogo');
        }
        ?>
        </strong>
        </p>
        <?php if (isset($odontologo['especialidad_principal'])): ?>
            <small class="text-muted">Especialidad: <?php echo htmlspecialchars($odontologo['especialidad_principal']); ?> | Duración de cita: <?php echo $odontologo['duracion_cita_min']; ?> min</small>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end d-flex gap-2 justify-content-end align-items-start">
        <a href="configurar_horarios.php" class="btn btn-primary btn-sm">
            <i class="bi bi-gear"></i> Horarios
        </a>
        <a href="bloquear_slots.php" class="btn btn-warning btn-sm">
            <i class="bi bi-lock"></i> Bloquear Slot
        </a>
    </div>
</div>

<?php if (isset($exito)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($exito); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Navegación del mes -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group">
        <a href="?mes=<?php echo $mes_anterior; ?>&anio=<?php echo $anio_anterior; ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-chevron-left"></i> <?php echo $meses[$mes_anterior]; ?>
        </a>
        <a href="?mes=<?php echo date('m'); ?>&anio=<?php echo date('Y'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar-check"></i> Hoy
        </a>
        <a href="?mes=<?php echo $mes_siguiente; ?>&anio=<?php echo $anio_siguiente; ?>" class="btn btn-outline-primary btn-sm">
            <?php echo $meses[$mes_siguiente]; ?> <i class="bi bi-chevron-right"></i>
        </a>
    </div>
    <h4 class="mb-0 fw-bold"><?php echo $nombre_mes . ' ' . $anio_actual; ?></h4>
</div>

<!-- Tabla del calendario -->
<div class="table-responsive">
    <table class="table table-bordered mb-0" style="table-layout:fixed;">
        <thead class="table-dark">
            32
                <?php foreach ($dias_semana as $dn): ?>
                    <th class="text-center" style="width:14.28%"><?php echo $dn; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php for ($i = 1; $i < $dia_semana_inicio; $i++): ?>
                    <td class="calendario-dia otro-mes"></td>
                <?php endfor; ?>

                <?php for ($dia = 1; $dia <= $total_dias_mes; $dia++):
                    $fecha_celda    = sprintf('%04d-%02d-%02d', $anio_actual, $mes_actual, $dia);
                    $dia_semana_num = (int)date('N', strtotime($fecha_celda));
                    $es_hoy         = ($fecha_celda === $hoy);

                    if ($dia_semana_num == 1 && $dia > 1) echo '</tr><tr>';

                    $info_citas   = $citas_por_dia[$dia] ?? null;
                    $num_bloqueos = $bloqueos_por_dia[$dia] ?? 0;
                    $num_citas    = $info_citas ? $info_citas['total'] : 0;
                    $num_activas  = $info_citas ? $info_citas['activas'] : 0;
                ?>
                    <td class="calendario-dia <?php echo $es_hoy ? 'hoy' : ''; ?>"
                        onclick="abrirModal('<?php echo $fecha_celda; ?>')">

                        <div class="dia-num"><?php echo $dia; ?></div>

                        <div class="celda-body">
                            <?php if ($num_citas > 0): ?>
                                <span class="badge-citas">
                                    <i class="bi bi-person-fill"></i> <?php echo $num_citas; ?> cita<?php echo $num_citas > 1 ? 's' : ''; ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($num_bloqueos > 0): ?>
                                <span class="badge-bloqueos">
                                    <i class="bi bi-lock-fill"></i> <?php echo $num_bloqueos; ?> bloq.
                                </span>
                            <?php endif; ?>

                            <?php if ($num_citas > 0 || $num_bloqueos > 0): ?>
                                <div class="btn-ver-citas">
                                    <i class="bi bi-eye"></i> Ver horarios
                                </div>
                            <?php elseif ($fecha_celda >= $hoy): ?>
                                <div style="font-size:.68rem;color:#adb5bd;margin-top:4px;text-align:center;">
                                    <i class="bi bi-plus-circle"></i> Libre
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                <?php endfor; ?>

                <?php
                $dias_restantes = 7 - (($dia_semana_inicio + $total_dias_mes - 1) % 7);
                if ($dias_restantes < 7):
                    for ($i = 0; $i < $dias_restantes; $i++): ?>
                        <td class="calendario-dia otro-mes"></td>
                    <?php endfor;
                endif; ?>
            </tr>
        </tbody>
    </table>
</div>

<!-- Leyenda -->
<div class="d-flex flex-wrap gap-3 mt-3 align-items-center" style="font-size:.82rem;">
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0d6efd;"></span> Programada</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#198754;"></span> Confirmada</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#6c757d;"></span> Completada</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ffc107;"></span> Ausente</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dc3545;"></span> Cancelada / Bloqueado</span>
    <span>
        <span style="display:inline-flex;align-items:center;justify-content:center;background:#1976d2;color:#fff;border-radius:50%;width:18px;height:18px;font-size:.65rem;font-weight:700;">H</span>
        Día actual
    </span>
</div>

<!-- MODAL DE HORARIOS DEL DÍA -->
<div class="modal fade" id="modalHorariosDia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-citas-header text-white py-3">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Agenda del día
                    </h5>
                    <small id="modal-fecha-label" class="opacity-75"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modal-slots-container" style="min-height:200px;">
                    <div class="d-flex justify-content-center align-items-center" style="height:200px;">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" id="modal-btn-agendar" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Agendar cita
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModal(fecha) {
    // Obtener el id_odontologo actual
    var idOdontologo = null;
    
    // 1. Verificar si viene en la URL (ver_odontologo)
    var urlParams = new URLSearchParams(window.location.search);
    var urlId = urlParams.get('ver_odontologo');
    if (urlId && !isNaN(urlId)) {
        idOdontologo = urlId;
    }
    
    // 2. Si no, verificar si está en sesión (admin_viendo_odontologo)
    <?php if (isset($_SESSION['admin_viendo_odontologo']) && $_SESSION['admin_viendo_odontologo']): ?>
        if (!idOdontologo) {
            idOdontologo = <?php echo $_SESSION['admin_viendo_odontologo']; ?>;
        }
    <?php endif; ?>
    
    // 3. Si es admin y no hay id_odontologo, mostrar mensaje
    <?php if ($es_admin): ?>
        if (!idOdontologo) {
            alert('Primero selecciona un odontólogo para ver sus citas');
            return false;
        }
    <?php endif; ?>
    
    // Formatear fecha para mostrar: YYYY-MM-DD → DD/MM/YYYY
    var p = fecha.split('-');
    document.getElementById('modal-fecha-label').textContent = p[2] + '/' + p[1] + '/' + p[0];
    
    // Construir URL para agendar cita con el id_odontologo
    var urlAgendar = 'agendar_cita.php?fecha=' + fecha;
    if (idOdontologo) {
        urlAgendar += '&id_odontologo=' + idOdontologo;
    }
    document.getElementById('modal-btn-agendar').href = urlAgendar;
    
    // Spinner mientras carga
    document.getElementById('modal-slots-container').innerHTML =
        '<div class="d-flex justify-content-center align-items-center" style="height:200px;">' +
        '<div class="spinner-border text-primary"></div></div>';
    
    // Abrir modal
    new bootstrap.Modal(document.getElementById('modalHorariosDia')).show();
    
    // Cargar slots vía AJAX - PASAR EL ID_ODONTOLOGO
    var urlSlots = 'citas_por_dia.php?fecha=' + fecha + '&t=' + Date.now();
    if (idOdontologo) {
        urlSlots += '&id_odontologo=' + idOdontologo;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            document.getElementById('modal-slots-container').innerHTML = this.responseText;
        }
    };
    xhr.open('GET', urlSlots, true);
    xhr.send();
}
</script>

<?php require_once '../../includes/footer.php'; ?>