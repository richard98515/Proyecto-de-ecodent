<?php
// includes/funciones.php
// Archivo con funciones útiles para todo el sistema

// Iniciamos la sesión si no está iniciada
// La sesión nos permite mantener datos del usuario entre páginas (como "estoy logueado")
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * FUNCIÓN: redirigir
 * Redirige al usuario a otra página
 * @param string $url La URL a donde redirigir
 */
/**
 * FUNCIÓN: redirigir
 * Redirige al usuario a otra página SIN errores de headers
 * @param string $url La URL a donde redirigir
 */
function redirigir($url) {
    // Asegurarse de que no haya salida antes de los headers
    if (!headers_sent()) {
        // Si no se han enviado headers, usar header()
        header("Location: $url");
        exit;
    } else {
        // Si ya se enviaron headers, usar JavaScript
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

/**
 * FUNCIÓN: estaLogueado
 * Verifica si el usuario ha iniciado sesión
 * @return bool True si está logueado, False si no
 */
function estaLogueado() {
    return isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario']);
}

/**
 * FUNCIÓN: esOdontologo
 * Verifica si el usuario logueado es odontólogo
 * @return bool True si es odontólogo, False si no
 */
function esOdontologo() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'odontologo';
}

/**
 * FUNCIÓN: esPaciente
 * Verifica si el usuario logueado es paciente
 * @return bool True si es paciente, False si no
 */
function esPaciente() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'paciente';
}

/**
 * FUNCIÓN: requerirLogin
 * Si el usuario NO está logueado, lo redirige al login
 * Útil para páginas que solo pueden ver usuarios autenticados
 */
function requerirLogin() {
    if (!estaLogueado()) {
        redirigir('/ecodent/public/login.php');
    }
}

/**
 * FUNCIÓN: requerirRol
 * Verifica que el usuario tenga un rol específico
 * @param string $rol El rol requerido ('paciente' u 'odontologo')
 */
function requerirRol($rol) {
    requerirLogin(); // Primero asegurar que está logueado
    if ($_SESSION['rol'] !== $rol) {
        // Si no tiene el rol correcto, redirigir al inicio
        redirigir('/ecodent/public/index.php');
    }
}

/**
 * FUNCIÓN: mostrarAlerta
 * Muestra un mensaje de alerta (usando Bootstrap) y lo elimina de la sesión
 * @param string $tipo success, danger, warning, info
 */
function mostrarAlerta($tipo) {
    if (isset($_SESSION[$tipo]) && !empty($_SESSION[$tipo])) {
        $mensaje = $_SESSION[$tipo];
        unset($_SESSION[$tipo]); // Eliminar para que no se muestre otra vez
        echo "<div class='alert alert-{$tipo} alert-dismissible fade show' role='alert'>
                {$mensaje}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Cerrar'></button>
              </div>";
    }
}

/**
 * FUNCIÓN: sanitizar
 * Limpia texto para evitar XSS (Cross Site Scripting)
 * @param string $dato El texto a limpiar
 * @return string El texto limpio
 */
function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

/**
 * FUNCIÓN: generarTokenCSRF
 * Genera un token único para proteger formularios contra CSRF
 * @return string El token generado
 */
function generarTokenCSRF() {
    if (!isset($_SESSION['token_csrf'])) {
        // bin2hex(random_bytes(32)) genera un string aleatorio de 64 caracteres
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

/**
 * FUNCIÓN: verificarTokenCSRF
 * Verifica que el token CSRF del formulario sea correcto
 * @param string $token El token enviado por el formulario
 * @return bool True si es válido, False si no
 */
function verificarTokenCSRF($token) {
    if (!isset($_SESSION['token_csrf']) || $token !== $_SESSION['token_csrf']) {
        return false;
    }
    return true;
}
?>