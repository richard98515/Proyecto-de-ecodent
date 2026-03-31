<?php
// includes/header.php
// Encabezado común para todas las páginas del sistema

// Verificamos si ya hay una sesión iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECO-DENT - Sistema Odontológico</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- CSS personalizado -->
    <link href="/ecodent/public/css/estilos.css" rel="stylesheet">
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <!-- Logo y nombre del sistema -->
            <a class="navbar-brand" href="/ecodent/public/index.php">
                <i class="bi bi-hospital"></i> ECO-DENT
            </a>
            
            <!-- Botón para menú en móviles -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menú de navegación -->
            <div class="collapse navbar-collapse" id="navbarPrincipal">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/ecodent/public/index.php">
                            <i class="bi bi-house"></i> Inicio
                        </a>
                    </li>
                    
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        <!-- MENÚ PARA USUARIOS LOGUEADOS -->
                        
                        <?php if ($_SESSION['rol'] === 'odontologo'): ?>
                            <!-- Menú para ODONTÓLOGOS -->
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Panel
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/calendario.php">
                                    <i class="bi bi-calendar-week"></i> Calendario
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/bloquear_slots.php">
                                    <i class="bi bi-lock-fill text-warning"></i> Bloquear Slots
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/agendar_cita.php">
                                    <i class="bi bi-calendar-plus-fill text-success"></i> Multiples Citas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/pacientes.php">
                                    <i class="bi bi-people"></i> Pacientes
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- Menú para PACIENTES -->
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/paciente/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Mi Panel
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/paciente/agendar.php">
                                    <i class="bi bi-calendar-plus"></i> Agendar Cita
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/paciente/mis_citas.php">
                                    <i class="bi bi-calendar-check"></i> Mis Citas
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <!-- Menú del lado derecho -->
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['nombre_completo']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="/ecodent/public/perfil.php">
                                        <i class="bi bi-person"></i> Mi Perfil
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/ecodent/public/logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/ecodent/public/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/ecodent/public/registro.php">
                                <i class="bi bi-person-plus"></i> Registrarse
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Contenedor principal -->
    <main class="container mt-4">