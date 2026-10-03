/**
 * ESTUDIANTES_ADMIN.JS - DEVIOZ ACADEMY
 * Gestión de estudiantes desde el panel de administrador
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Verificar que sea admin
    const usuario = JSON.parse(localStorage.getItem('devioz_usuario'));
    if (!usuario || usuario.rol !== 'admin') {
        window.location.href = 'index.html';
        return;
    }
    
    cargarEstudiantes();
    
    // ---------------------------------------------------------
    // CARGAR LISTA DE ESTUDIANTES
    // ---------------------------------------------------------
    async function cargarEstudiantes() {
        const tbody = document.getElementById('tablaEstudiantes');
        if (!tbody) return;
        
        try {
            const data = await apiPost('EstudianteController.php', 'listar');
            
            if (data.ok) {
                tbody.innerHTML = '';
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay estudiantes registrados</td></tr>';
                    return;
                }
                
                data.data.forEach(est => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${est.id}</td>
                        <td>${est.nombre}</td>
                        <td>${est.email}</td>
                        <td><span class="badge badge-${est.estado}">${est.estado}</span></td>
                        <td>
                            <button class="action-btn btn-edit" onclick="editarEstudiante(${est.id}, '${est.nombre}', '${est.estado}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="eliminarEstudiante(${est.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        } catch (error) {
            console.error('Error cargando estudiantes:', error);
        }
    }
    
    // ---------------------------------------------------------
    // EDITAR ESTUDIANTE
    // ---------------------------------------------------------
    window.editarEstudiante = function(id, nombre, estado) {
        const nuevoNombre = prompt('Nuevo nombre:', nombre);
        if (nuevoNombre && nuevoNombre.trim() !== '') {
            const nuevoEstado = prompt('Estado (activo/inactivo):', estado);
            if (nuevoEstado === 'activo' || nuevoEstado === 'inactivo') {
                actualizarEstudiante(id, nuevoNombre.trim(), nuevoEstado);
            }
        }
    };
    
    async function actualizarEstudiante(id, nombre, estado) {
        try {
            const data = await apiPost('EstudianteController.php', 'actualizar', {
                id, nombre, estado
            });
            
            if (data.ok) {
                alert('Estudiante actualizado correctamente');
                cargarEstudiantes();
            } else {
                alert('Error: ' + data.msg);
            }
        } catch (error) {
            alert('Error de conexión');
        }
    }
    
    // ---------------------------------------------------------
    // ELIMINAR ESTUDIANTE
    // ---------------------------------------------------------
    window.eliminarEstudiante = function(id) {
        if (confirm('¿Estás seguro de eliminar este estudiante? Esta acción no se puede deshacer.')) {
            eliminarEstudianteConfirm(id);
        }
    };
    
    async function eliminarEstudianteConfirm(id) {
        try {
            const data = await apiPost('EstudianteController.php', 'eliminar', { id });
            
            if (data.ok) {
                alert('Estudiante eliminado correctamente');
                cargarEstudiantes();
            } else {
                alert('Error: ' + data.msg);
            }
        } catch (error) {
            alert('Error de conexión');
        }
    }
    
    // ---------------------------------------------------------
    // BÚSQUEDA DE ESTUDIANTES
    // ---------------------------------------------------------
    const searchInput = document.getElementById('buscarEstudiante');
    if (searchInput) {
        searchInput.addEventListener('input', async function() {
            const termino = this.value.trim();
            const tbody = document.getElementById('tablaEstudiantes');
            
            if (termino.length < 2) {
                cargarEstudiantes();
                return;
            }
            
            try {
                const data = await apiPost('EstudianteController.php', 'buscar', { termino });
                
                if (data.ok) {
                    tbody.innerHTML = '';
                    
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No se encontraron resultados</td></tr>';
                        return;
                    }
                    
                    data.data.forEach(est => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${est.id}</td>
                            <td>${est.nombre}</td>
                            <td>${est.email}</td>
                            <td><span class="badge badge-${est.estado}">${est.estado}</span></td>
                            <td>
                                <button class="action-btn btn-edit" onclick="editarEstudiante(${est.id}, '${est.nombre}', '${est.estado}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn btn-delete" onclick="eliminarEstudiante(${est.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (error) {
                console.error('Error en búsqueda:', error);
            }
        });
    }
});