<?php
// includes/funciones.php
// Archivo con funciones útiles para todo el sistema

// Iniciamos la sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * FUNCIÓN: redirigir
 * Redirige al usuario a otra página SIN errores de headers
 * @param string $url La URL a donde redirigir
 */
function redirigir($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
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
 * FUNCIÓN: esAdmin
 * Verifica si el usuario logueado es administrador
 * @return bool True si es admin, False si no
 */
function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

/**
 * FUNCIÓN: requerirLogin
 * Si el usuario NO está logueado, lo redirige al login
 */
function requerirLogin() {
    if (!estaLogueado()) {
        redirigir('/ecodent/public/login.php');
    }
}

/**
 * FUNCIÓN: requerirRol (VERSIÓN ACTUALIZADA)
 * Verifica que el usuario tenga un rol específico
 * Si es ADMIN, tiene acceso a todo
 * @param string $rol El rol requerido ('paciente' u 'odontologo')
 */
function requerirRol($rol) {
    requerirLogin(); // Primero asegurar que está logueado
    
    // Si es admin, tiene acceso a todo (sin importar el rol solicitado)
    if (esAdmin()) {
        return true;
    }
    
    // Si no es admin, verificar el rol específico
    if ($_SESSION['rol'] !== $rol) {
        redirigir('/ecodent/public/index.php');
    }
    
    return true;
}

/**
 * FUNCIÓN: requerirRolOAdmin
 * Verifica que el usuario tenga un rol específico O sea admin
 * @param string $rol El rol requerido
 */
function requerirRolOAdmin($rol) {
    requerirLogin();
    
    if (esAdmin()) {
        return true;
    }
    
    if ($_SESSION['rol'] !== $rol) {
        redirigir('/ecodent/public/index.php');
    }
    
    return true;
}

/**
 * FUNCIÓN: mostrarAlerta
 * Muestra un mensaje de alerta (usando Bootstrap)
 * @param string $tipo success, danger, warning, info
 */
function mostrarAlerta($tipo) {
    if (isset($_SESSION[$tipo]) && !empty($_SESSION[$tipo])) {
        $mensaje = $_SESSION[$tipo];
        unset($_SESSION[$tipo]);
        echo "<div class='alert alert-{$tipo} alert-dismissible fade show' role='alert'>
                {$mensaje}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Cerrar'></button>
              </div>";
    }
}

/**
 * FUNCIÓN: sanitizar
 * Limpia texto para evitar XSS
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