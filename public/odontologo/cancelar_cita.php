<?php
// public/odontologo/cancelar_cita.php

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/cancelaciones.php';
require_once '../../includes/slots.php';
date_default_timezone_set('America/La_Paz');

// Verificar permisos (admin u odontólogo)
$es_admin = esAdmin();
$es_odontologo = esOdontologo();

if (!estaLogueado() || (!$es_admin && !$es_odontologo)) {
    redirigir('/ecodent/public/login.php');
}

$id_usuario = $_SESSION['id_usuario'];
$id_odontologo = null;

// Obtener ID del odontólogo (si es odontólogo)
if ($es_odontologo) {
    $stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $odontologo_data = $stmt->get_result()->fetch_assoc();
    $id_odontologo = $odontologo_data['id_odontologo'];
}

// =============================================
// VERIFICAR ID DE CITA
// =============================================
if (!isset($_GET['id_cita'])) {
    $_SESSION['error'] = 'No se especificó la cita a cancelar';
    redirigir('/ecodent/public/odontologo/calendario.php');
}

$id_cita = (int)$_GET['id_cita'];

// =============================================
// OBTENER DATOS DE LA CITA (CORREGIDO: usando tratamientos)
// =============================================
$sql = "SELECT c.*, 
               t.id_paciente,
               t.id_odontologo,
               u.nombre_completo as nombre_paciente,
               u.email,
               u.telefono
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE c.id_cita = ?";

if ($es_odontologo && $id_odontologo) {
    $sql .= " AND t.id_odontologo = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id_cita, $id_odontologo);
} else {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_cita);
}

$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();

if (!$cita) {
    $_SESSION['error'] = 'Cita no encontrada o no autorizado';
    redirigir('/ecodent/public/odontologo/calendario.php');
}

// Guardar el id_odontologo de la cita (puede ser diferente al del usuario si es admin)
$id_odontologo_cita = $cita['id_odontologo'];

// =============================================
// AJAX: buscar slots por fecha
// =============================================
if (isset($_GET['ajax_fecha']) && isset($_GET['fecha_buscar'])) {
    $fecha_buscar = $_GET['fecha_buscar'];
    $id_odonto_ajax = isset($_GET['id_odontologo']) ? (int)$_GET['id_odontologo'] : $id_odontologo_cita;

    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_buscar);
    if (!$fecha_obj || $fecha_buscar < date('Y-m-d')) {
        echo json_encode(['error' => 'Fecha inválida o pasada']);
        exit;
    }

    $slots = generarSlotsDisponibles($id_odonto_ajax, $fecha_buscar, $conexion);
    $resultado_ajax = [];
    foreach ($slots as $slot) {
        $resultado_ajax[] = [
            'fecha'            => $fecha_buscar,
            'hora'             => $slot['hora_inicio'],
            'hora_fin'         => $slot['hora_fin'],
            'fecha_formato'    => date('d/m/Y', strtotime($fecha_buscar)),
            'hora_formato'     => $slot['hora_inicio_formato'],
            'hora_fin_formato' => $slot['hora_fin_formato']
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($resultado_ajax);
    exit;
}

// =============================================
// SLOTS INICIALES (próximos 7 días)
// =============================================
$opciones_generadas = [];
$fecha_inicio = date('Y-m-d', strtotime('+1 day'));
for ($i = 0; $i < 7; $i++) {
    $fecha = date('Y-m-d', strtotime("+$i day", strtotime($fecha_inicio)));
    $slots = generarSlotsDisponibles($id_odontologo_cita, $fecha, $conexion);
    foreach ($slots as $slot) {
        $opciones_generadas[] = [
            'fecha'            => $fecha,
            'hora'             => $slot['hora_inicio'],
            'hora_fin'         => $slot['hora_fin'],
            'fecha_formato'    => date('d/m/Y', strtotime($fecha)),
            'hora_formato'     => $slot['hora_inicio_formato'],
            'hora_fin_formato' => $slot['hora_fin_formato']
        ];
    }
}

// =============================================
// PROCESAR CANCELACIÓN (POST)
// =============================================
$whatsapp_link = null;
$email_enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {

    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {
        $motivo = sanitizar($_POST['motivo']);

        $opciones_seleccionadas = [];
        if (isset($_POST['opciones_json'])) {
            $opciones_seleccionadas = json_decode($_POST['opciones_json'], true) ?? [];
        }

        if (count($opciones_seleccionadas) < 2) {
            $error = 'Debes ofrecer al menos 2 opciones de reprogramación al paciente';
        } else {
            $conexion->begin_transaction();
            
            try {
                // 1. Actualizar estado de la cita
                $nuevo_estado = 'cancelada_doc';
                $stmt = $conexion->prepare("
                    UPDATE citas 
                    SET estado = ?, 
                        cancelado_por = ?, 
                        fecha_cancelacion = NOW(), 
                        motivo_cancelacion = ?
                    WHERE id_cita = ?
                ");
                $cancelado_por = $es_admin ? 8 : $id_odontologo; // 8 = admin por defecto
                $stmt->bind_param("sisi", $nuevo_estado, $cancelado_por, $motivo, $id_cita);
                $stmt->execute();
                
                // 2. Bloquear el slot (usando el id_odontologo de la cita)
                $hora_fin = date('H:i:s', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']) + (40 * 60));
                $stmt_bloq = $conexion->prepare("
                    INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $motivo_bloqueo = "Cancelación por odontólogo: " . $motivo;
                $stmt_bloq->bind_param("issss", $id_odontologo_cita, $cita['fecha_cita'], $cita['hora_cita'], $hora_fin, $motivo_bloqueo);
                $stmt_bloq->execute();
                
                // 3. Guardar opciones de reprogramación
                $stmt_opcion = $conexion->prepare("
                    INSERT INTO opciones_reprogramacion_cita 
                    (id_cita_original, fecha_propuesta, hora_propuesta, id_odontologo)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($opciones_seleccionadas as $opcion) {
                    $stmt_opcion->bind_param("issi", $id_cita, $opcion['fecha'], $opcion['hora'], $id_odontologo_cita);
                    $stmt_opcion->execute();
                }
                
                // 4. Construir mensaje de notificación
                $mensaje_notificacion = "🦷 *ECO-DENT - Cita Cancelada*\n\n";
                $mensaje_notificacion .= "Hola " . $cita['nombre_paciente'] . ", lamentamos informarte que tu cita del *" . date('d/m/Y', strtotime($cita['fecha_cita'])) . "* a las *" . date('h:i A', strtotime($cita['hora_cita'])) . "* ha sido cancelada.\n\n";
                $mensaje_notificacion .= "📋 *Motivo:* " . $motivo . "\n\n";
                $mensaje_notificacion .= "📅 *Opciones de reprogramación disponibles:*\n";
                
                foreach ($opciones_seleccionadas as $index => $opcion) {
                    $num = $index + 1;
                    $fecha_mostrar = date('d/m/Y', strtotime($opcion['fecha']));
                    $hora_mostrar = date('h:i A', strtotime($opcion['hora']));
                    $mensaje_notificacion .= "  $num. $fecha_mostrar a las $hora_mostrar\n";
                }
                
                $mensaje_notificacion .= "\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233";
                
                // 5. Guardar mensaje de EMAIL en mensajes_pendientes
                $stmt_email = $conexion->prepare("
                    INSERT INTO mensajes_pendientes (id_usuario, id_cita, tipo, canal, mensaje, email_destino, telefono_destino, enviado)
                    VALUES (?, ?, 'cancelacion_doctor', 'email', ?, ?, ?, 0)
                ");
                $stmt_email->bind_param("iisss", $cita['id_paciente'], $id_cita, $mensaje_notificacion, $cita['email'], $cita['telefono']);
                $stmt_email->execute();
                
                // 6. Guardar mensaje de WHATSAPP en mensajes_pendientes
                $stmt_whatsapp = $conexion->prepare("
                    INSERT INTO mensajes_pendientes (id_usuario, id_cita, tipo, canal, mensaje, email_destino, telefono_destino, enviado)
                    VALUES (?, ?, 'cancelacion_doctor', 'whatsapp', ?, ?, ?, 0)
                ");
                $stmt_whatsapp->bind_param("iisss", $cita['id_paciente'], $id_cita, $mensaje_notificacion, $cita['email'], $cita['telefono']);
                $stmt_whatsapp->execute();
                
                // 7. Generar enlace de WhatsApp para mostrar al doctor
                $telefono_limpio = preg_replace('/[^0-9]/', '', $cita['telefono']);
                if (substr($telefono_limpio, 0, 1) == '0') {
                    $telefono_limpio = '591' . substr($telefono_limpio, 1);
                } elseif (substr($telefono_limpio, 0, 2) != '59') {
                    $telefono_limpio = '591' . $telefono_limpio;
                }
                $mensaje_wa_codificado = urlencode($mensaje_notificacion);
                $whatsapp_link = "https://wa.me/{$telefono_limpio}?text={$mensaje_wa_codificado}";
                
                $conexion->commit();
                
                $_SESSION['exito'] = "✅ Cita cancelada exitosamente.";
                $_SESSION['whatsapp_link'] = $whatsapp_link;
                $_SESSION['whatsapp_telefono'] = $cita['telefono'];
                $_SESSION['whatsapp_mensaje'] = $mensaje_notificacion;
                
                redirigir('/ecodent/public/odontologo/calendario.php?whatsapp_pendiente=1');
                
            } catch (Exception $e) {
                $conexion->rollback();
                $error = "Error al cancelar: " . $e->getMessage();
            }
        }
    }
}

$token_csrf = generarTokenCSRF();

require_once '../../includes/header.php';
?>

<style>
/* (ESTILOS IGUALES QUE ANTES) */
.slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
    padding: 10px 5px;
}
.slots-grid::-webkit-scrollbar { width: 6px; }
.slots-grid::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 3px; }

.opcion-slot {
    border: 2px solid #198754;
    border-radius: 10px;
    padding: 14px 10px;
    cursor: pointer;
    transition: all 0.25s;
    background: #d1e7dd;
    color: #0f5132;
    text-align: center;
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    user-select: none;
}
.opcion-slot:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(25,135,84,0.25);
    border-color: #0f5132;
}
.opcion-slot.seleccionada {
    background: #198754;
    border-color: #0f5132;
    color: white;
    transform: scale(1.04);
    box-shadow: 0 0 0 3px rgba(25,135,84,0.3);
}
.opcion-slot.seleccionada::after {
    content: "✓";
    position: absolute;
    top: 6px;
    right: 8px;
    background: white;
    color: #198754;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}
.opcion-slot .slot-fecha { font-size: 0.78rem; font-weight: 600; margin-bottom: 4px; opacity: 0.85; }
.opcion-slot .slot-hora { font-size: 1.25rem; font-weight: 700; margin-bottom: 2px; }
.opcion-slot .slot-hora-fin { font-size: 0.82rem; font-weight: 500; opacity: 0.85; }
.opcion-slot .slot-badge { font-size: 0.72rem; background: rgba(255,255,255,0.35); border-radius: 20px; padding: 2px 8px; display: inline-block; margin-top: 4px; }
.buscador-fecha { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; border: 1px solid #dee2e6; }
.seleccionadas-preview { background: #e8f5e9; border: 2px solid #198754; border-radius: 10px; padding: 12px; margin-top: 10px; display: none; }
.tag-opcion { display: inline-block; background: #198754; color: white; border-radius: 20px; padding: 4px 12px; margin: 3px; font-size: 0.85rem; }
.tag-opcion .quitar { cursor: pointer; margin-left: 6px; opacity: 0.8; }
.tag-opcion .quitar:hover { opacity: 1; }
.contador-badge { font-size: 1rem; padding: 6px 14px; }
.whatsapp-card { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; }
.whatsapp-card .btn-whatsapp { background: white; color: #25D366; border: none; }
.whatsapp-card .btn-whatsapp:hover { background: #f0f0f0; transform: scale(1.02); }
</style>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-x-circle text-danger"></i> Cancelar Cita</h1>
        <p class="lead">El slot quedará <strong>bloqueado</strong> automáticamente al cancelar.</p>
        <hr>
    </div>
</div>

<div class="row">
    <!-- Columna izquierda: datos de la cita -->
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-x"></i> Cita a cancelar</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tr><th>Paciente:</th><td><?php echo htmlspecialchars($cita['nombre_paciente']); ?></td>
                    <tr><th>Fecha:</th><td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    <tr><th>Hora:</th><td><?php echo date('h:i A', strtotime($cita['hora_cita'])); ?></td>
                    <tr><th>Motivo:</th><td><?php echo htmlspecialchars($cita['motivo'] ?? 'No especificado'); ?></td>
                </table>
            </div>
        </div>

        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Importante:</strong> Al cancelar, el slot quedará <strong>BLOQUEADO</strong>.
            Se generarán opciones de reprogramación para el paciente.
        </div>

        <!-- Formulario de cancelación -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-send"></i> Confirmar cancelación</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="formCancelacion">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    <input type="hidden" name="opciones_json" id="opcionesJson" value="[]">

                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo de cancelación *</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3"
                                  placeholder="Ej: Emergencia personal, capacitación..." required></textarea>
                        <div class="form-text">Este motivo será enviado al paciente por email y WhatsApp.</div>
                    </div>

                    <div class="seleccionadas-preview" id="seleccionadasPreview">
                        <strong><i class="bi bi-check-circle-fill text-success"></i>
                            Opciones seleccionadas: <span id="contadorSeleccionadas" class="badge bg-success contador-badge">0</span>
                        </strong>
                        <div id="tagSeleccionadas" class="mt-2"></div>
                    </div>

                    <div class="alert alert-info mt-3 mb-3" id="alertMinimo" style="display:none;">
                        <i class="bi bi-info-circle"></i> Selecciona al menos <strong>2 opciones</strong> de reprogramación.
                    </div>

                    <button type="submit" name="cancelar" class="btn btn-danger w-100" id="btnConfirmar" disabled>
                        <i class="bi bi-x-circle"></i> Confirmar Cancelación
                    </button>

                    <a href="calendario.php" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i> Volver al Calendario
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Columna derecha: buscador de slots -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check"></i> Opciones de reprogramación
                    <small class="ms-2 opacity-75">(selecciona 2 o 3)</small>
                </h5>
            </div>
            <div class="card-body">
                <div class="buscador-fecha">
                    <label class="form-label fw-bold"><i class="bi bi-search"></i> Buscar slots en otra fecha:</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="fechaBuscar"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        <button class="btn btn-success" onclick="buscarSlotsPorFecha()">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    <div id="spinnerBusqueda" class="text-center mt-2" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-success"></div>
                        <small class="ms-2">Buscando slots disponibles...</small>
                    </div>
                </div>

                <div id="tituloFecha" class="fw-bold mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> Próximos días disponibles:
                </div>

                <div class="slots-grid" id="listaOpciones">
                    <?php if (empty($opciones_generadas)): ?>
                        <div class="alert alert-warning">
                            No hay slots disponibles en los próximos 7 días. <a href="configurar_horarios.php">Configura más horarios</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($opciones_generadas as $index => $opcion): ?>
                            <div class="opcion-slot" id="opcion_<?php echo $index; ?>"
                                 onclick="toggleOpcion(<?php echo $index; ?>, '<?php echo $opcion['fecha']; ?>', '<?php echo $opcion['hora']; ?>', '<?php echo $opcion['fecha_formato']; ?>', '<?php echo $opcion['hora_formato']; ?>', '<?php echo $opcion['hora_fin']; ?>', '<?php echo $opcion['hora_fin_formato']; ?>')">
                                <div class="slot-fecha"><i class="bi bi-calendar3"></i> <?php echo $opcion['fecha_formato']; ?></div>
                                <div class="slot-hora"><?php echo $opcion['hora_formato']; ?></div>
                                <div class="slot-hora-fin">hasta <?php echo $opcion['hora_fin_formato']; ?></div>
                                <div class="slot-badge">Disponible</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let opcionesSeleccionadas = [];

function toggleOpcion(index, fecha, hora, fechaFormato, horaFormato, horaFin, horaFinFormato) {
    let id = 'opcion_' + index;
    let div = document.getElementById(id);
    let key = fecha + '_' + hora;

    let existe = opcionesSeleccionadas.findIndex(o => o.key === key);

    if (existe === -1) {
        opcionesSeleccionadas.push({ key, index, fecha, hora, fechaFormato, horaFormato, horaFin, horaFinFormato });
        if (div) div.classList.add('seleccionada');
    } else {
        opcionesSeleccionadas.splice(existe, 1);
        if (div) div.classList.remove('seleccionada');
    }
    actualizarFormulario();
}

function quitarOpcion(key) {
    let existe = opcionesSeleccionadas.findIndex(o => o.key === key);
    if (existe !== -1) {
        let opcion = opcionesSeleccionadas[existe];
        let div = document.getElementById('opcion_' + opcion.index);
        if (div) div.classList.remove('seleccionada');
        opcionesSeleccionadas.splice(existe, 1);
        actualizarFormulario();
    }
}

function actualizarFormulario() {
    let cantidad = opcionesSeleccionadas.length;
    let preview = document.getElementById('seleccionadasPreview');
    let tags = document.getElementById('tagSeleccionadas');
    let contador = document.getElementById('contadorSeleccionadas');
    let btn = document.getElementById('btnConfirmar');
    let alerta = document.getElementById('alertMinimo');

    let datos = opcionesSeleccionadas.map(o => ({ fecha: o.fecha, hora: o.hora, hora_fin: o.horaFin }));
    document.getElementById('opcionesJson').value = JSON.stringify(datos);

    if (cantidad > 0) {
        preview.style.display = 'block';
        contador.textContent = cantidad;
        tags.innerHTML = '';
        opcionesSeleccionadas.forEach(o => {
            tags.innerHTML += `<span class="tag-opcion"><i class="bi bi-calendar2-check"></i> ${o.fechaFormato} — ${o.horaFormato} <span class="quitar" onclick="quitarOpcion('${o.key}')">✕</span></span>`;
        });
    } else {
        preview.style.display = 'none';
    }

    if (cantidad >= 2) {
        btn.disabled = false;
        alerta.style.display = 'none';
    } else {
        btn.disabled = true;
        alerta.style.display = cantidad === 1 ? 'block' : 'none';
    }
}

function buscarSlotsPorFecha() {
    let fecha = document.getElementById('fechaBuscar').value;
    if (!fecha) { alert('Selecciona una fecha'); return; }

    let spinner = document.getElementById('spinnerBusqueda');
    let lista = document.getElementById('listaOpciones');
    let titulo = document.getElementById('tituloFecha');

    spinner.style.display = 'block';
    lista.innerHTML = '';

    fetch(`cancelar_cita.php?id_cita=<?php echo $id_cita; ?>&ajax_fecha=1&fecha_buscar=${fecha}&id_odontologo=<?php echo $id_odontologo_cita; ?>`)
        .then(r => r.json())
        .then(slots => {
            spinner.style.display = 'none';
            let partes = fecha.split('-');
            let fechaLegible = partes[2] + '/' + partes[1] + '/' + partes[0];
            titulo.innerHTML = `<i class="bi bi-calendar3"></i> Slots disponibles para <strong>${fechaLegible}</strong>:`;

            if (slots.error || slots.length === 0) {
                lista.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No hay slots disponibles para esta fecha.</div>`;
                return;
            }

            lista.className = 'slots-grid';
            slots.forEach((slot, i) => {
                let idx = 'ajax_' + fecha.replace(/-/g, '') + '_' + i;
                let key = slot.fecha + '_' + slot.hora;
                let yaSeleccionada = opcionesSeleccionadas.findIndex(o => o.key === key) !== -1;

                let div = document.createElement('div');
                div.className = 'opcion-slot' + (yaSeleccionada ? ' seleccionada' : '');
                div.id = 'opcion_' + idx;
                div.setAttribute('onclick', `toggleOpcionAjax('${idx}', '${slot.fecha}', '${slot.hora}', '${slot.fecha_formato}', '${slot.hora_formato}', '${slot.hora_fin}', '${slot.hora_fin_formato}')`);
                div.innerHTML = `<div class="slot-fecha"><i class="bi bi-calendar3"></i> ${slot.fecha_formato}</div>
                                <div class="slot-hora">${slot.hora_formato}</div>
                                <div class="slot-hora-fin">hasta ${slot.hora_fin_formato}</div>
                                <div class="slot-badge">Disponible</div>`;
                lista.appendChild(div);
            });
        })
        .catch(() => {
            spinner.style.display = 'none';
            lista.innerHTML = `<div class="alert alert-danger">Error al buscar slots. Intenta nuevamente.</div>`;
        });
}

function toggleOpcionAjax(index, fecha, hora, fechaFormato, horaFormato, horaFin, horaFinFormato) {
    let div = document.getElementById('opcion_' + index);
    let key = fecha + '_' + hora;
    let existe = opcionesSeleccionadas.findIndex(o => o.key === key);

    if (existe === -1) {
        opcionesSeleccionadas.push({ key, index, fecha, hora, fechaFormato, horaFormato, horaFin, horaFinFormato });
        if (div) div.classList.add('seleccionada');
    } else {
        opcionesSeleccionadas.splice(existe, 1);
        if (div) div.classList.remove('seleccionada');
    }
    actualizarFormulario();
}

document.getElementById('formCancelacion').addEventListener('submit', function(e) {
    if (opcionesSeleccionadas.length < 2) {
        e.preventDefault();
        alert('Debes seleccionar al menos 2 opciones de reprogramación');
        return;
    }
    if (!confirm('¿Estás seguro de cancelar esta cita?\n\nEl slot quedará BLOQUEADO y el paciente recibirá las opciones de reprogramación.')) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>