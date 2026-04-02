<?php
// cron/cron_backup.php
// Realiza backup automático de la base de datos
// Ejecutar: DIARIO (1 vez al día)

require_once __DIR__ . '/../config/database.php';

$conexion = conectarBD();

// Configuración
$backup_dir = __DIR__ . '/../backups/';
$fecha = date('Y-m-d_H-i-s');

// Determinar tipo de backup (diario, semanal, mensual)
$dia_semana = date('w'); // 0=domingo, 1=lunes...
$dia_mes = date('j');

if ($dia_mes == 1) {
    $tipo = 'mensual';
} elseif ($dia_semana == 0) {
    $tipo = 'semanal';
} else {
    $tipo = 'diario';
}

echo "========================================\n";
echo "ECO-DENT - Backup Automático\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Tipo: {$tipo}\n";
echo "========================================\n\n";

// Crear directorio si no existe
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
    echo "✓ Directorio de backups creado: {$backup_dir}\n";
}

// Nombre del archivo
$nombre_archivo = "ecodent_backup_{$tipo}_{$fecha}.sql";
$ruta_completa = $backup_dir . $nombre_archivo;

// Configuración de MySQL (ajusta según tu XAMPP)
$host = 'localhost';
$user = 'root';
$pass = ''; // Si tienes contraseña, ponla aquí
$dbname = 'ecodent';

// Ruta de mysqldump en XAMPP
$mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

// Comando mysqldump
$comando = "\"{$mysqldump_path}\" --host={$host} --user={$user} --password={$pass} {$dbname} > \"{$ruta_completa}\" 2>&1";

echo "Ejecutando backup...\n";
exec($comando, $output, $return_code);

if ($return_code === 0 && file_exists($ruta_completa)) {
    $tamano = filesize($ruta_completa);
    
    // Registrar en la tabla backups
    $stmt = $conexion->prepare("
        INSERT INTO backups (nombre_archivo, tipo, tamano, ruta, estado, fecha_creacion)
        VALUES (?, ?, ?, ?, 'exitoso', NOW())
    ");
    $stmt->bind_param("ssis", $nombre_archivo, $tipo, $tamano, $ruta_completa);
    
    if ($stmt->execute()) {
        echo "✓ Backup exitoso!\n";
        echo "  Archivo: {$nombre_archivo}\n";
        echo "  Tamaño: " . number_format($tamano / 1024, 2) . " KB\n";
        echo "  Ruta: {$ruta_completa}\n";
    } else {
        echo "✗ Error al registrar en BD: " . $conexion->error . "\n";
    }
    
    // Limpiar backups antiguos (mantener últimos 30 días para diarios)
    if ($tipo == 'diario') {
        $fecha_limite = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Obtener backups antiguos para eliminar
        $antiguos = $conexion->query("
            SELECT id_backup, ruta FROM backups 
            WHERE tipo = 'diario' AND fecha_creacion < '{$fecha_limite}'
        ");
        
        $eliminados = 0;
        while ($old = $antiguos->fetch_assoc()) {
            // Eliminar archivo físico
            if (file_exists($old['ruta'])) {
                unlink($old['ruta']);
            }
            // Eliminar registro
            $conexion->query("DELETE FROM backups WHERE id_backup = {$old['id_backup']}");
            $eliminados++;
        }
        
        if ($eliminados > 0) {
            echo "\n✓ Limpieza de backups antiguos: {$eliminados} archivos eliminados\n";
        }
    }
    
} else {
    // Registrar error
    $error_msg = implode("\n", $output);
    echo "✗ Error en backup!\n";
    echo "  Código de retorno: {$return_code}\n";
    echo "  Error: {$error_msg}\n";
    
    $stmt = $conexion->prepare("
        INSERT INTO backups (nombre_archivo, tipo, estado, fecha_creacion)
        VALUES (?, ?, 'fallido', NOW())
    ");
    $stmt->bind_param("ss", $nombre_archivo, $tipo);
    $stmt->execute();
}

echo "\n✅ Proceso finalizado: " . date('Y-m-d H:i:s') . "\n";
?>