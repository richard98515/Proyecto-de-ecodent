<?php
// includes/slots.php

function generarSlotsDisponibles($id_odontologo, $fecha, $conexion, $excluir_cita = 0) {
    
    $dia_ingles = date('l', strtotime($fecha));
    $dias = [
        'Monday' => 'lunes',
        'Tuesday' => 'martes',
        'Wednesday' => 'miercoles',
        'Thursday' => 'jueves',
        'Friday' => 'viernes',
        'Saturday' => 'sabado',
        'Sunday' => 'domingo'
    ];
    $dia_semana = $dias[$dia_ingles];
    
    $sql = "SELECT h.hora_inicio, h.hora_fin, o.duracion_cita_min 
            FROM horarios_odontologos h
            JOIN odontologos o ON h.id_odontologo = o.id_odontologo
            WHERE h.id_odontologo = ? AND h.dia_semana = ? AND h.activo = 1";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $id_odontologo, $dia_semana);
    $stmt->execute();
    $horario = $stmt->get_result()->fetch_assoc();
    
    if (!$horario) return [];
    
    $inicio = strtotime($fecha . ' ' . $horario['hora_inicio']);
    $fin    = strtotime($fecha . ' ' . $horario['hora_fin']);
    $duracion = (int) $horario['duracion_cita_min'];
    $paso   = $duracion * 60;
    
    // =============================================
    // PASO 3: Obtener citas ocupadas con hora_inicio Y hora_fin
    // ✅ FIX: traemos hora_fin para detectar citas largas
    // =============================================
    $sql2 = "SELECT hora_cita, hora_fin FROM citas 
             WHERE id_odontologo = ? AND fecha_cita = ? 
             AND estado IN ('programada', 'confirmada')
             AND id_cita != ?";
    
    $stmt2 = $conexion->prepare($sql2);
    $stmt2->bind_param("isi", $id_odontologo, $fecha, $excluir_cita);
    $stmt2->execute();
    $resultado2 = $stmt2->get_result();
    
    // ✅ FIX: guardamos rangos [inicio, fin] en vez de solo hora_cita
    $citas_ocupadas = [];
    while ($row = $resultado2->fetch_assoc()) {
        $hora_fin_cita = $row['hora_fin'] 
            ? strtotime($fecha . ' ' . $row['hora_fin'])
            : strtotime($fecha . ' ' . $row['hora_cita']) + $paso; // fallback si hora_fin es NULL
        
        $citas_ocupadas[] = [
            'inicio' => strtotime($fecha . ' ' . $row['hora_cita']),
            'fin'    => $hora_fin_cita
        ];
    }
    
    // PASO 4: Slots bloqueados (sin cambios)
    $sql3 = "SELECT hora_inicio, hora_fin FROM slots_bloqueados 
             WHERE id_odontologo = ? AND fecha = ?";
    $stmt3 = $conexion->prepare($sql3);
    $stmt3->bind_param("is", $id_odontologo, $fecha);
    $stmt3->execute();
    $resultado3 = $stmt3->get_result();
    
    $bloqueos = [];
    while ($row = $resultado3->fetch_assoc()) {
        $bloqueos[] = [
            'inicio' => strtotime($fecha . ' ' . $row['hora_inicio']),
            'fin'    => strtotime($fecha . ' ' . $row['hora_fin'])
        ];
    }
    
    // =============================================
    // PASO 5: Generar slots verificando rangos completos
    // =============================================
    $slots_disponibles = [];
    $hora_actual = $inicio;
    
    while ($hora_actual + $paso <= $fin) {
        $hora_fin_slot = $hora_actual + $paso;
        
        // ✅ FIX: verificar solapamiento con rango completo, no solo hora exacta
        $ocupado = false;
        foreach ($citas_ocupadas as $cita) {
            // El slot se solapa si empieza antes de que termine la cita
            // Y termina después de que empieza la cita
            if ($hora_actual < $cita['fin'] && $hora_fin_slot > $cita['inicio']) {
                $ocupado = true;
                break;
            }
        }
        
        $bloqueado = false;
        foreach ($bloqueos as $bloqueo) {
            if ($hora_actual < $bloqueo['fin'] && $hora_fin_slot > $bloqueo['inicio']) {
                $bloqueado = true;
                break;
            }
        }
        
        if (!$ocupado && !$bloqueado) {
            $slots_disponibles[] = [
                'hora_inicio'         => date('H:i:s', $hora_actual),
                'hora_fin'            => date('H:i:s', $hora_fin_slot),
                'hora_inicio_formato' => date('h:i A', $hora_actual),
                'hora_fin_formato'    => date('h:i A', $hora_fin_slot)
            ];
        }
        
        $hora_actual += $paso;
    }
    
    return $slots_disponibles;
}

function generarSlotsPorDia($id_odontologo, $fecha_inicio, $dias, $conexion) {
    $resultado = [];
    $fecha_actual = strtotime($fecha_inicio);
    
    for ($i = 0; $i < $dias; $i++) {
        $fecha_str = date('Y-m-d', $fecha_actual);
        $slots = generarSlotsDisponibles($id_odontologo, $fecha_str, $conexion);
        if (!empty($slots)) {
            $resultado[$fecha_str] = $slots;
        }
        $fecha_actual = strtotime('+1 day', $fecha_actual);
    }
    
    return $resultado;
}

function slotEstaDisponible($id_odontologo, $fecha, $hora, $conexion, $excluir_cita = 0) {
    $slots = generarSlotsDisponibles($id_odontologo, $fecha, $conexion, $excluir_cita);
    foreach ($slots as $slot) {
        if ($slot['hora_inicio'] === $hora) return true;
    }
    return false;
}

function calcularHoraFin($id_odontologo, $hora_inicio, $conexion) {
    $sql = "SELECT duracion_cita_min FROM odontologos WHERE id_odontologo = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_odontologo);
    $stmt->execute();
    $odontologo = $stmt->get_result()->fetch_assoc();
    $duracion = $odontologo['duracion_cita_min'] ?? 40;
    return date('H:i:s', strtotime($hora_inicio) + ($duracion * 60));
}

function bloquearSlot($id_odontologo, $fecha, $hora_inicio, $motivo, $conexion) {
    $hora_fin = calcularHoraFin($id_odontologo, $hora_inicio, $conexion);
    $sql = "INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("issss", $id_odontologo, $fecha, $hora_inicio, $hora_fin, $motivo);
    return $stmt->execute();
}

function desbloquearSlot($id_odontologo, $fecha, $hora_inicio, $conexion) {
    $sql = "DELETE FROM slots_bloqueados 
            WHERE id_odontologo = ? AND fecha = ? AND hora_inicio = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
    return $stmt->execute();
}
?>