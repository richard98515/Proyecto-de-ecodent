<?php
// cron/cron_generar_alertas.php - VERSIÓN SIMPLIFICADA
// Solo trabaja con la tabla pacientes

require_once __DIR__ . '/../config/database.php';

$conexion = conectarBD();

echo "========================================\n";
echo "ECO-DENT - Generando Alertas Automáticas\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Obtener reglas activas
$reglas = $conexion->query("SELECT * FROM reglas_alertas WHERE activa = 1");

if ($reglas->num_rows == 0) {
    echo "⚠️ No hay reglas de alertas activas.\n";
    exit;
}

echo "Reglas activas: " . $reglas->num_rows . "\n\n";

// Obtener ID del admin
$stmt_admin = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'admin' LIMIT 1");
$stmt_admin->execute();
$admin = $stmt_admin->get_result()->fetch_assoc();
$id_admin = $admin ? $admin['id_usuario'] : 8;

echo "Admin ID: {$id_admin}\n\n";

$alertas_generadas = 0;

while ($regla = $reglas->fetch_assoc()) {
    echo "----------------------------------------\n";
    echo "📋 {$regla['nombre']}\n";
    echo "   Condición: {$regla['condicion']}\n";
    
    // Verificar si ya hay alerta hoy para esta regla
    $check = $conexion->prepare("
        SELECT id_alerta FROM alertas 
        WHERE id_regla = ? AND DATE(fecha_creacion) = CURDATE()
        LIMIT 1
    ");
    $check->bind_param("i", $regla['id_regla']);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        echo "   ⏭️ Ya se generó alerta hoy\n";
        continue;
    }
    
    // Buscar pacientes que cumplan la condición
    $sql = "
        SELECT p.id_paciente, u.nombre_completo, 
               p.ausencias_sin_aviso, p.llegadas_tarde, p.estado_cuenta
        FROM pacientes p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE {$regla['condicion']}
    ";
    
    $result = $conexion->query($sql);
    
    if (!$result) {
        echo "   ❌ Error: " . $conexion->error . "\n";
        continue;
    }
    
    if ($result->num_rows > 0) {
        echo "   🎯 Afectados: " . $result->num_rows . "\n";
        
        while ($paciente = $result->fetch_assoc()) {
            $mensaje = str_replace(
                ['{nombre_paciente}', '{ausencias}', '{llegadas_tarde}'],
                [
                    $paciente['nombre_completo'],
                    $paciente['ausencias_sin_aviso'],
                    $paciente['llegadas_tarde']
                ],
                $regla['mensaje']
            );
            
            $titulo = "⚠️ {$regla['nombre']} - {$paciente['nombre_completo']}";
            
            // Determinar tipo
            $tipo = 'warning';
            if ($paciente['estado_cuenta'] == 'bloqueada') {
                $tipo = 'danger';
            } elseif ($paciente['ausencias_sin_aviso'] >= 6) {
                $tipo = 'danger';
            } elseif ($paciente['llegadas_tarde'] >= 5) {
                $tipo = 'warning';
            }
            
            $insert = $conexion->prepare("
                INSERT INTO alertas (id_usuario, id_regla, titulo, mensaje, tipo, leida)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $insert->bind_param("iisss", $id_admin, $regla['id_regla'], $titulo, $mensaje, $tipo);
            
            if ($insert->execute()) {
                $alertas_generadas++;
                echo "      ✅ Alerta: {$paciente['nombre_completo']}\n";
            }
        }
    } else {
        echo "   ✅ Sin pacientes\n";
    }
}

echo "\n========================================\n";
echo "✅ Alertas generadas: {$alertas_generadas}\n";
echo "📅 " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";
?>