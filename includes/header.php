<?php
// includes/header.php
// Encabezado común para todas las páginas del sistema - VERSIÓN ODONTOLÓGICA

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
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS personalizado -->
    <link href="/ecodent/public/css/estilos.css" rel="stylesheet">
</head>
<body>
    <!-- Barra de navegación superior - ODONTOLÓGICA -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <!-- Logo y nombre del sistema -->
            <a class="navbar-brand" href="/ecodent/public/index.php">
                <div class="d-flex align-items-center">
                    <div class="logo-icon me-2">
                         <!-- AQUÍ ESTÁ LA IMAGEN/ICONO -->
                        <i class="bi bi-teeth fs-2"></i>  <!-- Este es un ícono de Bootstrap -->
                        <!-- O si usas imagen real:-->
                        <img src="/ecodent/public/image/logo1.png" alt="ECO-DENT" width="200" height="200">
                        
                    </div>
                    <div>
                        <span class="brand-name">ECO-DENT</span>
                        <small class="brand-subtitle d-block">Sistema Odontológico</small>
                    </div>
                </div>
            </a>
            
            <!-- Botón para menú en móviles -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menú de navegación -->
            <div class="collapse navbar-collapse" id="navbarPrincipal">
                <ul class="navbar-nav mx-auto">
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        <!-- MENÚ PARA USUARIOS LOGUEADOS -->
                        
                        <?php if ($_SESSION['rol'] === 'admin'): ?>
                            <!-- Menú para ADMINISTRADOR -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="/ecodent/public/admin/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/admin/gestion_odontologos.php">
                                    <i class="bi bi-person-badge"></i> Odontólogos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/admin/estadisticas.php">
                                    <i class="bi bi-graph-up"></i> Estadísticas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/admin/ver_backups.php">
                                    <i class="bi bi-database"></i> Backups
                                </a>
                            </li>
                            
                        <?php elseif ($_SESSION['rol'] === 'odontologo'): ?>
                            <!-- Menú para ODONTÓLOGOS -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="/ecodent/public/odontologo/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Panel
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'active' : ''; ?>" href="/ecodent/public/odontologo/calendario.php">
                                    <i class="bi bi-calendar-week"></i> Calendario
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/pacientes.php">
                                    <i class="bi bi-people"></i> Pacientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/odontologo/bloquear_slots.php">
                                    <i class="bi bi-lock-fill"></i> Bloques
                                </a>
                            </li>
                            
                        <?php elseif ($_SESSION['rol'] === 'paciente'): ?>
                            <!-- Menú para PACIENTES -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="/ecodent/public/paciente/dashboard.php">
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
                            <li class="nav-item">
                                <a class="nav-link" href="/ecodent/public/paciente/mis_pagos.php">
                                    <i class="bi bi-cash-stack"></i> Mis Pagos
                                </a>
                            </li>
                            <li class="nav-item">
                                    <a class="nav-link" href="/ecodent/public/asistente/index.php">
                                        <i class="bi bi-robot"></i> Asistente IA
                                    </a>
                                </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Menú para NO LOGUEADOS -->
                        <li class="nav-item">
                            <a class="nav-link" href="/ecodent/public/index.php">
                                <i class="bi bi-house"></i> Inicio
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/ecodent/public/asistente_virtual.php">
                                <i class="bi bi-robot"></i> Asistente Virtual
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Menú del lado derecho con foto/avatar -->
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle user-dropdown" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar">
                                    <?php 
                                    $inicial = strtoupper(substr($_SESSION['nombre_completo'], 0, 1));
                                    if ($_SESSION['rol'] === 'admin') {
                                        echo '<i class="bi bi-shield-shaded"></i>';
                                    } elseif ($_SESSION['rol'] === 'odontologo') {
                                        echo '<i class="bi bi-hospital"></i>';
                                    } else {
                                        echo '<i class="bi bi-person-circle"></i>';
                                    }
                                    ?>
                                </div>
                                <span class="user-name d-none d-lg-inline"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li class="dropdown-header text-muted">
                                    <small><?php echo ucfirst($_SESSION['rol']); ?></small>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="/ecodent/public/perfil.php">
                                        <i class="bi bi-person"></i> Mi Perfil
                                    </a>
                                </li>
                                <?php if ($_SESSION['rol'] === 'odontologo'): ?>
                                <li>
                                    <a class="dropdown-item" href="/ecodent/public/odontologo/horarios.php">
                                        <i class="bi bi-clock"></i> Mis Horarios
                                    </a>
                                </li>
                                <?php endif; ?>
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
                            <a class="nav-link btn btn-outline-light rounded-pill px-3 mx-1" href="/ecodent/public/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Ingresar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-light rounded-pill px-3 text-primary" href="/ecodent/public/registro.php">
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