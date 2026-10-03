/**
 * ACADEMICO.JS - Gestión de módulos preuniversitarios
 */
const API = '/lms_prepa/backend/controllers/AcademicoController.php';
let moduloActual = 'ciclos';
let editandoId = null;
let datosCache = [];
let ciclosCache = [];

const CONFIG = {
    ciclos: {
        nombre: 'Ciclo', plural: 'Ciclos Académicos',
        campos: [
            { name: 'nombre', label: 'Nombre del ciclo *', tipo: 'text', placeholder: 'Ej: Verano' },
            { name: 'duracion', label: 'Duración', tipo: 'text', placeholder: 'Ej: 3 meses' },
            { name: 'descripcion', label: 'Descripción', tipo: 'textarea' }
        ]
    },
    orientaciones: {
        nombre: 'Orientación', plural: 'Orientaciones',
        campos: [
            { name: 'nombre', label: 'Nombre *', tipo: 'text', placeholder: 'Ej: San Marcos' },
            { name: 'universidad', label: 'Universidad', tipo: 'text', placeholder: 'Ej: UNMSM' },
            { name: 'descripcion', label: 'Descripción', tipo: 'textarea' }
        ]
    },
    categorias: {
        nombre: 'Categoría', plural: 'Categorías de Cursos',
        campos: [
            { name: 'nombre', label: 'Nombre *', tipo: 'text', placeholder: 'Ej: Matemáticas' },
            { name: 'icono', label: 'Icono (Font Awesome)', tipo: 'text', placeholder: 'fas fa-calculator' },
            { name: 'descripcion', label: 'Descripción', tipo: 'textarea' }
        ]
    },
    niveles: {
        nombre: 'Nivel', plural: 'Niveles',
        campos: [
            { name: 'nombre', label: 'Nombre *', tipo: 'text', placeholder: 'Ej: Básico' },
            { name: 'orden', label: 'Orden', tipo: 'number' },
            { name: 'descripcion', label: 'Descripción', tipo: 'textarea' }
        ]
    },
    boletines: {
        nombre: 'Boletín', plural: 'Boletines',
        campos: [
            { name: 'titulo', label: 'Título *', tipo: 'text', placeholder: 'Ej: Boletín Ciclo Verano' },
            { name: 'ciclo_id', label: 'Ciclo', tipo: 'select_ciclos' },
            { name: 'anio', label: 'Año', tipo: 'number' },
            { name: 'archivo_url', label: 'URL del PDF', tipo: 'text' },
            { name: 'descripcion', label: 'Descripción', tipo: 'textarea' }
        ]
    }
};

const COLUMNAS = {
    ciclos: ['id', 'nombre', 'duracion', 'estado'],
    orientaciones: ['id', 'nombre', 'universidad', 'estado'],
    categorias: ['id', 'nombre', 'icono', 'estado'],
    niveles: ['id', 'nombre', 'orden', 'estado'],
    boletines: ['id', 'titulo', 'ciclo_nombre', 'anio', 'estado']
};

// ---------- RENDER FORMULARIO ----------
function renderForm() {
    const conf = CONFIG[moduloActual];
    document.getElementById('formTitulo').textContent = (editandoId ? 'Editar ' : 'Nuevo ') + conf.nombre;
    let html = '';
    conf.campos.forEach(c => {
        html += `<div class="campo"><label>${c.label}</label>`;
        if (c.tipo === 'textarea') html += `<textarea id="f_${c.name}" rows="2"></textarea>`;
        else if (c.tipo === 'select_ciclos') html += `<select id="f_${c.name}"><option value="">Sin ciclo</option>${ciclosCache.map(x => `<option value="${x.id}">${x.nombre}</option>`).join('')}</select>`;
        else html += `<input type="${c.tipo}" id="f_${c.name}" placeholder="${c.placeholder || ''}">`;
        html += `</div>`;
    });
    html += `<div class="campo"><label>Estado</label><select id="f_estado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>`;
    html += `<button type="submit" class="btn-devioz"><i class="fas fa-save"></i> Guardar</button>
             ${editandoId ? '<button type="button" class="btn-cancelar" id="btnCancelar">Cancelar</button>' : ''}`;
    document.getElementById('formModulo').innerHTML = html;

    const btnCancel = document.getElementById('btnCancelar');
    if (btnCancel) btnCancel.onclick = () => { editandoId = null; renderForm(); };
}

// ---------- RENDER TABLA ----------
function renderTabla(datos) {
    const cols = COLUMNAS[moduloActual];
    let html = '<thead><tr>' + cols.map(c => `<th>${c}</th>`).join('') + '<th>Acciones</th></tr></thead><tbody>';
    if (!datos.length) html += `<tr><td colspan="${cols.length + 1}" style="text-align:center">Sin registros</td></tr>`;
    datos.forEach(d => {
        html += '<tr>' + cols.map(c => `<td>${d[c] ?? ''}</td>`).join('') +
            `<td><button class="btn-edit" onclick="editar(${d.id})"><i class="fas fa-edit"></i></button>
             <button class="btn-del" onclick="eliminar(${d.id})"><i class="fas fa-trash"></i></button></td></tr>`;
    });
    document.getElementById('tablaModulo').innerHTML = html + '</tbody>';
}

// ---------- LISTAR ----------
async function listar() {
    const res = await fetch(API + '?accion=' + moduloActual + '_listar');
    const data = await res.json();
    if (data.ok) { datosCache = data.datos || []; renderTabla(datosCache); }
}

// ---------- EDITAR ----------
function editar(id) {
    const d = datosCache.find(x => x.id == id);
    if (!d) return;
    editandoId = id;
    renderForm();
    CONFIG[moduloActual].campos.forEach(c => {
        const el = document.getElementById('f_' + c.name);
        if (el) el.value = d[c.name] ?? '';
    });
    document.getElementById('f_estado').value = d.estado || 'activo';
}

// ---------- ELIMINAR ----------
async function eliminar(id) {
    if (!confirm('¿Eliminar este registro?')) return;
    const fd = new FormData();
    fd.append('accion', moduloActual + '_eliminar');
    fd.append('id', id);
    const res = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    alert(data.msg);
    listar();
}

// ---------- CARGAR CICLOS (para select de boletines) ----------
async function cargarCiclos() {
    const res = await fetch(API + '?accion=ciclos_listar');
    const data = await res.json();
    if (data.ok) ciclosCache = data.datos || [];
}

// ---------- INICIALIZACIÓN ----------
document.addEventListener('DOMContentLoaded', async () => {
    const usuario = JSON.parse(localStorage.getItem('devioz_usuario'));
    if (!usuario || usuario.rol !== 'admin') { window.location.href = 'index.html'; return; }
    document.getElementById('nombreBadge').textContent = usuario.nombre;

    document.getElementById('btnLogout').onclick = (e) => {
        e.preventDefault();
        localStorage.removeItem('devioz_usuario');
        window.location.href = 'index.html';
    };

    // Tabs
    document.querySelectorAll('.tab').forEach(t => {
        t.onclick = () => {
            document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            moduloActual = t.dataset.mod;
            editandoId = null;
            document.getElementById('tablaTitulo').textContent = CONFIG[moduloActual].plural;
            renderForm();
            listar();
        };
    });

    // Guardar
    document.getElementById('formModulo').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('accion', moduloActual + (editandoId ? '_actualizar' : '_crear'));
        if (editandoId) fd.append('id', editandoId);
        CONFIG[moduloActual].campos.forEach(c => {
            const el = document.getElementById('f_' + c.name);
            if (el) fd.append(c.name, el.value);
        });
        fd.append('estado', document.getElementById('f_estado').value);
        const res = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            editandoId = null;
            renderForm(); listar();
            if (moduloActual === 'ciclos') cargarCiclos();
        } else alert(data.msg);
    });

    await cargarCiclos();
    renderForm();
    listar();
});