<?php
// includes/cancelaciones.php
// SISTEMA DE CANCELACIONES - PDF Páginas 17-21
// Todas las funciones para manejar cancelaciones de citas

// NOTA: La función desbloquearSlot() está en slots.php, NO LA REPITAS AQUÍ

/**
 * CASO 1: CANCELACIÓN POR PACIENTE
 * El paciente cancela su cita -> el slot queda DISPONIBLE para otros pacientes
 * 
 * @param int $id_cita ID de la cita a cancelar
 * @param int $id_paciente ID del paciente que cancela
 * @param object $conexion Conexión a MySQL
 * @return array Resultado de la operación
 */
function cancelarCitaPaciente($id_cita, $id_paciente, $conexion) {
    
    // PASO 1: Verificar que la cita pertenece al paciente y está activa
    $sql = "SELECT c.*, p.id_usuario 
            FROM citas c
            JOIN pacientes p ON c.id_paciente = p.id_paciente
            WHERE c.id_cita = ? AND p.id_paciente = ? 
            AND c.estado IN ('programada', 'confirmada')";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id_cita, $id_paciente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cita = $resultado->fetch_assoc();
    
    if (!$cita) {
        return [
            'exito' => false,
            'error' => 'Cita no encontrada o no autorizado para cancelar'
        ];
    }
    
    // PASO 2: Cancelar la cita (cambiar estado a cancelada_pac)
    // IMPORTANTE: NO se inserta en slots_bloqueados
    // El slot quedará disponible automáticamente
    $sql2 = "UPDATE citas 
             SET estado = 'cancelada_pac', 
                 cancelado_por = ?, 
                 fecha_cancelacion = NOW() 
             WHERE id_cita = ?";
    
    $stmt2 = $conexion->prepare($sql2);
    $id_usuario = $_SESSION['id_usuario']; // Quién está ejecutando la acción
    $stmt2->bind_param("ii", $id_usuario, $id_cita);
    
    if ($stmt2->execute()) {
        // PASO 3: Registrar en mensajes_pendientes para notificación
        $sql3 = "INSERT INTO mensajes_pendientes 
                 (id_usuario, id_cita, tipo, canal, mensaje, fecha_programado)
                 VALUES (?, ?, 'cancelacion', 'email', ?, NOW())";
        
        $mensaje = "Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.";
        
        $stmt3 = $conexion->prepare($sql3);
        $stmt3->bind_param("iis", $cita['id_usuario'], $id_cita, $mensaje);
        $stmt3->execute();
        
        return [
            'exito' => true,
            'mensaje' => 'Cita cancelada. El horario queda disponible para otros pacientes.'
        ];
    } else {
        return [
            'exito' => false,
            'error' => 'Error al cancelar la cita'
        ];
    }
}

/**
 * CASO 2: CANCELACIÓN POR ODONTÓLOGO
 * El odontólogo cancela por emergencia -> el slot queda BLOQUEADO
 * 
 * @param int $id_cita ID de la cita a cancelar
 * @param int $id_odontologo ID del odontólogo
 * @param string $motivo Motivo de la cancelación
 * @param array $opciones_reprogramacion Array con fechas/horas alternativas
 * @param object $conexion Conexión a MySQL
 * @return array Resultado de la operación
 */
function cancelarCitaOdontologo($id_cita, $id_odontologo, $motivo, $opciones_reprogramacion, $conexion) {
    
    // INICIAR TRANSACCIÓN (TODO O NADA)
    $conexion->begin_transaction();
    
    try {
        // PASO 1: Obtener datos de la cita
        $sql = "SELECT c.*, p.id_usuario as id_usuario_paciente 
                FROM citas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                WHERE c.id_cita = ? AND c.id_odontologo = ? 
                AND c.estado IN ('programada', 'confirmada')";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $id_cita, $id_odontologo);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $cita = $resultado->fetch_assoc();
        
        if (!$cita) {
            throw new Exception('Cita no encontrada o no autorizado para cancelar');
        }
        
        // PASO 2: Calcular hora_fin del slot (hora_inicio + 40 minutos)
        $hora_fin = date('H:i:s', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']) + (40 * 60));
        
        // PASO 3: Cambiar estado de la cita a cancelada_doc
        $sql2 = "UPDATE citas 
                 SET estado = 'cancelada_doc', 
                     cancelado_por = ?, 
                     fecha_cancelacion = NOW(), 
                     motivo_cancelacion = ?,
                     notificacion_enviada = 0
                 WHERE id_cita = ?";
        
        $stmt2 = $conexion->prepare($sql2);
        $id_usuario = $_SESSION['id_usuario']; // El odontólogo logueado
        $stmt2->bind_param("isi", $id_usuario, $motivo, $id_cita);
        $stmt2->execute();
        
        // PASO 4: ★ BLOQUEAR EL SLOT AUTOMÁTICAMENTE ★
        $sql3 = "INSERT INTO slots_bloqueados 
                 (id_odontologo, fecha, hora_inicio, hora_fin, motivo) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $motivo_bloqueo = 'Cancelación por odontólogo: ' . $motivo;
        $stmt3 = $conexion->prepare($sql3);
        $stmt3->bind_param("issss", 
            $id_odontologo, 
            $cita['fecha_cita'], 
            $cita['hora_cita'], 
            $hora_fin, 
            $motivo_bloqueo
        );
        $stmt3->execute();
        
        // PASO 5: Guardar opciones de reprogramación para el paciente
        foreach ($opciones_reprogramacion as $opcion) {
            $sql4 = "INSERT INTO opciones_reprogramacion_cita 
                     (id_cita_original, fecha_propuesta, hora_propuesta, hora_propuesta_fin, id_odontologo) 
                     VALUES (?, ?, ?, ?, ?)";
            
            $stmt4 = $conexion->prepare($sql4);
            $stmt4->bind_param("isssi", 
                $id_cita, 
                $opcion['fecha'], 
                $opcion['hora'],
                $opcion['hora_fin'],
                $id_odontologo
            );
            $stmt4->execute();
        }
        
        // PASO 6: Generar mensaje para notificación
        $sql5 = "INSERT INTO mensajes_pendientes 
                 (id_usuario, id_cita, tipo, canal, mensaje, fecha_programado)
                 VALUES (?, ?, 'cancelacion_doctor', 'email', ?, NOW())";
        
        $mensaje = "Tu cita ha sido cancelada por el odontólogo. Motivo: $motivo. 
                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.";
        
        $stmt5 = $conexion->prepare($sql5);
        $stmt5->bind_param("iis", $cita['id_usuario_paciente'], $id_cita, $mensaje);
        $stmt5->execute();
        
        // SI TODO SALIÓ BIEN, CONFIRMAR TRANSACCIÓN
        $conexion->commit();
        
        return [
            'exito' => true,
            'mensaje' => 'Cita cancelada. Slot bloqueado. Paciente notificado.'
        ];
        
    } catch (Exception $e) {
        // SI ALGO FALLÓ, DESHACER TODO
        $conexion->rollback();
        
        return [
            'exito' => false,
            'error' => 'Error al cancelar: ' . $e->getMessage()
        ];
    }
}

/**
 * FUNCIÓN: obtenerOpcionesReprogramacion
 * Obtiene las opciones de reprogramación para una cita cancelada
 * 
 * @param int $id_cita ID de la cita original
 * @param object $conexion Conexión MySQL
 * @return array Opciones de reprogramación
 */
function obtenerOpcionesReprogramacion($id_cita, $conexion) {
    
    $sql = "SELECT * FROM opciones_reprogramacion_cita 
            WHERE id_cita_original = ? AND seleccionada = FALSE
            ORDER BY fecha_propuesta ASC, hora_propuesta ASC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $opciones = [];
    while ($row = $resultado->fetch_assoc()) {
        $opciones[] = $row;
    }
    
    return $opciones;
}

/**
 * FUNCIÓN: aceptarReprogramacion
 * El paciente acepta una de las opciones de reprogramación
 * 
 * @param int $id_opcion ID de la opción seleccionada
 * @param int $id_paciente ID del paciente
 * @param object $conexion Conexión MySQL
 * @return array Resultado de la operación
 */
function aceptarReprogramacion($id_opcion, $id_paciente, $conexion) {
    
    $conexion->begin_transaction();
    
    try {
        // Obtener la opción seleccionada
        $sql = "SELECT o.*, c.id_paciente, c.motivo 
                FROM opciones_reprogramacion_cita o
                JOIN citas c ON o.id_cita_original = c.id_cita
                WHERE o.id_opcion = ? AND c.id_paciente = ?";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $id_opcion, $id_paciente);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $opcion = $resultado->fetch_assoc();
        
        if (!$opcion) {
            throw new Exception('Opción no válida');
        }
        
        // Marcar la opción como seleccionada
        $sql2 = "UPDATE opciones_reprogramacion_cita 
                 SET seleccionada = TRUE 
                 WHERE id_opcion = ?";
        
        $stmt2 = $conexion->prepare($sql2);
        $stmt2->bind_param("i", $id_opcion);
        $stmt2->execute();
        
        // Crear la nueva cita
        $sql3 = "INSERT INTO citas 
                 (id_paciente, id_odontologo, fecha_cita, hora_cita, hora_fin, motivo, estado) 
                 VALUES (?, ?, ?, ?, ?, ?, 'programada')";
        
        $stmt3 = $conexion->prepare($sql3);
        $stmt3->bind_param("iissss", 
            $opcion['id_paciente'], 
            $opcion['id_odontologo'], 
            $opcion['fecha_propuesta'], 
            $opcion['hora_propuesta'],
            $opcion['hora_propuesta_fin'],
            $opcion['motivo']
        );
        $stmt3->execute();
        
        $conexion->commit();
        
        return [
            'exito' => true,
            'mensaje' => 'Cita reprogramada exitosamente'
        ];
        
    } catch (Exception $e) {
        $conexion->rollback();
        
        return [
            'exito' => false,
            'error' => 'Error al reprogramar: ' . $e->getMessage()
        ];
    }
}

// FIN DEL ARCHIVO - NO HAY MÁS FUNCIONES
?>