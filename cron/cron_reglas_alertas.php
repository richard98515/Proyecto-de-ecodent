<?php
// cron/cron_reglas_alertas.php
// Verifica y crea reglas de alertas por defecto si no existen

require_once __DIR__ . '/../config/database.php';

$conexion = conectarBD();

echo "========================================\n";
echo "ECO-DENT - Verificando Reglas de Alertas\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Verificar si hay reglas
$result = $conexion->query("SELECT COUNT(*) as total FROM reglas_alertas");
$total = $result->fetch_assoc()['total'];

if ($total == 0) {
    echo "No hay reglas de alertas. Creando reglas por defecto...\n\n";
    
    $reglas = [
        ['Paciente con 3+ ausencias', 'ausencias_sin_aviso >= 3', 'El paciente tiene {ausencias} ausencias sin aviso. Revisar estado de cuenta.', 1],
        ['Paciente con llegadas tarde', 'llegadas_tarde >= 3', 'El paciente ha llegado tarde {llegadas_tarde} veces. Considerar recordatorio.', 1],
        ['Saldo pendiente alto', 'saldo_pendiente > 500', 'El paciente tiene un saldo pendiente de Bs. {saldo_pendiente}. Gestionar cobro.', 1],
        ['Tratamiento atrasado', 'fecha_fin < CURDATE() AND estado != "completado"', 'El tratamiento "{nombre_tratamiento}" está atrasado.', 1],
        ['Cita próxima sin confirmar', 'fecha_cita = CURDATE() + 1 AND estado = "programada"', 'Cita programada para mañana sin confirmar.', 1]
    ];
    
    $stmt = $conexion->prepare("INSERT INTO reglas_alertas (nombre, condicion, mensaje, activa) VALUES (?, ?, ?, ?)");
    
    foreach ($reglas as $regla) {
        $stmt->bind_param("sssi", $regla[0], $regla[1], $regla[2], $regla[3]);
        if ($stmt->execute()) {
            echo "  ✓ Creada regla: {$regla[0]}\n";
        } else {
            echo "  ✗ Error al crear: {$regla[0]}\n";
        }
    }
    
    echo "\n✅ Se crearon " . count($reglas) . " reglas de alertas.\n";
} else {
    echo "✓ Ya existen {$total} reglas de alertas.\n";
}

echo "\n✅ Proceso finalizado: " . date('Y-m-d H:i:s') . "\n";
?>