<?php
// public/admin/marcar_todas_alertas.php
// Marcar todas las alertas como leídas

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

if (!estaLogueado() || !esAdmin()) {
    redirigir('/ecodent/public/login.php');
}

$conexion = conectarBD();

$stmt = $conexion->prepare("UPDATE alertas SET leida = 1 WHERE id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();

$_SESSION['mensaje'] = "Todas las alertas fueron marcadas como leídas";
redirigir('/ecodent/public/admin/ver_todas_alertas.php');
?>