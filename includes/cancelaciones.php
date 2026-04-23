<?php
// includes/cancelaciones.php
// SISTEMA DE CANCELACIONES - PDF Páginas 17-21

/**
 * CASO 1: CANCELACIÓN POR PACIENTE
 * El slot queda DISPONIBLE para otros pacientes
 */
function cancelarCitaPaciente($id_cita, $id_paciente, $conexion) {
    
    $sql = "SELECT c.*, p.id_usuario, u.email, u.telefono, u.nombre_completo
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE c.id_cita = ? AND p.id_paciente = ? 
        AND c.estado IN ('programada', 'confirmada')";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id_cita, $id_paciente);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    
    if (!$cita) {
        return ['exito' => false, 'error' => 'Cita no encontrada o no autorizado'];
    }
    
    $sql2 = "UPDATE citas 
             SET estado = 'cancelada_pac', 
                 cancelado_por = ?, 
                 fecha_cancelacion = NOW() 
             WHERE id_cita = ?";
    
    $stmt2 = $conexion->prepare($sql2);
    $id_usuario = $_SESSION['id_usuario'];
    $stmt2->bind_param("ii", $id_usuario, $id_cita);
    
    if ($stmt2->execute()) {

        // ✅ Ahora con email_destino y telefono_destino
        $sql3 = "INSERT INTO mensajes_pendientes 
                 (id_usuario, id_cita, tipo, canal, mensaje, email_destino, telefono_destino, fecha_programado)
                 VALUES (?, ?, 'cancelacion', 'email', ?, ?, ?, NOW())";
        
        $mensaje = "Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.";
        
        $stmt3 = $conexion->prepare($sql3);
        $stmt3->bind_param("iisss", 
            $cita['id_usuario'], 
            $id_cita, 
            $mensaje,
            $cita['email'],      // ← email_destino
            $cita['telefono']    // ← telefono_destino
        );
        $stmt3->execute();
        
        return [
            'exito' => true,
            'mensaje' => 'Cita cancelada. El horario queda disponible para otros pacientes.'
        ];
    } else {
        return ['exito' => false, 'error' => 'Error al cancelar la cita'];
    }
}

/**
 * CASO 2: CANCELACIÓN POR ODONTÓLOGO
 * El slot queda BLOQUEADO + mensaje WhatsApp generado
 */
function cancelarCitaOdontologo($id_cita, $id_odontologo, $motivo, $opciones_reprogramacion, $conexion) {
    
    $conexion->begin_transaction();
    
    try {
        // PASO 1: Obtener datos de la cita + paciente
        $sql = "SELECT c.*, 
               p.id_usuario as id_usuario_paciente,
               u.email as email_paciente,
               u.telefono as telefono_paciente,
               u.nombre_completo as nombre_paciente,
               u2.nombre_completo as nombre_odontologo
        FROM citas c
        JOIN tratamientos t ON c.id_tratamiento = t.id_tratamiento
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN odontologos o ON t.id_odontologo = o.id_odontologo
        JOIN usuarios u2 ON o.id_usuario = u2.id_usuario
        WHERE c.id_cita = ? AND t.id_odontologo = ? 
        AND c.estado IN ('programada', 'confirmada')";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $id_cita, $id_odontologo);
        $stmt->execute();
        $cita = $stmt->get_result()->fetch_assoc();
        
        if (!$cita) throw new Exception('Cita no encontrada o no autorizado');
        
        // PASO 2: Calcular hora_fin del slot
        $hora_fin = date('H:i:s', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita']) + (40 * 60));
        
        // PASO 3: Cambiar estado a cancelada_doc
        $sql2 = "UPDATE citas 
                 SET estado = 'cancelada_doc', 
                     cancelado_por = ?, 
                     fecha_cancelacion = NOW(), 
                     motivo_cancelacion = ?,
                     notificacion_enviada = 0
                 WHERE id_cita = ?";
        
        $stmt2 = $conexion->prepare($sql2);
        $id_usuario = $_SESSION['id_usuario'];
        $stmt2->bind_param("isi", $id_usuario, $motivo, $id_cita);
        $stmt2->execute();
        
        // PASO 4: ★ BLOQUEAR EL SLOT ★
        $sql3 = "INSERT INTO slots_bloqueados 
                 (id_odontologo, fecha, hora_inicio, hora_fin, motivo) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $motivo_bloqueo = 'Cancelación por odontólogo: ' . $motivo;
        $stmt3 = $conexion->prepare($sql3);
        $stmt3->bind_param("issss", $id_odontologo, $cita['fecha_cita'], $cita['hora_cita'], $hora_fin, $motivo_bloqueo);
        $stmt3->execute();
        
        // PASO 5: Guardar opciones de reprogramación
        foreach ($opciones_reprogramacion as $opcion) {
            $sql4 = "INSERT INTO opciones_reprogramacion_cita 
                     (id_cita_original, fecha_propuesta, hora_propuesta, hora_propuesta_fin, id_odontologo) 
                     VALUES (?, ?, ?, ?, ?)";
            
            $stmt4 = $conexion->prepare($sql4);
            $stmt4->bind_param("isssi", $id_cita, $opcion['fecha'], $opcion['hora'], $opcion['hora_fin'], $id_odontologo);
            $stmt4->execute();
        }
        
        // PASO 6: ✅ Mensaje EMAIL con email_destino y telefono_destino
        $fecha_formato = date('d/m/Y', strtotime($cita['fecha_cita']));
        $hora_formato  = date('H:i', strtotime($cita['hora_cita']));

        $mensaje_email = "Tu cita del {$fecha_formato} a las {$hora_formato} ha sido cancelada por el odontólogo. 
Motivo: {$motivo}. 
Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.";
        
        $sql5 = "INSERT INTO mensajes_pendientes 
                 (id_usuario, id_cita, tipo, canal, mensaje, email_destino, telefono_destino, fecha_programado)
                 VALUES (?, ?, 'cancelacion_doctor', 'email', ?, ?, ?, NOW())";
        
        $stmt5 = $conexion->prepare($sql5);
        $stmt5->bind_param("iisss", 
            $cita['id_usuario_paciente'], 
            $id_cita, 
            $mensaje_email,
            $cita['email_paciente'],    // ← email_destino
            $cita['telefono_paciente']  // ← telefono_destino
        );
        $stmt5->execute();

        // PASO 7: ✅ ★ MENSAJE WHATSAPP ★
        // Construir texto con opciones de reprogramación
        $texto_opciones = '';
        foreach ($opciones_reprogramacion as $i => $op) {
            $n = $i + 1;
            $fop = date('d/m/Y', strtotime($op['fecha']));
            $hop = date('H:i', strtotime($op['hora']));
            $texto_opciones .= "  {$n}. {$fop} a las {$hop}\n";
        }

        $mensaje_whatsapp = "🦷 *EcoDent - Cita Cancelada*\n\n"
            . "Hola {$cita['nombre_paciente']}, lamentamos informarte que tu cita del "
            . "*{$fecha_formato}* a las *{$hora_formato}* ha sido cancelada.\n\n"
            . "📋 *Motivo:* {$motivo}\n\n"
            . "📅 *Opciones de reprogramación disponibles:*\n"
            . $texto_opciones
            . "\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n"
            . "📞 Tel: 77112233";

        $sql6 = "INSERT INTO mensajes_pendientes 
                 (id_usuario, id_cita, tipo, canal, mensaje, email_destino, telefono_destino, fecha_programado)
                 VALUES (?, ?, 'cancelacion_doctor', 'whatsapp', ?, ?, ?, NOW())";
        
        $stmt6 = $conexion->prepare($sql6);
        $stmt6->bind_param("iisss", 
            $cita['id_usuario_paciente'], 
            $id_cita, 
            $mensaje_whatsapp,
            $cita['email_paciente'],
            $cita['telefono_paciente']  // ← número para el link de WhatsApp
        );
        $stmt6->execute();
        
        $conexion->commit();
        
        return [
            'exito'   => true,
            'mensaje' => 'Cita cancelada. Slot bloqueado. Paciente notificado.',
            // ✅ Devolver datos para mostrar botón WhatsApp en la vista
            'whatsapp' => [
                'telefono' => $cita['telefono_paciente'],
                'mensaje'  => $mensaje_whatsapp
            ]
        ];
        
    } catch (Exception $e) {
        $conexion->rollback();
        return ['exito' => false, 'error' => 'Error al cancelar: ' . $e->getMessage()];
    }
}

/**
 * Obtener opciones de reprogramación de una cita cancelada
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
 * El paciente acepta una opción de reprogramación
 */
function aceptarReprogramacion($id_opcion, $id_paciente, $conexion) {
    $conexion->begin_transaction();
    
    try {
        $sql = "SELECT o.*, c.id_paciente, c.motivo 
                FROM opciones_reprogramacion_cita o
                JOIN citas c ON o.id_cita_original = c.id_cita
                WHERE o.id_opcion = ? AND c.id_paciente = ?";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $id_opcion, $id_paciente);
        $stmt->execute();
        $opcion = $stmt->get_result()->fetch_assoc();
        
        if (!$opcion) throw new Exception('Opción no válida');
        
        $sql2 = "UPDATE opciones_reprogramacion_cita SET seleccionada = TRUE WHERE id_opcion = ?";
        $stmt2 = $conexion->prepare($sql2);
        $stmt2->bind_param("i", $id_opcion);
        $stmt2->execute();
        
        $sql3 = "INSERT INTO citas 
         (id_tratamiento, fecha_cita, hora_cita, hora_fin, motivo, estado) 
         VALUES (?, ?, ?, ?, ?, 'programada')";
        
        $stmt3 = $conexion->prepare($sql3);
        $stmt3->bind_param("iissss", 
            $opcion['id_paciente'], $opcion['id_odontologo'], 
            $opcion['fecha_propuesta'], $opcion['hora_propuesta'],
            $opcion['hora_propuesta_fin'], $opcion['motivo']
        );
        $stmt3->execute();
        
        $conexion->commit();
        return ['exito' => true, 'mensaje' => 'Cita reprogramada exitosamente'];
        
    } catch (Exception $e) {
        $conexion->rollback();
        return ['exito' => false, 'error' => 'Error al reprogramar: ' . $e->getMessage()];
    }
}
?>