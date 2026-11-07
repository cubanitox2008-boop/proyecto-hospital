const URL_TRASLADOS = 'php/api/traslados.php';

const formTraslado = document.getElementById('formTraslado');
const mensaje = document.getElementById('mensaje');
const cuerpoTabla = document.getElementById('cuerpoTraslados');
let catalogos = { vehiculos: [], choferes: [], enfermeros: [], estados: [] };
let trasladoEnEdicion = null;

sesionLista.then(async (usuario) => {
    if (!usuario) { return; }
    await cargarCatalogos();
    listarTraslados();
});

async function cargarCatalogos() {
    const r = await api('php/api/catalogos.php');
    if (!r.ok) { mostrarMensaje(mensaje, r.datos.error || 'No se pudieron cargar los datos', true); return; }
    catalogos = r.datos;

    llenarSelect(document.getElementById('chofer'), catalogos.choferes, 'identificacion', 'nombre', 'Seleccione el chofer...');
    llenarSelect(document.getElementById('enfermero'), catalogos.enfermeros, 'identificacion', 'nombre', 'Sin enfermero');
    const estados = catalogos.estados.map((e) => ({ valor: e, texto: e }));
    llenarSelect(document.getElementById('estado'), estados, 'valor', 'texto', 'Pendiente (por defecto)');
    llenarSelect(document.getElementById('filtroEstado'), estados, 'valor', 'texto', 'Todos');
    llenarVehiculos('');
}

function llenarVehiculos(tipo) {
    const select = document.getElementById('vehiculo');
    const lista = catalogos.vehiculos.filter((v) => tipo === '' || v.tipo === tipo);
    llenarSelect(select, lista, 'matricula', 'matricula', tipo === '' ? 'Primero elija el tipo de unidad...' : 'Seleccione la matrícula...');
}

document.querySelectorAll('input[name="tipo_ambulancia"]').forEach((radio) => {
    radio.addEventListener('change', () => llenarVehiculos(radio.value));
});

document.getElementById('pacienteCedula').addEventListener('change', async (e) => {
    const cedula = e.target.value.trim();
    if (!Validar.cedula(cedula)) { return; }
    const r = await api(URL_TRASLADOS + '?accion=paciente&cedula=' + cedula);
    if (r.ok && r.datos.paciente) {
        document.getElementById('pacienteNombre').value = r.datos.paciente.nombre;
        document.getElementById('pacienteDatos').value = r.datos.paciente.datos_paciente || '';
    }
});

async function listarTraslados() {
    const parametros = new URLSearchParams({ accion: 'listar' });
    const paciente = document.getElementById('filtroPaciente').value.trim();
    const vehiculo = document.getElementById('filtroVehiculo').value.trim();
    const estado = document.getElementById('filtroEstado').value;
    if (paciente) { parametros.set('paciente', paciente); }
    if (vehiculo) { parametros.set('vehiculo', vehiculo); }
    if (estado) { parametros.set('estado', estado); }

    const r = await api(URL_TRASLADOS + '?' + parametros.toString());
    if (!r.ok) { mostrarMensaje(mensaje, r.datos.error || 'No se pudo cargar el historial', true); return; }

    limpiar(cuerpoTabla);
    if (r.datos.traslados.length === 0) {
        const fila = document.createElement('tr');
        const celda = crearCelda('No hay traslados para mostrar');
        celda.colSpan = 9;
        celda.className = 'c-vacio';
        fila.appendChild(celda);
        cuerpoTabla.appendChild(fila);
        return;
    }
    r.datos.traslados.forEach((t) => cuerpoTabla.appendChild(crearFila(t)));
}

function crearFila(t) {
    const fila = document.createElement('tr');
    fila.appendChild(crearCelda(t.vehiculo_matricula + ' - ' + t.vehiculo_tipo));
    fila.appendChild(crearCelda(t.chofer_nombre));
    fila.appendChild(crearCelda(t.enfermero_nombre || '-'));
    fila.appendChild(crearCelda(t.paciente_nombre + ' (' + t.paciente_cedula + ')'));
    fila.appendChild(crearCelda(t.origen));
    fila.appendChild(crearCelda(t.destino));
    fila.appendChild(crearCelda(formatearHora(t.hora_salida) + ' → ' + (formatearHora(t.hora_llegada) || '...')));

    const celdaEstado = document.createElement('td');
    if (['admin', 'enfermero', 'chofer'].includes(usuarioActual.rol)) {
        const select = document.createElement('select');
        catalogos.estados.forEach((estado) => {
            const opcion = document.createElement('option');
            opcion.value = estado;
            opcion.textContent = estado;
            opcion.selected = estado === t.estado;
            select.appendChild(opcion);
        });
        select.addEventListener('change', () => cambiarEstado(t.identificacion, select.value));
        celdaEstado.appendChild(select);
    } else {
        const etiqueta = document.createElement('span');
        etiqueta.className = 'c-badge c-estado-' + t.estado.replace(' ', '-');
        etiqueta.textContent = t.estado;
        celdaEstado.appendChild(etiqueta);
    }
    fila.appendChild(celdaEstado);

    const acciones = document.createElement('td');
    if (['admin', 'enfermero'].includes(usuarioActual.rol)) {
        acciones.appendChild(crearBoton('Editar', 'c-btn-secundario', () => editarTraslado(t)));
    }
    if (usuarioActual.rol === 'admin') {
        acciones.appendChild(crearBoton('Eliminar', 'c-btn-peligro', () => eliminarTraslado(t)));
    }
    fila.appendChild(acciones);
    return fila;
}

async function cambiarEstado(id, estado) {
    const r = await apiJson(URL_TRASLADOS + '?accion=estado&id=' + id, { estado: estado });
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    listarTraslados();
}

async function eliminarTraslado(t) {
    if (!confirm('¿Eliminar el traslado de ' + t.paciente_nombre + '? Esta acción no se puede deshacer.')) { return; }
    const r = await api(URL_TRASLADOS + '?accion=eliminar&id=' + t.identificacion, { method: 'POST' });
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    if (r.ok) { listarTraslados(); }
}

function editarTraslado(t) {
    trasladoEnEdicion = t.identificacion;
    const radio = Array.from(document.querySelectorAll('input[name="tipo_ambulancia"]')).find((r) => r.value === t.vehiculo_tipo);
    if (radio) { radio.checked = true; }
    llenarVehiculos(t.vehiculo_tipo);
    document.getElementById('vehiculo').value = t.vehiculo_matricula;
    document.getElementById('chofer').value = t.chofer_id;
    document.getElementById('enfermero').value = t.enfermero_id || '';
    document.getElementById('acompanante').value = t.acompanante || '';
    document.getElementById('pacienteCedula').value = t.paciente_cedula;
    document.getElementById('pacienteNombre').value = t.paciente_nombre;
    document.getElementById('pacienteDatos').value = t.datos_paciente || '';
    document.getElementById('origen').value = t.origen;
    document.getElementById('destino').value = t.destino;
    document.getElementById('horaSalida').value = formatearHora(t.hora_salida);
    document.getElementById('horaLlegada').value = formatearHora(t.hora_llegada);
    document.getElementById('estado').value = t.estado;
    document.getElementById('btnGuardar').textContent = 'Guardar cambios';
    document.getElementById('btnCancelar').style.display = 'inline-block';
    formTraslado.scrollIntoView({ behavior: 'smooth' });
}

function terminarEdicion() {
    trasladoEnEdicion = null;
    formTraslado.reset();
    llenarVehiculos('');
    document.getElementById('btnGuardar').textContent = 'Confirmar y Enviar Ambulancia';
    document.getElementById('btnCancelar').style.display = 'none';
}

function validarTraslado(d) {
    if (!d.vehiculo) { return 'Seleccioná el tipo de unidad y la matrícula del vehículo'; }
    if (!d.chofer) { return 'Seleccioná un chofer'; }
    if (!Validar.cedula(d.paciente_cedula)) { return 'La cédula del paciente debe tener 7 u 8 números'; }
    if (!Validar.nombre(d.paciente_nombre)) { return 'Ingresá un nombre de paciente válido (solo letras y espacios)'; }
    if (!Validar.texto(d.paciente_datos, 0, 1000)) { return 'Los datos del paciente no pueden superar los 1000 caracteres'; }
    if (!Validar.texto(d.origen, 3, 255) || !Validar.texto(d.destino, 3, 255)) { return 'El origen y el destino deben tener entre 3 y 255 caracteres'; }
    if (!Validar.texto(d.acompanante, 0, 100)) { return 'El acompañante no puede superar los 100 caracteres'; }
    if (!Validar.hora(d.hora_salida)) { return 'Ingresá una hora de salida válida'; }
    if (d.hora_llegada !== '' && !Validar.hora(d.hora_llegada)) { return 'La hora de llegada no es válida'; }
    if (d.hora_llegada !== '' && d.hora_llegada <= d.hora_salida) { return 'La hora de llegada debe ser posterior a la de salida'; }
    return null;
}

formTraslado.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = {
        vehiculo: document.getElementById('vehiculo').value,
        chofer: document.getElementById('chofer').value,
        enfermero: document.getElementById('enfermero').value,
        acompanante: document.getElementById('acompanante').value.trim(),
        paciente_cedula: document.getElementById('pacienteCedula').value.trim(),
        paciente_nombre: document.getElementById('pacienteNombre').value.trim(),
        paciente_datos: document.getElementById('pacienteDatos').value.trim(),
        origen: document.getElementById('origen').value.trim(),
        destino: document.getElementById('destino').value.trim(),
        hora_salida: document.getElementById('horaSalida').value,
        hora_llegada: document.getElementById('horaLlegada').value,
        estado: document.getElementById('estado').value
    };

    const error = validarTraslado(datos);
    if (error) { mostrarMensaje(mensaje, error, true); return; }

    const url = trasladoEnEdicion === null
        ? URL_TRASLADOS + '?accion=crear'
        : URL_TRASLADOS + '?accion=editar&id=' + trasladoEnEdicion;

    const r = await apiJson(url, datos);
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    if (r.ok) {
        terminarEdicion();
        listarTraslados();
    }
});

document.getElementById('btnCancelar').addEventListener('click', terminarEdicion);
document.getElementById('formFiltros').addEventListener('submit', (e) => { e.preventDefault(); listarTraslados(); });
document.getElementById('btnLimpiarFiltros').addEventListener('click', () => {
    document.getElementById('formFiltros').reset();
    listarTraslados();
});
