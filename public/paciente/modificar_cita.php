<?php
// public/paciente/modificar_cita.php
// Modificar cita existente - Con control de modificaciones (TRIGGER)

require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/slots.php';

requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];

// ACEPTAR AMBOS PARÁMETROS: id O id_cita
$id_cita = 0;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_cita = (int)$_GET['id'];
} elseif (isset($_GET['id_cita']) && is_numeric($_GET['id_cita'])) {
    $id_cita = (int)$_GET['id_cita'];
}

if ($id_cita <= 0) {
    $_SESSION['error'] = "ID de cita no válido.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// Obtener datos de la cita
$stmt = $conexion->prepare("
    SELECT c.*, p.id_paciente, u.nombre_completo as odontologo_nombre,
           c.puede_modificar, c.cambios_realizados, c.limite_cambios,
           o.id_odontologo
    FROM citas c
    JOIN pacientes p ON c.id_paciente = p.id_paciente
    JOIN odontologos o ON c.id_odontologo = o.id_odontologo
    JOIN usuarios u ON o.id_usuario = u.id_usuario
    WHERE c.id_cita = ? AND p.id_usuario = ?
");
$stmt->bind_param("ii", $id_cita, $id_usuario);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();

if (!$cita) {
    $_SESSION['error'] = "Cita no encontrada.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// VERIFICAR SI PUEDE MODIFICAR (controlado por trigger)
if (!$cita['puede_modificar']) {
    $_SESSION['error'] = "⚠️ Has alcanzado el límite de {$cita['limite_cambios']} modificaciones. No puedes modificar esta cita.";
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

$error = '';

// Procesar modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar token CSRF
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {
        
        $nueva_fecha = $_POST['fecha'];
        $nueva_hora = $_POST['hora'];
        $nueva_hora_fin = $_POST['hora_fin'];
        $motivo = sanitizar($_POST['motivo']);
        
        // Validaciones
        if (strtotime($nueva_fecha) < strtotime(date('Y-m-d'))) {
            $error = "No puedes seleccionar fechas pasadas.";
        }
        elseif (empty($nueva_hora)) {
            $error = "Debes seleccionar un horario disponible.";
        }
       elseif (!slotEstaDisponible($cita['id_odontologo'], $nueva_fecha, $nueva_hora, $conexion, $id_cita)) {
            $error = "El horario seleccionado ya no está disponible. Por favor elige otro.";
        }
        else {
            // SOLO ESTA CONSULTA - EL TRIGGER HACE TODO LO DEMÁS
            $sql = "UPDATE citas 
                    SET fecha_cita = ?, hora_cita = ?, hora_fin = ?, motivo = ?
                    WHERE id_cita = ? AND id_paciente = ?";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ssssii", $nueva_fecha, $nueva_hora, $nueva_hora_fin, $motivo, $id_cita, $cita['id_paciente']);
            
            if ($stmt->execute()) {
                // ✅ EL TRIGGER YA HIZO:
                // 1. Incrementó cambios_realizados
                // 2. Si llegó a 3, deshabilitó más modificaciones
                // 3. Registró en historial_modificaciones_citas
                
                $modificaciones_restantes = $cita['limite_cambios'] - ($cita['cambios_realizados'] + 1);
                
                if ($modificaciones_restantes > 0) {
                    $_SESSION['exito'] = "✅ Cita modificada exitosamente. Te quedan {$modificaciones_restantes} modificación(es) disponible(s).";
                } else {
                    $_SESSION['exito'] = "✅ Cita modificada exitosamente. ⚠️ Esta fue tu ÚLTIMA modificación permitida.";
                }
                
                redirigir('/ecodent/public/paciente/mis_citas.php');
            } else {
                $error = "Error al modificar la cita. Intenta nuevamente.";
            }
        }
    }
}

// Calcular modificaciones restantes
$modificaciones_restantes = $cita['limite_cambios'] - $cita['cambios_realizados'];

require_once '../../includes/header.php';
?>

<style>
.slot-disponible {
    cursor: pointer;
    padding: 12px;
    border: 2px solid #28a745;
    border-radius: 10px;
    transition: all 0.2s;
    text-align: center;
    margin-bottom: 10px;
}
.slot-disponible:hover {
    background-color: #d4edda;
    transform: scale(1.02);
}
.slot-disponible.seleccionado {
    background-color: #28a745;
    color: white;
    border-color: #1e7e34;
}
.slot-disponible.seleccionado .badge {
    background-color: white !important;
    color: #28a745 !important;
}
.slot-disponible .badge {
    margin-top: 5px;
}
</style>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-pencil-square text-primary"></i> Modificar Cita</h1>
        <p class="lead">Cambia la fecha u hora de tu cita.</p>
        <hr>
    </div>
</div>

<!-- ALERTA DE MODIFICACIONES RESTANTES -->
<div class="alert alert-info mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-info-circle-fill fs-3 me-3"></i>
        <div>
            <strong>📋 Información de modificaciones:</strong><br>
            Has modificado esta cita <strong><?php echo $cita['cambios_realizados']; ?></strong> 
            de <strong><?php echo $cita['limite_cambios']; ?></strong> veces permitidas.
            
            <?php if ($modificaciones_restantes == 1): ?>
                <span class="badge bg-warning ms-2">⚠️ ¡ÚLTIMA modificación disponible!</span>
            <?php elseif ($modificaciones_restantes == 0): ?>
                <span class="badge bg-danger ms-2">❌ No puedes modificar más</span>
            <?php else: ?>
                <span class="badge bg-success ms-2">✅ Te quedan <?php echo $modificaciones_restantes; ?> modificación(es)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Datos actuales de la cita</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td width="40%"><strong>📅 Fecha:</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>⏰ Hora:</strong></td>
                        <td><?php echo date('h:i A', strtotime($cita['hora_cita'])); ?> - <?php echo date('h:i A', strtotime($cita['hora_fin'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>👨‍⚕️ Odontólogo:</strong></td>
                        <td>Dr(a). <?php echo $cita['odontologo_nombre']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>📝 Motivo:</strong></td>
                        <td><?php echo $cita['motivo'] ?: 'No especificado'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> ¿Sabías que...?</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>✅ Puedes modificar tu cita hasta <strong>3 veces</strong></li>
                    <li>✅ Las citas duran <strong>40 minutos</strong></li>
                    <li>✅ Recibirás recordatorios por email</li>
                    <li>✅ Si cancelas con anticipación, otro paciente puede usar tu horario</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-plus"></i> Seleccionar nuevo horario</h5>
            </div>
            <div class="card-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="formModificar">
                    <input type="hidden" name="token_csrf" value="<?php echo generarTokenCSRF(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">📅 Nueva fecha:</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">⏰ Nuevo horario:</label>
                        <div id="slots-container" class="row mt-2">
                            <div class="col-12 text-center text-muted py-4">
                                <i class="bi bi-calendar2-week fs-1"></i><br>
                                Selecciona una fecha para ver los horarios disponibles
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="hora" id="hora_seleccionada">
                    <input type="hidden" name="hora_fin" id="hora_fin_seleccionada">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">📝 Motivo (opcional):</label>
                        <textarea class="form-control" name="motivo" rows="2" 
                                  placeholder="Ej: Cambio de horario por trabajo, emergencia..."><?php echo htmlspecialchars($cita['motivo'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Guardar cambios
                        </button>
                        <a href="mis_citas.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Cargar slots cuando cambie la fecha
document.getElementById('fecha').addEventListener('change', function() {
    let fecha = this.value;
    let odontologo_id = <?php echo $cita['id_odontologo']; ?>;
    
    if (fecha) {
        fetch(`../ajax/slots_disponibles.php?odontologo=${odontologo_id}&fecha=${fecha}&excluir_cita=<?php echo $id_cita; ?>`)
            .then(response => response.json())
            .then(data => {
                let container = document.getElementById('slots-container');
                if (data.length > 0) {
                    container.innerHTML = '';
                    data.forEach(slot => {
                        container.innerHTML += `
                            <div class="col-md-6">
                                <div class="slot-disponible" onclick="seleccionarSlot('${slot.hora_inicio}', '${slot.hora_fin}', this)">
                                    <strong>${slot.hora_inicio_formato}</strong>
                                    <br>
                                    <small>a ${slot.hora_fin_formato}</small>
                                    <br>
                                    <span class="badge bg-success">Disponible</span>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    container.innerHTML = `
                        <div class="col-12 text-center text-muted py-4">
                            <i class="bi bi-calendar-x fs-1"></i><br>
                            No hay horarios disponibles para esta fecha
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('slots-container').innerHTML = `
                    <div class="col-12 text-center text-danger py-4">
                        <i class="bi bi-exclamation-triangle fs-1"></i><br>
                        Error al cargar los horarios
                    </div>
                `;
            });
    }
});

let slotSeleccionado = null;

function seleccionarSlot(hora, horaFin, elemento) {
    // Quitar selección anterior
    if (slotSeleccionado) {
        slotSeleccionado.classList.remove('seleccionado');
    }
    
    // Marcar nuevo seleccionado
    elemento.classList.add('seleccionado');
    slotSeleccionado = elemento;
    
    // Guardar valores
    document.getElementById('hora_seleccionada').value = hora;
    document.getElementById('hora_fin_seleccionada').value = horaFin;
}

// Cargar slots del día actual al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    let fechaInput = document.getElementById('fecha');
    if (fechaInput) {
        fechaInput.dispatchEvent(new Event('change'));
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>