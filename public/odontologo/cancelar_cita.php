<?php
// public/odontologo/cancelar_cita.php

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/cancelaciones.php';
require_once '../../includes/slots.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$odontologo = $stmt->get_result()->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

$error = '';
$exito = '';
$cita  = null;
$opciones_generadas = [];

// =============================================
// AJAX: buscar slots por fecha
// =============================================
if (isset($_GET['ajax_fecha']) && isset($_GET['fecha_buscar'])) {
    $fecha_buscar = $_GET['fecha_buscar'];

    // Validar formato
    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_buscar);
    if (!$fecha_obj || $fecha_buscar < date('Y-m-d')) {
        echo json_encode(['error' => 'Fecha inválida o pasada']);
        exit;
    }

    $slots = generarSlotsDisponibles($id_odontologo, $fecha_buscar, $conexion);
    $resultado_ajax = [];
    foreach ($slots as $slot) {
        $resultado_ajax[] = [
            'fecha'        => $fecha_buscar,
            'hora'         => $slot['hora_inicio'],
            'hora_fin'     => $slot['hora_fin'],
            'fecha_formato'  => date('d/m/Y', strtotime($fecha_buscar)),
            'hora_formato'   => $slot['hora_inicio_formato'],
            'hora_fin_formato'=> $slot['hora_fin_formato']
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($resultado_ajax);
    exit;
}

// =============================================
// VERIFICAR ID DE CITA
// =============================================
if (!isset($_GET['id_cita'])) {
    $_SESSION['error'] = 'No se especificó la cita a cancelar';
    redirigir('/ecodent/public/odontologo/calendario.php');
}

$id_cita = (int)$_GET['id_cita'];

$sql = "SELECT c.*, 
               p.id_paciente,
               u.nombre_completo as nombre_paciente,
               u.email,
               u.telefono
        FROM citas c
        JOIN pacientes p ON c.id_paciente = p.id_paciente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE c.id_cita = ? AND c.id_odontologo = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $id_cita, $id_odontologo);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();

if (!$cita) {
    $_SESSION['error'] = 'Cita no encontrada o no autorizado';
    redirigir('/ecodent/public/odontologo/calendario.php');
}

// =============================================
// SLOTS INICIALES (próximos 5 días)
// =============================================
$fecha_inicio = date('Y-m-d', strtotime('+1 day'));
for ($i = 0; $i < 5; $i++) {
    $fecha = date('Y-m-d', strtotime("+$i day", strtotime($fecha_inicio)));
    $slots = generarSlotsDisponibles($id_odontologo, $fecha, $conexion);
    foreach ($slots as $slot) {
        $opciones_generadas[] = [
            'fecha'        => $fecha,
            'hora'         => $slot['hora_inicio'],
            'hora_fin'     => $slot['hora_fin'],
            'fecha_formato'=> date('d/m/Y', strtotime($fecha)),
            'hora_formato' => $slot['hora_inicio_formato'],
            'hora_fin_formato' => $slot['hora_fin_formato']
        ];
    }
}

// =============================================
// PROCESAR CANCELACIÓN (POST)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {

    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {

        $motivo = sanitizar($_POST['motivo']);

        // Reconstruir opciones desde POST
        $opciones_seleccionadas = [];
        if (isset($_POST['opciones_json'])) {
            $opciones_seleccionadas = json_decode($_POST['opciones_json'], true) ?? [];
        }

        if (count($opciones_seleccionadas) < 2) {
            $error = 'Debes ofrecer al menos 2 opciones de reprogramación al paciente';
        } else {
            $resultado_cancelacion = cancelarCitaOdontologo(
                $id_cita,
                $id_odontologo,
                $motivo,
                $opciones_seleccionadas,
                $conexion
            );

            if ($resultado_cancelacion['exito']) {
                $_SESSION['exito'] = $resultado_cancelacion['mensaje'];
                redirigir('/ecodent/public/odontologo/calendario.php');
            } else {
                $error = $resultado_cancelacion['error'];
            }
        }
    }
}

$token_csrf = generarTokenCSRF();

require_once '../../includes/header.php';
?>

<style>
/* (Mantén tu CSS igual) */
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
    line-height: 20px;
}
.opcion-slot .slot-fecha {
    font-size: 0.78rem;
    font-weight: 600;
    margin-bottom: 4px;
    opacity: 0.85;
}
.opcion-slot .slot-hora {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 2px;
}
.opcion-slot .slot-hora-fin {
    font-size: 0.82rem;
    font-weight: 500;
    opacity: 0.85;
    margin-bottom: 3px;
}
.opcion-slot .slot-badge {
    font-size: 0.72rem;
    background: rgba(255,255,255,0.35);
    border-radius: 20px;
    padding: 2px 8px;
    display: inline-block;
    margin-top: 4px;
}
.opcion-slot.seleccionada .slot-badge {
    background: rgba(255,255,255,0.25);
}

.buscador-fecha {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
}
.seleccionadas-preview {
    background: #e8f5e9;
    border: 2px solid #198754;
    border-radius: 10px;
    padding: 12px;
    margin-top: 10px;
    display: none;
}
.tag-opcion {
    display: inline-block;
    background: #198754;
    color: white;
    border-radius: 20px;
    padding: 4px 12px;
    margin: 3px;
    font-size: 0.85rem;
}
.tag-opcion .quitar {
    cursor: pointer;
    margin-left: 6px;
    opacity: 0.8;
}
.tag-opcion .quitar:hover { opacity: 1; }
.contador-badge { font-size: 1rem; padding: 6px 14px; }
#spinnerBusqueda { display: none; }
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
                    <tr>
                        <th>Paciente:</th>
                        <td><?php echo htmlspecialchars($cita['nombre_paciente']); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha:</th>
                        <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    </tr>
                    <tr>
                        <th>Hora:</th>
                        <td><?php echo date('h:i A', strtotime($cita['hora_cita'])); ?></td>
                    </tr>
                    <tr>
                        <th>Motivo:</th>
                        <td><?php echo htmlspecialchars($cita['motivo'] ?? 'No especificado'); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Importante:</strong> Al cancelar, el slot quedará <strong>BLOQUEADO</strong>.
            Si te recuperas antes, puedes desbloquearlo desde el calendario.
        </div>

        <!-- Formulario de cancelación -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-send"></i> Confirmar cancelación</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="formCancelacion">
                    <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                    <input type="hidden" name="opciones_json" id="opcionesJson" value="[]">

                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo de cancelación *</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3"
                                  placeholder="Ej: Emergencia personal, capacitación..." required></textarea>
                        <div class="form-text">Este motivo será enviado al paciente por email.</div>
                    </div>

                    <!-- Preview de opciones seleccionadas -->
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

                <!-- Buscador por fecha -->
                <div class="buscador-fecha">
                    <label class="form-label fw-bold">
                        <i class="bi bi-search"></i> Buscar slots en otra fecha:
                    </label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="fechaBuscar"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        <button class="btn btn-success" onclick="buscarSlotsPorFecha()">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    <div id="spinnerBusqueda" class="text-center mt-2">
                        <div class="spinner-border spinner-border-sm text-success"></div>
                        <small class="ms-2">Buscando slots disponibles...</small>
                    </div>
                </div>

                <!-- Lista de slots -->
                <div id="tituloFecha" class="fw-bold mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> Próximos días disponibles:
                </div>

                <div class="slots-grid" id="listaOpciones">
                    <?php if (empty($opciones_generadas)): ?>
                        <div class="alert alert-warning">
                            No hay slots disponibles. <a href="configurar_horarios.php">Configura más horarios</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($opciones_generadas as $index => $opcion): ?>
                            <div class="opcion-slot" id="opcion_<?php echo $index; ?>"
                                 onclick="toggleOpcion(<?php echo $index; ?>, '<?php echo $opcion['fecha']; ?>', '<?php echo $opcion['hora']; ?>', '<?php echo $opcion['fecha_formato']; ?>', '<?php echo $opcion['hora_formato']; ?>', '<?php echo $opcion['hora_fin']; ?>', '<?php echo $opcion['hora_fin_formato']; ?>')">
                                <div class="slot-fecha">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo $opcion['fecha_formato']; ?>
                                </div>
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
// Array de opciones seleccionadas
let opcionesSeleccionadas = [];
let contadorDinamico = <?php echo count($opciones_generadas); ?>;

function toggleOpcion(index, fecha, hora, fechaFormato, horaFormato, horaFin, horaFinFormato) {
    let id = 'opcion_' + index;
    let div = document.getElementById(id);
    let key = fecha + '_' + hora;

    let existe = opcionesSeleccionadas.findIndex(o => o.key === key);

    if (existe === -1) {
        // Agregar
        opcionesSeleccionadas.push({ 
            key, 
            index, 
            fecha, 
            hora, 
            fechaFormato, 
            horaFormato,
            horaFin,
            horaFinFormato
        });
        if (div) div.classList.add('seleccionada');
    } else {
        // Quitar
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
    let preview  = document.getElementById('seleccionadasPreview');
    let tags     = document.getElementById('tagSeleccionadas');
    let contador = document.getElementById('contadorSeleccionadas');
    let btn      = document.getElementById('btnConfirmar');
    let alerta   = document.getElementById('alertMinimo');

    // Actualizar hidden JSON
    let datos = opcionesSeleccionadas.map(o => ({
        fecha: o.fecha, 
        hora: o.hora,
        hora_fin: o.horaFin,
        fecha_formato: o.fechaFormato,
        hora_formato: o.horaFormato,
        hora_fin_formato: o.horaFinFormato
    }));
    document.getElementById('opcionesJson').value = JSON.stringify(datos);

    // Mostrar/ocultar preview
    if (cantidad > 0) {
        preview.style.display = 'block';
        contador.textContent = cantidad;
        tags.innerHTML = '';
        opcionesSeleccionadas.forEach(o => {
            tags.innerHTML += `
                <span class="tag-opcion">
                    <i class="bi bi-calendar2-check"></i>
                    ${o.fechaFormato} — ${o.horaFormato} (hasta ${o.horaFinFormato})
                    <span class="quitar" onclick="quitarOpcion('${o.key}')">✕</span>
                </span>`;
        });
    } else {
        preview.style.display = 'none';
    }

    // Habilitar botón si hay al menos 2
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
    let lista   = document.getElementById('listaOpciones');
    let titulo  = document.getElementById('tituloFecha');

    spinner.style.display = 'block';
    lista.innerHTML = '';

    fetch(`cancelar_cita.php?id_cita=<?php echo $id_cita; ?>&ajax_fecha=1&fecha_buscar=${fecha}`)
        .then(r => r.json())
        .then(slots => {
            spinner.style.display = 'none';

            // Formatear fecha para el título
            let partes = fecha.split('-');
            let fechaLegible = partes[2] + '/' + partes[1] + '/' + partes[0];
            titulo.innerHTML = `<i class="bi bi-calendar3"></i> Slots disponibles para <strong>${fechaLegible}</strong>:`;

            if (slots.error || slots.length === 0) {
                lista.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        No hay slots disponibles para esta fecha. Prueba con otra.
                    </div>`;
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
                div.innerHTML = `
                    <div class="slot-fecha">
                        <i class="bi bi-calendar3"></i> ${slot.fecha_formato}
                    </div>
                    <div class="slot-hora">${slot.hora_formato}</div>
                    <div class="slot-hora-fin">hasta ${slot.hora_fin_formato}</div>
                    <div class="slot-badge">Disponible</div>
                `;
                lista.appendChild(div);
            });
        })
        .catch(() => {
            spinner.style.display = 'none';
            lista.innerHTML = `<div class="alert alert-danger">Error al buscar slots. Intenta nuevamente.</div>`;
        });
}

// Toggle para slots cargados por AJAX
function toggleOpcionAjax(index, fecha, hora, fechaFormato, horaFormato, horaFin, horaFinFormato) {
    let id  = 'opcion_' + index;
    let div = document.getElementById(id);
    let key = fecha + '_' + hora;

    let existe = opcionesSeleccionadas.findIndex(o => o.key === key);

    if (existe === -1) {
        opcionesSeleccionadas.push({ 
            key, 
            index, 
            fecha, 
            hora, 
            fechaFormato, 
            horaFormato,
            horaFin,
            horaFinFormato
        });
        if (div) div.classList.add('seleccionada');
    } else {
        opcionesSeleccionadas.splice(existe, 1);
        if (div) div.classList.remove('seleccionada');
    }

    actualizarFormulario();
}

// Confirmar antes de enviar
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