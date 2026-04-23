<?php
// public/odontologo/vincular_tratamiento.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esOdontologo()) {
    redirigir('/ecodent/public/login.php');
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_odontologo
$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$odontologo = $stmt->get_result()->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

$id_cita = (int)$_GET['id_cita'];
$id_paciente = (int)$_GET['id_paciente'];

// Verificar si viene de crear nuevo tratamiento
$nuevo_tratamiento_id = null;
if (isset($_SESSION['nuevo_tratamiento_id'])) {
    $nuevo_tratamiento_id = $_SESSION['nuevo_tratamiento_id'];
    unset($_SESSION['nuevo_tratamiento_id']);
}

// Obtener datos de la cita
$stmt_cita = $conexion->prepare("
    SELECT c.*, u.nombre_completo as paciente_nombre
    FROM citas c
    JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
    JOIN pacientes p ON t.id_paciente = p.id_paciente
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE c.id_cita = ? AND t.id_odontologo = ?
");
$stmt_cita->bind_param("ii", $id_cita, $id_odontologo);
$stmt_cita->execute();
$cita = $stmt_cita->get_result()->fetch_assoc();

if (!$cita) {
    $_SESSION['error'] = "Cita no encontrada";
    redirigir('/ecodent/public/odontologo/calendario.php');
}

// Si ya tiene tratamiento, redirigir
if ($cita['id_tratamiento']) {
    $_SESSION['error'] = "Esta cita ya tiene un tratamiento vinculado";
    redirigir('/ecodent/public/odontologo/detalle_cita.php?id_cita=' . $id_cita);
}

// Procesar vinculación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular'])) {
    $id_tratamiento = (int)$_POST['id_tratamiento'];
    
    if ($id_tratamiento > 0) {
        $stmt_update = $conexion->prepare("UPDATE citas SET id_tratamiento = ? WHERE id_cita = ?");
        $stmt_update->bind_param("ii", $id_tratamiento, $id_cita);
        
        if ($stmt_update->execute()) {
            $_SESSION['exito'] = "✅ Tratamiento vinculado correctamente a la cita";
            redirigir('/ecodent/public/odontologo/detalle_cita.php?id_cita=' . $id_cita);
        } else {
            $error = "Error al vincular el tratamiento";
        }
    } else {
        $error = "Debes seleccionar un tratamiento";
    }
}

// Obtener tratamientos existentes del paciente
$stmt_trat = $conexion->prepare("
    SELECT id_tratamiento, nombre_tratamiento, estado, saldo_pendiente
    FROM tratamientos
    WHERE id_paciente = ?
    ORDER BY fecha_creacion DESC
");
$stmt_trat->bind_param("i", $id_paciente);
$stmt_trat->execute();
$tratamientos = $stmt_trat->get_result();

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-warning">
                    <h4><i class="bi bi-link"></i> Vincular Tratamiento a Cita</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <strong><i class="bi bi-calendar-check"></i> Cita:</strong><br>
                        <strong>Paciente:</strong> <?php echo htmlspecialchars($cita['paciente_nombre']); ?><br>
                        <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?> - <?php echo date('h:i A', strtotime($cita['hora_cita'])); ?>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="opcion" id="opcionExistente" value="existente" checked>
                                <label class="form-check-label" for="opcionExistente">
                                    <i class="bi bi-list-ul"></i> Usar tratamiento existente
                                </label>
                            </div>
                            
                            <div id="divExistente" class="ms-4 mt-2">
                                <select name="id_tratamiento" id="select_tratamiento" class="form-select">
                                    <option value="">-- Seleccionar tratamiento --</option>
                                    <?php while($t = $tratamientos->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id_tratamiento']; ?>" 
                                            <?php echo ($nuevo_tratamiento_id == $t['id_tratamiento']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['nombre_tratamiento']); ?> 
                                            - <?php echo $t['estado']; ?>
                                            (Saldo: Bs. <?php echo number_format($t['saldo_pendiente'], 2); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <?php if ($tratamientos->num_rows == 0): ?>
                                    <div class="alert alert-warning mt-2">
                                        <i class="bi bi-exclamation-triangle"></i> No hay tratamientos registrados para este paciente.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="opcion" id="opcionNuevo" value="nuevo">
                                <label class="form-check-label" for="opcionNuevo">
                                    <i class="bi bi-plus-circle"></i> Crear nuevo tratamiento
                                </label>
                            </div>
                            
                            <div id="divNuevo" class="ms-4 mt-2" style="display:none;">
                                <a href="tratamiento_nuevo.php?paciente=<?php echo $id_paciente; ?>&return_to=vincular&cita=<?php echo $id_cita; ?>" 
                                   class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Crear nuevo tratamiento
                                </a>
                                <p class="text-muted small mt-2">
                                    <i class="bi bi-info-circle"></i> Se abrirá en nueva pestaña. 
                                    Después de crear el tratamiento, vuelve aquí y estará seleccionado automáticamente.
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <a href="detalle_cita.php?id_cita=<?php echo $id_cita; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" name="vincular" class="btn btn-primary">
                                <i class="bi bi-link"></i> Vincular tratamiento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar/ocultar según opción seleccionada
document.querySelectorAll('input[name="opcion"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'existente') {
            document.getElementById('divExistente').style.display = 'block';
            document.getElementById('divNuevo').style.display = 'none';
        } else {
            document.getElementById('divExistente').style.display = 'none';
            document.getElementById('divNuevo').style.display = 'block';
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>