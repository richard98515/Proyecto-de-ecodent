<?php
// public/asistente/index.php
// Asistente Virtual Inteligente con AJAX

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

// Verificar autenticación
if (!estaLogueado()) {
    redirigir('/ecodent/public/login.php');
}

// Solo pacientes pueden usar el asistente
if (!esPaciente()) {
    if (esAdmin()) {
        redirigir('/ecodent/public/admin/dashboard.php');
    } elseif (esOdontologo()) {
        redirigir('/ecodent/public/odontologo/dashboard.php');
    }
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener id_paciente
$stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$id_paciente = $paciente['id_paciente'];
$_SESSION['id_paciente'] = $id_paciente;

// Obtener nombre del paciente
$nombre_paciente = $_SESSION['nombre_completo'];

require_once '../../includes/header.php';
?>

<style>
:root {
    --chat-primary: #0a58ca;
    --chat-secondary: #20c997;
    --chat-dark: #0a3a8c;
}

.chat-container {
    max-width: 900px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
    height: calc(100vh - 200px);
    min-height: 500px;
    display: flex;
    flex-direction: column;
}

.chat-header {
    background: linear-gradient(135deg, var(--chat-primary), var(--chat-dark));
    color: white;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.chat-header-icon {
    background: rgba(255,255,255,0.2);
    width: 50px;
    height: 50px;
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.chat-header-info h2 {
    margin: 0;
    font-size: 1.3rem;
}

.chat-header-info p {
    margin: 0;
    font-size: 0.8rem;
    opacity: 0.8;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fc;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.message {
    display: flex;
    margin-bottom: 8px;
    animation: fadeIn 0.3s ease;
}

.message.user {
    justify-content: flex-end;
}

.message.bot {
    justify-content: flex-start;
}

.message-content {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 0.95rem;
    line-height: 1.4;
}

.message.user .message-content {
    background: linear-gradient(135deg, var(--chat-primary), var(--chat-dark));
    color: white;
    border-radius: 18px 18px 4px 18px;
}

.message.bot .message-content {
    background: white;
    color: #333;
    border-radius: 18px 18px 18px 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.message-time {
    font-size: 0.7rem;
    margin-top: 5px;
    opacity: 0.6;
}

.message.user .message-time {
    text-align: right;
    color: #666;
}

.message.bot .message-time {
    text-align: left;
    color: #666;
}

.chat-input-area {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
}

.chat-input-area input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.chat-input-area input:focus {
    outline: none;
    border-color: var(--chat-primary);
}

.chat-input-area button {
    background: var(--chat-primary);
    border: none;
    color: white;
    padding: 0 25px;
    border-radius: 30px;
    font-weight: 500;
    transition: all 0.2s;
}

.chat-input-area button:hover {
    background: var(--chat-dark);
    transform: scale(1.02);
}

.chat-input-area button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.quick-buttons {
    display: flex;
    gap: 10px;
    padding: 10px 20px;
    background: #f8f9fc;
    border-top: 1px solid #e9ecef;
    flex-wrap: wrap;
}

.quick-btn {
    background: white;
    border: 1px solid #dee2e6;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.quick-btn:hover {
    background: var(--chat-primary);
    color: white;
    border-color: var(--chat-primary);
}

.typing-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    background: white;
    border-radius: 18px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #999;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
        opacity: 0.4;
    }
    30% {
        transform: translateY(-10px);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.welcome-message {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.welcome-message i {
    font-size: 3rem;
    color: var(--chat-primary);
    margin-bottom: 15px;
}

/* Scrollbar */
.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 150px);
        border-radius: 0;
    }
    
    .message-content {
        max-width: 85%;
    }
    
    .quick-buttons {
        display: none;
    }
}
</style>

<div class="container mt-3">
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-header-icon">
                <i class="bi bi-robot"></i>
            </div>
            <div class="chat-header-info">
                <h2>Asistente Virtual ECO-DENT</h2>
                <p><i class="bi bi-check-circle-fill"></i> Disponible 24/7 - Respuesta inmediata</p>
            </div>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message bot">
                <div class="message-content">
                    <i class="bi bi-robot me-2"></i>
                    ¡Hola <?php echo htmlspecialchars($nombre_paciente); ?>! 👋<br><br>
                    Soy el asistente virtual de <strong>ECO-DENT</strong>. Estoy aquí para ayudarte 24/7.<br><br>
                    <strong>¿Qué puedo hacer por ti?</strong><br>
                    • 📅 <strong>Agendar cita</strong> - Te guío paso a paso<br>
                    • 👀 <strong>Ver mis citas</strong> - Te muestro tus próximas citas<br>
                    • ❌ <strong>Cancelar cita</strong> - Cancela una cita existente<br>
                    • 💰 <strong>Ver mis pagos</strong> - Consulta tu historial y saldo<br>
                    • 📋 <strong>Tratamientos</strong> - Información de tus tratamientos<br><br>
                    <small class="text-muted">Escribe tu mensaje o usa los botones rápidos 👇</small>
                </div>
            </div>
        </div>
        
        <div class="quick-buttons">
            <button class="quick-btn" onclick="enviarMensajeRapido('Hola')">👋 Saludar</button>
            <button class="quick-btn" onclick="enviarMensajeRapido('Quiero agendar una cita')">📅 Agendar cita</button>
            <button class="quick-btn" onclick="enviarMensajeRapido('Ver mis citas')">👀 Mis citas</button>
            <button class="quick-btn" onclick="enviarMensajeRapido('Cancelar mi cita')">❌ Cancelar cita</button>
            <button class="quick-btn" onclick="enviarMensajeRapido('Ver mis pagos')">💰 Mis pagos</button>
            <button class="quick-btn" onclick="enviarMensajeRapido('Ayuda')">❓ Ayuda</button>
        </div>
        
        <div class="chat-input-area">
            <input type="text" id="mensajeInput" placeholder="Escribe tu mensaje aquí..." onkeypress="if(event.key==='Enter') enviarMensaje()">
            <button id="btnEnviar" onclick="enviarMensaje()">
                <i class="bi bi-send-fill"></i> Enviar
            </button>
        </div>
    </div>
</div>

<script>
const idPaciente = <?php echo $id_paciente; ?>;
const nombrePaciente = "<?php echo htmlspecialchars($nombre_paciente); ?>";

function enviarMensajeRapido(mensaje) {
    document.getElementById('mensajeInput').value = mensaje;
    enviarMensaje();
}

function enviarMensaje() {
    const input = document.getElementById('mensajeInput');
    const mensaje = input.value.trim();
    const btnEnviar = document.getElementById('btnEnviar');
    
    if (!mensaje) return;
    
    // Limpiar input
    input.value = '';
    
    // Deshabilitar botón mientras se envía
    btnEnviar.disabled = true;
    
    // Mostrar mensaje del usuario
    agregarMensaje(mensaje, 'user');
    
    // Mostrar indicador de escritura
    mostrarTyping();
    
    // Enviar a la API
    fetch('/ecodent/public/asistente/chat_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            mensaje: mensaje,
            id_paciente: idPaciente
        })
    })
    .then(response => response.json())
    .then(data => {
        // Ocultar indicador de escritura
        ocultarTyping();
        
        if (data.error) {
            agregarMensaje('Lo siento, hubo un error. Por favor intenta de nuevo.', 'bot');
        } else {
            agregarMensaje(data.respuesta, 'bot');
            
            // Si hay acción adicional (como redirección)
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
        }
        
        btnEnviar.disabled = false;
        scrollToBottom();
    })
    .catch(error => {
        ocultarTyping();
        agregarMensaje('Error de conexión. Por favor intenta de nuevo.', 'bot');
        btnEnviar.disabled = false;
    });
}

function agregarMensaje(texto, tipo) {
    const chatMessages = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${tipo}`;
    
    const ahora = new Date();
    const hora = ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    
    messageDiv.innerHTML = `
        <div class="message-content">
            ${texto}
            <div class="message-time">${hora}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

function mostrarTyping() {
    const chatMessages = document.getElementById('chatMessages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="typing-indicator">
            <span></span>
            <span></span>
            <span></span>
        </div>
    `;
    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}

function ocultarTyping() {
    const typing = document.getElementById('typingIndicator');
    if (typing) {
        typing.remove();
    }
}

function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Enfocar input al cargar
document.getElementById('mensajeInput').focus();
</script>

<?php require_once '../../includes/footer.php'; ?></br>