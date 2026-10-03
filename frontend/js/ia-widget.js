// ia-widget.js - DEVIOZ ACADEMY
const IA_BASE = '/lms_prepa/backend/controllers/';

async function sendIAChat() {
    var input = document.getElementById('iaChatInput');
    var mensaje = input.value.trim();
    if (!mensaje) return;
    
    agregarIAMensaje('user', mensaje);
    input.value = '';
    var loadingId = agregarIAMensaje('ia', '<i class="fas fa-spinner fa-spin"></i> Analizando...');
    
    try {
        var formData = new FormData();
        formData.append('mensaje', mensaje);
        formData.append('admin_id', 1);
        
        var res = await fetch(IA_BASE + 'IAController.php?accion=chatLibre', { 
            method: 'POST', 
            body: formData 
        });
        var data = await res.json();
        
        document.getElementById(loadingId)?.remove();
        
        if (data.ok) {
            if (data.tiene_accion) {
                agregarIAMensaje('ia', `✅ Acción detectada: <strong>${data.accion.accion}</strong><br>Ejecutando...`);
                ejecutarAccionIA(data.accion);
            } else {
                agregarIAMensaje('ia', data.respuesta);
            }
        } else {
            agregarIAMensaje('ia', '❌ ' + data.msg);
        }
    } catch (error) {
        document.getElementById(loadingId)?.remove();
        agregarIAMensaje('ia', '❌ Error de red: ' + error.message);
    }
}

async function ejecutarAccionIA(accion) {
    var loadingId = agregarIAMensaje('ia', '<i class="fas fa-cogs fa-spin"></i> Guardando en BD...');
    try {
        var formData = new FormData();
        formData.append('accion', JSON.stringify(accion));
        formData.append('admin_id', 1);
        
        var res = await fetch(IA_BASE + 'IAController.php?accion=ejecutarAccion', { 
            method: 'POST', 
            body: formData 
        });
        var data = await res.json();
        
        document.getElementById(loadingId)?.remove();
        agregarIAMensaje('ia', data.ok ? `✅ ${data.msg}` : ` ${data.msg}`);
        
        if (data.ok) setTimeout(() => location.reload(), 1200);
    } catch (error) {
        document.getElementById(loadingId)?.remove();
        agregarIAMensaje('ia', '❌ Error al ejecutar: ' + error.message);
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