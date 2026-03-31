<?php
// public/odontologo/citas_por_dia.php
// Devuelve HTML con slots de 40 min organizados por hora (cargado vía AJAX)

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

if (!estaLogueado() || !esOdontologo()) {
    echo '<div class="alert alert-danger m-3">No autorizado</div>';
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$odontologo    = $stmt->get_result()->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

if (!isset($_GET['fecha'])) {
    echo '<div class="alert alert-warning m-3">No se especificó la fecha</div>';
    exit;
}

$fecha = $_GET['fecha'];

// ──────────────────────────────────────────
// OBTENER CITAS DEL DÍA (TODAS, incluidas canceladas)
// ──────────────────────────────────────────
$stmt_citas = $conexion->prepare(
    "SELECT c.*, 
            p.id_paciente,
            u.nombre_completo AS nombre_paciente,
            u.telefono,
            u.email
     FROM citas c
     JOIN pacientes p ON c.id_paciente = p.id_paciente
     JOIN usuarios  u ON p.id_usuario  = u.id_usuario
     WHERE c.id_odontologo = ? AND c.fecha_cita = ?
     ORDER BY c.hora_cita ASC, c.estado ASC"
);
$stmt_citas->bind_param("is", $id_odontologo, $fecha);
$stmt_citas->execute();
$res_citas = $stmt_citas->get_result();

$DURACION = 40; // minutos por slot

// ──────────────────────────────────────────
// INDEXAR CITAS: AHORA SOPORTA MÚLTIPLES CITAS POR SLOT
// clave  => "HH:MM"  (hora del slot)
// valor  => array de entradas de cita
//           cada entrada: ['cita' => $row, 'es_inicio' => bool, 'total_slots' => int, 'slot_num' => int]
// ──────────────────────────────────────────
$citas_map = [];
while ($c = $res_citas->fetch_assoc()) {
    $ts_inicio = strtotime($fecha . ' ' . $c['hora_cita']);

    if (!empty($c['hora_fin'])) {
        $ts_fin_cita = strtotime($fecha . ' ' . $c['hora_fin']);
    } else {
        $ts_fin_cita = $ts_inicio + $DURACION * 60;
    }

    $minutos_duracion = round(($ts_fin_cita - $ts_inicio) / 60);
    $num_slots        = max(1, (int)ceil($minutos_duracion / $DURACION));

    $ts_slot   = $ts_inicio;
    $es_inicio = true;
    for ($i = 0; $i < $num_slots; $i++) {
        $key = date('H:i', $ts_slot);

        // Inicializar el array del slot si no existe
        if (!isset($citas_map[$key])) {
            $citas_map[$key] = [];
        }

        // Agregar esta cita al slot (permite múltiples citas por slot)
        $citas_map[$key][] = [
            'cita'        => $c,
            'es_inicio'   => $es_inicio,
            'total_slots' => $num_slots,
            'slot_num'    => $i + 1,
        ];

        $es_inicio = false;
        $ts_slot  += $DURACION * 60;
    }
}

// ──────────────────────────────────────────
// OBTENER SLOTS BLOQUEADOS DEL DÍA
// ──────────────────────────────────────────
$stmt_blq = $conexion->prepare(
    "SELECT * FROM slots_bloqueados
     WHERE id_odontologo = ? AND fecha = ?
     ORDER BY hora_inicio ASC"
);
$stmt_blq->bind_param("is", $id_odontologo, $fecha);
$stmt_blq->execute();
$res_blq = $stmt_blq->get_result();

$bloqueos_map = [];
while ($b = $res_blq->fetch_assoc()) {
    $key = substr($b['hora_inicio'], 0, 5);
    $bloqueos_map[$key] = $b;
}

// ──────────────────────────────────────────
// OBTENER HORARIO LABORAL DEL ODONTÓLOGO
// ──────────────────────────────────────────
$dia_semana_num = (int)date('N', strtotime($fecha));
$dias_map = [
    1 => 'lunes', 2 => 'martes',   3 => 'miercoles',
    4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'
];
$dia_nombre = $dias_map[$dia_semana_num];

$stmt_horario = $conexion->prepare(
    "SELECT hora_inicio, hora_fin FROM horarios_odontologos
     WHERE id_odontologo = ? AND dia_semana = ? AND activo = 1
     LIMIT 1"
);
$stmt_horario->bind_param("is", $id_odontologo, $dia_nombre);
$stmt_horario->execute();
$horario = $stmt_horario->get_result()->fetch_assoc();

$hora_inicio_lab = $horario ? $horario['hora_inicio'] : '08:00:00';
$hora_fin_lab    = $horario ? $horario['hora_fin']    : '18:00:00';

// ──────────────────────────────────────────
// GENERAR LISTA DE SLOTS DE 40 MINUTOS
// ──────────────────────────────────────────
$slots  = [];
$ts     = strtotime($fecha . ' ' . $hora_inicio_lab);
$ts_fin = strtotime($fecha . ' ' . $hora_fin_lab);

while ($ts < $ts_fin) {
    $slots[] = date('H:i', $ts);
    $ts += $DURACION * 60;
}

// ──────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────
function estadoClase($estado) {
    if ($estado == 'programada')                return 'programada';
    if ($estado == 'confirmada')                return 'confirmada';
    if ($estado == 'completada')                return 'completada';
    if ($estado == 'ausente')                   return 'ausente';
    if (strpos($estado, 'cancelada') !== false) return 'cancelada';
    return '';
}
function estadoTexto($estado) {
    $map = [
        'programada'    => 'Programada',
        'confirmada'    => 'Confirmada',
        'completada'    => 'Completada',
        'ausente'       => 'No asistió',
        'cancelada_pac' => 'Canceló paciente',
        'cancelada_doc' => 'Canceló doctor',
        'cancelada'     => 'Cancelada',
    ];
    return $map[$estado] ?? ucfirst($estado);
}
function badgeColor($estado) {
    $map = [
        'programada' => 'bg-primary',
        'confirmada' => 'bg-success',
        'completada' => 'bg-secondary',
        'ausente'    => 'bg-warning text-dark',
    ];
    return $map[$estado] ?? 'bg-danger';
}

// ──────────────────────────────────────────
// HELPER: renderizar una tarjeta de cita individual
// ──────────────────────────────────────────
function renderizarTarjetaCita($entry, $fecha, $es_la_unica) {
    $border_colors = [
        'programada' => '#0d6efd', 'confirmada' => '#198754',
        'completada' => '#6c757d', 'ausente'    => '#ffc107',
        'cancelada'  => '#dc3545',
    ];
    $bg_colors = [
        'programada' => '#e7f1ff', 'confirmada' => '#d1e7dd',
        'completada' => '#e9ecef', 'ausente'    => '#fff3cd',
        'cancelada'  => '#f8d7da',
    ];
    $DURACION = 40;

    $c  = $entry['cita'];
    $es_inicio_cita  = $entry['es_inicio'];
    $es_cont_cita    = !$entry['es_inicio'];
    $slot_num        = $entry['slot_num'];
    $total_slots_cita= $entry['total_slots'];

    $ec = estadoClase($c['estado']);
    $et = estadoTexto($c['estado']);
    $bc = badgeColor($c['estado']);
    $bc_left = $border_colors[$ec] ?? '#dee2e6';
    $bg_card = $bg_colors[$ec]     ?? '#f8f9fa';
    $es_cancelada = strpos($c['estado'], 'cancelada') !== false;

    ob_start();

    if ($es_inicio_cita) {
        // Calcular duración real
        if (!empty($c['hora_fin'])) {
            $mins_dur = round((strtotime($fecha.' '.$c['hora_fin']) - strtotime($fecha.' '.$c['hora_cita'])) / 60);
            $dur_texto = $mins_dur >= 60
                ? floor($mins_dur/60).'h '.($mins_dur%60 > 0 ? ($mins_dur%60).'min' : '')
                : $mins_dur.'min';
        } else {
            $dur_texto = '40min';
        }

        // Si es cancelada y no es la única en el slot, mostrar más compacta
        $padding_card = $es_cancelada && !$es_la_unica ? '8px 12px' : '12px 15px';
        $opacity_cancelada = $es_cancelada && !$es_la_unica ? 'opacity:0.85;' : '';
        ?>
        <div style="flex:1;border-left:5px solid <?php echo $bc_left; ?>;background:<?php echo $bg_card; ?>;padding:<?php echo $padding_card; ?>;border-radius:8px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.06);<?php echo $opacity_cancelada; ?>">
            <div style="flex:1;">
                <div style="font-weight:700;font-size:<?php echo ($es_cancelada && !$es_la_unica) ? '0.88rem' : '1rem'; ?>;color:#212529;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-person-circle"></i>
                    <?php echo htmlspecialchars($c['nombre_paciente']); ?>
                    <?php if ($total_slots_cita > 1): ?>
                        <span style="font-size:0.7rem;background:rgba(0,0,0,0.08);padding:2px 8px;border-radius:20px;font-weight:600;color:#495057;">
                            <i class="bi bi-layers me-1"></i><?php echo $total_slots_cita; ?> slots · <?php echo $dur_texto; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.78rem;color:#6c757d;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    <?php if ($c['telefono']): ?>
                        <span><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($c['telefono']); ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-chat-left-text me-1"></i><?php echo htmlspecialchars($c['motivo'] ?? 'Sin motivo'); ?></span>
                    <?php if (!empty($c['hora_fin'])): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#fff;border:1px solid #dee2e6;border-radius:20px;padding:2px 8px;font-weight:600;color:#495057;font-size:0.75rem;">
                            <i class="bi bi-clock" style="color:<?php echo $bc_left; ?>;"></i>
                            <?php echo date('g:i A', strtotime($c['hora_cita'])); ?>
                            <span style="color:#adb5bd;font-size:0.7em;">→</span>
                            <?php echo date('g:i A', strtotime($c['hora_fin'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <span class="badge <?php echo $bc; ?>" style="font-size:0.72rem;padding:4px 10px;border-radius:20px;"><?php echo $et; ?></span>
                <a href="detalle_cita.php?id_cita=<?php echo $c['id_cita']; ?>"
                   class="btn btn-sm btn-outline-primary"
                   title="Ver detalle"
                   style="padding:4px 10px;">
                    <i class="bi bi-eye"></i> Ver
                </a>
            </div>
        </div>
        <?php
    } else {
        // Slot de continuación
        ?>
        <div style="flex:1;border-left:5px dashed <?php echo $bc_left; ?>;background:<?php echo $bg_card; ?>;opacity:0.7;padding:10px 15px;border-radius:8px;display:flex;align-items:center;gap:12px;">
            <i class="bi bi-arrow-return-right" style="color:<?php echo $bc_left; ?>;font-size:1.1rem;"></i>
            <span style="font-size:0.85rem;color:#495057;font-weight:500;">
                Continuación de cita de
                <strong><?php echo htmlspecialchars($c['nombre_paciente']); ?></strong>
            </span>
            <span style="font-size:0.75rem;color:#6c757d;margin-left:auto;">
                Slot <?php echo $slot_num; ?> de <?php echo $total_slots_cita; ?>
            </span>
        </div>
        <?php
    }

    return ob_get_clean();
}

// ──────────────────────────────────────────
// CONTAR PARA EL RESUMEN
// ──────────────────────────────────────────
$total_citas   = 0;
$total_libres  = 0;

foreach ($citas_map as $slot_hora => $entries) {
    foreach ($entries as $entry) {
        // Contar solo inicios de citas activas (no canceladas)
        if ($entry['es_inicio'] && strpos($entry['cita']['estado'], 'cancelada') === false) {
            $total_citas++;
        }
    }
}
$total_bloqueos = count($bloqueos_map);
foreach ($slots as $s) {
    if (!isset($citas_map[$s]) && !isset($bloqueos_map[$s])) $total_libres++;
    elseif (isset($citas_map[$s])) {
        // Libre si TODAS las citas del slot son canceladas y no hay bloqueo
        $todas_canceladas = true;
        foreach ($citas_map[$s] as $entry) {
            if (strpos($entry['cita']['estado'], 'cancelada') === false) {
                $todas_canceladas = false;
                break;
            }
        }
        if ($todas_canceladas && !isset($bloqueos_map[$s])) $total_libres++;
    }
}
?>

<!-- ── Resumen del día ── -->
<div class="d-flex flex-wrap gap-2 px-3 py-2 border-bottom align-items-center" style="background:#f8f9fa;">
    <span class="badge bg-primary px-3 py-2">
        <i class="bi bi-person-fill me-1"></i><?php echo $total_citas; ?> cita<?php echo $total_citas != 1 ? 's' : ''; ?>
    </span>
    <?php if ($total_bloqueos > 0): ?>
        <span class="badge bg-danger px-3 py-2">
            <i class="bi bi-lock-fill me-1"></i><?php echo $total_bloqueos; ?> bloqueado<?php echo $total_bloqueos != 1 ? 's' : ''; ?>
        </span>
    <?php endif; ?>
    <span class="badge bg-light text-secondary border px-3 py-2">
        <i class="bi bi-circle me-1"></i><?php echo $total_libres; ?> libre<?php echo $total_libres != 1 ? 's' : ''; ?>
    </span>

    <div class="ms-auto d-flex align-items-center gap-1" style="font-size:.8rem;">
        <i class="bi bi-clock-history text-muted"></i>
        <span class="fw-semibold text-dark" style="font-size:.85rem;">
            <?php echo date('h:i A', strtotime($hora_inicio_lab)); ?>
        </span>
        <span class="text-muted mx-1">→</span>
        <span class="fw-semibold text-dark" style="font-size:.85rem;">
            <?php echo date('h:i A', strtotime($hora_fin_lab)); ?>
        </span>
        <span class="badge bg-light text-secondary border ms-1" style="font-size:.7rem;">
            <i class="bi bi-hourglass-split me-1"></i>Slots de 40 min
        </span>
    </div>
</div>

<?php if (empty($slots)): ?>
    <div class="alert alert-info m-3">
        <i class="bi bi-info-circle"></i> No hay horario configurado para este día.
        <a href="configurar_horarios.php" class="alert-link">Configurar horarios</a>
    </div>
<?php else: ?>

<!-- ── Timeline de slots ── -->
<div style="overflow-y:auto; max-height:560px;">
    <?php foreach ($slots as $slot_hora):
        $tiene_cita    = isset($citas_map[$slot_hora]);
        $tiene_bloqueo = isset($bloqueos_map[$slot_hora]);
        $hora_fin_slot = date('H:i', strtotime($slot_hora . ' + 40 minutes'));

        // Analizar las citas de este slot
        $entries_slot         = $tiene_cita ? $citas_map[$slot_hora] : [];
        $citas_activas        = [];  // programada, confirmada, completada, ausente
        $citas_canceladas     = [];  // cancelada_pac, cancelada_doc
        $continuaciones       = [];  // continuaciones de citas ACTIVAS únicamente

        foreach ($entries_slot as $entry) {
            $es_cancelada = strpos($entry['cita']['estado'], 'cancelada') !== false;
            if (!$entry['es_inicio']) {
                // Solo mostrar continuación si la cita es activa
                if (!$es_cancelada) {
                    $continuaciones[] = $entry;
                }
                // Las continuaciones de canceladas se ignoran silenciosamente
            } elseif ($es_cancelada) {
                $citas_canceladas[] = $entry;
            } else {
                $citas_activas[] = $entry;
            }
        }

        // Determinar el color de borde izquierdo del slot (prioridad: activa > cancelada > libre)
        $color_borde_slot = '#dee2e6';
        $bg_hora_slot     = '#f8f9fa';
        $border_colors_slot = [
            'programada' => '#0d6efd', 'confirmada' => '#198754',
            'completada' => '#6c757d', 'ausente'    => '#ffc107',
            'cancelada'  => '#dc3545',
        ];
        $bg_colors_slot = [
            'programada' => '#e7f1ff', 'confirmada' => '#d1e7dd',
            'completada' => '#e9ecef', 'ausente'    => '#fff3cd',
            'cancelada'  => '#f8d7da',
        ];

        if (!empty($citas_activas)) {
            $ec = estadoClase($citas_activas[0]['cita']['estado']);
            $color_borde_slot = $border_colors_slot[$ec] ?? '#dee2e6';
            $bg_hora_slot     = $bg_colors_slot[$ec]     ?? '#f8f9fa';
        } elseif (!empty($citas_canceladas)) {
            $color_borde_slot = '#dc3545';
            $bg_hora_slot     = '#fff5f5';
        } elseif (!empty($continuaciones)) {
            $ec = estadoClase($continuaciones[0]['cita']['estado']);
            $color_borde_slot = $border_colors_slot[$ec] ?? '#dee2e6';
            $bg_hora_slot     = $bg_colors_slot[$ec]     ?? '#f8f9fa';
        } elseif ($tiene_bloqueo) {
            $color_borde_slot = '#dc3545';
            $bg_hora_slot     = '#fff5f5';
        }

        // ¿Hay solo canceladas (slot visualmente libre para agendar)?
        $solo_canceladas = $tiene_cita && empty($citas_activas) && empty($continuaciones);

        // Calcular altura mínima del slot según cuántas citas tiene
        $num_tarjetas = count($citas_activas) + count($citas_canceladas) + count($continuaciones);
        $min_altura   = max(85, $num_tarjetas * 72);
    ?>
        <div style="display:flex;align-items:stretch;border-bottom:2px solid #e9ecef;min-height:<?php echo $min_altura; ?>px;background:#fff;transition:background 0.15s ease;">

            <!-- ── Columna de hora ── -->
            <div style="width:110px;min-width:110px;padding:10px 8px;background:<?php echo $bg_hora_slot; ?>;border-right:3px solid <?php echo $color_borde_slot; ?>;display:flex;flex-direction:column;justify-content:center;align-items:center;gap:2px;">
                <div style="font-size:1.05rem;font-weight:800;color:#212529;line-height:1;">
                    <?php echo date('g:i', strtotime($slot_hora)); ?>
                    <span style="font-size:1.05rem;font-weight:800;color:#212529;line-height:1;">
                        <?php echo date('A', strtotime($slot_hora)); ?>
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:3px;color:#2ecc71;font-size:0.7rem;margin:2px 0;">
                    <div style="width:18px;height:2px;background:#27ae60;"></div>
                    <i class="bi bi-arrow-down" style="font-size:0.65rem;color:#27ae60;"></i>
                    <div style="width:18px;height:2px;background:#27ae60;"></div>
                </div>
                <div style="font-size:1.05rem;font-weight:800;color:#212529;line-height:1;">
                    <?php echo date('g:i', strtotime($hora_fin_slot)); ?>
                    <span style="font-size:1.05rem;font-weight:800;color:#212529;line-height:1;">
                        <?php echo date('A', strtotime($hora_fin_slot)); ?>
                    </span>
                </div>
            </div>

            <!-- ── Contenido del slot ── -->
            <div style="flex:1;padding:10px 15px;display:flex;flex-direction:column;gap:8px;justify-content:center;">

                <?php
                // ── CASO 1: Solo continuaciones (sin inicio en este slot) ──
                if (empty($citas_activas) && empty($citas_canceladas) && !empty($continuaciones)):
                    foreach ($continuaciones as $entry):
                        echo renderizarTarjetaCita($entry, $fecha, true);
                    endforeach;

                // ── CASO 2: Bloqueo (sin citas de ningún tipo) ──
                elseif ($tiene_bloqueo && empty($entries_slot)):
                    $b = $bloqueos_map[$slot_hora];
                ?>
                    <div style="flex:1;border-left:5px solid #dc3545;background:#fff5f5;padding:12px 15px;border-radius:8px;display:flex;align-items:center;justify-content:space-between;gap:15px;">
                        <div style="flex:1;color:#dc3545;font-size:0.9rem;">
                            <i class="bi bi-lock-fill me-2" style="font-size:1.1rem;"></i>
                            <strong>Horario bloqueado</strong>
                            <span style="color:#6c757d;margin-left:10px;">
                                <i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($b['motivo']); ?>
                            </span>
                        </div>
                        <a href="calendario.php?desbloquear=1&fecha=<?php echo htmlspecialchars($fecha); ?>&hora=<?php echo htmlspecialchars($b['hora_inicio']); ?>"
                           class="btn btn-sm btn-outline-success"
                           onclick="return confirm('¿Desbloquear este slot? Los pacientes podrán agendarlo.')"
                           style="font-size:0.8rem;padding:5px 15px;flex-shrink:0;">
                            <i class="bi bi-unlock"></i> Desbloquear
                        </a>
                    </div>

                <?php
                // ── CASO 3: Hay citas (activas y/o canceladas) ──
                elseif (!empty($entries_slot)):

                    // Primero las citas canceladas (historial)
                    foreach ($citas_canceladas as $entry):
                        echo renderizarTarjetaCita($entry, $fecha, count($entries_slot) === 1);
                    endforeach;

                    // Separador si hay ambas (canceladas + activas)
                    if (!empty($citas_canceladas) && !empty($citas_activas)):
                    ?>
                        <div style="display:flex;align-items:center;gap:8px;font-size:0.72rem;color:#6c757d;">
                            <div style="flex:1;height:1px;background:#dee2e6;"></div>
                            <span style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:20px;padding:2px 10px;">
                                <i class="bi bi-arrow-down me-1"></i>Nuevo agendamiento
                            </span>
                            <div style="flex:1;height:1px;background:#dee2e6;"></div>
                        </div>
                    <?php
                    endif;

                    // Luego las citas activas (programada, confirmada, etc.)
                    foreach ($citas_activas as $entry):
                        echo renderizarTarjetaCita($entry, $fecha, count($entries_slot) === 1);
                    endforeach;

                    // Continuaciones de citas activas (canceladas ya filtradas en clasificación)
                    foreach ($continuaciones as $entry):
                        echo renderizarTarjetaCita($entry, $fecha, count($entries_slot) === 1);
                    endforeach;

                    // Si solo hay canceladas → mostrar botón de agendar debajo
                    if ($solo_canceladas && !$tiene_bloqueo):
                    ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:15px;padding:6px 4px 2px;">
                            <span style="color:#28a745;font-size:0.82rem;">
                                <i class="bi bi-check-circle me-1"></i>
                                <strong>Slot disponible</strong>
                            </span>
                            <a href="agendar_cita.php?fecha=<?php echo htmlspecialchars($fecha); ?>&hora=<?php echo $slot_hora; ?>"
                               class="btn btn-success btn-sm"
                               style="font-size:0.8rem;padding:5px 16px;">
                                <i class="bi bi-plus-circle"></i> Agendar cita
                            </a>
                        </div>
                    <?php
                    endif;

                // ── CASO 4: Slot completamente libre ──
                else:
                ?>
                    <div style="flex:1;display:flex;align-items:center;justify-content:space-between;gap:15px;">
                        <span style="color:#6c757d;font-size:0.9rem;">
                            <i class="bi bi-check-circle me-2" style="color:#28a745;"></i>
                            <span style="font-weight:500;">Disponible para agendar</span>
                        </span>
                        <a href="agendar_cita.php?fecha=<?php echo htmlspecialchars($fecha); ?>&hora=<?php echo $slot_hora; ?>"
                           class="btn btn-success btn-sm"
                           style="font-size:0.8rem;padding:6px 18px;">
                            <i class="bi bi-plus-circle"></i> Agendar cita
                        </a>
                    </div>
                <?php endif; ?>

            </div><!-- /contenido slot -->
        </div><!-- /slot row -->
    <?php endforeach; ?>
</div><!-- /timeline -->

<?php endif; ?>

<!-- Botón agendar al pie -->
<div class="text-center py-4 border-top" style="background:#f8f9fa;">
    <a href="agendar_cita.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="btn btn-primary px-4 py-2">
        <i class="bi bi-plus-circle me-2"></i> Agendar nueva cita para este día
    </a>
</div>