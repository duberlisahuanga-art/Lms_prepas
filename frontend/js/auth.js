/**
 * AUTH.JS - DEVIOZ ACADEMY
 * Sistema de autenticación completo
 */

console.log('✅ auth.js cargado correctamente');

// ==========================================
// CONFIGURACIÓN GLOBAL
// ==========================================
const API_URL = '/lms_prepa/backend/controllers/AuthController.php';

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

function mostrarMensaje(elemento, texto, tipo) {
    if (!elemento) {
        alert(texto);
        return;
    }
    elemento.textContent = texto;
    elemento.className = 'mensaje ' + (tipo === 'exito' ? 'exito' : 'error');
    elemento.style.display = 'block';
    
    if (tipo === 'exito') {
        setTimeout(() => { elemento.style.display = 'none'; }, 3000);
    }
}

function setButtonLoading(btn, isLoading, originalText) {
    if (isLoading) {
        btn.disabled = true;
        btn.dataset.originalText = originalText;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText || originalText;
    }
}

async function procesarSolicitud(formData, btn, mensajeDiv, textoOriginal) {
    console.log(' Enviando petición a:', API_URL);
    console.log('📦 Datos:', Object.fromEntries(formData));
    
    setButtonLoading(btn, true, textoOriginal);
    
    try {
        const response = await fetch(API_URL, { 
            method: 'POST', 
            body: formData 
        });
        
        console.log('📥 Status:', response.status);
        
        // Leer respuesta incluso si hay error
        const responseText = await response.text();
        console.log('📄 Respuesta raw:', responseText);
        
        if (!response.ok) {
            let errorMsg = 'Error del servidor';
            try {
                const errorData = JSON.parse(responseText);
                errorMsg = errorData.msg || errorMsg;
            } catch (e) {
                errorMsg = responseText || 'Error ' + response.status;
            }
            console.error('❌ Error:', errorMsg);
            mostrarMensaje(mensajeDiv, errorMsg, 'error');
            setButtonLoading(btn, false, textoOriginal);
            return null;
        }
        
        const data = JSON.parse(responseText);
        console.log('✅ Datos del backend:', data);
        
        if (data.ok) {
            mostrarMensaje(mensajeDiv, data.msg, 'exito');
            return data;
        } else {
            mostrarMensaje(mensajeDiv, data.msg, 'error');
            setButtonLoading(btn, false, textoOriginal);
            return null;
        }
    } catch (error) {
        console.error(' Error en la solicitud:', error);
        mostrarMensaje(mensajeDiv, 'Error de conexión: ' + error.message, 'error');
        setButtonLoading(btn, false, textoOriginal);
        return null;
    }
}

// ==========================================
// LÓGICA DE FORMULARIOS
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOM cargado');
    
    // ---------------------------------------------------------
    // 1. LOGIN (login.html)
    // ---------------------------------------------------------
    const formLogin = document.getElementById('formLogin');
    console.log(' Formulario login encontrado:', !!formLogin);
    
    if (formLogin) {
        formLogin.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('🖱️ Submit del login disparado');
            
            const email = document.getElementById('email')?.value?.trim() || '';
            const password = document.getElementById('password')?.value || '';
            const btn = this.querySelector('button[type="submit"]');
            const mensajeDiv = document.getElementById('mensaje');
            
            console.log('📧 Email:', email);
            console.log('🔑 Password:', password ? '***' : '(vacía)');
            
            if (!email || !password) {
                return mostrarMensaje(mensajeDiv, 'Completa todos los campos', 'error');
            }
            
            const formData = new FormData();
            formData.append('accion', 'login');
            formData.append('email', email);
            formData.append('password', password);
            
            console.log('🚀 Enviando login...');
            const data = await procesarSolicitud(formData, btn, mensajeDiv, 'Iniciar sesión');
            
            if (data) {
                console.log('💾 Guardando usuario:', data.usuario);
                localStorage.setItem('devioz_usuario', JSON.stringify(data.usuario));
                
                const rol = data.usuario.rol;
                console.log('🎭 Rol:', rol);
                
                const destino = (rol === 'admin') ? 'dashboard_admin.html' : 'dashboard_estudiante.html';
                console.log(' Redirigiendo a:', destino);
                
                setTimeout(() => { 
                    window.location.href = destino; 
                }, 1000);
            }
        });
    }
    
    // ---------------------------------------------------------
    // 2. REGISTRO (registro.html)
    // ---------------------------------------------------------
    const formRegistro = document.getElementById('formRegistro');
    console.log('📋 Formulario registro encontrado:', !!formRegistro);
    
    if (formRegistro) {
        formRegistro.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('🖱️ Submit del registro disparado');
            
            const nombre = document.getElementById('nombre').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmarPassword = document.getElementById('confirmarPassword').value;
            const btn = this.querySelector('button[type="submit"]');
            const mensajeDiv = document.getElementById('mensaje');
            
            console.log('👤 Nombre:', nombre);
            console.log(' Email:', email);
            
            if (!nombre || !email || !password) {
                return mostrarMensaje(mensajeDiv, 'Completa todos los campos', 'error');
            }
            if (password !== confirmarPassword) {
                return mostrarMensaje(mensajeDiv, 'Las contraseñas no coinciden', 'error');
            }
            if (password.length < 6) {
                return mostrarMensaje(mensajeDiv, 'La contraseña debe tener al menos 6 caracteres', 'error');
            }
            
            const formData = new FormData();
            formData.append('accion', 'registrar');
            formData.append('nombre', nombre);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirmarPassword', confirmarPassword);
            
            console.log('🚀 Enviando registro...');
            const data = await procesarSolicitud(formData, btn, mensajeDiv, 'Registrarme Ahora');
            
            if (data) {
                console.log('✅ Registro exitoso, redirigiendo al login');
                setTimeout(() => window.location.href = 'login.html', 2000);
            }
        });
    }
});