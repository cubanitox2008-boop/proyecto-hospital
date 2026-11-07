const URL_DOCUMENTOS = 'php/api/documentos.php';
const EXTENSIONES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png'];
const TAMANO_MAXIMO = 5 * 1024 * 1024;

const formDocumento = document.getElementById('formDocumento');
const mensaje = document.getElementById('mensaje');
const cuerpoTabla = document.getElementById('cuerpoDocumentos');
let documentoEnEdicion = null;

sesionLista.then(async (usuario) => {
    if (!usuario) { return; }
    await cargarCategorias();
    listarDocumentos();
});

async function cargarCategorias() {
    const r = await api('php/api/catalogos.php');
    if (!r.ok) { mostrarMensaje(mensaje, r.datos.error || 'No se pudieron cargar las categorías', true); return; }
    llenarSelect(document.getElementById('categoria'), r.datos.categorias, 'identificacion', 'nombre', 'Seleccione la categoría...');
    llenarSelect(document.getElementById('filtroCategoria'), r.datos.categorias, 'identificacion', 'nombre', 'Todas');
}

async function listarDocumentos() {
    const parametros = new URLSearchParams({ accion: 'listar' });
    const categoria = document.getElementById('filtroCategoria').value;
    const texto = document.getElementById('filtroTexto').value.trim();
    const paciente = document.getElementById('filtroPaciente').value.trim();
    if (categoria) { parametros.set('categoria', categoria); }
    if (texto) { parametros.set('q', texto); }
    if (paciente) { parametros.set('paciente', paciente); }

    const r = await api(URL_DOCUMENTOS + '?' + parametros.toString());
    if (!r.ok) { mostrarMensaje(mensaje, r.datos.error || 'No se pudo cargar el listado', true); return; }

    limpiar(cuerpoTabla);
    if (r.datos.documentos.length === 0) {
        const fila = document.createElement('tr');
        const celda = crearCelda('No hay documentos para mostrar');
        celda.colSpan = 7;
        celda.className = 'c-vacio';
        fila.appendChild(celda);
        cuerpoTabla.appendChild(fila);
        return;
    }
    r.datos.documentos.forEach((doc) => cuerpoTabla.appendChild(crearFila(doc)));
}

function crearFila(doc) {
    const fila = document.createElement('tr');
    fila.appendChild(crearCelda(doc.titulo));
    fila.appendChild(crearCelda(doc.categoria_nombre));
    fila.appendChild(crearCelda(doc.paciente_cedula ? doc.paciente_nombre + ' (' + doc.paciente_cedula + ')' : '-'));
    fila.appendChild(crearCelda(doc.archivo_nombre));
    fila.appendChild(crearCelda(doc.fecha_subida.slice(0, 16)));
    fila.appendChild(crearCelda(doc.subido_por_nombre || '-'));

    const acciones = document.createElement('td');
    const ver = document.createElement('a');
    ver.className = 'c-btn';
    ver.textContent = 'Ver';
    ver.target = '_blank';
    ver.href = URL_DOCUMENTOS + '?accion=descargar&id=' + doc.identificacion;
    acciones.appendChild(ver);

    if (['admin', 'enfermero', 'funcionario'].includes(usuarioActual.rol)) {
        acciones.appendChild(crearBoton('Editar', 'c-btn-secundario', () => editarDocumento(doc)));
    }
    if (usuarioActual.rol === 'admin') {
        acciones.appendChild(crearBoton('Eliminar', 'c-btn-peligro', () => eliminarDocumento(doc)));
    }
    fila.appendChild(acciones);
    return fila;
}

function editarDocumento(doc) {
    documentoEnEdicion = doc.identificacion;
    document.getElementById('titulo').value = doc.titulo;
    document.getElementById('categoria').value = doc.categoria_id;
    document.getElementById('paciente').value = doc.paciente_cedula || '';
    document.getElementById('descripcion').value = doc.descripcion || '';
    document.getElementById('archivo').value = '';
    document.getElementById('ayudaArchivo').textContent = 'Archivo actual: ' + doc.archivo_nombre + ' (dejalo vacío para conservarlo)';
    document.getElementById('btnGuardar').textContent = 'Guardar cambios';
    document.getElementById('btnCancelar').style.display = 'inline-block';
    formDocumento.scrollIntoView({ behavior: 'smooth' });
}

function terminarEdicion() {
    documentoEnEdicion = null;
    formDocumento.reset();
    document.getElementById('ayudaArchivo').textContent = 'PDF, JPG o PNG. Máximo 5 MB.';
    document.getElementById('btnGuardar').textContent = 'Confirmar';
    document.getElementById('btnCancelar').style.display = 'none';
}

async function eliminarDocumento(doc) {
    if (!confirm('¿Eliminar el documento "' + doc.titulo + '"? Esta acción no se puede deshacer.')) { return; }
    const r = await api(URL_DOCUMENTOS + '?accion=eliminar&id=' + doc.identificacion, { method: 'POST' });
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    if (r.ok) { listarDocumentos(); }
}

function validarFormulario(titulo, categoria, paciente, descripcion, archivo) {
    if (!Validar.texto(titulo, 3, 150)) { return 'El título debe tener entre 3 y 150 caracteres'; }
    if (!categoria) { return 'Seleccioná una categoría'; }
    if (paciente !== '' && !Validar.cedula(paciente)) { return 'La cédula del paciente debe tener 7 u 8 números'; }
    if (!Validar.texto(descripcion, 0, 1000)) { return 'La descripción no puede superar los 1000 caracteres'; }
    if (!archivo && documentoEnEdicion === null) { return 'Seleccioná un archivo para subir'; }
    if (archivo) {
        const extension = archivo.name.split('.').pop().toLowerCase();
        if (!EXTENSIONES_PERMITIDAS.includes(extension)) { return 'Solo se permiten archivos PDF, JPG o PNG'; }
        if (archivo.size > TAMANO_MAXIMO) { return 'El archivo supera el tamaño máximo permitido (5 MB)'; }
    }
    return null;
}

formDocumento.addEventListener('submit', async (e) => {
    e.preventDefault();
    const titulo = document.getElementById('titulo').value.trim();
    const categoria = document.getElementById('categoria').value;
    const paciente = document.getElementById('paciente').value.trim();
    const descripcion = document.getElementById('descripcion').value.trim();
    const archivo = document.getElementById('archivo').files[0];

    const error = validarFormulario(titulo, categoria, paciente, descripcion, archivo);
    if (error) { mostrarMensaje(mensaje, error, true); return; }

    const datos = new FormData();
    datos.append('titulo', titulo);
    datos.append('categoria', categoria);
    datos.append('paciente', paciente);
    datos.append('descripcion', descripcion);
    if (archivo) { datos.append('archivo', archivo); }

    const url = documentoEnEdicion === null
        ? URL_DOCUMENTOS + '?accion=crear'
        : URL_DOCUMENTOS + '?accion=editar&id=' + documentoEnEdicion;

    const r = await api(url, { method: 'POST', body: datos });
    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    if (r.ok) {
        terminarEdicion();
        listarDocumentos();
    }
});

document.getElementById('btnCancelar').addEventListener('click', terminarEdicion);
document.getElementById('formFiltros').addEventListener('submit', (e) => { e.preventDefault(); listarDocumentos(); });
document.getElementById('btnLimpiarFiltros').addEventListener('click', () => {
    document.getElementById('formFiltros').reset();
    listarDocumentos();
});
