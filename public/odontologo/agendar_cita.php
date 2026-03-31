<?php
// =============================================
// public/odontologo/agendar_cita.php
// =============================================

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// --- Leer fecha y hora preseleccionada desde GET ---
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$hora_preseleccionada = $_GET['hora'] ?? null; // ej: "08:00" viene del calendario

// Validar formato de fecha
$fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fecha_obj) {
    $fecha = date('Y-m-d');
} else {
    $fecha = $fecha_obj->format('Y-m-d'); // garantiza ceros: 2026-03-06
}

// Normalizar hora a H:i:s para comparar con data-hora-inicio de los slots
// Ejemplo: "08:00" → "08:00:00"
$hora_pre_normalizada = null;
if ($hora_preseleccionada) {
    $hora_pre_normalizada = date('H:i:s', strtotime($hora_preseleccionada));
}

// --- Datos del odontólogo logueado ---
$stmt = $conexion->prepare("SELECT id_odontologo, duracion_cita_min FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado  = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];
$duracion_slot = $odontologo['duracion_cita_min']; // minutos por slot, ej: 40

// --- Lista de pacientes ---
$sql_pacientes = "SELECT p.id_paciente, u.nombre_completo, u.email, u.telefono
                  FROM pacientes p
                  JOIN usuarios u ON p.id_usuario = u.id_usuario
                  ORDER BY u.nombre_completo ASC";
$pacientes = $conexion->query($sql_pacientes);

// --- Citas del día (TODOS los estados relevantes para pintar slots) ---
// Incluimos:
//   programada / confirmada  → ocupado   (amarillo, no se puede agendar)
//   completada               → completada (gris, el paciente asistió, no se puede agendar)
//   ausente                  → ausente   (morado, no se presentó, no se puede agendar)
//   cancelada_pac / cancelada_doc → NO se incluyen (slot queda libre/verde)
$sql_ocupados = "SELECT c.id_cita, c.hora_cita, c.hora_fin, c.motivo, c.estado,
                        u.nombre_completo as paciente
                 FROM citas c
                 JOIN pacientes p ON c.id_paciente = p.id_paciente
                 JOIN usuarios u ON p.id_usuario = u.id_usuario
                 WHERE c.id_odontologo = ?
                   AND c.fecha_cita = ?
                   AND c.estado IN ('programada','confirmada','completada','ausente')
                 ORDER BY c.hora_cita ASC";
$stmt_ocupados = $conexion->prepare($sql_ocupados);
$stmt_ocupados->bind_param("is", $id_odontologo, $fecha);
$stmt_ocupados->execute();
$ocupados = $stmt_ocupados->get_result();

// Construir array de citas con sus rangos de tiempo y estado
$citas_ocupadas = [];
while ($oc = $ocupados->fetch_assoc()) {
    $inicio = strtotime($fecha . ' ' . $oc['hora_cita']);
    $fin    = strtotime($fecha . ' ' . $oc['hora_fin']);
    $citas_ocupadas[] = [
        'id_cita'     => $oc['id_cita'],
        'inicio'      => $inicio,
        'fin'         => $fin,
        'paciente'    => $oc['paciente'],
        'motivo'      => $oc['motivo'],
        'hora_inicio' => $oc['hora_cita'],
        'hora_fin'    => $oc['hora_fin'],
        'estado'      => $oc['estado'], // 'programada'|'confirmada'|'completada'|'ausente'
    ];
}

// --- Slots bloqueados manualmente ---
$sql_bloqueados = "SELECT * FROM slots_bloqueados WHERE id_odontologo = ? AND fecha = ?";
$stmt_bloqueados = $conexion->prepare($sql_bloqueados);
$stmt_bloqueados->bind_param("is", $id_odontologo, $fecha);
$stmt_bloqueados->execute();
$bloqueados_result = $stmt_bloqueados->get_result();

$slots_bloqueados = [];
while ($bl = $bloqueados_result->fetch_assoc()) {
    $slots_bloqueados[] = [
        'inicio' => strtotime($fecha . ' ' . $bl['hora_inicio']),
        'fin'    => strtotime($fecha . ' ' . $bl['hora_fin']),
        'motivo' => $bl['motivo']
    ];
}

// --- Horario del odontólogo para el día seleccionado ---
$dia_semana = strtolower(date('l', strtotime($fecha)));
$dias_es = [
    'monday'    => 'lunes',
    'tuesday'   => 'martes',
    'wednesday' => 'miercoles',
    'thursday'  => 'jueves',
    'friday'    => 'viernes',
    'saturday'  => 'sabado',
    'sunday'    => 'domingo'
];
$dia_es = $dias_es[$dia_semana];

$sql_horario = "SELECT hora_inicio, hora_fin FROM horarios_odontologos
                WHERE id_odontologo = ? AND dia_semana = ? AND activo = 1";
$stmt_horario = $conexion->prepare($sql_horario);
$stmt_horario->bind_param("is", $id_odontologo, $dia_es);
$stmt_horario->execute();
$horario = $stmt_horario->get_result()->fetch_assoc();

// --- Generar slots del día ---
$slots_dia = [];
if ($horario) {
    $inicio  = strtotime($fecha . ' ' . $horario['hora_inicio']);
    $fin     = strtotime($fecha . ' ' . $horario['hora_fin']);
    $paso    = $duracion_slot * 60; // segundos por slot

    $hora_actual = $inicio;
    while ($hora_actual + $paso <= $fin) {

        $hora_inicio_slot = date('H:i:s', $hora_actual);
        $hora_fin_slot    = date('H:i:s', $hora_actual + $paso);

        // Determinar estado visual del slot
        // Prioridad: cita (cualquier estado) > bloqueado > disponible
        $estado_slot = 'disponible';
        $cita_info   = null;

        foreach ($citas_ocupadas as $cita) {
            if ($hora_actual >= $cita['inicio'] && $hora_actual < $cita['fin']) {
                // Mapear estado de la BD al estado visual del slot
                if ($cita['estado'] === 'completada') {
                    $estado_slot = 'completada'; // gris neutro
                } elseif ($cita['estado'] === 'ausente') {
                    $estado_slot = 'ausente';    // morado apagado
                } else {
                    // programada o confirmada
                    $estado_slot = 'ocupado';    // amarillo
                }
                $cita_info = $cita;
                break;
            }
        }

        // Si sigue disponible, verificar bloqueo manual
        $bloqueo_info = null;
        if ($estado_slot === 'disponible') {
            foreach ($slots_bloqueados as $bloqueo) {
                if ($hora_actual >= $bloqueo['inicio'] && $hora_actual < $bloqueo['fin']) {
                    $estado_slot  = 'bloqueado'; // rojo
                    $bloqueo_info = $bloqueo;
                    break;
                }
            }
        }

        $slots_dia[] = [
            'timestamp'        => $hora_actual,
            'hora_inicio'      => $hora_inicio_slot,
            'hora_fin'         => $hora_fin_slot,
            'hora_display'     => date('h:i A', $hora_actual),
            'hora_fin_display' => date('h:i A', $hora_actual + $paso),
            // Estado: 'disponible' | 'ocupado' | 'completada' | 'ausente' | 'bloqueado'
            'estado'           => $estado_slot,
            // Solo 'disponible' puede clickearse para agendar
            'disponible'       => $estado_slot === 'disponible',
            'info'             => $cita_info ?? $bloqueo_info,
        ];

        $hora_actual += $paso;
    }
}

// --- Token CSRF y mensajes flash ---
$token_csrf = generarTokenCSRF();
$exito = '';
$error = '';
if (isset($_SESSION['exito'])) { $exito = $_SESSION['exito']; unset($_SESSION['exito']); }
if (isset($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }

require_once '../../includes/header.php';
?>

<style>
/* ============================================================
   GRID DE SLOTS
   ============================================================ */
.slots-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
    max-height: 600px;
    overflow-y: auto;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

/* ---- Tarjeta base ---- */
.slot-card {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Hover solo en disponibles */
.slot-card:hover:not(.no-click) {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    border-color: #0d6efd;
}

/* ---- DISPONIBLE → verde ---- */
.slot-card.disponible {
    background: #d1e7dd;
    border-color: #0f5132;
    color: #0f5132;
}

/* ---- OCUPADO (programada / confirmada) → amarillo ---- */
.slot-card.ocupado {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
    cursor: not-allowed;
}

/* ---- COMPLETADA (paciente asistió) → gris neutro ---- */
.slot-card.completada {
    background: #e2e3e5;
    border-color: #6c757d;
    color: #41464b;
    cursor: not-allowed;
    opacity: 0.85;
}

/* ---- AUSENTE (no se presentó) → morado apagado ---- */
.slot-card.ausente {
    background: #e8d5f5;
    border-color: #6f42c1;
    color: #432874;
    cursor: not-allowed;
    opacity: 0.85;
}

/* ---- BLOQUEADO manualmente → rojo ---- */
.slot-card.bloqueado {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
    cursor: not-allowed;
    opacity: 0.85;
}

/* Deshabilitar cursor en todos los no-disponibles */
.slot-card.no-click { cursor: not-allowed; }

/* ---- SELECCIONADO → azul ---- */
.slot-card.seleccionado {
    background: #cfe2ff;
    border-color: #0d6efd;
    color: #084298;
    transform: scale(1.02);
    box-shadow: 0 0 0 3px rgba(13,110,253,0.25);
}

/* Checkmark esquina superior derecha */
.slot-card.seleccionado::after {
    content: "✓";
    position: absolute;
    top: 5px; right: 5px;
    background: #0d6efd;
    color: white;
    width: 20px; height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center; justify-content: center;
    font-size: 12px; font-weight: bold;
}

/* ---- Textos internos del slot ---- */
.slot-hora      { font-size: 1.4rem; font-weight: bold; margin-bottom: 5px; }
.slot-hora-fin  { font-size: 1rem; font-weight: 500; opacity: 0.9; margin-bottom: 5px; }
.slot-estado    { font-size: 0.85rem; margin-top: 5px; }

/* Badges de estado dentro del slot */
.slot-badge-estado {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    margin-top: 4px;
    letter-spacing: 0.3px;
}
.badge-completada { background: #6c757d; color: white; }
.badge-ausente    { background: #6f42c1; color: white; }
.badge-ocupado    { background: #ffc107; color: #333; }
.badge-bloqueado  { background: #dc3545; color: white; }

/* ============================================================
   RESUMEN INFERIOR
   ============================================================ */
.resumen-card {
   /* DESPUÉS */
background: linear-gradient(135deg,  #0288d1 100%, #0288d1 100%);
box-shadow: 0 10px 30px rgba(2,136,209,0.25);
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
    color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.resumen-contenido { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.resumen-datos     { display: flex; align-items: center; gap: 25px; flex-wrap: wrap; }
.resumen-item {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,0.15);
    padding: 10px 20px; border-radius: 50px;
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(5px);
}
.resumen-item i      { font-size: 1.2rem; }
.resumen-item strong { font-weight: 600; margin-right: 5px; }

.motivo-field {
    background: white;
    border: 2px solid rgba(255,255,255,0.6);
    border-radius: 50px;
    color: #0288d1;
    padding: 10px 20px;
    width: 280px;
    font-size: 0.95rem;
    font-weight: 600;
    max-height: 45px;
    overflow-y: auto;
    cursor: pointer;
}
.motivo-field:focus  { background: white; border-color: white; outline: none; color: #0288d1; }
.motivo-field option { color: #212529; background: white; font-weight: 400; padding: 5px; }


.btn-registrar {
    background: white; color: #667eea; border: none;
    padding: 12px 35px; font-size: 1.1rem; font-weight: 600;
    border-radius: 50px; cursor: pointer; transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2); white-space: nowrap;
}
.btn-registrar:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); background: #f8f9fa; }
.btn-registrar:disabled { opacity: 0.5; cursor: not-allowed; }

/* ============================================================
   TAGS DE SELECCIONADOS
   ============================================================ */
.seleccionados-tags {
    background: white; border: 1px solid #dee2e6; border-radius: 10px;
    padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.slot-tag {
    display: inline-block; background: #e9ecef; border: 2px solid #0d6efd;
    border-radius: 30px; padding: 5px 15px; margin: 0 5px 5px 0;
    font-size: 0.9rem; font-weight: 500; color: #084298;
}
.slot-tag i       { color: #dc3545; cursor: pointer; margin-left: 8px; }
.slot-tag i:hover { color: #b02a37; }

/* ============================================================
   LEYENDA
   ============================================================ */
.legend-badge     { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; margin-right: 8px; margin-bottom: 6px; font-weight: 500; }
.badge-disponible { background: #d1e7dd; color: #0f5132; border: 1px solid #0f5132; }
.badge-ocup       { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
.badge-comp       { background: #e2e3e5; color: #41464b; border: 1px solid #6c757d; }
.badge-aus        { background: #e8d5f5; color: #432874; border: 1px solid #6f42c1; }
.badge-bloq       { background: #f8d7da; color: #721c24; border: 1px solid #dc3545; }
.badge-sel        { background: #cfe2ff; color: #084298; border: 1px solid #0d6efd; }
</style>

<div class="container-fluid">

    <!-- Encabezado -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-calendar-plus"></i> Agendar Procedimiento</h1>
            <p class="lead">Selecciona los slots que ocupará el procedimiento</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="calendario.php" class="btn btn-primary">
                <i class="bi bi-calendar-week"></i> Ver Calendario
            </a>
        </div>
    </div>

    <!-- Alertas flash -->
    <?php if ($exito): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?php echo $exito; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Leyenda actualizada con los 6 estados -->
    <div class="row mb-4">
        <div class="col-12">
            <span class="legend-badge badge-disponible"><i class="bi bi-check-circle"></i> Disponible</span>
            <span class="legend-badge badge-ocup"><i class="bi bi-person-fill"></i> Con cita</span>
            <span class="legend-badge badge-comp"><i class="bi bi-check2-circle"></i> Completada (asistió)</span>
            <span class="legend-badge badge-aus"><i class="bi bi-person-x"></i> Ausente</span>
            <span class="legend-badge badge-bloq"><i class="bi bi-lock-fill"></i> Bloqueado</span>
            <span class="legend-badge badge-sel"><i class="bi bi-check-circle-fill"></i> Seleccionado</span>
        </div>
    </div>

    <!-- PASO 1: Fecha | PASO 2: Paciente -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar"></i> 1. Seleccionar Fecha</h5>
                </div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="date" class="form-control" id="fechaSelector"
                               value="<?php echo htmlspecialchars($fecha); ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                        <button class="btn btn-outline-primary" onclick="cambiarFecha()">
                            <i class="bi bi-arrow-repeat"></i> Cambiar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-person"></i> 2. Seleccionar Paciente</h5>
                </div>
                <div class="card-body">
                    <select class="form-select" id="pacienteSelect" required>
                        <option value="">-- Seleccionar paciente --</option>
                        <?php while ($paciente = $pacientes->fetch_assoc()): ?>
                            <option value="<?php echo $paciente['id_paciente']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                <?php echo htmlspecialchars($paciente['nombre_completo']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <?php if ($horario): ?>

        <!-- PASO 3: Slots -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock"></i> 3. Seleccionar Slots
                            <small class="ms-2 opacity-75"><?php echo date('d/m/Y', strtotime($fecha)); ?></small>
                        </h5>
                        <div>
                            <button class="btn btn-light btn-sm me-2" onclick="seleccionarTodosDisponibles()">
                                <i class="bi bi-check-all"></i> Todos
                            </button>
                            <button class="btn btn-light btn-sm" onclick="deseleccionarTodos()">
                                <i class="bi bi-x"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($slots_dia)): ?>
                            <div class="alert alert-warning mb-0">
                                No hay horarios disponibles para este día.
                            </div>
                        <?php else: ?>
                            <div class="slots-container">
                                <?php foreach ($slots_dia as $slot):
                                    // Clase CSS = estado del slot (disponible|ocupado|completada|ausente|bloqueado)
                                    $clase_estado = $slot['estado'];
                                    // Los no-disponibles reciben clase extra para cursor
                                    $clase_extra  = $slot['disponible'] ? '' : 'no-click';
                                ?>
                                    <div class="slot-card <?php echo $clase_estado . ' ' . $clase_extra; ?>"
                                         id="slot_<?php echo $slot['timestamp']; ?>"
                                         onclick="toggleSlot(
                                             <?php echo $slot['timestamp']; ?>,
                                             '<?php echo $slot['hora_inicio']; ?>',
                                             '<?php echo $slot['hora_fin']; ?>',
                                             '<?php echo $slot['hora_display']; ?>',
                                             '<?php echo $slot['hora_fin_display']; ?>',
                                             <?php echo $slot['disponible'] ? 'true' : 'false'; ?>
                                         )"
                                         data-timestamp="<?php echo $slot['timestamp']; ?>"
                                         data-hora-inicio="<?php echo $slot['hora_inicio']; ?>"
                                         data-hora-fin="<?php echo $slot['hora_fin']; ?>"
                                         data-hora-display="<?php echo $slot['hora_display']; ?>"
                                         data-hora-fin-display="<?php echo $slot['hora_fin_display']; ?>">

                                        <!-- Hora de inicio del slot -->
                                        <div class="slot-hora"><?php echo $slot['hora_display']; ?></div>
                                        <!-- Hora de fin del slot -->
                                        <div class="slot-hora-fin">- <?php echo $slot['hora_fin_display']; ?></div>

                                        <?php if ($slot['estado'] === 'completada' && $slot['info']): ?>
                                            <!-- Paciente que asistió → gris con badge "Asistió" -->
                                            <div class="slot-estado">
                                                <i class="bi bi-person"></i>
                                                <?php echo htmlspecialchars(substr($slot['info']['paciente'], 0, 15)); ?>
                                            </div>
                                            <span class="slot-badge-estado badge-completada">
                                                <i class="bi bi-check2-circle"></i> Asistió
                                            </span>

                                        <?php elseif ($slot['estado'] === 'ausente' && $slot['info']): ?>
                                            <!-- Paciente que no se presentó → morado con badge "No asistió" -->
                                            <div class="slot-estado">
                                                <i class="bi bi-person"></i>
                                                <?php echo htmlspecialchars(substr($slot['info']['paciente'], 0, 15)); ?>
                                            </div>
                                            <span class="slot-badge-estado badge-ausente">
                                                <i class="bi bi-person-x"></i> No asistió
                                            </span>

                                        <?php elseif ($slot['estado'] === 'ocupado' && $slot['info']): ?>
                                            <!-- Cita programada/confirmada → amarillo con nombre y motivo -->
                                            <div class="slot-estado">
                                                <i class="bi bi-person"></i>
                                                <?php echo htmlspecialchars(substr($slot['info']['paciente'], 0, 15)); ?>
                                            </div>
                                            <div class="slot-estado" style="font-size:10px;">
                                                <?php echo substr($slot['info']['motivo'] ?? '', 0, 20); ?>
                                            </div>

                                        <?php elseif ($slot['estado'] === 'bloqueado' && $slot['info']): ?>
                                            <!-- Bloqueado manualmente → rojo con motivo -->
                                            <div class="slot-estado">
                                                <i class="bi bi-lock"></i>
                                                <?php echo substr($slot['info']['motivo'] ?? 'Bloqueado', 0, 20); ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tags de slots seleccionados -->
        <div class="row" id="seleccionadosContainer" style="display:none;">
            <div class="col-12">
                <div class="seleccionados-tags">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle-fill text-primary"></i>
                            Slots seleccionados:
                            <span class="badge bg-primary" id="contadorSeleccionados">0</span>
                        </h5>
                        <div>
                            <span class="badge bg-info me-2" id="duracionTotal"></span>
                            <button class="btn btn-outline-danger btn-sm" onclick="limpiarSeleccion()">
                                <i class="bi bi-trash"></i> Limpiar todo
                            </button>
                        </div>
                    </div>
                    <div id="listaSlotsSeleccionados"></div>
                </div>
            </div>
        </div>

        <!-- Resumen + botón agendar -->
        <div class="row" id="resumenContainer" style="display:none;">
            <div class="col-12">
                <div class="resumen-card">
                    <div class="resumen-contenido">
                        <div class="resumen-datos">
                            <div class="resumen-item">
                                <i class="bi bi-person-circle"></i>
                                <strong>Paciente:</strong> <span id="resumenPaciente">---</span>
                            </div>
                            <div class="resumen-item">
                                <i class="bi bi-calendar"></i>
                                <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha)); ?>
                            </div>
                            <div class="resumen-item">
                                <i class="bi bi-clock"></i>
                                <strong>Horario:</strong> <span id="resumenHorarioCompleto">---</span>
                            </div>
                            <div class="resumen-item">
                                <i class="bi bi-hourglass"></i>
                                <strong>Duración:</strong> <span id="resumenDuracion">---</span>
                            </div>
                            <!--para selecionar el motivo de la cita-->
                           <select class="motivo-field" id="motivoCita" size="1" style="border-radius:12px; max-height:45px; overflow-y:auto;">
                                <option value="" disabled selected>Selecciona un motivo...</option>
                                <option value="consulta_general">Consulta general</option>
                                <option value="limpieza">Limpieza dental</option>
                                <option value="extraccion">Extracción dental</option>
                                <option value="cirugia_molar">Cirugía de muelas</option>
                                <option value="implante">Implante dental</option>
                                <option value="ortodoncia">Ortodoncia</option>
                                <option value="endodoncia">Endodoncia (nervio)</option>
                                <option value="blanqueamiento">Blanqueamiento dental</option>
                                <option value="protesis">Prótesis dental</option>
                                <option value="revision">Revisión / Control</option>
                                <option value="emergencia">Emergencia dental</option>
                                <option value="otro">Otro</option>
                            </select>
                            <button class="btn-registrar" id="btnAgendar"
                                onclick="mostrarModalConfirmacion()" disabled>
                            <i class="bi bi-calendar-check"></i> Agendar Procedimiento
                        </button>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            No hay horario configurado para este día.
            <a href="configurar_horarios.php">Configurar horarios</a>
        </div>
    <?php endif; ?>

</div><!-- /container-fluid -->


<!-- Modal de confirmación -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle"></i> Confirmar Procedimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Se agendará <strong>UNA SOLA CITA</strong> que ocupará
                    <span id="modalTotalSlots">0</span> slots.
                </div>
                <table class="table table-bordered">
                    <tr><th style="width:30%;">Paciente:</th>     <td id="modalPaciente"></td></tr>
                    <tr><th>Fecha:</th>        <td><?php echo date('d/m/Y', strtotime($fecha)); ?></td></tr>
                    <tr><th>Horario:</th>      <td id="modalHorario"></td></tr>
                    <tr><th>Duración:</th>     <td id="modalDuracion"></td></tr>
                    <tr><th>Procedimiento:</th><td id="modalMotivo"></td></tr>
                </table>
                <h6 class="mt-3">Slots que ocupará:</h6>
                <div id="modalListaHorarios"
                     style="max-height:150px;overflow-y:auto;background:#f8f9fa;padding:10px;border-radius:5px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="confirmarCitaCompuesta()">
                    <i class="bi bi-check-circle"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Formulario oculto que se envía al confirmar -->
<form id="formAgendarCompuesta" method="POST"
      action="procesar_agendar_compuesta.php" style="display:none;">
    <input type="hidden" name="token_csrf"     value="<?php echo $token_csrf; ?>">
    <input type="hidden" name="fecha"          value="<?php echo htmlspecialchars($fecha); ?>">
    <input type="hidden" name="id_paciente"    id="hiddenPaciente">
    <input type="hidden" name="motivo"         id="hiddenMotivo">
    <input type="hidden" name="hora_inicio"    id="hiddenHoraInicio">
    <input type="hidden" name="hora_fin"       id="hiddenHoraFin">
    <input type="hidden" name="slots_ocupados" id="hiddenSlotsOcupados">
</form>


<script>
// ============================================================
// ESTADO GLOBAL
// ============================================================
let slotsSeleccionados = []; // Slots elegidos por el usuario en esta sesión

// ============================================================
// UTILIDADES
// ============================================================

/** Ordena slots de menor a mayor por timestamp */
function ordenarSlotsPorHora(slots) {
    return slots.sort((a, b) => a.timestamp - b.timestamp);
}

/** Redirige con la nueva fecha del datepicker */
function cambiarFecha() {
    let fecha = document.getElementById('fechaSelector').value;
    window.location.href = 'agendar_cita.php?fecha=' + fecha;
}

/**
 * Verifica que todos los slots seleccionados sean consecutivos (sin huecos).
 * Duración del slot en segundos viene de PHP.
 */
function sonSlotsConsecutivos(slots) {
    if (slots.length <= 1) return true;
    let ordenados   = ordenarSlotsPorHora([...slots]);
    let duracionSeg = <?php echo $duracion_slot * 60; ?>; // PHP → JS
    for (let i = 0; i < ordenados.length - 1; i++) {
        if (ordenados[i].timestamp + duracionSeg !== ordenados[i + 1].timestamp) {
            return false; // Hay un hueco
        }
    }
    return true;
}

// ============================================================
// MANEJO DE SELECCIÓN
// ============================================================

/**
 * Alterna la selección de un slot.
 * Si disponible === false el click no hace nada (seguridad en JS además del CSS).
 */
function toggleSlot(timestamp, horaInicio, horaFin, horaDisplay, horaFinDisplay, disponible) {
    if (!disponible) return; // ocupado | completada | ausente | bloqueado → ignorar

    if (!document.getElementById('pacienteSelect').value) {
        alert('Primero selecciona un paciente');
        return;
    }

    let card  = document.getElementById('slot_' + timestamp);
    let index = slotsSeleccionados.findIndex(s => s.timestamp === timestamp);

    if (index === -1) {
        // No estaba → agregar y marcar azul
        slotsSeleccionados.push({ timestamp, horaInicio, horaFin, horaDisplay, horaFinDisplay });
        card.classList.add('seleccionado');
    } else {
        // Ya estaba → quitar y desmarcar
        slotsSeleccionados.splice(index, 1);
        card.classList.remove('seleccionado');
    }

    actualizarInterfaz();
}

/** Selecciona todos los slots con clase CSS 'disponible' */
function seleccionarTodosDisponibles() {
    if (!document.getElementById('pacienteSelect').value) {
        alert('Primero selecciona un paciente');
        return;
    }
    // Limpiar selección previa
    slotsSeleccionados.forEach(s => {
        let el = document.getElementById('slot_' + s.timestamp);
        if (el) el.classList.remove('seleccionado');
    });
    slotsSeleccionados = [];

    // Solo los verdaderamente disponibles (clase CSS 'disponible')
    document.querySelectorAll('.slot-card.disponible').forEach(card => {
        let timestamp = parseInt(card.id.replace('slot_', ''));
        slotsSeleccionados.push({
            timestamp,
            horaInicio:     card.dataset.horaInicio,
            horaFin:        card.dataset.horaFin,
            horaDisplay:    card.dataset.horaDisplay,
            horaFinDisplay: card.dataset.horaFinDisplay
        });
        card.classList.add('seleccionado');
    });

    actualizarInterfaz();
}

/** Limpia toda la selección */
function deseleccionarTodos() {
    slotsSeleccionados.forEach(s => {
        let el = document.getElementById('slot_' + s.timestamp);
        if (el) el.classList.remove('seleccionado');
    });
    slotsSeleccionados = [];
    actualizarInterfaz();
}

function limpiarSeleccion() { deseleccionarTodos(); }

/** Quita un slot específico desde el tag (X) */
function removerSlot(timestamp) {
    let index = slotsSeleccionados.findIndex(s => s.timestamp === timestamp);
    if (index !== -1) {
        let el = document.getElementById('slot_' + timestamp);
        if (el) el.classList.remove('seleccionado');
        slotsSeleccionados.splice(index, 1);
        actualizarInterfaz();
    }
}

// ============================================================
// ACTUALIZAR INTERFAZ
// ============================================================

/** Refresca contadores, tags, resumen y estado del botón Agendar */
function actualizarInterfaz() {
    let contador               = slotsSeleccionados.length;
    let resumenContainer       = document.getElementById('resumenContainer');
    let seleccionadosContainer = document.getElementById('seleccionadosContainer');
    let btnAgendar             = document.getElementById('btnAgendar');

    if (contador > 0) {
        resumenContainer.style.display       = 'block';
        seleccionadosContainer.style.display = 'block';

        slotsSeleccionados = ordenarSlotsPorHora(slotsSeleccionados);
        let consecutivos   = sonSlotsConsecutivos(slotsSeleccionados);

        // Regenerar tags
        let listaSlots = document.getElementById('listaSlotsSeleccionados');
        listaSlots.innerHTML = '';
        slotsSeleccionados.forEach(slot => {
            listaSlots.innerHTML += `
                <span class="slot-tag">
                    <i class="bi bi-clock"></i> ${slot.horaDisplay} - ${slot.horaFinDisplay}
                    <i class="bi bi-x-circle" onclick="removerSlot(${slot.timestamp})"></i>
                </span>`;
        });

        document.getElementById('contadorSeleccionados').textContent = contador;

        // Duración total
        let duracionMin  = contador * <?php echo $duracion_slot; ?>;
        let horas        = Math.floor(duracionMin / 60);
        let minutos      = duracionMin % 60;
        let duracionText = horas > 0 ? `${horas}h ${minutos}min` : `${minutos} minutos`;
        document.getElementById('duracionTotal').textContent = duracionText;

        // Resumen
        let pacienteNombre = document.getElementById('pacienteSelect').selectedOptions[0].dataset.nombre;
        document.getElementById('resumenPaciente').textContent = pacienteNombre;

        let primerSlot = slotsSeleccionados[0];
        let ultimoSlot = slotsSeleccionados[slotsSeleccionados.length - 1];
        document.getElementById('resumenHorarioCompleto').textContent =
            `${primerSlot.horaDisplay} - ${ultimoSlot.horaFinDisplay}`;
        document.getElementById('resumenDuracion').textContent = duracionText;

        // Botón: solo habilitado si los slots son consecutivos
        if (!consecutivos && contador > 1) {
            btnAgendar.disabled = true;
            btnAgendar.title    = "Los slots deben ser consecutivos";
            document.getElementById('duracionTotal').classList.add('bg-warning');
        } else {
            btnAgendar.disabled = false;
            btnAgendar.title    = "";
            document.getElementById('duracionTotal').classList.remove('bg-warning');
        }

    } else {
        resumenContainer.style.display       = 'none';
        seleccionadosContainer.style.display = 'none';
        btnAgendar.disabled = true;
    }
}

// ============================================================
// MODAL DE CONFIRMACIÓN
// ============================================================

function mostrarModalConfirmacion() {
    if (slotsSeleccionados.length === 0)                                     { alert('Selecciona al menos un slot'); return; }
    if (slotsSeleccionados.length > 1 && !sonSlotsConsecutivos(slotsSeleccionados)) { alert('Los slots deben ser consecutivos'); return; }
    if (!document.getElementById('pacienteSelect').value)                    { alert('Selecciona un paciente'); return; }
    let motivo = document.getElementById('motivoCita').value;
    if (!motivo)                                                             { alert('Especifica el procedimiento'); return; }

    slotsSeleccionados = ordenarSlotsPorHora(slotsSeleccionados);

    let duracionMin  = slotsSeleccionados.length * <?php echo $duracion_slot; ?>;
    let horas        = Math.floor(duracionMin / 60);
    let minutos      = duracionMin % 60;
    let duracionText = horas > 0 ? `${horas} hora(s) ${minutos} min` : `${minutos} minutos`;

    let pacienteNombre = document.getElementById('pacienteSelect').selectedOptions[0].dataset.nombre;
    document.getElementById('modalPaciente').textContent   = pacienteNombre;
    document.getElementById('modalTotalSlots').textContent = slotsSeleccionados.length;

    let primerSlot = slotsSeleccionados[0];
    let ultimoSlot = slotsSeleccionados[slotsSeleccionados.length - 1];
    document.getElementById('modalHorario').textContent  = `${primerSlot.horaDisplay} - ${ultimoSlot.horaFinDisplay}`;
    document.getElementById('modalDuracion').textContent = duracionText;
    document.getElementById('modalMotivo').textContent   = motivo;

    let listaHorarios = document.getElementById('modalListaHorarios');
    listaHorarios.innerHTML = '';
    slotsSeleccionados.forEach(slot => {
        listaHorarios.innerHTML += `
            <div class="d-flex justify-content-between border-bottom py-1">
                <span><i class="bi bi-clock"></i> ${slot.horaDisplay} - ${slot.horaFinDisplay}</span>
            </div>`;
    });

    new bootstrap.Modal(document.getElementById('modalConfirmacion')).show();
}

/** Rellena el formulario oculto y lo envía al servidor */
function confirmarCitaCompuesta() {
    slotsSeleccionados = ordenarSlotsPorHora(slotsSeleccionados);

    let primerSlot    = slotsSeleccionados[0];
    let ultimoSlot    = slotsSeleccionados[slotsSeleccionados.length - 1];
    let slotsOcupados = slotsSeleccionados.map(s => s.horaInicio);

    document.getElementById('hiddenPaciente').value      = document.getElementById('pacienteSelect').value;
    document.getElementById('hiddenMotivo').value        = document.getElementById('motivoCita').value;
    document.getElementById('hiddenHoraInicio').value    = primerSlot.horaInicio;
    document.getElementById('hiddenHoraFin').value       = ultimoSlot.horaFin;
    document.getElementById('hiddenSlotsOcupados').value = JSON.stringify(slotsOcupados);

    document.getElementById('formAgendarCompuesta').submit();
}

// ============================================================
// LISTENER: cambio de paciente actualiza resumen
// ============================================================
document.getElementById('pacienteSelect').addEventListener('change', function() {
    if (this.value && slotsSeleccionados.length > 0) {
        document.getElementById('resumenPaciente').textContent =
            this.selectedOptions[0].dataset.nombre;
    }
});

// ============================================================
// PRE-SELECCIÓN AUTOMÁTICA DESDE URL (?hora=HH:MM)
// Si el usuario llega desde el calendario con una hora en la URL,
// se marca automáticamente ese slot al cargar la página.
// ============================================================
(function autoSeleccionarDesdeURL() {
    // Hora normalizada "HH:MM:SS" generada en PHP; null si no viene en URL
    const horaParam = <?php echo $hora_pre_normalizada ? json_encode($hora_pre_normalizada) : 'null'; ?>;

    if (!horaParam) return; // Sin hora en URL → no hacer nada

    // Buscar el slot disponible cuyo data-hora-inicio coincida
    document.querySelectorAll('.slot-card.disponible').forEach(card => {
        if (card.dataset.horaInicio === horaParam) {
            const timestamp      = parseInt(card.id.replace('slot_', ''));
            const horaInicio     = card.dataset.horaInicio;
            const horaFin        = card.dataset.horaFin;
            const horaDisplay    = card.dataset.horaDisplay;
            const horaFinDisplay = card.dataset.horaFinDisplay;

            // Agregar y marcar
            slotsSeleccionados.push({ timestamp, horaInicio, horaFin, horaDisplay, horaFinDisplay });
            card.classList.add('seleccionado');

            // Scroll suave hasta el slot
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });

            actualizarInterfaz();
        }
    });
})();
</script>

<?php require_once '../../includes/footer.php'; ?>