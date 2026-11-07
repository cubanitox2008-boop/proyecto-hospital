const ES_PAGINA_PUBLICA = document.body.dataset.publica !== undefined;
let usuarioActual = null;

async function api(url, opciones) {
    try {
        const respuesta = await fetch(url, Object.assign({ credentials: 'same-origin' }, opciones));
        let datos = {};
        try { datos = await respuesta.json(); } catch (e) { }
        if (respuesta.status === 401 && !ES_PAGINA_PUBLICA) {
            window.location.href = 'login.html';
        }
        return { ok: respuesta.ok, estado: respuesta.status, datos: datos };
    } catch (error) {
        return { ok: false, estado: 0, datos: { error: 'No se pudo conectar con el servidor' } };
    }
}

function apiJson(url, cuerpo) {
    return api(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cuerpo || {})
    });
}

function mostrarMensaje(elemento, texto, esError) {
    elemento.textContent = texto;
    elemento.className = esError ? 'c-msg-error' : 'c-msg-ok';
}

function limpiar(elemento) {
    while (elemento.firstChild) { elemento.removeChild(elemento.firstChild); }
}

function crearCelda(texto) {
    const celda = document.createElement('td');
    celda.textContent = (texto === null || texto === undefined) ? '' : texto;
    return celda;
}

function crearBoton(texto, clase, alHacerClic) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'c-btn ' + (clase || '');
    boton.textContent = texto;
    boton.addEventListener('click', alHacerClic);
    return boton;
}

function llenarSelect(select, lista, campoValor, campoTexto, textoInicial) {
    limpiar(select);
    const inicial = document.createElement('option');
    inicial.value = '';
    inicial.textContent = textoInicial;
    select.appendChild(inicial);
    lista.forEach((elemento) => {
        const opcion = document.createElement('option');
        opcion.value = elemento[campoValor];
        opcion.textContent = elemento[campoTexto];
        select.appendChild(opcion);
    });
}

function formatearHora(hora) {
    return hora ? hora.slice(0, 5) : '';
}

async function cerrarSesion() {
    await api('php/api/logout.php', { method: 'POST' });
    window.location.href = 'login.html';
}

function mostrarUsuario() {
    const lugar = document.getElementById('nombreUsuario');
    if (!lugar) { return; }
    limpiar(lugar);
    const texto = document.createElement('span');
    texto.textContent = '\u{1F464} ' + usuarioActual.nombre + ' (' + usuarioActual.rol + ')';
    lugar.appendChild(texto);
    lugar.appendChild(crearBoton('Salir', 'c-btn-salir', cerrarSesion));
}

function aplicarRoles() {
    document.querySelectorAll('[data-roles]').forEach((elemento) => {
        if (elemento.dataset.roles.split(',').includes(usuarioActual.rol)) {
            elemento.classList.add('rol-ok');
        }
    });
}

async function cargarSesion() {
    const r = await api('php/api/sesion.php');
    if (!r.datos.autenticado) {
        if (!ES_PAGINA_PUBLICA) { window.location.href = 'login.html'; }
        return null;
    }
    usuarioActual = r.datos.usuario;

    const rolesPagina = document.body.dataset.rolesPagina;
    if (rolesPagina && !rolesPagina.split(',').includes(usuarioActual.rol)) {
        alert('No tenés permisos para entrar a esta sección.');
        window.location.href = 'panel.html';
        return null;
    }

    mostrarUsuario();
    aplicarRoles();
    return usuarioActual;
}

const sesionLista = cargarSesion();
