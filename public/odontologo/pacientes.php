<?php
// public/odontologo/pacientes.php
// Módulo para gestionar pacientes - CRUD completo

// =============================================
// PROCESAMIENTO PRIMERO
// =============================================
require_once '../../config/database.php';
require_once '../../includes/funciones.php';
date_default_timezone_set('America/La_Paz');

// Verificar que esté logueado
if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

// Obtener el rol del usuario
$es_admin = esAdmin();
$es_odontologo = esOdontologo();

// Verificar permisos: solo admin u odontólogo pueden acceder
if (!$es_admin && !$es_odontologo) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirigir('/ecodent/public/dashboard.php');
}

$id_usuario = $_SESSION['id_usuario'];
$id_odontologo = null;
$nombre_odontologo = null;

// Solo si es odontólogo, obtener su ID
if ($es_odontologo) {
    $stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $odontologo = $resultado->fetch_assoc();
        $id_odontologo = $odontologo['id_odontologo'];
    } else {
        $_SESSION['error'] = "No se encontró información del odontólogo. Contacte al administrador.";
        redirigir('/ecodent/public/dashboard.php');
    }
}

// =============================================
// PROCESAR CRUD DE PACIENTES
// =============================================

// Eliminar paciente (solo odontólogos pueden eliminar)
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    if (!$es_odontologo) {
        $_SESSION['error'] = "No tienes permisos para eliminar pacientes.";
        redirigir('/ecodent/public/odontologo/pacientes.php');
    }
    
    $id_paciente = $_GET['eliminar'];
    
    // Verificar que el paciente tenga citas con este odontólogo
    $stmt_verificar = $conexion->prepare("SELECT COUNT(*) as total FROM citas WHERE id_paciente = ? AND id_odontologo = ?");
    $stmt_verificar->bind_param("ii", $id_paciente, $id_odontologo);
    $stmt_verificar->execute();
    $total_citas = $stmt_verificar->get_result()->fetch_assoc()['total'];
    
    if ($total_citas > 0) {
        $_SESSION['error'] = "No se puede eliminar el paciente porque tiene $total_citas cita(s) registrada(s).";
    } else {
        // Eliminar paciente (se eliminará en cascada el usuario)
        $stmt_eliminar = $conexion->prepare("DELETE FROM pacientes WHERE id_paciente = ?");
        $stmt_eliminar->bind_param("i", $id_paciente);
        
        if ($stmt_eliminar->execute()) {
            $_SESSION['exito'] = "Paciente eliminado correctamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar el paciente.";
        }
    }
    
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

// =============================================
// OBTENER LISTA DE PACIENTES
// =============================================

// Búsqueda
$busqueda = isset($_GET['buscar']) ? sanitizar($_GET['buscar']) : '';

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$inicio = ($pagina - 1) * $por_pagina;

// Consulta con búsqueda
if ($busqueda) {
    $sql = "SELECT p.id_paciente, u.id_usuario, u.nombre_completo, u.email, u.telefono, 
                   p.fecha_nacimiento, p.direccion, p.ausencias_sin_aviso, p.llegadas_tarde, 
                   p.estado_cuenta, p.puede_agendar
            FROM pacientes p
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE u.nombre_completo LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?
            ORDER BY u.nombre_completo ASC
            LIMIT ?, ?";
    $stmt = $conexion->prepare($sql);
    $param_busqueda = "%$busqueda%";
    $stmt->bind_param("sssii", $param_busqueda, $param_busqueda, $param_busqueda, $inicio, $por_pagina);
    $stmt->execute();
    $pacientes = $stmt->get_result();
    
    // Contar total
    $sql_total = "SELECT COUNT(*) as total FROM pacientes p
                  JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE u.nombre_completo LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?";
    $stmt_total = $conexion->prepare($sql_total);
    $stmt_total->bind_param("sss", $param_busqueda, $param_busqueda, $param_busqueda);
    $stmt_total->execute();
    $total_registros = $stmt_total->get_result()->fetch_assoc()['total'];
} else {
    $sql = "SELECT p.id_paciente, u.id_usuario, u.nombre_completo, u.email, u.telefono, 
                   p.fecha_nacimiento, p.direccion, p.ausencias_sin_aviso, p.llegadas_tarde, 
                   p.estado_cuenta, p.puede_agendar
            FROM pacientes p
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            ORDER BY u.nombre_completo ASC
            LIMIT ?, ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $inicio, $por_pagina);
    $stmt->execute();
    $pacientes = $stmt->get_result();
    
    // Contar total
    $sql_total = "SELECT COUNT(*) as total FROM pacientes";
    $result_total = $conexion->query($sql_total);
    $total_registros = $result_total ? $result_total->fetch_assoc()['total'] : 0;
}

$total_paginas = ceil($total_registros / $por_pagina);

// =============================================
// INCLUIR HEADER
// =============================================
require_once '../../includes/header.php';

// Mostrar mensajes
if (isset($_SESSION['exito'])) {
    $exito = $_SESSION['exito'];
    unset($_SESSION['exito']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<style>
.paciente-card {
    transition: all 0.3s ease;
    border-left: 4px solid #4361ee;
}

.paciente-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.estado-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.estado-normal {
    background: #d4edda;
    color: #155724;
}

.estado-observacion {
    background: #fff3cd;
    color: #856404;
}

.estado-restringida {
    background: #ffe5d0;
    color: #e67e22;
}

.estado-bloqueada {
    background: #f8d7da;
    color: #721c24;
}

.btn-accion {
    padding: 5px 10px;
    margin: 0 3px;
    border-radius: 8px;
    transition: all 0.2s;
}

.btn-accion:hover {
    transform: scale(1.05);
}

.table-pacientes tbody tr {
    transition: all 0.2s;
    cursor: pointer;
}

.table-pacientes tbody tr:hover {
    background: linear-gradient(90deg, #e8f0fe 0%, #f0f8ff 100%);
    transform: scale(1.01);
}

.filtro-busqueda {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-people-fill text-primary"></i> <?php echo $es_admin ? 'Todos los Pacientes' : 'Mis Pacientes'; ?></h1>
        <p class="lead">Gestiona los pacientes, visualiza su información y estado.</p>
        <?php if ($es_admin): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Como administrador, puedes ver todos los pacientes del sistema.
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($es_odontologo): ?>
            <a href="paciente_nuevo.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill"></i> Nuevo Paciente
            </a>
        <?php endif; ?>
        <a href="<?php echo $es_admin ? '/ecodent/public/admin/dashboard.php' : 'odontologos.php'; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php if (isset($exito)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $exito; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filtro de búsqueda -->
<div class="filtro-busqueda">
    <form method="GET" action="" class="row g-3">
        <div class="col-md-9">
            <label class="form-label">Buscar paciente</label>
            <input type="text" class="form-control" name="buscar" 
                   placeholder="Buscar por nombre, email o teléfono..."
                   value="<?php echo htmlspecialchars($busqueda); ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search"></i> Buscar
            </button>
            <?php if ($busqueda): ?>
                <a href="pacientes.php" class="btn btn-secondary w-100 ms-2">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Lista de pacientes -->
<div class="card">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list-ul me-2 text-primary"></i>
                Lista de pacientes
                <span class="badge bg-primary ms-2"><?php echo $total_registros; ?> total</span>
            </h5>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-pacientes table-hover mb-0">
                <thead class="bg-light">
                    32
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <?php if ($es_odontologo): ?>
                            <th>Citas</th>
                        <?php endif; ?>
                        <?php if ($es_odontologo): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pacientes && $pacientes->num_rows > 0): ?>
                        <?php while ($paciente = $pacientes->fetch_assoc()): 
                            // Solo contar citas si es odontólogo
                            $total_citas = 0;
                            $ultima_cita = null;
                            
                            if ($es_odontologo && $id_odontologo) {
                                $stmt_citas = $conexion->prepare("SELECT COUNT(*) as total FROM citas WHERE id_paciente = ? AND id_odontologo = ?");
                                $stmt_citas->bind_param("ii", $paciente['id_paciente'], $id_odontologo);
                                $stmt_citas->execute();
                                $total_citas = $stmt_citas->get_result()->fetch_assoc()['total'];
                                
                                $stmt_ultima = $conexion->prepare("SELECT fecha_cita FROM citas WHERE id_paciente = ? AND id_odontologo = ? ORDER BY fecha_cita DESC LIMIT 1");
                                $stmt_ultima->bind_param("ii", $paciente['id_paciente'], $id_odontologo);
                                $stmt_ultima->execute();
                                $ultima_cita = $stmt_ultima->get_result()->fetch_assoc();
                            }
                            
                            // Estado badge
                            $estado_class = 'estado-normal';
                            $estado_texto = 'Normal';
                            if ($paciente['estado_cuenta'] == 'observacion') {
                                $estado_class = 'estado-observacion';
                                $estado_texto = 'Observación';
                            } elseif ($paciente['estado_cuenta'] == 'restringida') {
                                $estado_class = 'estado-restringida';
                                $estado_texto = 'Restringida';
                            } elseif ($paciente['estado_cuenta'] == 'bloqueada') {
                                $estado_class = 'estado-bloqueada';
                                $estado_texto = 'Bloqueada';
                            }
                        ?>
                            <tr onclick="window.location.href='paciente_detalle.php?id=<?php echo $paciente['id_paciente']; ?>'">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="bi bi-person-circle fs-4 text-secondary"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($paciente['nombre_completo']); ?></strong>
                                            <?php if ($paciente['fecha_nacimiento']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> 
                                                    <?php echo date('d/m/Y', strtotime($paciente['fecha_nacimiento'])); ?>
                                                    (<?php echo date('Y') - date('Y', strtotime($paciente['fecha_nacimiento'])); ?> años)
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <i class="bi bi-envelope me-1 text-muted"></i>
                                    <?php echo htmlspecialchars($paciente['email']); ?>
                                </td>
                                <td>
                                    <i class="bi bi-telephone me-1 text-muted"></i>
                                    <?php echo $paciente['telefono'] ? htmlspecialchars($paciente['telefono']) : 'No registrado'; ?>
                                </td>
                                <td>
                                    <span class="estado-badge <?php echo $estado_class; ?>">
                                        <?php echo $estado_texto; ?>
                                    </span>
                                    <?php if (!$paciente['puede_agendar']): ?>
                                        <br>
                                        <small class="text-danger">
                                            <i class="bi bi-ban"></i> No puede agendar
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($es_odontologo): ?>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $total_citas; ?> citas</span>
                                        <?php if ($ultima_cita): ?>
                                            <br>
                                            <small class="text-muted">
                                                Última: <?php echo date('d/m/Y', strtotime($ultima_cita['fecha_cita'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($es_odontologo): ?>
                                    <td class="text-nowrap">
                                        <a href="paciente_editar.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                           class="btn btn-sm btn-outline-primary btn-accion"
                                           onclick="event.stopPropagation()">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="paciente_historial.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                           class="btn btn-sm btn-outline-info btn-accion"
                                           onclick="event.stopPropagation()">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="javascript:void(0)" 
                                           onclick="event.stopPropagation(); confirmarEliminacion(<?php echo $paciente['id_paciente']; ?>, '<?php echo addslashes($paciente['nombre_completo']); ?>')"
                                           class="btn btn-sm btn-outline-danger btn-accion">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $es_odontologo ? '6' : '4'; ?>" class="text-center py-5">
                                <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted">No hay pacientes registrados.</p>
                                <?php if ($es_odontologo): ?>
                                    <a href="paciente_nuevo.php" class="btn btn-primary btn-sm">
                                        <i class="bi bi-person-plus"></i> Registrar primer paciente
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?><?php echo $busqueda ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo $busqueda ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?><?php echo $busqueda ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmarEliminacion(id, nombre) {
    if (confirm(`¿Estás seguro de eliminar al paciente "${nombre}"?\n\nEsta acción eliminará todos los datos del paciente.`)) {
        window.location.href = `pacientes.php?eliminar=${id}`;
    }
}
</script>

<?php
require_once '../../includes/footer.php';
?>