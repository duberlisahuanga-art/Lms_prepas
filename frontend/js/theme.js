/**
 * THEME.JS - DEVIOZ ACADEMY (VERSIÓN FINAL)
 * Solo inyecta "Gestión Académica" y reordena el menú admin.
 * NO modifica el tema (para no alterar tamaños ni colores).
 */

function onReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

onReady(function () {
    try {
        const usuario = JSON.parse(localStorage.getItem('devioz_usuario') || 'null');
        if (!usuario || usuario.rol !== 'admin') return;

        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        /* ---- 1) Inyectar "Gestión Académica" si falta ---- */
        if (!sidebar.querySelector('a[href="admin_academico.html"]')) {
            const link = document.createElement('a');
            link.href = 'admin_academico.html';
            link.innerHTML = '<i class="fas fa-graduation-cap"></i> <span>Gestión Académica</span>';
            const logout = sidebar.querySelector('#btnLogout') || sidebar.querySelector('.logout-btn');
            logout ? sidebar.insertBefore(link, logout) : sidebar.appendChild(link);
        }

        /* ---- 2) Reordenar menú por orden de uso ---- */
        const orden = [
            'dashboard_admin.html',      // 1. Inicio
            'admin_academico.html',      // 2. Gestión Académica
            'materias_admin.html',       // 3. Materias
            'cursos_admin.html',         // 4. Cursos
            'agregar_libro_admin.html',  // 5. Agregar Libro
            'biblioteca_admin.html',     // 6. Biblioteca
            'universidades_admin.html',  // 7. Universidades
            'estudiantes_admin.html',    // 8. Estudiantes
            'monitoreo_admin.html',      // 9. Monitoreo
            'perfil_admin.html'          // 10. Mi Perfil
        ];

        const logout = sidebar.querySelector('#btnLogout') || sidebar.querySelector('.logout-btn');
        orden.forEach(href => {
            const link = sidebar.querySelector('a[href="' + href + '"]');
            if (link) sidebar.insertBefore(link, logout);
        });
    } catch (e) {
        console.warn('menu admin:', e);
    }
});