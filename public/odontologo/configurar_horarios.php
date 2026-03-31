<?php
// public/odontologo/configurar_horarios.php
// Página para que el odontólogo configure sus horarios de trabajo

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz'); // Cambia según tu ubicación

// Verificar que solo odontólogos puedan acceder
requerirRol('odontologo');

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_odontologo
$stmt = $conexion->prepare("SELECT id_odontologo, duracion_cita_min FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

// =============================================
// PROCESAR GUARDADO DE HORARIOS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_horarios'])) {
    
    // Actualizar duración de la cita (por si cambia)
    if (isset($_POST['duracion_cita']) && $_POST['duracion_cita'] != $odontologo['duracion_cita_min']) {
        $nueva_duracion = (int)$_POST['duracion_cita'];
        $stmt_duracion = $conexion->prepare("UPDATE odontologos SET duracion_cita_min = ? WHERE id_odontologo = ?");
        $stmt_duracion->bind_param("ii", $nueva_duracion, $id_odontologo);
        $stmt_duracion->execute();
    }
    
    // Primero desactivar todos los horarios actuales
    $stmt_desactivar = $conexion->prepare("UPDATE horarios_odontologos SET activo = 0 WHERE id_odontologo = ?");
    $stmt_desactivar->bind_param("i", $id_odontologo);
    $stmt_desactivar->execute();
    
    // Procesar cada día de la semana
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $horarios_guardados = 0;
    
    foreach ($dias as $dia) {
        $activo = isset($_POST["dia_$dia"]) ? 1 : 0;
        $hora_inicio = $_POST["inicio_$dia"] ?? '08:00';
        $hora_fin = $_POST["fin_$dia"] ?? '18:00';
        
        if ($activo) {
            // Verificar si ya existe un registro para este día
            $stmt_verificar = $conexion->prepare("SELECT id_horario FROM horarios_odontologos 
                                                  WHERE id_odontologo = ? AND dia_semana = ?");
            $stmt_verificar->bind_param("is", $id_odontologo, $dia);
            $stmt_verificar->execute();
            $existe = $stmt_verificar->get_result()->fetch_assoc();
            
            if ($existe) {
                // Actualizar existente
                $stmt_guardar = $conexion->prepare("UPDATE horarios_odontologos 
                                                    SET hora_inicio = ?, hora_fin = ?, activo = 1 
                                                    WHERE id_odontologo = ? AND dia_semana = ?");
                $stmt_guardar->bind_param("ssis", $hora_inicio, $hora_fin, $id_odontologo, $dia);
            } else {
                // Insertar nuevo
                $stmt_guardar = $conexion->prepare("INSERT INTO horarios_odontologos 
                                                    (id_odontologo, dia_semana, hora_inicio, hora_fin, activo) 
                                                    VALUES (?, ?, ?, ?, 1)");
                $stmt_guardar->bind_param("isss", $id_odontologo, $dia, $hora_inicio, $hora_fin);
            }
            
            if ($stmt_guardar->execute()) {
                $horarios_guardados++;
            }
        }
    }
    
    $_SESSION['exito'] = "Horarios guardados correctamente. Se actualizaron $horarios_guardados días.";
    redirigir('/ecodent/public/odontologo/configurar_horarios.php');
}

// =============================================
// OBTENER HORARIOS ACTUALES
// =============================================
$sql = "SELECT * FROM horarios_odontologos 
        WHERE id_odontologo = ? 
        ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_odontologo);
$stmt->execute();
$resultado = $stmt->get_result();

$horarios = [];
while ($row = $resultado->fetch_assoc()) {
    $horarios[$row['dia_semana']] = $row;
}

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-gear"></i> Configurar Horarios de Trabajo</h1>
        <p class="lead">Define tus horarios de atención para cada día de la semana</p>
        <hr>
        
        <?php if (isset($_SESSION['exito'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['exito']; unset($_SESSION['exito']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Configuración de Horarios</h5>
            </div>
            <div class="card-body">
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Los slots se generarán automáticamente de <strong>40 minutos</strong> dentro de los horarios que definas.
                    Puedes bloquear slots específicos desde el calendario.
                </div>
                
                <form method="POST" action="">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Día</th>
                                    <th>¿Trabaja?</th>
                                    <th>Hora Inicio</th>
                                    <th>Hora Fin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $dias_es = [
                                    'lunes' => 'Lunes',
                                    'martes' => 'Martes',
                                    'miercoles' => 'Miércoles',
                                    'jueves' => 'Jueves',
                                    'viernes' => 'Viernes',
                                    'sabado' => 'Sábado',
                                    'domingo' => 'Domingo'
                                ];
                                
                                foreach ($dias_es as $dia_key => $dia_nombre):
                                    $activo = isset($horarios[$dia_key]) && $horarios[$dia_key]['activo'];
                                    $hora_inicio = $horarios[$dia_key]['hora_inicio'] ?? '08:00:00';
                                    $hora_fin = $horarios[$dia_key]['hora_fin'] ?? '18:00:00';
                                    
                                    // Formatear horas para input time (H:i)
                                    $hora_inicio_formato = substr($hora_inicio, 0, 5);
                                    $hora_fin_formato = substr($hora_fin, 0, 5);
                                ?>
                                <tr>
                                    <td class="align-middle"><strong><?php echo $dia_nombre; ?></strong></td>
                                    <td class="align-middle text-center">
                                        <input type="checkbox" name="dia_<?php echo $dia_key; ?>" 
                                               value="1" <?php echo $activo ? 'checked' : ''; ?>
                                               class="form-check-input" style="transform: scale(1.5);">
                                    </td>
                                    <td>
                                        <input type="time" name="inicio_<?php echo $dia_key; ?>" 
                                               class="form-control" value="<?php echo $hora_inicio_formato; ?>"
                                               <?php echo !$activo ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="time" name="fin_<?php echo $dia_key; ?>" 
                                               class="form-control" value="<?php echo $hora_fin_formato; ?>"
                                               <?php echo !$activo ? 'disabled' : ''; ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="duracion_cita" class="form-label">Duración de cada cita (minutos):</label>
                                <input type="number" class="form-control" id="duracion_cita" 
                                       name="duracion_cita" min="20" max="120" 
                                       value="<?php echo $odontologo['duracion_cita_min']; ?>">
                                <div class="form-text">PDF página 11: Por defecto 40 minutos</div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="guardar_horarios" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Configuración
                        </button>
                        <a href="calendario.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Calendario
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Ejemplo visual -->
        <div class="card mt-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Ejemplo de cómo se generarán los slots</h5>
            </div>
            <div class="card-body">
                <p>Para un día laboral de 8:00 a 18:00 con slots de 40 minutos:</p>
                <div class="row">
                    <div class="col-md-3">
                        <span class="badge bg-success">08:00 - 08:40</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">08:40 - 09:20</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">09:20 - 10:00</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">10:00 - 10:40</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">10:40 - 11:20</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">11:20 - 12:00</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-warning">12:00 - 12:40 (bloqueado)</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-warning">12:40 - 13:20 (bloqueado)</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">13:20 - 14:00</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success">14:00 - 14:40</span>
                    </div>
                    <!-- ... más slots ... -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Habilitar/deshabilitar campos de hora según checkbox
document.querySelectorAll('input[type="checkbox"][name^="dia_"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let row = this.closest('tr');
        let inputs = row.querySelectorAll('input[type="time"]');
        inputs.forEach(input => {
            input.disabled = !this.checked;
        });
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>