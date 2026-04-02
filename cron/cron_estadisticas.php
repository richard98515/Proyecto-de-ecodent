<?php
// cron/cron_estadisticas.php
// Calcula estadísticas mensuales de odontólogos
// Ejecutar: 1 vez al mes o con el CRON diario

require_once __DIR__ . '/../config/database.php';

$conexion = conectarBD();

// Mes y año actual
$mes = date('n');
$anio = date('Y');

echo "========================================\n";
echo "ECO-DENT - Actualizando Estadísticas\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Periodo: {$mes}/{$anio}\n";
echo "========================================\n\n";

// Obtener todos los odontólogos activos
$odontologos = $conexion->query("SELECT id_odontologo FROM odontologos WHERE activo = 1");

if ($odontologos->num_rows == 0) {
    echo "No hay odontólogos activos.\n";
    exit;
}

$actualizados = 0;
$errores = 0;

while ($odonto = $odontologos->fetch_assoc()) {
    $id_odontologo = $odonto['id_odontologo'];
    
    echo "Procesando odontólogo ID: {$id_odontologo}...\n";
    
    // 1. Obtener estadísticas de citas
    $stmt = $conexion->prepare("
        SELECT 
            COUNT(*) as total_citas,
            SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) as ausencias,
            SUM(CASE WHEN llego_tarde = 1 THEN 1 ELSE 0 END) as llegadas_tarde
        FROM citas
        WHERE id_odontologo = ? 
        AND MONTH(fecha_cita) = ? 
        AND YEAR(fecha_cita) = ?
    ");
    $stmt->bind_param("iii", $id_odontologo, $mes, $anio);
    $stmt->execute();
    $citas = $stmt->get_result()->fetch_assoc();
    
    // 2. Obtener ingresos totales
    $stmt2 = $conexion->prepare("
        SELECT COALESCE(SUM(p.monto), 0) as ingresos
        FROM pagos p
        JOIN tratamientos t ON p.id_tratamiento = t.id_tratamiento
        WHERE t.id_odontologo = ? 
        AND MONTH(p.fecha_pago) = ? 
        AND YEAR(p.fecha_pago) = ?
    ");
    $stmt2->bind_param("iii", $id_odontologo, $mes, $anio);
    $stmt2->execute();
    $ingresos = $stmt2->get_result()->fetch_assoc();
    
    // Valores por defecto si no hay citas
    $total_citas = $citas['total_citas'] ?? 0;
    $completadas = $citas['completadas'] ?? 0;
    $ausencias = $citas['ausencias'] ?? 0;
    $llegadas_tarde = $citas['llegadas_tarde'] ?? 0;
    $ingresos_totales = $ingresos['ingresos'] ?? 0;
    
    // Verificar si ya existe registro para este mes
    $check = $conexion->prepare("
        SELECT id_estadistica FROM estadisticas_odontologos 
        WHERE id_odontologo = ? AND mes = ? AND anio = ?
    ");
    $check->bind_param("iii", $id_odontologo, $mes, $anio);
    $check->execute();
    $existe = $check->get_result()->fetch_assoc();
    
    if ($existe) {
        // Actualizar
        $update = $conexion->prepare("
            UPDATE estadisticas_odontologos 
            SET total_citas = ?, 
                citas_completadas = ?, 
                ausencias = ?, 
                llegadas_tarde = ?, 
                ingresos_totales = ?,
                fecha_calculo = NOW()
            WHERE id_odontologo = ? AND mes = ? AND anio = ?
        ");
        $update->bind_param("iiiiiiii", 
            $total_citas, $completadas, $ausencias, $llegadas_tarde, 
            $ingresos_totales, $id_odontologo, $mes, $anio
        );
        
        if ($update->execute()) {
            echo "  ✓ Actualizado: {$total_citas} citas, Bs. {$ingresos_totales}\n";
            $actualizados++;
        } else {
            echo "  ✗ Error al actualizar: " . $conexion->error . "\n";
            $errores++;
        }
    } else {
        // Insertar nuevo
        $insert = $conexion->prepare("
            INSERT INTO estadisticas_odontologos 
            (id_odontologo, mes, anio, total_citas, citas_completadas, ausencias, llegadas_tarde, ingresos_totales)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param("iiiiiiid", 
            $id_odontologo, $mes, $anio, 
            $total_citas, $completadas, $ausencias, $llegadas_tarde, $ingresos_totales
        );
        
        if ($insert->execute()) {
            echo "  ✓ Insertado: {$total_citas} citas, Bs. {$ingresos_totales}\n";
            $actualizados++;
        } else {
            echo "  ✗ Error al insertar: " . $conexion->error . "\n";
            $errores++;
        }
    }
}

echo "\n========================================\n";
echo "RESUMEN FINAL\n";
echo "========================================\n";
echo "✓ Registros actualizados: {$actualizados}\n";
echo "✗ Errores: {$errores}\n";
echo "✅ Proceso completado: " . date('Y-m-d H:i:s') . "\n";
?>