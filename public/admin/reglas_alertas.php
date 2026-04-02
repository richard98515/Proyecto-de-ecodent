<?php
// public/admin/reglas_alertas.php
// Configurar reglas automáticas de alertas

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

// Insertar reglas por defecto si no existen
$check = $conexion->query("SELECT COUNT(*) as total FROM reglas_alertas");
$total = $check->fetch_assoc()['total'];

if ($total == 0) {
    $reglas = [
        ['Paciente con 3+ ausencias', 'ausencias_sin_aviso >= 3', 'El paciente tiene {ausencias} ausencias sin aviso. Revisar estado de cuenta.', 1],
        ['Paciente con llegadas tarde', 'llegadas_tarde >= 3', 'El paciente ha llegado tarde {llegadas_tarde} veces.', 1],
        ['Saldo pendiente alto', 'saldo_pendiente > 500', 'Saldo pendiente: Bs. {saldo_pendiente}. Gestionar cobro.', 1],
        ['Tratamiento atrasado', 'fecha_fin < CURDATE() AND estado != "completado"', 'Tratamiento "{nombre_tratamiento}" atrasado.', 1],
        ['Cita próxima sin confirmar', 'fecha_cita = CURDATE() + 1 AND estado = "programada"', 'Cita sin confirmar para mañana.', 1]
    ];
    
    $stmt = $conexion->prepare("INSERT INTO reglas_alertas (nombre, condicion, mensaje, activa) VALUES (?, ?, ?, ?)");
    foreach ($reglas as $regla) {
        $stmt->bind_param("sssi", $regla[0], $regla[1], $regla[2], $regla[3]);
        $stmt->execute();
    }
}

// Actualizar estado activa/inactiva
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conexion->prepare("UPDATE reglas_alertas SET activa = NOT activa WHERE id_regla = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    redirigir('/ecodent/public/admin/reglas_alertas.php');
}

$reglas = $conexion->query("SELECT * FROM reglas_alertas ORDER BY id_regla");

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <h1><i class="bi bi-bell"></i> Reglas de Alertas</h1>
    <p class="text-muted">Estas reglas se ejecutan automáticamente cada noche para generar alertas.</p>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Condición SQL</th>
                        <th>Mensaje</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($regla = $reglas->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $regla['id_regla']; ?></td>
                        <td><strong><?php echo htmlspecialchars($regla['nombre']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($regla['condicion']); ?></code></td>
                        <td><?php echo htmlspecialchars($regla['mensaje']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $regla['activa'] ? 'success' : 'secondary'; ?>">
                                <?php echo $regla['activa'] ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?toggle=<?php echo $regla['id_regla']; ?>" class="btn btn-sm btn-<?php echo $regla['activa'] ? 'warning' : 'success'; ?>">
                                <i class="bi bi-<?php echo $regla['activa'] ? 'pause' : 'play'; ?>"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>