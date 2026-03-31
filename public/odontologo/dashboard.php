<?php
// public/odontologo/dashboard.php
// Panel principal del odontólogo - VERSIÓN SIMPLE Y BONITA (CORREGIDA)

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
$stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$odontologo = $resultado->fetch_assoc();
$id_odontologo = $odontologo['id_odontologo'];

// =============================================
// DATOS PARA EL DASHBOARD
// =============================================
$hoy = date('Y-m-d');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish');
// 1. Citas de HOY
$stmt_hoy = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM citas 
    WHERE id_odontologo = ? AND fecha_cita = ? 
    AND estado IN ('programada', 'confirmada')
");
$stmt_hoy->bind_param("is", $id_odontologo, $hoy);
$stmt_hoy->execute();
$citas_hoy = $stmt_hoy->get_result()->fetch_assoc()['total'];

// 2. Próxima cita (la más cercana)
$stmt_proxima = $conexion->prepare("
    SELECT c.hora_cita, c.hora_fin, u.nombre_completo as paciente
    FROM citas c
    JOIN pacientes p ON c.id_paciente = p.id_paciente
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE c.id_odontologo = ? AND c.fecha_cita = ? 
    AND c.estado IN ('programada', 'confirmada')
    ORDER BY c.hora_cita ASC
    LIMIT 1
");
$stmt_proxima->bind_param("is", $id_odontologo, $hoy);
$stmt_proxima->execute();
$proxima_cita = $stmt_proxima->get_result()->fetch_assoc();

// 3. Total de pacientes (los que has atendido)
$stmt_pacientes = $conexion->prepare("
    SELECT COUNT(DISTINCT c.id_paciente) as total
    FROM citas c
    WHERE c.id_odontologo = ?
");
$stmt_pacientes->bind_param("i", $id_odontologo);
$stmt_pacientes->execute();
$total_pacientes = $stmt_pacientes->get_result()->fetch_assoc()['total'];

// 4. Próximas citas (para la tabla) - CORREGIDO: agregamos id_cita
$stmt_proximas = $conexion->prepare("
    SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.hora_fin, u.nombre_completo as paciente
    FROM citas c
    JOIN pacientes p ON c.id_paciente = p.id_paciente
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE c.id_odontologo = ? 
    AND c.fecha_cita >= CURDATE()
    AND c.estado IN ('programada', 'confirmada')
    ORDER BY c.fecha_cita ASC, c.hora_cita ASC
    LIMIT 5
");
$stmt_proximas->bind_param("i", $id_odontologo);
$stmt_proximas->execute();
$proximas_citas = $stmt_proximas->get_result();

// 5. Slots bloqueados hoy (para el recordatorio)
$stmt_bloq_hoy = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM slots_bloqueados 
    WHERE id_odontologo = ? AND fecha = ?
");
$stmt_bloq_hoy->bind_param("is", $id_odontologo, $hoy);
$stmt_bloq_hoy->execute();
$bloqueos_hoy = $stmt_bloq_hoy->get_result()->fetch_assoc()['total'];

// 6. Pacientes atendidos hoy
$stmt_atendidos = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM citas 
    WHERE id_odontologo = ? AND fecha_cita = ? AND estado = 'completada'
");
$stmt_atendidos->bind_param("is", $id_odontologo, $hoy);
$stmt_atendidos->execute();
$atendidos_hoy = $stmt_atendidos->get_result()->fetch_assoc()['total'];

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';
?>

<style>
/* Estilos simples y bonitos */
.welcome-box {
    background-color: #4361ee;
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    border: 1px solid #f0f0f0;
    transition: all 0.3s;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: #eef2ff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4361ee;
    font-size: 1.8rem;
}

.stat-number {
    font-size: 2.2rem;
    font-weight: bold;
    color: #333;
    line-height: 1.2;
}

.stat-label {
    color: #666;
    font-size: 0.95rem;
}

.action-btn {
    display: block;
    width: 100%;
    padding: 15px 20px;
    margin-bottom: 10px;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 500;
    text-align: left;
    transition: all 0.3s;
    text-decoration: none;
}

.action-btn i {
    margin-right: 15px;
    font-size: 1.3rem;
}

.action-btn.primary {
    background: #4361ee;
    color: white;
}

.action-btn.primary:hover {
    background: #3651c4;
    color: white;
}

.action-btn.success {
    background: #06d6a0;
    color: white;
}

.action-btn.success:hover {
    background: #05b586;
    color: white;
}

.action-btn.warning {
    background: #ffb703;
    color: white;
}

.action-btn.warning:hover {
    background: #e0a102;
    color: white;
}

.action-btn.secondary {
    background: #6c757d;
    color: white;
}

.action-btn.secondary:hover {
    background: #5a6268;
    color: white;
}

/* ESTILOS DE LA TABLA CON EFECTO HOVER EN TODO EL RECUADRO */
.citas-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    border-collapse: separate;
    border-spacing: 0;
}

.citas-table thead {
    background: #f8f9fa;
    color: #333;
}

.citas-table thead th {
    padding: 15px;
    font-weight: 600;
    border-bottom: 2px solid #e9ecef;
}

.citas-table tbody tr {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    background-color: white;
}

/* EFECTO HOVER EN TODO EL RECUADRO - SE PINTA COMPLETO */
.citas-table tbody tr:hover {
    background: linear-gradient(135deg, #e8f0fe 0%, #d9e8ff 100%);
    transform: scale(1.01);
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
    border-radius: 12px;
}

/* Para que todas las celdas hereden el fondo del hover */
.citas-table tbody tr:hover td {
    background: transparent;
}

/* Estilo de las celdas */
.citas-table td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.2s ease;
    vertical-align: middle;
}

/* Efecto adicional: borde izquierdo en la primera celda al hacer hover */
.citas-table tbody tr:hover td:first-child {
    border-left: 4px solid #4361ee;
    border-radius: 8px 0 0 8px;
}

/* Efecto adicional: borde derecho en la última celda al hacer hover */
.citas-table tbody tr:hover td:last-child {
    border-right: 4px solid #4361ee;
    border-radius: 0 8px 8px 0;
}

/* Animación de entrada para las filas */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.citas-table tbody tr {
    animation: slideIn 0.4s ease forwards;
    animation-delay: calc(var(--row-index, 0) * 0.08s);
    opacity: 0;
}

.badge-hoy {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: normal;
    backdrop-filter: blur(5px);
}

.info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    border-left: 4px solid #4361ee;
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Efecto de clic */
.citas-table tbody tr:active {
    transform: scale(0.99);
    transition: transform 0.1s ease;
}

/* Mejora en la legibilidad de los badges */
.badge {
    padding: 5px 10px;
    font-weight: 500;
}

/* Efecto sutil en el ícono de flecha */
.citas-table tbody tr:hover .bi-chevron-right {
    transform: translateX(5px);
    transition: transform 0.3s ease;
}

.bi-chevron-right {
    transition: transform 0.3s ease;
}

/* Mejora en el aspecto de las filas alternas */
.citas-table tbody tr:nth-child(even) {
    background-color: #fafbfd;
}

.citas-table tbody tr:nth-child(even):hover {
    background: linear-gradient(135deg, #e8f0fe 0%, #d9e8ff 100%);
}
</style>

<!-- SALUDO SIMPLE -->
<div class="welcome-box">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1">👋 ¡Hola, <?php echo $_SESSION['nombre_completo']; ?>!</h2>
            <h2 class="mb-0 opacity-75"><?php echo strftime('%A, %d de %B de %Y', strtotime($hoy)); ?></h2>
        </div>
        <div>
            <span class="badge-hoy">
                <i class="bi bi-calendar-check"></i> <?php echo $citas_hoy; ?> cita(s) hoy
            </span>
        </div>
    </div>
</div>

<!-- 3 TARJETAS GRANDES Y CLARAS -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="stat-card d-flex align-items-center">
            <div class="stat-icon me-3">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div>
                <div class="stat-number"><?php echo $citas_hoy; ?></div>
                <div class="stat-label">Citas para hoy</div>
                <?php if ($proxima_cita): ?>
                    <small class="text-primary">
                        <i class="bi bi-clock"></i> Próxima: <?php echo (date('h:i A', strtotime($proxima_cita['hora_cita']))." - ".date('h:i A', strtotime($proxima_cita['hora_fin']))); ?> - <?php echo $proxima_cita['paciente']; ?>
                    </small>
                <?php else: ?>
                    <small class="text-muted">No hay citas programadas</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="stat-card d-flex align-items-center">
            <div class="stat-icon me-3" style="color: #06d6a0;">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <div class="stat-number"><?php echo $total_pacientes; ?></div>
                <div class="stat-label">Pacientes atendidos</div>
                <small class="text-muted">Desde que iniciaste</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="stat-card d-flex align-items-center">
            <div class="stat-icon me-3" style="color: #ffb703;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-number">40</div>
                <div class="stat-label">Minutos por cita</div>
                <small class="text-muted">Duración estándar</small>
            </div>
        </div>
    </div>
</div>

<!-- DOS COLUMNAS: TABLA DE CITAS Y ACCIONES RÁPIDAS -->
<div class="row">
    <!-- COLUMNA IZQUIERDA: PRÓXIMAS CITAS -->
    <div class="col-md-7 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar3 me-2 text-primary"></i>
                        Próximas citas
                    </h5>
                    <a href="calendario.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-right"></i> Ver calendario completo
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="citas-table table mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proximas_citas && $proximas_citas->num_rows > 0): ?>
                            <?php 
                            $row_index = 0;
                            while ($cita = $proximas_citas->fetch_assoc()): 
                            ?>
                                <tr onclick="window.location.href='detalle_cita.php?id_cita=<?php echo $cita['id_cita']; ?>'" 
                                    style="--row-index: <?php echo $row_index; ?>">
                                    <td>
                                        <?php 
                                        if ($cita['fecha_cita'] == $hoy) {
                                            echo '<span class="badge bg-primary"><i class="bi bi-sun"></i> Hoy</span>';
                                        } else {
                                            echo '<i class="bi bi-calendar-day me-1 text-secondary"></i> ' . date('d/m/Y', strtotime($cita['fecha_cita']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-clock me-1 text-secondary"></i>
                                        <strong><?php echo (date('h:i A', strtotime($cita['hora_cita']))." - ".date('h:i A', strtotime($cita['hora_fin']))); ?></strong>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-circle me-1 text-secondary"></i>
                                        <span class="fw-medium"><?php echo $cita['paciente']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <i class="bi bi-chevron-right text-primary"></i>
                                    </td>
                                </tr>
                            <?php 
                            $row_index++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No hay citas programadas
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- COLUMNA DERECHA: ACCIONES RÁPIDAS SIMPLES -->
    <div class="col-md-5 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-lightning-charge me-2 text-warning"></i>
                    Acciones rápidas
                </h5>
            </div>
            <div class="card-body">
                <!-- Botón 1: Ver calendario -->
                <a href="calendario.php" class="action-btn primary">
                    <i class="bi bi-calendar-week"></i>
                    Ver mi calendario
                </a>
                
                <!-- Botón 2: Agendar cita (para tratamientos) -->
                <a href="agendar_cita.php" class="action-btn success">
                    <i class="bi bi-calendar-plus"></i>
                    Agendar cita / tratamiento
                </a>
                
                <!-- Botón 3: Bloquear slots (emergencia) - DESTACADO -->
                <a href="bloquear_slots.php" class="action-btn warning">
                    <i class="bi bi-lock-fill"></i>
                    ⚠️ Bloquear slots (emergencia)
                </a>
                
                <!-- Botón 4: Pacientes -->
                <a href="pacientes.php" class="action-btn secondary">
                    <i class="bi bi-people"></i>
                    Ver mis pacientes
                </a>
                
                <!-- Botón 5: Configurar horarios -->
                <a href="configurar_horarios.php" class="action-btn secondary" style="background: #5a6268; margin-bottom: 0;">
                    <i class="bi bi-gear"></i>
                    Configurar horarios
                </a>
            </div>
        </div>
        
        <!-- Tarjeta extra: recordatorios simples -->
        <div class="row mt-3">
            <div class="col-6">
                <div class="info-card">
                    <i class="bi bi-lock text-warning fs-4"></i>
                    <div class="mt-2">
                        <strong><?php echo $bloqueos_hoy; ?></strong> slots bloqueados hoy
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="info-card" style="border-left-color: #06d6a0;">
                    <i class="bi bi-check-circle text-success fs-4"></i>
                    <div class="mt-2">
                        <strong><?php echo $atendidos_hoy; ?></strong> pacientes atendidos
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Agregar un pequeño efecto de feedback al hacer clic en las filas
document.querySelectorAll('.citas-table tbody tr').forEach(row => {
    if (row.querySelector('td[colspan]')) return; // Saltar la fila de "no hay citas"
    
    row.addEventListener('click', function(e) {
        // Evitar que se ejecute si se hizo clic en un enlace dentro de la fila
        if (e.target.tagName === 'A') return;
        
        // Efecto visual de clic
        this.style.transform = 'scale(0.98)';
        this.style.transition = 'transform 0.1s ease';
        setTimeout(() => {
            this.style.transform = '';
        }, 150);
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>