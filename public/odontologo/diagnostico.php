<?php
// C:\xampp\htdocs\ecodent\public\odontologo\diagnostico.php
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    echo "<h2>No hay sesión iniciada</h2>";
    echo "<p>Por favor, <a href='/ecodent/public/login.php'>inicia sesión</a> primero.</p>";
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

echo "<h2>Diagnóstico de Odontólogo</h2>";
echo "<p><strong>ID Usuario en sesión:</strong> " . $id_usuario . "</p>";
echo "<p><strong>Nombre en sesión:</strong> " . (isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'No disponible') . "</p>";
echo "<p><strong>Rol en sesión:</strong> " . (isset($_SESSION['rol']) ? $_SESSION['rol'] : 'No disponible') . "</p>";

$conexion = conectarBD();

// Verificar conexión
if (!$conexion) {
    die("<p style='color:red'>Error de conexión a la base de datos</p>");
}

// Verificar si el usuario existe en tabla usuarios
$query_usuario = "SELECT id_usuario, nombre_completo, email, rol FROM usuarios WHERE id_usuario = ?";
$stmt = $conexion->prepare($query_usuario);
if (!$stmt) {
    die("<p style='color:red'>Error en la consulta de usuario: " . $conexion->error . "</p>");
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result_usuario = $stmt->get_result();

echo "<hr>";
echo "<h3>Información del Usuario:</h3>";
if ($usuario = $result_usuario->fetch_assoc()) {
    echo "<table border='1' cellpadding='5'>";
    foreach($usuario as $key => $value) {
        echo "<tr><th>$key</th><td>$value</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ ERROR: No se encontró el usuario con ID: $id_usuario</p>";
}

// Verificar odontólogos asociados
echo "<hr>";
echo "<h3>Odontólogos asociados a este usuario:</h3>";

$query_odontologo = "SELECT * FROM odontologos WHERE id_usuario = ?";
$stmt2 = $conexion->prepare($query_odontologo);
if (!$stmt2) {
    die("<p style='color:red'>Error en la consulta de odontólogo: " . $conexion->error . "</p>");
}

$stmt2->bind_param("i", $id_usuario);
$stmt2->execute();
$result_odontologo = $stmt2->get_result();

if ($odontologo = $result_odontologo->fetch_assoc()) {
    echo "<p style='color:green'>✅ Odontólogo encontrado:</p>";
    echo "<table border='1' cellpadding='5'>";
    foreach($odontologo as $key => $value) {
        echo "<tr><th>$key</th><td>$value</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange'>⚠️ No se encontró odontólogo con id_usuario = $id_usuario</p>";
    
    // Verificar si hay odontólogos en el sistema
    $query_todos = "SELECT o.*, u.nombre_completo FROM odontologos o 
                    LEFT JOIN usuarios u ON o.id_usuario = u.id_usuario";
    $result_todos = $conexion->query($query_todos);
    
    echo "<h4>Todos los odontólogos registrados:</h4>";
    if ($result_todos && $result_todos->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID Odontólogo</th><th>ID Usuario</th><th>Nombre</th><th>Especialidad</th><th>Activo</th></tr>";
        while($row = $result_todos->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id_odontologo'] . "</td>";
            echo "<td>" . ($row['id_usuario'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['nombre_completo'] ?? 'No asignado') . "</td>";
            echo "<td>" . ($row['especialidad'] ?? 'No especificada') . "</td>";
            echo "<td>" . ($row['activo'] ? '✅ Sí' : '❌ No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay odontólogos registrados en el sistema.</p>";
    }
}

// Verificar la estructura de la tabla odontologos
echo "<hr>";
echo "<h3>Estructura de la tabla odontologos:</h3>";
$query_structure = "DESCRIBE odontologos";
$result_structure = $conexion->query($query_structure);
if ($result_structure && $result_structure->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
    while($row = $result_structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Verificar si el rol en la sesión es correcto
echo "<hr>";
echo "<h3>Verificación de Rol:</h3>";
if (function_exists('esAdmin')) {
    if (esAdmin()) {
        echo "<p>✅ El usuario tiene rol de ADMIN</p>";
    } elseif (esOdontologo()) {
        echo "<p>✅ El usuario tiene rol de ODONTÓLOGO</p>";
    } else {
        echo "<p>⚠️ El usuario NO tiene rol de odontólogo. Rol actual: " . (isset($_SESSION['rol']) ? $_SESSION['rol'] : 'No definido') . "</p>";
    }
} else {
    echo "<p>⚠️ La función esOdontologo() no está disponible</p>";
}

// Soluciones
echo "<hr>";
echo "<h3>🔧 Posibles soluciones:</h3>";
echo "<ol>";
echo "<li><strong>Si el usuario existe pero no tiene odontólogo asociado:</strong><br>";
echo "Ejecuta este SQL en phpMyAdmin (reemplaza X por el ID_USUARIO que aparece arriba):<br>";
echo "<code style='background:#f4f4f4;padding:5px;display:inline-block;margin-top:5px;'>INSERT INTO odontologos (id_usuario, especialidad, activo, duracion_cita_min, color_calendario) VALUES ($id_usuario, 'Odontología General', 1, 30, '#1976d2');</code></li>";
echo "<li><strong>Si el odontólogo está inactivo:</strong><br>";
echo "<code style='background:#f4f4f4;padding:5px;display:inline-block;margin-top:5px;'>UPDATE odontologos SET activo = 1 WHERE id_usuario = $id_usuario;</code></li>";
echo "<li><strong>Si el usuario no existe en la tabla usuarios:</strong><br>";
echo "Debes crear el usuario primero o verificar que el ID de sesión sea correcto.</li>";
echo "<li><strong>Si la tabla odontologos no tiene los campos necesarios:</strong><br>";
echo "Ejecuta este SQL para crear la tabla correctamente:<br>";
echo "<pre style='background:#f4f4f4;padding:10px;margin-top:5px;'>
CREATE TABLE IF NOT EXISTS odontologos (
    id_odontologo INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    especialidad VARCHAR(100) DEFAULT 'Odontología General',
    activo TINYINT DEFAULT 1,
    duracion_cita_min INT DEFAULT 30,
    color_calendario VARCHAR(7) DEFAULT '#1976d2',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);</pre></li>";
echo "</ol>";

// Botón para volver
echo "<hr>";
echo "<p><a href='calendario.php' class='btn btn-primary'>Volver al Calendario</a></p>";
echo "<p><a href='/ecodent/public/admin/dashboard.php' class='btn btn-secondary'>Ir al Dashboard</a></p>";
?>