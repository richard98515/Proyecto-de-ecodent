<?php
// public/odontologo/diagnostico_odontologo.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

// Verificar autenticación
if (!estaLogueado()) {
    die("No hay sesión iniciada");
}

$id_usuario = $_SESSION['id_usuario'];

echo "<h2>Diagnóstico de Odontólogo</h2>";
echo "<p><strong>ID Usuario en sesión:</strong> " . $id_usuario . "</p>";
echo "<p><strong>Nombre en sesión:</strong> " . ($_SESSION['nombre_completo'] ?? 'No disponible') . "</p>";
echo "<p><strong>Rol en sesión:</strong> " . ($_SESSION['rol'] ?? 'No disponible') . "</p>";

$conexion = conectarBD();

// Verificar si el usuario existe en tabla usuarios
$query_usuario = "SELECT id_usuario, nombre_completo, email, rol FROM usuarios WHERE id_usuario = ?";
$stmt = $conexion->prepare($query_usuario);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result_usuario = $stmt->get_result();

echo "<hr>";
echo "<h3>Información del Usuario:</h3>";
if ($usuario = $result_usuario->fetch_assoc()) {
    echo "<pre>";
    print_r($usuario);
    echo "</pre>";
} else {
    echo "<p style='color:red'>❌ ERROR: No se encontró el usuario con ID: $id_usuario</p>";
}

// Verificar odontólogos asociados
echo "<hr>";
echo "<h3>Odontólogos asociados a este usuario:</h3>";

$query_odontologo = "SELECT * FROM odontologos WHERE id_usuario = ?";
$stmt2 = $conexion->prepare($query_odontologo);
$stmt2->bind_param("i", $id_usuario);
$stmt2->execute();
$result_odontologo = $stmt2->get_result();

if ($odontologo = $result_odontologo->fetch_assoc()) {
    echo "<p style='color:green'>✅ Odontólogo encontrado:</p>";
    echo "<pre>";
    print_r($odontologo);
    echo "</pre>";
} else {
    echo "<p style='color:orange'>⚠️ No se encontró odontólogo con id_usuario = $id_usuario</p>";
    
    // Verificar si hay odontólogos en el sistema
    $query_todos = "SELECT o.*, u.nombre_completo FROM odontologos o 
                    LEFT JOIN usuarios u ON o.id_usuario = u.id_usuario";
    $result_todos = $conexion->query($query_todos);
    
    echo "<h4>Todos los odontólogos registrados:</h4>";
    if ($result_todos && $result_todos->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID Odontólogo</th><th>ID Usuario</th><th>Nombre</th><th>Activo</th></tr>";
        while($row = $result_todos->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id_odontologo'] . "</td>";
            echo "<td>" . $row['id_usuario'] . "</td>";
            echo "<td>" . ($row['nombre_completo'] ?? 'No asignado') . "</td>";
            echo "<td>" . ($row['activo'] ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay odontólogos registrados en el sistema.</p>";
    }
}

// Verificar si la sesión tiene el rol correcto
echo "<hr>";
echo "<h3>Verificación de Rol:</h3>";
if (esAdmin()) {
    echo "<p>✅ El usuario tiene rol de ADMIN</p>";
} elseif (esOdontologo()) {
    echo "<p>✅ El usuario tiene rol de ODONTÓLOGO</p>";
} else {
    echo "<p>⚠️ El usuario NO tiene rol de odontólogo. Rol actual: " . ($_SESSION['rol'] ?? 'No definido') . "</p>";
}

echo "<hr>";
echo "<h3>Posibles soluciones:</h3>";
echo "<ol>";
echo "<li>Si el usuario existe pero no tiene odontólogo asociado, ejecuta este SQL (AJUSTA LOS DATOS):<br>";
echo "<code>INSERT INTO odontologos (id_usuario, especialidad, activo, duracion_cita_min) VALUES ($id_usuario, 'General', 1, 30);</code></li>";
echo "<li>Si el odontólogo está inactivo, actualízalo:<br>";
echo "<code>UPDATE odontologos SET activo = 1 WHERE id_usuario = $id_usuario;</code></li>";
echo "<li>Si el usuario no existe en la tabla usuarios, debes crearlo primero.</li>";
echo "</ol>";
?>