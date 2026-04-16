<?php
// public/odontologo/pago_nuevo.php
// Registrar nuevo pago para tratamiento - CON REDIMENSIONAMIENTO DE IMÁGENES

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

// VERIFICACIÓN MODIFICADA - Permite admin y odontólogo
if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

if (!esAdmin() && !esOdontologo()) {
    redirigir('/ecodent/public/index.php');
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_odontologo si es odontólogo
if (esOdontologo()) {
    $stmt = $conexion->prepare("SELECT id_odontologo FROM odontologos WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $odontologo = $resultado->fetch_assoc();
    $id_odontologo = $odontologo['id_odontologo'];
} else {
    $id_odontologo = null; // Admin no tiene restricción por odontólogo
}

// Verificar parámetros
if (!isset($_GET['tratamiento']) || !is_numeric($_GET['tratamiento']) || !isset($_GET['paciente']) || !is_numeric($_GET['paciente'])) {
    $_SESSION['error'] = "Parámetros no válidos.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$id_tratamiento = $_GET['tratamiento'];
$id_paciente = $_GET['paciente'];

// Obtener datos del tratamiento - MODIFICADO para admin
if (esAdmin()) {
    // Admin ve cualquier tratamiento
    $stmt_tratamiento = $conexion->prepare("
        SELECT t.*, u.nombre_completo as paciente_nombre
        FROM tratamientos t
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE t.id_tratamiento = ?
    ");
    $stmt_tratamiento->bind_param("i", $id_tratamiento);
} else {
    // Odontólogo solo ve sus tratamientos
    $stmt_tratamiento = $conexion->prepare("
        SELECT t.*, u.nombre_completo as paciente_nombre
        FROM tratamientos t
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE t.id_tratamiento = ? AND t.id_odontologo = ?
    ");
    $stmt_tratamiento->bind_param("ii", $id_tratamiento, $id_odontologo);
}

$stmt_tratamiento->execute();
$tratamiento = $stmt_tratamiento->get_result()->fetch_assoc();

if (!$tratamiento) {
    $_SESSION['error'] = "Tratamiento no encontrado o no tienes permisos para verlo.";
    redirigir('/ecodent/public/odontologo/pacientes.php');
}

$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $monto = (float)$_POST['monto'];
    $concepto = sanitizar($_POST['concepto']);
    $metodo_pago = $_POST['metodo_pago'];
    $observaciones = sanitizar($_POST['observaciones']);
    
    // Validaciones
    if ($monto <= 0) {
        $errores[] = "El monto debe ser mayor a 0.";
    }
    
    if ($monto > $tratamiento['saldo_pendiente']) {
        $errores[] = "El monto excede el saldo pendiente (S/. " . number_format($tratamiento['saldo_pendiente'], 2) . ").";
    }
    
    if (empty($concepto)) {
        $errores[] = "El concepto es obligatorio.";
    }
    
    // =============================================
    // PROCESAR COMPROBANTE CON REDIMENSIONAMIENTO
    // =============================================
    $foto_comprobante = null;
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] == 0) {
        
        // PASO 1 y 2: Validar tipo MIME real (no solo extensión)
        $tipos_permitidos = ['image/jpeg', 'image/png'];
        $tipo_real = mime_content_type($_FILES['comprobante']['tmp_name']);
        
        if (!in_array($tipo_real, $tipos_permitidos)) {
            $errores[] = "Solo se permiten imágenes JPG o PNG. El archivo subido es: " . $tipo_real;
        }
        
        // PASO 3: Validar tamaño máximo 5MB
        elseif ($_FILES['comprobante']['size'] > 5 * 1024 * 1024) {
            $errores[] = "La imagen no debe superar 5MB. Tamaño actual: " . round($_FILES['comprobante']['size'] / 1024 / 1024, 2) . "MB";
        }
        
        // Si no hay errores, procesar la imagen
        if (empty($errores)) {
            // Crear directorio si no existe
            $directorio = $_SERVER['DOCUMENT_ROOT'] . '/ecodent/uploads/comprobantes/';
            if (!file_exists($directorio)) {
                mkdir($directorio, 0777, true);
            }
            
            // PASO 4 y 5: Redimensionar a máximo 1280px y comprimir
            // Obtener dimensiones originales
            list($ancho_orig, $alto_orig) = getimagesize($_FILES['comprobante']['tmp_name']);
            $ancho_max = 1280;
            
            if ($ancho_orig > $ancho_max) {
                // Calcular nuevo alto manteniendo proporción
                $alto_nuevo = (int)($alto_orig * $ancho_max / $ancho_orig);
                $ancho_nuevo = $ancho_max;
            } else {
                // Si ya es menor a 1280px, mantener tamaño original
                $ancho_nuevo = $ancho_orig;
                $alto_nuevo = $alto_orig;
            }
            
            // Crear imagen desde el archivo original
            if ($tipo_real === 'image/jpeg') {
                $img_original = imagecreatefromjpeg($_FILES['comprobante']['tmp_name']);
            } else {
                $img_original = imagecreatefrompng($_FILES['comprobante']['tmp_name']);
                // Preservar transparencia PNG
                imagealphablending($img_original, false);
                imagesavealpha($img_original, true);
            }
            
            // Crear lienzo con las nuevas dimensiones
            $img_nueva = imagecreatetruecolor($ancho_nuevo, $alto_nuevo);
            
            // Preservar transparencia para PNG
            if ($tipo_real === 'image/png') {
                imagealphablending($img_nueva, false);
                imagesavealpha($img_nueva, true);
                $transparent = imagecolorallocatealpha($img_nueva, 255, 255, 255, 127);
                imagefilledrectangle($img_nueva, 0, 0, $ancho_nuevo, $alto_nuevo, $transparent);
            }
            
            // Redimensionar manteniendo calidad
            imagecopyresampled(
                $img_nueva, $img_original,
                0, 0, 0, 0,
                $ancho_nuevo, $alto_nuevo,
                $ancho_orig, $alto_orig
            );
            
            // PASO 6: Generar nombre seguro
            $extension = ($tipo_real === 'image/jpeg') ? 'jpg' : 'png';
            $nombre_archivo = 'pago_' . time() . '_' . $id_tratamiento . '.' . $extension;
            $ruta_completa = $directorio . $nombre_archivo;
            
            // PASO 7: Guardar archivo comprimido
            if ($extension === 'jpg') {
                imagejpeg($img_nueva, $ruta_completa, 75); // Calidad 75% - según tu guía
            } else {
                imagepng($img_nueva, $ruta_completa, 6); // Compresión 6 - según tu guía
            }
            
            // Liberar memoria
            imagedestroy($img_original);
            imagedestroy($img_nueva);
            
            // Verificar que se guardó correctamente
            if (file_exists($ruta_completa)) {
                // PASO 8: Guardar ruta en BD (SOLO la ruta, no la imagen)
                $foto_comprobante = '/ecodent/uploads/comprobantes/' . $nombre_archivo;
                
                // Registrar en log el tamaño final
                $tamano_final = filesize($ruta_completa);
                error_log("Imagen procesada: Original " . round($_FILES['comprobante']['size']/1024,2) . 
                          "KB -> Final " . round($tamano_final/1024,2) . "KB");
            } else {
                $errores[] = "Error al guardar la imagen procesada.";
            }
        }
    }
    
    // Si no hay errores, insertar
    if (empty($errores)) {
        $conexion->begin_transaction();
        
        try {
            // Insertar pago
            // INSERT CORREGIDO
            // Insertar pago (SIN id_paciente)
            $stmt_pago = $conexion->prepare("
                INSERT INTO pagos (id_tratamiento, id_usuario_registro, monto, concepto, 
                                fecha_pago, foto_comprobante, observaciones, metodo_pago)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $fecha_pago = date('Y-m-d');
            $stmt_pago->bind_param("iidsssss", 
                $id_tratamiento, $id_usuario, $monto, $concepto,
                $fecha_pago, $foto_comprobante, $observaciones, $metodo_pago
            );
            $stmt_pago->execute();
            
            // Actualizar total pagado en tratamiento
            $nuevo_total = $tratamiento['total_pagado'] + $monto;
            $stmt_update = $conexion->prepare("
                UPDATE tratamientos 
                SET total_pagado = ?
                WHERE id_tratamiento = ?
            ");
            $stmt_update->bind_param("di", $nuevo_total, $id_tratamiento);
            $stmt_update->execute();
            
            $conexion->commit();
            $_SESSION['exito'] = "Pago registrado correctamente. Nuevo saldo: S/. " . number_format($tratamiento['saldo_pendiente'] - $monto, 2);
            redirigir('/ecodent/public/odontologo/tratamiento_detalle.php?id=' . $id_tratamiento);
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error al registrar: " . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
?>

<style>
.form-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.resumen-pago {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.saldo-number {
    font-size: 2rem;
    font-weight: bold;
}
</style>

<div class="row mb-3">
    <div class="col-md-8">
        <h1><i class="bi bi-cash-stack text-success"></i> Registrar Pago</h1>
        <p class="lead">Registra un nuevo pago para el tratamiento.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="tratamiento_detalle.php?id=<?php echo $id_tratamiento; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver al tratamiento
        </a>
    </div>
</div>

<div class="resumen-pago">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h5><?php echo htmlspecialchars($tratamiento['nombre_tratamiento']); ?></h5>
            <p class="mb-0">Paciente: <?php echo htmlspecialchars($tratamiento['paciente_nombre']); ?></p>
        </div>
        <div class="col-md-6 text-end">
            <div>Saldo pendiente</div>
            <div class="saldo-number">S/. <?php echo number_format($tratamiento['saldo_pendiente'], 2); ?></div>
        </div>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0 mt-2">
            <?php foreach ($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="form-section">
        <h5><i class="bi bi-info-circle me-2 text-primary"></i> Información del pago</h5>
        <hr>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Monto *</label>
                <div class="input-group">
                    <span class="input-group-text">S/.</span>
                    <input type="number" class="form-control" name="monto" step="0.01" required
                           max="<?php echo $tratamiento['saldo_pendiente']; ?>"
                           value="<?php echo isset($_POST['monto']) ? $_POST['monto'] : ''; ?>">
                </div>
                <small class="text-muted">Máximo: S/. <?php echo number_format($tratamiento['saldo_pendiente'], 2); ?></small>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Método de pago *</label>
                <select class="form-select" name="metodo_pago" required>
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta">Tarjeta de crédito/débito</option>
                    <option value="transferencia">Transferencia bancaria</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Concepto *</label>
            <input type="text" class="form-control" name="concepto" required
                   placeholder="Ej: Pago inicial, Cuota 1, Pago completo..."
                   value="<?php echo isset($_POST['concepto']) ? htmlspecialchars($_POST['concepto']) : ''; ?>">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" name="observaciones" rows="2" 
                      placeholder="Información adicional sobre el pago..."><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Comprobante (opcional)</label>
            <input type="file" class="form-control" name="comprobante" accept="image/jpeg,image/png">
            <small class="text-muted">
                Formatos permitidos: JPG, PNG. Máximo 5MB. 
                La imagen se redimensionará automáticamente a máximo 1280px y se comprimirá al 75%.
            </small>
        </div>
    </div>
    
    <div class="text-end">
        <a href="tratamiento_detalle.php?id=<?php echo $id_tratamiento; ?>" class="btn btn-secondary btn-lg">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-success btn-lg">
            <i class="bi bi-cash-stack"></i> Registrar pago
        </button>
    </div>
</form>

<?php
require_once '../../includes/footer.php';
?>