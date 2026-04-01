<?php
// public/odontologo/calendario.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/slots.php';
require_once '../../includes/cancelaciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

$stmt = $conexion->prepare("SELECT id_odontologo, duracion_cita_min, color_calendario FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

// DESBLOQUEO DE SLOT
if (isset($_GET['desbloquear']) && isset($_GET['fecha']) && isset($_GET['hora'])) {
    $fecha = $_GET['fecha'];
    $hora  = $_GET['hora'];
    if (desbloquearSlot($id_odontologo, $fecha, $hora, $conexion)) {
        $_SESSION['exito'] = 'Slot desbloqueado exitosamente.';
    } else {
        $_SESSION['error'] = 'Error al desbloquear el slot.';
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
$stmt_citas->bind_param("iss", $id_odontologo, $primer_dia, $ultimo_dia);
$stmt_citas->execute();
$res_citas = $stmt_citas->get_result();

// Organizar: contar por día y ver si hay alguna confirmada/programada
$citas_por_dia = [];
while ($row = $res_citas->fetch_assoc()) {
    $d = (int)$row['dia'];
    if (!isset($citas_por_dia[$d])) $citas_por_dia[$d] = ['total' => 0, 'activas' => 0];
    $citas_por_dia[$d]['total']++;
    if (in_array($row['estado'], ['programada', 'confirmada'])) {
        $citas_por_dia[$d]['activas']++;
    }
}

// BLOQUEOS DEL MES
$stmt_bloqueos = $conexion->prepare(
    "SELECT DAY(fecha) as dia, COUNT(*) as total
     FROM slots_bloqueados
     WHERE id_odontologo = ? AND fecha BETWEEN ? AND ?
     GROUP BY DAY(fecha)"
);
$stmt_bloqueos->bind_param("iss", $id_odontologo, $primer_dia, $ultimo_dia);
$stmt_bloqueos->execute();
$res_bloqueos = $stmt_bloqueos->get_result();

$bloqueos_por_dia = [];
while ($row = $res_bloqueos->fetch_assoc()) {
    $bloqueos_por_dia[(int)$row['dia']] = (int)$row['total'];
}

// GENERAR CALENDARIO
$primer_dia_timestamp = strtotime($primer_dia);
$dia_semana_inicio    = (int)date('N', $primer_dia_timestamp);
$total_dias_mes       = (int)date('t', $primer_dia_timestamp);
$dias_semana          = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$hoy                  = date('Y-m-d');

require_once '../../includes/header.php';


if (isset($_SESSION['exito'])) { $exito = $_SESSION['exito']; unset($_SESSION['exito']); }
if (isset($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }
?>
<?php if (isset($_SESSION['whatsapp_pendiente'])): 
    $wp = $_SESSION['whatsapp_pendiente'];
    // Limpiar número: quitar espacios, guiones, agregar código Bolivia +591
    $telefono_limpio = preg_replace('/[^0-9]/', '', $wp['telefono']);
    if (strlen($telefono_limpio) == 8) {
        $telefono_limpio = '591' . $telefono_limpio; // Bolivia
    }
    $mensaje_encoded = urlencode($wp['mensaje']);
    $link_whatsapp = "https://wa.me/{$telefono_limpio}?text={$mensaje_encoded}";
    unset($_SESSION['whatsapp_pendiente']); // Limpiar sesión
?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <h5><i class="bi bi-check-circle-fill"></i> Cita cancelada exitosamente</h5>
    <p class="mb-2">El slot quedó bloqueado y el email fue registrado para envío.</p>
    <p class="mb-2"><strong>¿Deseas notificar también por WhatsApp?</strong></p>
    <a href="<?php echo $link_whatsapp; ?>" 
       target="_blank" 
       class="btn btn-success btn-lg">
        <i class="bi bi-whatsapp"></i> Enviar WhatsApp al paciente
    </a>
    <small class="d-block mt-2 text-muted">
        El mensaje ya está escrito. Solo haz click en Enviar dentro de WhatsApp.
    </small>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php 
endif; 
?>
<style>
/* ─── Celda del calendario ─── */
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

/* ─── Día actual ─── */
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

/* ─── Número del día ─── */
.dia-num {
    font-weight: 700;
    font-size: .9rem;
    color: #343a40;
    line-height: 1;
}

/* ─── Contenido de la celda ─── */
.celda-body {
    margin-top: 4px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

/* Badges de conteo */
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

/* Botón "Ver citas" */
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

/* Punto de color por tipo de cita */
.punto-estado {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    margin-right: 2px;
}

/* ─── Modal ─── */
.modal-citas-header { background: linear-gradient(135deg,#1565c0,#1976d2); }

/* ─── Slots timeline en modal ─── */
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
        <p class="mb-0">Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></strong></p>
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
        <i class="bi bi-check-circle-fill"></i> <?php echo $exito; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
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
            <tr>
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

<!-- ══════════════════════════════════════════
     MODAL DE HORARIOS DEL DÍA
════════════════════════════════════════════ -->
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
                    <!-- cargado vía AJAX -->
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
    // Formatear fecha para mostrar: YYYY-MM-DD → DD/MM/YYYY
    var p = fecha.split('-');
    document.getElementById('modal-fecha-label').textContent = p[2] + '/' + p[1] + '/' + p[0];
    document.getElementById('modal-btn-agendar').href = 'agendar_cita.php?fecha=' + fecha;

    // Spinner mientras carga
    document.getElementById('modal-slots-container').innerHTML =
        '<div class="d-flex justify-content-center align-items-center" style="height:200px;">' +
        '<div class="spinner-border text-primary"></div></div>';

    // Abrir modal
    new bootstrap.Modal(document.getElementById('modalHorariosDia')).show();

    // Cargar slots vía AJAX
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            document.getElementById('modal-slots-container').innerHTML = this.responseText;
        }
    };
    xhr.open('GET', 'citas_por_dia.php?fecha=' + fecha + '&t=' + Date.now(), true);
    xhr.send();
}
</script>

<?php require_once '../../includes/footer.php'; ?>