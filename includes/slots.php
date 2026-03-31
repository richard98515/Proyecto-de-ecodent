<?php
// includes/slots.php
// CORAZÓN DEL SISTEMA - Algoritmo de generación de slots de 40 minutos
// BASADO EN PDF PÁGINAS 13-16

/**
 * FUNCIÓN PRINCIPAL: generarSlotsDisponibles
 * Genera los slots disponibles para un odontólogo en una fecha específica
 * 
 * @param int $id_odontologo ID del odontólogo
 * @param string $fecha Fecha en formato YYYY-MM-DD
 * @param object $conexion Objeto de conexión a MySQL
 * @return array Lista de slots disponibles (cada slot es un array con hora_inicio y hora_fin)
 */
function generarSlotsDisponibles($id_odontologo, $fecha, $conexion) {
    
    // =============================================
    // PASO 1: Obtener el horario del odontólogo para ese día
    // =============================================
    // Convertir el nombre del día en español (ej: "Monday" -> "lunes")
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
    
    // Consultar el horario del odontólogo para ese día
    $sql = "SELECT h.hora_inicio, h.hora_fin, o.duracion_cita_min 
            FROM horarios_odontologos h
            JOIN odontologos o ON h.id_odontologo = o.id_odontologo
            WHERE h.id_odontologo = ? AND h.dia_semana = ? AND h.activo = 1";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $id_odontologo, $dia_semana);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $horario = $resultado->fetch_assoc();
    
    // Si no trabaja ese día, retornar array vacío
    if (!$horario) {
        return [];
    }
    
    // =============================================
    // PASO 2: Convertir horas a timestamp para poder iterar
    // =============================================
    $inicio = strtotime($fecha . ' ' . $horario['hora_inicio']);
    $fin = strtotime($fecha . ' ' . $horario['hora_fin']);
    $duracion = (int) $horario['duracion_cita_min']; // DEBE SER 40 (según PDF)
    $paso = $duracion * 60; // Convertir minutos a segundos
    
    // =============================================
    // PASO 3: Obtener citas ya agendadas (ocupadas)
    // =============================================
    $sql2 = "SELECT hora_cita FROM citas 
             WHERE id_odontologo = ? AND fecha_cita = ? 
             AND estado IN ('programada', 'confirmada')";
    
    $stmt2 = $conexion->prepare($sql2);
    $stmt2->bind_param("is", $id_odontologo, $fecha);
    $stmt2->execute();
    $resultado2 = $stmt2->get_result();
    
    $citas_ocupadas = [];
    while ($row = $resultado2->fetch_assoc()) {
        $citas_ocupadas[] = $row['hora_cita'];
    }
    
    // =============================================
    // PASO 4: Obtener slots bloqueados (por el odontólogo)
    // =============================================
    // ★ NUEVA TABLA: slots_bloqueados (PDF página 11)
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
            'fin' => strtotime($fecha . ' ' . $row['hora_fin'])
        ];
    }
    
    // =============================================
    // PASO 5: ITERAR y generar slots
    // =============================================
    $slots_disponibles = [];
    $hora_actual = $inicio;
    
    // Iterar mientras no hayamos pasado la hora de fin
    while ($hora_actual + $paso <= $fin) {
        $hora_fin_slot = $hora_actual + $paso;
        $hora_inicio_str = date('H:i:s', $hora_actual);
        
        // Verificar si el slot está OCUPADO por una cita
        $ocupado = in_array($hora_inicio_str, $citas_ocupadas);
        
        // Verificar si el slot está BLOQUEADO
        $bloqueado = false;
        foreach ($bloqueos as $bloqueo) {
            // Si el slot actual está dentro de un rango bloqueado
            if ($hora_actual >= $bloqueo['inicio'] && $hora_fin_slot <= $bloqueo['fin']) {
                $bloqueado = true;
                break;
            }
            // Si el slot se solapa parcialmente con un bloqueo (también lo consideramos bloqueado)
            if (($hora_actual < $bloqueo['fin'] && $hora_fin_slot > $bloqueo['inicio'])) {
                $bloqueado = true;
                break;
            }
        }
        
        // Si NO está ocupado NI bloqueado, está DISPONIBLE
        if (!$ocupado && !$bloqueado) {
            $slots_disponibles[] = [
                'hora_inicio' => $hora_inicio_str,
                'hora_fin' => date('H:i:s', $hora_fin_slot),
                'hora_inicio_formato' => date('h:i A', $hora_actual), // Formato para mostrar (ej: 08:00 AM)
                'hora_fin_formato' => date('h:i A', $hora_fin_slot)
            ];
        }
        
        // Avanzar al siguiente slot
        $hora_actual += $paso;
    }
    
    return $slots_disponibles;
}

/**
 * FUNCIÓN: generarSlotsPorDia
 * Genera slots para varios días (útil para el calendario)
 * 
 * @param int $id_odontologo ID del odontólogo
 * @param string $fecha_inicio Fecha inicio YYYY-MM-DD
 * @param int $dias Número de días a generar
 * @param object $conexion Conexión MySQL
 * @return array Slots agrupados por día
 */
function generarSlotsPorDia($id_odontologo, $fecha_inicio, $dias, $conexion) {
    $resultado = [];
    $fecha_actual = strtotime($fecha_inicio);
    
    for ($i = 0; $i < $dias; $i++) {
        $fecha_str = date('Y-m-d', $fecha_actual);
        $slots = generarSlotsDisponibles($id_odontologo, $fecha_str, $conexion);
        
        if (!empty($slots)) {
            $resultado[$fecha_str] = $slots;
        }
        
        // Avanzar al siguiente día
        $fecha_actual = strtotime('+1 day', $fecha_actual);
    }
    
    return $resultado;
}

/**
 * FUNCIÓN: slotEstaDisponible
 * Verifica si un slot específico está disponible (útil antes de agendar)
 * 
 * @param int $id_odontologo ID del odontólogo
 * @param string $fecha Fecha YYYY-MM-DD
 * @param string $hora Hora HH:MM:SS
 * @param object $conexion Conexión MySQL
 * @return bool True si está disponible, False si no
 */
function slotEstaDisponible($id_odontologo, $fecha, $hora, $conexion) {
    // Obtener todos los slots disponibles
    $slots = generarSlotsDisponibles($id_odontologo, $fecha, $conexion);
    
    // Buscar si la hora solicitada está en la lista
    foreach ($slots as $slot) {
        if ($slot['hora_inicio'] === $hora) {
            return true;
        }
    }
    
    return false;
}

/**
 * FUNCIÓN: calcularHoraFin
 * Calcula la hora de fin de un slot basado en la duración del odontólogo
 * 
 * @param int $id_odontologo ID del odontólogo
 * @param string $hora_inicio Hora de inicio
 * @param object $conexion Conexión MySQL
 * @return string Hora de fin en formato HH:MM:SS
 */
function calcularHoraFin($id_odontologo, $hora_inicio, $conexion) {
    // Obtener duración del odontólogo
    $sql = "SELECT duracion_cita_min FROM odontologos WHERE id_odontologo = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_odontologo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $odontologo = $resultado->fetch_assoc();
    
    $duracion = $odontologo['duracion_cita_min'] ?? 40; // Por defecto 40
    
    // Calcular hora fin
    $timestamp = strtotime($hora_inicio);
    $timestamp_fin = $timestamp + ($duracion * 60);
    
    return date('H:i:s', $timestamp_fin);
}

/**
 * FUNCIÓN: bloquearSlot (para cuando el odontólogo cancela)
 * Inserta un registro en slots_bloqueados
 * 
 * @param int $id_odontologo ID del odontólogo
 * @param string $fecha Fecha
 * @param string $hora_inicio Hora inicio
 * @param string $motivo Motivo del bloqueo
 * @param object $conexion Conexión MySQL
 * @return bool True si se bloqueó correctamente
 */
function bloquearSlot($id_odontologo, $fecha, $hora_inicio, $motivo, $conexion) {
    // Calcular hora fin (hora_inicio + duración)
    $hora_fin = calcularHoraFin($id_odontologo, $hora_inicio, $conexion);
    
    $sql = "INSERT INTO slots_bloqueados (id_odontologo, fecha, hora_inicio, hora_fin, motivo) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("issss", $id_odontologo, $fecha, $hora_inicio, $hora_fin, $motivo);
    
    return $stmt->execute();
}


/**
 * FUNCIÓN: desbloquearSlot (para cuando el odontólogo se recupera)
 * Elimina un registro de slots_bloqueados
 * 
 * @param int $id_odontologo ID del odontólogo
 * @param string $fecha Fecha
 * @param string $hora_inicio Hora inicio
 * @param object $conexion Conexión MySQL
 * @return bool True si se desbloqueó correctamente
 */
function desbloquearSlot($id_odontologo, $fecha, $hora_inicio, $conexion) {
    $sql = "DELETE FROM slots_bloqueados 
            WHERE id_odontologo = ? AND fecha = ? AND hora_inicio = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $id_odontologo, $fecha, $hora_inicio);
    
    return $stmt->execute();
}
?>