<?php
// public/paciente/agendar.php
// Página para que los pacientes agenden citas viendo los slots disponibles
// CON VALIDACIÓN DE ESTADO DE CUENTA

// =============================================
// PROCESAMIENTO PRIMERO (ANTES DE CUALQUIER HTML)
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/slots.php';

// Verificar que solo pacientes puedan acceder
requerirRol('paciente');

// Obtener ID del paciente (desde la sesión)
$id_usuario = $_SESSION['id_usuario'];

// Obtener el id_paciente y estado de cuenta
$stmt = $conexion->prepare("
    SELECT p.id_paciente, p.estado_cuenta, p.puede_agendar, p.limite_citas_simultaneas,
           p.ausencias_sin_aviso, p.llegadas_tarde, u.nombre_completo
    FROM pacientes p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_usuario = ?
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$paciente = $resultado->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

// =============================================
// VERIFICAR SI EL PACIENTE PUEDE AGENDAR
// =============================================

// 1. Verificar si está bloqueado
if (!$paciente['puede_agendar'] || $paciente['estado_cuenta'] == 'bloqueada') {
    $_SESSION['error'] = "❌ Tu cuenta está BLOQUEADA. No puedes agendar citas por el sistema. Por favor, comunícate al 77112233 para más información.";
    redirigir('/ecodent/public/paciente/dashboard.php');
}

// 2. Verificar límite de citas simultáneas
$stmt_citas = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM citas 
    WHERE id_paciente = ? 
    AND fecha_cita >= CURDATE() 
    AND estado IN ('programada', 'confirmada')
");
$stmt_citas->bind_param("i", $id_paciente);
$stmt_citas->execute();
$citas_activas = $stmt_citas->get_result()->fetch_assoc()['total'];

if ($citas_activas >= $paciente['limite_citas_simultaneas']) {
    $_SESSION['error'] = "⚠️ Has alcanzado el límite de " . $paciente['limite_citas_simultaneas'] . " cita(s) simultáneas. No puedes agendar más hasta que completes o canceles alguna.";
    redirigir('/ecodent/public/paciente/dashboard.php');
}

// Variables
$error = '';
$exito = '';

// =============================================
// PROCESAR EL AGENDAMIENTO (POST)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar'])) {
    
    // Verificar token CSRF
    if (!isset($_POST['token_csrf']) || !verificarTokenCSRF($_POST['token_csrf'])) {
        $error = 'Error de seguridad. Intente nuevamente.';
    } else {
        
        $odontologo_id = (int)$_POST['odontologo_id'];
        $fecha         = $_POST['fecha'];
        $hora          = $_POST['hora'];
        $hora_fin      = $_POST['hora_fin'];
        $motivo        = sanitizar($_POST['motivo']);
        
        // Verificar que el slot sigue disponible
        if (!slotEstaDisponible($odontologo_id, $fecha, $hora, $conexion)) {
            $error = 'Lo sentimos, ese horario ya no está disponible. Por favor elige otro.';
        } else {
            
            // Verificar límite de citas del paciente (otra vez por si cambió)
            $stmt_limite = $conexion->prepare("
                SELECT limite_citas_simultaneas, 
                       (SELECT COUNT(*) FROM citas WHERE id_paciente = ? AND estado IN ('programada', 'confirmada') AND fecha_cita >= CURDATE()) as citas_actuales
                FROM pacientes 
                WHERE id_paciente = ?
            ");
            $stmt_limite->bind_param("ii", $id_paciente, $id_paciente);
            $stmt_limite->execute();
            $limite_info = $stmt_limite->get_result()->fetch_assoc();
            
            if ($limite_info['citas_actuales'] >= $limite_info['limite_citas_simultaneas']) {
                $error = "Has alcanzado tu límite de {$limite_info['limite_citas_simultaneas']} citas simultáneas.";
            } else {
                
                // INICIAR TRANSACCIÓN
                $conexion->begin_transaction();
                
                try {
                    // Insertar la cita
                    $stmt_cita = $conexion->prepare("
                        INSERT INTO citas (id_paciente, id_odontologo, fecha_cita, hora_cita, hora_fin, motivo, estado)
                        VALUES (?, ?, ?, ?, ?, ?, 'programada')
                    ");
                    $stmt_cita->bind_param("iissss", $id_paciente, $odontologo_id, $fecha, $hora, $hora_fin, $motivo);
                    
                    if (!$stmt_cita->execute()) {
                        throw new Exception("Error al agendar cita");
                    }
                    
                    $id_cita = $conexion->insert_id;
                    
                    // Programar recordatorios
                    $fecha_recordatorio_24h = date('Y-m-d H:i:s', strtotime($fecha . ' ' . $hora) - (24 * 3600));
                    $fecha_recordatorio_1h  = date('Y-m-d H:i:s', strtotime($fecha . ' ' . $hora) - 3600);
                    
                    $stmt_recordatorio = $conexion->prepare("
                        UPDATE citas 
                        SET fecha_recordatorio_24h = ?, fecha_recordatorio_1h = ?
                        WHERE id_cita = ?
                    ");
                    $stmt_recordatorio->bind_param("ssi", $fecha_recordatorio_24h, $fecha_recordatorio_1h, $id_cita);
                    $stmt_recordatorio->execute();
                    
                    $conexion->commit();
                    
                    $_SESSION['exito'] = '¡Cita agendada exitosamente! Te enviaremos un recordatorio.';
                    redirigir('/ecodent/public/paciente/mis_citas.php');
                    
                } catch (Exception $e) {
                    $conexion->rollback();
                    $error = 'Error al agendar: ' . $e->getMessage();
                }
            }
        }
    }
}

// =============================================
// OBTENER DATOS PARA EL FORMULARIO
// =============================================
$odontologos = [];
$sql_odontologos = "SELECT o.id_odontologo, u.nombre_completo, o.especialidad_principal, o.color_calendario
                    FROM odontologos o
                    JOIN usuarios u ON o.id_usuario = u.id_usuario
                    WHERE o.activo = 1";
$resultado_odontologos = $conexion->query($sql_odontologos);
while ($row = $resultado_odontologos->fetch_assoc()) {
    $odontologos[] = $row;
}

// Procesar selección de fecha y odontólogo (vía GET)
$fecha_seleccionada    = date('Y-m-d');
$odontologo_seleccionado = '';
$slots_disponibles     = [];

if (isset($_GET['odontologo']) && isset($_GET['fecha'])) {
    $odontologo_seleccionado = (int)$_GET['odontologo'];
    $fecha_seleccionada      = $_GET['fecha'];
    
    if (strtotime($fecha_seleccionada) < strtotime(date('Y-m-d'))) {
        $error = 'No puedes seleccionar fechas pasadas';
    } else {
        $slots_disponibles = generarSlotsDisponibles($odontologo_seleccionado, $fecha_seleccionada, $conexion);
    }
}

// Generar token CSRF
$token_csrf = generarTokenCSRF();

// =============================================
// INCLUIR EL HEADER (HTML)
// =============================================
require_once '../../includes/header.php';
?>

<style>
.slot-disponible {
    cursor: pointer;
    padding: 10px;
    border: 2px solid #28a745;
    border-radius: 8px;
    transition: all 0.2s;
}
.slot-disponible:hover {
    background-color: #d4edda;
    transform: scale(1.03);
}
.slot-disponible.seleccionado {
    background-color: #28a745;
    color: white;
}
.slot-disponible.seleccionado small,
.slot-disponible.seleccionado strong {
    color: white !important;
}
.slot-disponible.seleccionado .badge {
    background-color: white !important;
    color: #28a745 !important;
}
</style>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-calendar-plus"></i> Agendar Nueva Cita</h1>
        <p class="lead">Selecciona el odontólogo, la fecha y el horario disponible</p>
        <hr>
    </div>
</div>

<!-- ADVERTENCIA DE ESTADO DE CUENTA -->
<?php if ($paciente['estado_cuenta'] == 'restringida'): ?>
    <div class="alert alert-warning mb-3">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Cuenta Restringida:</strong> Solo puedes tener <strong>1 cita activa</strong> a la vez.
        Actualmente tienes <strong><?php echo $citas_activas; ?></strong> cita(s) activa(s).
    </div>
<?php elseif ($paciente['estado_cuenta'] == 'observacion'): ?>
    <div class="alert alert-info mb-3">
        <i class="bi bi-eye"></i>
        <strong>Cuenta en Observación:</strong> Puedes tener hasta <strong>2 citas activas</strong>.
        Actualmente tienes <strong><?php echo $citas_activas; ?></strong> de 2 cita(s).
    </div>
<?php endif; ?>

<div class="row">
    <!-- Columna izquierda: Selección de odontólogo y fecha -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">1. Elegir Odontólogo y Fecha</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="GET" action="" id="formSeleccion">
                    <div class="mb-3">
                        <label for="odontologo" class="form-label">Odontólogo:</label>
                        <select class="form-select" id="odontologo" name="odontologo" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($odontologos as $odontologo): ?>
                                <option value="<?php echo $odontologo['id_odontologo']; ?>" 
                                        <?php echo ($odontologo_seleccionado == $odontologo['id_odontologo']) ? 'selected' : ''; ?>>
                                    <?php echo $odontologo['nombre_completo']; ?> 
                                    (<?php echo $odontologo['especialidad_principal']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha" class="form-label">Fecha:</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" 
                               value="<?php echo $fecha_seleccionada; ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Ver Horarios Disponibles
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Información
            </div>
            <div class="card-body">
                <p><strong>Duración de cada cita:</strong> 40 minutos</p>
                <p><strong>Recordatorios:</strong> 24 horas y 1 hora antes</p>
                <p><strong>Modificaciones:</strong> Máximo 3 cambios por cita</p>
                <p><strong>Cancelaciones:</strong> Con 24 horas de anticipación</p>
                <hr>
                <p class="mb-0 text-muted small">
                    <i class="bi bi-info-circle"></i> Tu límite actual: 
                    <strong><?php echo $citas_activas; ?>/<?php echo $paciente['limite_citas_simultaneas']; ?></strong> citas activas
                </p>
            </div>
        </div>
    </div>
    
    <!-- Columna derecha: Slots disponibles -->
    <div class="col-md-8">
        <?php if ($odontologo_seleccionado && $fecha_seleccionada): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        2. Horarios Disponibles para 
                        <?php 
                        foreach ($odontologos as $odontologo) {
                            if ($odontologo['id_odontologo'] == $odontologo_seleccionado) {
                                echo $odontologo['nombre_completo'];
                                break;
                            }
                        }
                        ?>
                        el <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?>
                    </h5>
                </div>
                <div class="card-body">
                    
                    <?php if (empty($slots_disponibles)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No hay horarios disponibles para esta fecha. 
                            <a href="agendar.php">Intentar con otra fecha</a>
                        </div>
                    <?php else: ?>
                        
                        <div class="row">
                            <?php foreach ($slots_disponibles as $slot): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="slot-disponible" 
                                         onclick="seleccionarSlot(
                                             this,
                                             '<?php echo $slot['hora_inicio']; ?>', 
                                             '<?php echo $slot['hora_inicio_formato']; ?>', 
                                             '<?php echo $slot['hora_fin']; ?>',
                                             '<?php echo $slot['hora_fin_formato']; ?>'
                                         )">
                                        <div class="text-center">
                                            <strong><?php echo $slot['hora_inicio_formato']; ?></strong>
                                            <br>
                                            <small><strong>a <?php echo $slot['hora_fin_formato']; ?></strong></small>
                                            <br>
                                            <span class="badge bg-success">Disponible</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Formulario para agendar -->
                        <div id="formAgendar" style="display: none; margin-top: 20px;" class="border-top pt-3">
                            <h5>Confirmar cita</h5>
                            <form method="POST" action="">
                                <input type="hidden" name="token_csrf" value="<?php echo $token_csrf; ?>">
                                <input type="hidden" name="odontologo_id" value="<?php echo $odontologo_seleccionado; ?>">
                                <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                                <input type="hidden" name="hora" id="hora_seleccionada">
                                <input type="hidden" name="hora_fin" id="hora_fin_seleccionada">
                                
                                <div class="mb-3">
                                    <label class="form-label">Horario seleccionado:</label>
                                    <p class="form-control-plaintext fw-bold text-success" id="hora_mostrar"></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="motivo" class="form-label">Motivo de la consulta:</label>
                                    <textarea class="form-control" id="motivo" name="motivo" rows="2" 
                                              placeholder="Ej: Dolor de muela, limpieza, consulta..."></textarea>
                                </div>
                                
                                <button type="submit" name="agendar" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Confirmar Cita
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelarSeleccion()">
                                    Cancelar
                                </button>
                            </form>
                        </div>
                        
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function seleccionarSlot(elemento, hora, horaFormato, horaFin, horaFinFormato) {
    // Quitar resaltado de todos los slots
    document.querySelectorAll('.slot-disponible').forEach(el => {
        el.classList.remove('seleccionado');
    });

    // Resaltar el slot clickeado
    elemento.classList.add('seleccionado');

    // Asignar valores al formulario
    document.getElementById('hora_seleccionada').value = hora;
    document.getElementById('hora_fin_seleccionada').value = horaFin;
    document.getElementById('hora_mostrar').innerHTML = horaFormato + ' a ' + horaFinFormato;

    // Mostrar formulario y hacer scroll
    document.getElementById('formAgendar').style.display = 'block';
    document.getElementById('formAgendar').scrollIntoView({ behavior: 'smooth' });
}

function cancelarSeleccion() {
    // Quitar resaltado de todos los slots
    document.querySelectorAll('.slot-disponible').forEach(el => {
        el.classList.remove('seleccionado');
    });

    // Limpiar y ocultar formulario
    document.getElementById('formAgendar').style.display = 'none';
    document.getElementById('hora_seleccionada').value = '';
    document.getElementById('hora_fin_seleccionada').value = '';
    document.getElementById('hora_mostrar').innerHTML = '';
}
</script>

<?php
require_once '../../includes/footer.php';
?>