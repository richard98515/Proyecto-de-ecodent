<?php
// public/index.php
// Página principal del sistema

// Incluimos el header (que ya inicia la sesión)
require_once '../includes/header.php';
require_once '../includes/funciones.php';
?>

<div class="row">
    <div class="col-md-8">
        <h1 class="display-4">Bienvenido a ECO-DENT</h1>
        <p class="lead">Sistema de Gestión Odontológica con Asistente Virtual Inteligente</p>
        
        <hr class="my-4">
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-calendar-check text-primary"></i>
                            Gestión de Citas
                        </h5>
                        <p class="card-text">
                            Agenda tus citas de 40 minutos con los odontólogos disponibles.
                            Sistema inteligente de slots que muestra disponibilidad en tiempo real.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-robot text-success"></i>
                            Asistente Virtual 24/7
                        </h5>
                        <p class="card-text">
                            Disponible las 24 horas para responder tus preguntas, agendar citas
                            y consultar pagos sin esperar horario de oficina.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-cash-coin text-warning"></i>
                            Control de Pagos
                        </h5>
                        <p class="card-text">
                            Registro digital de pagos con comprobantes fotográficos.
                            Historial accesible desde cualquier dispositivo.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-shield-lock text-danger"></i>
                            Sistema Seguro
                        </h5>
                        <p class="card-text">
                            8 capas de seguridad: bcrypt, tokens CSRF, prepared statements,
                            y sistema de protección contra pacientes malintencionados.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!estaLogueado()): ?>
            <div class="text-center mt-4">
                <a href="registro.php" class="btn btn-primary btn-lg me-2">
                    <i class="bi bi-person-plus"></i> Regístrate Ahora
                </a>
                <a href="login.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Tarjeta de información del consultorio -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Consultorio ECO-DENT
            </div>
            <div class="card-body">
                <p><i class="bi bi-geo-alt"></i> La Paz, Bolivia</p>
                <p><i class="bi bi-telephone"></i> Tel: 77112233</p>
                <p><i class="bi bi-clock"></i> Horario: Lunes a Viernes</p>
                <p><i class="bi bi-clock"></i> Dr. Mamani: 8:00 - 18:00</p>
                <p><i class="bi bi-clock"></i> Dra. Quispe: 9:00 - 17:00</p>
                <p><i class="bi bi-hourglass"></i> Duración citas: 40 minutos</p>
            </div>
        </div>
    </div>
</div>

<?php
// Incluimos el footer
require_once '../includes/footer.php';
?>