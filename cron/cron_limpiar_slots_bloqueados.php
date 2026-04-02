<?php
// cron/cron_limpiar_slots_bloqueados.php
// Elimina slots bloqueados que ya pasaron (fecha < hoy)
// Ejecutar: DIARIAMENTE a las 00:00 (medianoche)

require_once __DIR__ . '/../config/database.php';

$conexion = conectarBD();

echo "========================================\n";
echo "ECO-DENT - Limpiando slots_bloqueados\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Contar antes de limpiar
$result = $conexion->query("SELECT COUNT(*) as total FROM slots_bloqueados");
$antes = $result->fetch_assoc()['total'];
echo "Slots bloqueados antes: {$antes}\n";

// Eliminar slots con fecha anterior a hoy
$hoy = date('Y-m-d');
$stmt = $conexion->prepare("DELETE FROM slots_bloqueados WHERE fecha < ?");
$stmt->bind_param("s", $hoy);
$stmt->execute();
$eliminados = $stmt->affected_rows;

echo "Slots eliminados: {$eliminados}\n";

// Contar después
$result = $conexion->query("SELECT COUNT(*) as total FROM slots_bloqueados");
$despues = $result->fetch_assoc()['total'];
echo "Slocks bloqueados después: {$despues}\n";

echo "\n✅ Limpieza completada: " . date('Y-m-d H:i:s') . "\n";
?>