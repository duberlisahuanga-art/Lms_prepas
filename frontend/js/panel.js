/* ===== PANEL DEVIOZ: sidebar automático + utilidades ===== */
const BASE = '/lms_prepa/backend/controllers/';

(function inyectarSidebar(){
    const usuario = JSON.parse(localStorage.getItem('devioz_usuario') || 'null');
    if (!usuario || !usuario.rol){ location.href = 'login.html'; return; }
    const links = usuario.rol === 'admin' ? [
        ['dashboard_admin.html','fa-home','Dashboard'],
        ['estudiantes_admin.html','fa-users','Estudiantes'],
        ['cursos_admin.html','fa-book-open','Cursos'],
        ['biblioteca_admin.html','fa-book','Biblioteca'],
        ['universidades_admin.html','fa-university','Universidades'],
        ['monitoreo_admin.html','fa-chart-line','Monitoreo'],
        ['perfil_admin.html','fa-user-shield','Perfil']
    ] : [
        ['dashboard_estudiante.html','fa-home','Inicio'],
        ['cursos_estudiante.html','fa-book-open','Cursos'],
        ['biblioteca_estudiante.html','fa-book','Biblioteca'],
        ['universidades_estudiante.html','fa-university','Universidades'],
        ['perfil_estudiante.html','fa-user','Perfil']
    ];
    const actual = location.pathname.split('/').pop();
    const aside = document.getElementById('sidebar');
    aside.innerHTML =
        `<div class="brand"><img src="../img/logo-devioz.png" alt="Devioz"><span>Devioz <em>Academy</em></span></div>` +
        links.map(l => `<a href="${l[0]}" class="${l[0] === actual ? 'active' : ''}"><i class="fas ${l[1]}"></i><span>${l[2]}</span></a>`).join('') +
        `<a href="#" class="logout" id="btnLogout"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>`;

    let btn = document.getElementById('menuBtn');
    if (!btn){
        btn = document.createElement('button');
        btn.className = 'menu-btn'; btn.id = 'menuBtn';
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(btn);
    }
    btn.onclick = () => aside.classList.toggle('open');
    aside.querySelector('#btnLogout').onclick = e => {
        e.preventDefault();
        localStorage.removeItem('devioz_usuario');
        location.href = 'login.html';
    };
})();

/* ===== Utilidades compartidas ===== */
async function apiListar(ctrl){
    const r = await fetch(BASE + ctrl + '?accion=listar');
    const d = await r.json();
    return d.data || d.datos || [];
}
async function apiPost(ctrl, fd){
    const r = await fetch(BASE + ctrl, { method:'POST', body: fd });
    return r.json();
}
function tabla(head, rows){
    if (!rows.length) return '<p class="empty">Sin registros todavía.</p>';
    return '<table><tr>' + head.map(h => `<th>${h}</th>`).join('') + '</tr>' +
        rows.map(r => '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>').join('') + '</table>';
}
const nombreDe = (arr, id) => { const o = (arr||[]).find(x => x.id == id); return o ? (o.nombre||o.titulo||'—') : '—'; };