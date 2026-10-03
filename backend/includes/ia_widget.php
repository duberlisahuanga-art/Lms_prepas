<!-- Botón Flotante IA -->
<button class="ia-toggle-btn" onclick="toggleIAChat()" title="Asistente IA">
    <i class="fas fa-robot"></i>
</button>

<!-- Widget de Chat IA -->
<div class="ia-chat-widget" id="iaChatWidget">
    <div class="ia-chat-header">
        <h3><i class="fas fa-robot"></i> Asistente Devioz <span class="ia-context-badge" id="iaContextBadge">Activo</span></h3>
        <button class="ia-chat-close" onclick="toggleIAChat()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ia-chat-messages" id="iaChatMessages">
        <!-- Mensaje de bienvenida DINÁMICO -->
        <div class="chat-msg ia" style="max-width: 100%;">
            <div class="chat-msg-avatar"><i class="fas fa-robot"></i></div>
            <div class="chat-msg-content">
                <strong>DEVIOZ AI</strong>
                <p style="font-size: 0.9rem;">¡Hola! Soy tu asistente inteligente.<br><br>
                <strong>Contexto actual:</strong><br>
                <!-- El script de abajo cambiará este texto automáticamente -->
                <span id="iaContexto" style="color: var(--mu);">Detectando módulo...</span><br><br>
                <em style="font-size: 0.85rem;">Pregúntame o pide una acción.</em></p>
            </div>
        </div>
    </div>
    <div class="ia-chat-input">
        <input type="text" id="iaChatInput" placeholder="Escribe tu consulta...">
        <button onclick="sendIAChat()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
const IA_BASE = '/lms_prepa/backend/controllers/';
let iaChatOpen = false;

// Mapa de contextos para cada módulo
const contextosPorModulo = {
    'dashboard': 'Ver estadísticas, crear reportes, analizar datos del sistema, generar resúmenes',
    'estudiantes': 'Crear estudiantes, asignar cursos, gestionar notas, ver progreso, enviar mensajes',
    'cursos': 'Crear cursos, módulos, asignar profesores, subir material, gestionar contenidos',
    'biblioteca': 'Subir libros, gestionar PDFs, organizar material educativo, indexar contenidos',
    'universidades': 'Registrar universidades, buscar en internet (Google), gestionar tipos (nacional/particular/instituto)',
    'monitoreo': 'Ver avances de estudiantes, estadísticas de rendimiento, alertas, reportes',
    'perfil': 'Actualizar datos personales, cambiar contraseña, configuración de cuenta',
    'default': 'Navegar por el sistema, crear contenido, gestionar información'
};

document.addEventListener('DOMContentLoaded', function() {
    detectarModuloActual();
    
    document.getElementById('iaChatInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') sendIAChat();
    });
});

// ✅ Función que detecta dónde estás y actualiza el chat
function detectarModuloActual() {
    var path = window.location.pathname;
    var modulo = 'default';
    
    // Lógica de detección simple basada en la URL
    if (path.includes('universidades')) modulo = 'universidades';
    else if (path.includes('dashboard')) modulo = 'dashboard';
    else if (path.includes('estudiantes')) modulo = 'estudiantes';
    else if (path.includes('cursos')) modulo = 'cursos';
    else if (path.includes('biblioteca')) modulo = 'biblioteca';
    else if (path.includes('monitoreo')) modulo = 'monitoreo';
    else if (path.includes('perfil')) modulo = 'perfil';
    
    var contextoEl = document.getElementById('iaContexto');
    var badgeEl = document.getElementById('iaContextBadge');
    
    // Actualizar el mensaje de bienvenida si el elemento existe
    if (contextoEl && contextosPorModulo[modulo]) {
        contextoEl.textContent = contextosPorModulo[modulo];
    }
    
    // Actualizar el badge de arriba
    if (badgeEl) {
        var nombreModulo = modulo.charAt(0).toUpperCase() + modulo.slice(1);
        badgeEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + nombreModulo;
    }
}

function toggleIAChat() {
    var widget = document.getElementById('iaChatWidget');
    var btn = document.querySelector('.ia-toggle-btn');
    iaChatOpen = !iaChatOpen;
    
    if (iaChatOpen) {
        widget.classList.add('active');
        btn.style.animation = 'none';
        setTimeout(function() { document.getElementById('iaChatInput').focus(); }, 300);
    } else {
        widget.classList.remove('active');
        btn.style.animation = 'bounce 2s infinite';
    }
}

async function sendIAChat() {
    var input = document.getElementById('iaChatInput');
    var mensaje = input.value.trim();
    if (!mensaje) return;
    
    agregarIAMensaje('user', mensaje);
    input.value = '';
    var loadingId = agregarIAMensaje('ia', '<i class="fas fa-spinner fa-spin"></i> Pensando...');
    
    try {
        var formData = new FormData();
        formData.append('mensaje', mensaje);
        formData.append('admin_id', 1);
        
        // 🔑 Enviar el módulo detectado para que la IA sepa el contexto
        var path = window.location.pathname;
        if (path.includes('universidades')) formData.append('modulo', 'universidades');
        else if (path.includes('cursos')) formData.append('modulo', 'cursos');
        else if (path.includes('monitoreo')) formData.append('modulo', 'monitoreo');
        else formData.append('modulo', 'general');
        
        var res = await fetch(IA_BASE + 'IAController.php?accion=chatLibre', { 
            method: 'POST', 
            body: formData 
        });
        var data = await res.json();
        
        var loadingEl = document.getElementById(loadingId);
        if (loadingEl) loadingEl.remove();
        
        if (data.ok) {
            agregarIAMensaje('ia', data.respuesta);
            
            // Si la IA detectó que quieres crear algo, ejecuta la acción
            if (data.tiene_accion && data.accion) {
                setTimeout(function() { ejecutarAccionIA(data.accion); }, 500);
            }
        } else {
            agregarIAMensaje('ia', '❌ ' + data.msg);
        }
    } catch (error) {
        var loadingEl = document.getElementById(loadingId);
        if (loadingEl) loadingEl.remove();
        agregarIAMensaje('ia', '❌ Error de conexión: ' + error.message);
    }
}

function agregarIAMensaje(tipo, contenido) {
    var container = document.getElementById('iaChatMessages');
    var msgDiv = document.createElement('div');
    msgDiv.className = 'chat-msg ' + tipo;
    msgDiv.id = 'ia-msg-' + Date.now();
    msgDiv.style.maxWidth = '100%';
    
    if (tipo === 'ia') {
        msgDiv.innerHTML = '<div class="chat-msg-avatar"><i class="fas fa-robot"></i></div><div class="chat-msg-content"><strong>DEVIOZ AI</strong><p style="font-size: 0.9rem;">' + contenido + '</p></div>';
    } else {
        msgDiv.innerHTML = '<div class="chat-msg-content" style="background: var(--glow); color: #000;"><strong>Tú</strong><p style="font-size: 0.9rem;">' + contenido + '</p></div><div class="chat-msg-avatar user" style="background: rgba(0,255,136,0.2); border-color: var(--success); color: var(--success);"><i class="fas fa-user"></i></div>';
    }
    
    container.appendChild(msgDiv);
    container.scrollTop = container.scrollHeight;
    return msgDiv.id;
}

async function ejecutarAccionIA(accion) {
    var loadingId = agregarIAMensaje('ia', '<i class="fas fa-cogs fa-spin"></i> Ejecutando...');
    try {
        var formData = new FormData();
        formData.append('accion', JSON.stringify(accion));
        formData.append('admin_id', 1);
        
        var res = await fetch(IA_BASE + 'IAController.php?accion=ejecutarAccion', { 
            method: 'POST', 
            body: formData 
        });
        var data = await res.json();
        
        var loadingEl = document.getElementById(loadingId);
        if (loadingEl) loadingEl.remove();
        
        if (data.ok) {
            agregarIAMensaje('ia', '✅ ' + data.msg);
            setTimeout(function() { location.reload(); }, 1500); // Recarga para ver cambios
        } else {
            agregarIAMensaje('ia', '❌ ' + data.msg);
        }
    } catch (error) {
        var loadingEl = document.getElementById(loadingId);
        if (loadingEl) loadingEl.remove();
        agregarIAMensaje('ia', '❌ Error: ' + error.message);
    }
}
</script>