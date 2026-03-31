<?php
// public/paciente/detalle_cita.php
// Muestra el detalle de una cita específica para el paciente

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

// Verificar que solo pacientes puedan acceder
requerirRol('paciente');

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_paciente
$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$paciente = $resultado->fetch_assoc();
$id_paciente = $paciente['id_paciente'];

// =============================================
// VERIFICAR QUE VIENE UN ID DE CITA VÁLIDO
// =============================================
if (!isset($_GET['id_cita'])) {
    $_SESSION['error'] = 'No se especificó la cita';
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

$id_cita = (int)$_GET['id_cita'];

// =============================================
// OBTENER DATOS DE LA CITA
// =============================================
$sql = "SELECT c.*, 
               u.nombre_completo as nombre_odontologo,
               o.especialidad_principal,
               o.color_calendario
        FROM citas c
        JOIN odontologos o ON c.id_odontologo = o.id_odontologo
        JOIN usuarios u ON o.id_usuario = u.id_usuario
        WHERE c.id_cita = ? AND c.id_paciente = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $id_cita, $id_paciente);
$stmt->execute();
$resultado = $stmt->get_result();
$cita = $resultado->fetch_assoc();

if (!$cita) {
    $_SESSION['error'] = 'Cita no encontrada';
    redirigir('/ecodent/public/paciente/mis_citas.php');
}

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1><i class="bi bi-calendar-check"></i> Detalle de mi Cita</h1>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <!-- Información de la cita -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de la Cita</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 30%">Odontólogo:</th>
                        <td><?php echo $cita['nombre_odontologo']; ?></td>
                    </tr>
                    <tr>
                        <th>Especialidad:</th>
                        <td><?php echo $cita['especialidad_principal']; ?></td>
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
                        <th>Duración:</th>
                        <td>40 minutos</td>
                    </tr>
                    <tr>
                        <th>Motivo:</th>
                        <td><?php echo $cita['motivo'] ?? 'No especificado'; ?></td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <?php
                            $estados = [
                                'programada' => 'badge bg-primary',
                                'confirmada' => 'badge bg-success',
                                'completada' => 'badge bg-secondary',
                                'cancelada_pac' => 'badge bg-danger',
                                'cancelada_doc' => 'badge bg-danger',
                                'ausente' => 'badge bg-warning'
                            ];
                            
                            $estado_texto = [
                                'programada' => 'Programada',
                                'confirmada' => 'Confirmada',
                                'completada' => 'Completada',
                                'cancelada_pac' => 'Cancelada por ti',
                                'cancelada_doc' => 'Cancelada por el odontólogo',
                                'ausente' => 'No asististe'
                            ];
                            
                            $clase = $estados[$cita['estado']] ?? 'badge bg-secondary';
                            ?>
                            <span class="<?php echo $clase; ?>"><?php echo $estado_texto[$cita['estado']]; ?></span>
                        </td>
                    </tr>
                    
                    <?php if ($cita['estado'] == 'cancelada_doc' && $cita['motivo_cancelacion']): ?>
                        <tr>
                            <th>Motivo cancelación:</th>
                            <td class="text-danger"><?php echo $cita['motivo_cancelacion']; ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($cita['llego_tarde']): ?>
                        <tr>
                            <th>Registro:</th>
                            <td class="text-warning">Llegaste tarde (<?php echo $cita['minutos_tarde']; ?> minutos)</td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($cita['fecha_cancelacion']): ?>
                        <tr>
                            <th>Cancelada el:</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($cita['fecha_cancelacion'])); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                
                <?php if ($cita['estado'] == 'cancelada_doc'): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        El odontólogo canceló esta cita. Hemos guardado tu cupo, 
                        <a href="reprogramar.php?id_cita=<?php echo $id_cita; ?>">haz clic aquí para reprogramar</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recordatorios -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Recordatorios</h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item">
                        <i class="bi bi-envelope"></i>
                        <strong>24 horas antes:</strong> 
                        <?php echo $cita['fecha_recordatorio_24h'] ? date('d/m/Y H:i', strtotime($cita['fecha_recordatorio_24h'])) : 'Pendiente'; ?>
                    </li>
                    <li class="list-group-item">
                        <i class="bi bi-envelope"></i>
                        <strong>1 hora antes:</strong> 
                        <?php echo $cita['fecha_recordatorio_1h'] ? date('d/m/Y H:i', strtotime($cita['fecha_recordatorio_1h'])) : 'Pendiente'; ?>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div class="d-grid gap-2">
            <?php if ($cita['estado'] == 'programada' || $cita['estado'] == 'confirmada'): ?>
                <a href="mis_citas.php?cancelar=1&id_cita=<?php echo $id_cita; ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('¿Estás seguro de cancelar esta cita?')">
                    <i class="bi bi-x-circle"></i> Cancelar esta cita
                </a>
            <?php endif; ?>
            
            <a href="mis_citas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a mis citas
            </a>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>