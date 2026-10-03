/**
 * API.JS - DEVIOZ ACADEMY
 * Helper para hacer llamadas fetch al backend de forma consistente
 */

const API_BASE = '/lms_prepa/backend/controllers/';

/**
 * Hace una petición POST al backend
 */
async function apiPost(controller, accion, datos = {}) {
    try {
        const formData = new FormData();
        formData.append('accion', accion);
        
        for (const [key, value] of Object.entries(datos)) {
            formData.append(key, value);
        }
        
        const response = await fetch(`${API_BASE}${controller}`, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error(`❌ Error en apiPost (${controller}/${accion}):`, error);
        return { ok: false, msg: 'Error de conexión con el servidor' };
    }
}

/**
 * Hace una petición GET al backend
 */
async function apiGet(controller, accion, params = {}) {
    try {
        const queryString = new URLSearchParams({ accion, ...params }).toString();
        const response = await fetch(`${API_BASE}${controller}?${queryString}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error(`❌ Error en apiGet (${controller}/${accion}):`, error);
        return { ok: false, msg: 'Error de conexión con el servidor' };
    }
}

/**
 * Hace una petición POST con archivos (FormData completo)
 */
async function apiPostWithFiles(controller, accion, formData) {
    try {
        formData.append('accion', accion);
        
        const response = await fetch(`${API_BASE}${controller}`, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error(`❌ Error en apiPostWithFiles (${controller}/${accion}):`, error);
        return { ok: false, msg: 'Error de conexión con el servidor' };
    }
}

/**
 * Verifica si hay sesión activa
 */
async function verificarSesion() {
    return await apiPost('AuthController.php', 'me');
}

/**
 * Cierra la sesión actual
 */
async function cerrarSesion() {
    const result = await apiPost('AuthController.php', 'logout');
    if (result.ok) {
        localStorage.removeItem('devioz_usuario');
        window.location.href = 'index.html';
    }
    return result;
}