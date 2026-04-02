<?php
// includes/footer.php
// Pie de página común - Versión moderna
?>
    </main>

    <!-- Footer Moderno -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <i class="bi bi-hospital"></i>
                    <span>EcoDent</span>
                </div>
                <div class="footer-links">
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        <a href="/ecodent/public/perfil.php">Mi Perfil</a>
                        <?php if ($_SESSION['rol'] === 'admin'): ?>
                            <a href="/ecodent/public/admin/dashboard.php">Dashboard</a>
                        <?php elseif ($_SESSION['rol'] === 'odontologo'): ?>
                            <a href="/ecodent/public/odontologo/calendario.php">Calendario</a>
                        <?php else: ?>
                            <a href="/ecodent/public/paciente/dashboard.php">Mi Panel</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="/ecodent/public/login.php">Iniciar Sesión</a>
                        <a href="/ecodent/public/registro.php">Registrarse</a>
                    <?php endif; ?>
                    <a href="/ecodent/public/asistente_virtual.php">Asistente Virtual</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> EcoDent. Todos los derechos reservados.</p>
                <p class="footer-phone"><i class="bi bi-telephone"></i> 77112233</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="/ecodent/public/js/script.js"></script>
</body>
</html>