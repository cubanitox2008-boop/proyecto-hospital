const URL_USUARIOS = 'php/api/usuarios.php';
const mensaje = document.getElementById('mensaje');
const cuerpoTabla = document.getElementById('cuerpoUsuarios');

sesionLista.then((usuario) => {
    if (usuario) { listarUsuarios(); }
});

async function listarUsuarios() {
    const r = await api(URL_USUARIOS + '?accion=listar');
    if (!r.ok) { mostrarMensaje(mensaje, r.datos.error || 'No se pudo cargar el listado', true); return; }

    limpiar(cuerpoTabla);
    r.datos.usuarios.forEach((u) => {
        const esUnoMismo = Number(u.identificacion) === usuarioActual.identificacion;
        const fila = document.createElement('tr');
        fila.appendChild(crearCelda(u.nombre));
        fila.appendChild(crearCelda(u.CI_funcionario));

        const celdaRol = document.createElement('td');
        const select = document.createElement('select');
        r.datos.roles.forEach((rol) => {
            const opcion = document.createElement('option');
            opcion.value = rol;
            opcion.textContent = rol;
            opcion.selected = rol === u.tipo;
            select.appendChild(opcion);
        });
        select.disabled = esUnoMismo;
        celdaRol.appendChild(select);
        fila.appendChild(celdaRol);

        const acciones = document.createElement('td');
        if (!esUnoMismo) {
            acciones.appendChild(crearBoton('Guardar rol', '', () => cambiarRol(u, select.value)));
            acciones.appendChild(crearBoton('Eliminar', 'c-btn-peligro', () => eliminarUsuario(u)));
        } else {
            acciones.textContent = '(vos)';
        }
        fila.appendChild(acciones);
        cuerpoTabla.appendChild(fila);
    });
}

async function cambiarRol(u, nuevoRol) {
    const r = await apiJson(URL_USUARIOS + '?accion=rol&id=' + u.identificacion, { tipo: nuevoRol });
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    listarUsuarios();
}

async function eliminarUsuario(u) {
    if (!confirm('¿Eliminar al usuario ' + u.nombre + '? Esta acción no se puede deshacer.')) { return; }
    const r = await api(URL_USUARIOS + '?accion=eliminar&id=' + u.identificacion, { method: 'POST' });
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    listarUsuarios();
}
