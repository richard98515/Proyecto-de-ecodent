<?php
// public/paciente/reprogramar.php

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];

$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

$error = '';
$cita_original = null;
$opciones = [];

// =============================================
// VERIFICAR ID DE CITA
// =============================================
if (!isset($_GET['id_cita'])) {
    $_SESSION['error'] = 'No se especificó la cita';
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

$id_cita = (int)$_GET['id_cita'];

// Obtener cita original cancelada por el doctor
$sql = "SELECT c.*, 
               u.nombre_completo as nombre_odontologo,
               o.especialidad_principal,
               o.id_odontologo
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN odontologos o ON t.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE c.id_cita = ? AND t.id_paciente = ? AND c.estado = 'cancelada_doc'";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $id_cita, $id_paciente);
$stmt->execute();
$cita_original = $stmt->get_result()->fetch_assoc();

if (!$cita_original) {
    $_SESSION['error'] = 'No hay opciones de reprogramación para esta cita';
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// Obtener opciones de reprogramación pendientes (tabla correcta: opciones_reprogramacion_cita)
$stmt_op = $conexion->prepare("
    SELECT * FROM opciones_reprogramacion_cita 
    WHERE id_cita_original = ? AND seleccionada = FALSE
    ORDER BY fecha_propuesta ASC, hora_propuesta ASC
");
$stmt_op->bind_param("i", $id_cita);
$stmt_op->execute();
$opciones = $stmt_op->get_result()->fetch_all(MYSQLI_ASSOC);

// =============================================
// PROCESAR SELECCIÓN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seleccionar'])) {

    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {

        $id_opcion = (int)$_POST['id_opcion'];

        // Verificar que la opción es válida y pertenece a esta cita
        $stmt_ver = $conexion->prepare("
            SELECT * FROM opciones_reprogramacion_cita 
            WHERE id_opcion = ? AND id_cita_original = ? AND seleccionada = FALSE
        ");
        $stmt_ver->bind_param("ii", $id_opcion, $id_cita);
        $stmt_ver->execute();
        $opcion_elegida = $stmt_ver->get_result()->fetch_assoc();

        if (!$opcion_elegida) {
            $error = 'Opción no válida o ya no está disponible';
        } else {

            // Verificar que el slot sigue disponible (no está ocupado por otra cita)
            $stmt_check = $conexion->prepare("
    SELECT COUNT(*) as total FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    WHERE t.id_odontologo = ? 
      AND c.fecha_cita = ? 
      AND c.hora_cita = ? 
      AND c.estado IN ('programada', 'confirmada')
");
$stmt_check->bind_param("iss", $cita_original['id_odontologo'], $opcion_elegida['fecha_propuesta'], $opcion_elegida['hora_propuesta']);
            $stmt_check->execute();
            $check = $stmt_check->get_result()->fetch_assoc();

            if ($check['total'] > 0) {
                $error = 'Lo sentimos, ese horario ya no está disponible. Elige otra opción.';
            } else {

                // TRANSACCIÓN
                $conexion->begin_transaction();

                try {
                    // 1. Crear la nueva cita con hora_fin incluida
                   $stmt_nueva = $conexion->prepare("
    INSERT INTO citas 
        (id_tratamiento, fecha_cita, hora_cita, hora_fin, motivo, estado)
    VALUES (?, ?, ?, ?, ?, 'programada')
");
$stmt_nueva->bind_param("issss", $cita_original['id_tratamiento'], $opcion_elegida['fecha_propuesta'], $opcion_elegida['hora_propuesta'], $opcion_elegida['hora_propuesta_fin'], $cita_original['motivo']);
                    if (!$stmt_nueva->execute()) {
                        throw new Exception("Error al crear la nueva cita");
                    }

                    $id_nueva_cita = $conexion->insert_id;

                    // 2. Marcar opción elegida como seleccionada = TRUE
                    $stmt_acepta = $conexion->prepare("
                        UPDATE opciones_reprogramacion_cita 
                        SET seleccionada = TRUE 
                        WHERE id_opcion = ?
                    ");
                    $stmt_acepta->bind_param("i", $id_opcion);
                    if (!$stmt_acepta->execute()) {
                        throw new Exception("Error al aceptar la opción");
                    }

                    // 3. Las OTRAS opciones quedan como están (seleccionada = FALSE)
                    // No es necesario marcarlas como rechazadas, simplemente no se usan

                    // 4. Desbloquear el slot elegido en slots_bloqueados (si estaba bloqueado)
                    $stmt_desbloqueo = $conexion->prepare("
                        DELETE FROM slots_bloqueados 
                        WHERE id_odontologo = ? 
                          AND fecha = ? 
                          AND hora_inicio = ?
                    ");
                    $stmt_desbloqueo->bind_param(
                        "iss",
                        $cita_original['id_odontologo'],
                        $opcion_elegida['fecha_propuesta'],
                        $opcion_elegida['hora_propuesta']
                    );
                    $stmt_desbloqueo->execute();

                    // 5. Programar recordatorios para la nueva cita
                    $fecha_hora = $opcion_elegida['fecha_propuesta'] . ' ' . $opcion_elegida['hora_propuesta'];
                    $rec_24h = date('Y-m-d H:i:s', strtotime($fecha_hora) - (24 * 3600));
                    $rec_1h  = date('Y-m-d H:i:s', strtotime($fecha_hora) - 3600);

                    $stmt_rec = $conexion->prepare("
                        UPDATE citas 
                        SET fecha_recordatorio_24h = ?, fecha_recordatorio_1h = ?
                        WHERE id_cita = ?
                    ");
                    $stmt_rec->bind_param("ssi", $rec_24h, $rec_1h, $id_nueva_cita);
                    $stmt_rec->execute();

                    $conexion->commit();

                    $_SESSION['exito'] = '¡Cita reprogramada exitosamente para el '
                        . date('d/m/Y', strtotime($opcion_elegida['fecha_propuesta']))
                        . ' a las '
                        . date('h:i A', strtotime($opcion_elegida['hora_propuesta']))
                        . '!';

                    redirigir('/ecodent/public/paciente/mis_citas.php');

                } catch (Exception $e) {
                    $conexion->rollback();
                    $error = 'Error al reprogramar: ' . $e->getMessage();
                }
            }
        }
    }
}

$token_csrf = generarTokenCSRF();

require_once '../../includes/header.php';
?>

<style>
.opcion-card {
    border: 2px solid #198754;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
    background: #d1e7dd;
    color: #0f5132;
    display: flex;
    align-items: center;
    gap: 15px;
}
.opcion-card:hover {
    background: #a3cfbb;
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(25,135,84,0.2);
}
.opcion-card.seleccionada {
    background: #198754;
    color: white;
    border-color: #0f5132;
    box-shadow: 0 0 0 3px rgba(25,135,84,0.3);
}
.opcion-card input[type="radio"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #198754;
}
.opcion-card.seleccionada input[type="radio"] {
    accent-color: white;
}
.opcion-fecha {
    font-size: 1rem;
    font-weight: 700;
}
.opcion-hora {
    font-size: 0.9rem;
    opacity: 0.85;
}
.cita-cancelada {
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.cita-cancelada .badge-doctor {
    background-color: #dc3545;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    display: inline-block;
    margin-bottom: 10px;
}
</style>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-calendar-plus"></i> Reprogramar Cita</h1>
        <p class="lead">El odontólogo canceló tu cita. Elige una nueva fecha entre las opciones disponibles.</p>
        <hr>
    </div>
</div>

<div class="row">
    <!-- Cita original cancelada (SIEMPRE visible como cancelada_doc) -->
    <div class="col-md-5">
        <div class="card mb-3 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-x-circle"></i> Cita Cancelada por el Doctor
                </h5>
            </div>
            <div class="card-body">
                <div class="cita-cancelada">
                    <span class="badge-doctor">
                        <i class="bi bi-exclamation-triangle"></i> CANCELADA POR EL ODONTÓLOGO
                    </span>
                    
                    <?php if (!empty($cita_original['motivo_cancelacion'])): ?>
                        <div class="alert alert-warning py-2 mt-2">
                            <i class="bi bi-chat-quote"></i>
                            <strong>Motivo:</strong> <?php echo htmlspecialchars($cita_original['motivo_cancelacion']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="table table-bordered mb-0">
                    <tr>
                        <th style="width: 40%;">Odontólogo:</th>
                        <td>
                            <strong><?php echo htmlspecialchars($cita_original['nombre_odontologo']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($cita_original['especialidad_principal']); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th>Fecha original:</th>
                        <td>
                            <span class="text-decoration-line-through">
                                <?php echo date('d/m/Y', strtotime($cita_original['fecha_cita'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Hora original:</th>
                        <td>
                            <span class="text-decoration-line-through">
                                <?php echo date('h:i A', strtotime($cita_original['hora_cita']))." - " . date('h:i A', strtotime($cita_original['hora_cita']) + (40 * 60)); ?>
                                
                                
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Estado actual:</th>
                        <td>
                            <span class="badge bg-danger">Cancelada por odontólogo</span>
                            <span class="badge bg-warning text-dark">Requiere reprogramación</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Importante:</strong> Solo puedes seleccionar <u>UNA</u> opción de reprogramación.
            Una vez que confirmes, las demás opciones quedarán descartadas automáticamente.
        </div>
    </div>

    <!-- Opciones de reprogramación -->
    <div class="col-md-7">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check"></i> Opciones de Reprogramación
                    <small class="ms-2 opacity-75">(Selecciona solo una)</small>
                </h5>
            </div>
            <div class="card-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($opciones)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        No hay opciones de reprogramación disponibles en este momento.
                        Por favor, contacta al consultorio al <strong>77112233</strong>
                        para agendar una nueva cita.
                    </div>
                <?php else: ?>

                    <p class="text-muted mb-3">
                        <i class="bi bi-hand-index-thumb"></i>
                        El odontólogo te ofrece las siguientes opciones. Elige la que mejor te convenga:
                    </p>

                    <form method="POST" action="" id="formReprogramar">
                        <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">

                        <div class="mb-4">
                            <?php foreach ($opciones as $index => $opcion): ?>
                                <label class="opcion-card" id="card_<?php echo $opcion['id_opcion']; ?>"
                                       onclick="seleccionarOpcion(<?php echo $opcion['id_opcion']; ?>)">
                                    <input class="form-check-input" type="radio"
                                           name="id_opcion"
                                           id="radio_<?php echo $opcion['id_opcion']; ?>"
                                           value="<?php echo $opcion['id_opcion']; ?>"
                                           <?php echo $index === 0 ? 'required' : ''; ?>>
                                    <div style="flex: 1;">
                                        <div class="opcion-fecha">
                                            <i class="bi bi-calendar3"></i>
                                            <?php echo date('d/m/Y', strtotime($opcion['fecha_propuesta'])); ?>
                                            <span class="badge bg-light text-dark ms-2">
                                                <?php
                                                $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                                                echo $dias[date('w', strtotime($opcion['fecha_propuesta']))];
                                                ?>
                                            </span>
                                        </div>
                                        <div class="opcion-hora">
                                            <i class="bi bi-clock"></i>
                                            <?php echo date('h:i A', strtotime($opcion['hora_propuesta'])); ?>
                                            —
                                            <?php echo !empty($opcion['hora_propuesta_fin'])
                                                ? date('h:i A', strtotime($opcion['hora_propuesta_fin']))
                                                : 'N/A'; ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <hr>

                        <button type="submit" name="seleccionar" class="btn btn-success w-100"
                                id="btnConfirmar" disabled>
                            <i class="bi bi-check-circle"></i> Confirmar Nueva Cita
                        </button>

                        <a href="mis_citas.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-arrow-left"></i> Decidir después (volver a mis citas)
                        </a>
                    </form>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function seleccionarOpcion(id) {
    // Quitar selección de todas las tarjetas
    document.querySelectorAll('.opcion-card').forEach(c => c.classList.remove('seleccionada'));

    // Seleccionar la tarjeta elegida
    let card = document.getElementById('card_' + id);
    let radio = document.getElementById('radio_' + id);
    
    if (card) {
        card.classList.add('seleccionada');
    }
    
    if (radio) {
        radio.checked = true;
    }

    // Habilitar botón de confirmar
    document.getElementById('btnConfirmar').disabled = false;
}

// Si ya hay un radio seleccionado por algún motivo, habilitar el botón
document.addEventListener('DOMContentLoaded', function() {
    let radioSeleccionado = document.querySelector('input[name="id_opcion"]:checked');
    if (radioSeleccionado) {
        let id = radioSeleccionado.value;
        let card = document.getElementById('card_' + id);
        if (card) {
            card.classList.add('seleccionada');
        }
        document.getElementById('btnConfirmar').disabled = false;
    }
});

// Confirmar antes de enviar
document.getElementById('formReprogramar').addEventListener('submit', function(e) {
    let seleccionada = document.querySelector('input[name="id_opcion"]:checked');
    
    if (!seleccionada) {
        e.preventDefault();
        alert('Por favor, selecciona una opción de reprogramación antes de confirmar.');
        return;
    }
    
    if (!confirm('¿Confirmas esta nueva fecha y hora?\n\nLas otras opciones quedarán descartadas automáticamente.')) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>