<?php
// config/database.php
// Archivo de configuración para la conexión a la base de datos MySQL.
// Todas las páginas que necesiten la BD incluirán este archivo.

// --- CONFIGURACIÓN DE LA CONEXIÓN ---
// Definimos constantes con los datos de nuestra base de datos.
// Usamos constantes porque son valores que no cambian durante la ejecución.
define('SERVIDOR_BD', 'localhost'); // El servidor donde está MySQL (XAMPP está en localhost)
define('NOMBRE_USUARIO_BD', 'root'); // El usuario de MySQL en XAMPP por defecto es 'root'
define('CONTRASENA_BD', ''); // La contraseña por defecto en XAMPP para 'root' está VACÍA
define('NOMBRE_BD', 'ecodent'); // El nombre de la base de datos que acabamos de crear

// --- CREAR LA CONEXIÓN USANDO MySQLi (Mejorado) ---
// MySQLi es una extensión de PHP para trabajar con MySQL de forma segura.
// La variable $conexion será nuestro puente para comunicarnos con la BD.
$conexion = new mysqli(
    SERVIDOR_BD,
    NOMBRE_USUARIO_BD,
    CONTRASENA_BD,
    NOMBRE_BD
);

// --- VERIFICAR SI HUBO UN ERROR EN LA CONEXIÓN ---
// La propiedad 'connect_error' de $conexion tiene un mensaje de error si la conexión falló.
if ($conexion->connect_error) {
    // Si falló, detenemos la ejecución del script (die) y mostramos el error.
    // En un sistema real, no mostrarías este detalle al usuario, pero para nosotros es útil.
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

// --- CONFIGURAR EL JUEGO DE CARACTERES ---
// Esto es para que los textos con tildes, ñ, etc. se guarden y lean correctamente.
$conexion->set_charset("utf8mb4");

// Si llegamos a este punto, la conexión fue exitosa.
// La variable $conexion ya está lista para usarse en otros archivos.
?>