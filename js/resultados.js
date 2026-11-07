const mensaje = document.getElementById('mensaje');

sesionLista.then(async (usuario) => {
    if (!usuario) { return; }
    const r = await api('php/api/encuesta.php?accion=resultados');
    if (!r.ok) { mostrarMensaje(mensaje, r.datos.error || 'No se pudieron cargar los resultados', true); return; }
    dibujarResultados(r.datos);
});

function dibujarResultados(datos) {
    document.getElementById('totalRespuestas').textContent = datos.total;
    document.getElementById('promedio').textContent = datos.promedio === null ? '-' : datos.promedio + ' / 4';
    dibujarBarras(document.getElementById('graficoCalificaciones'), datos.calificaciones);
    dibujarBarras(document.getElementById('graficoSectores'), datos.sectores);

    const lista = document.getElementById('listaComentarios');
    limpiar(lista);
    if (datos.comentarios.length === 0) {
        const vacio = document.createElement('p');
        vacio.className = 'c-vacio';
        vacio.textContent = 'Todavía no hay comentarios';
        lista.appendChild(vacio);
    }
    datos.comentarios.forEach((c) => {
        const caja = document.createElement('div');
        caja.className = 'c-comentario';
        const texto = document.createElement('div');
        texto.textContent = c.texto;
        const fecha = document.createElement('small');
        fecha.textContent = c.fecha.slice(0, 16);
        caja.appendChild(texto);
        caja.appendChild(fecha);
        lista.appendChild(caja);
    });
}

function dibujarBarras(contenedor, lista) {
    limpiar(contenedor);
    const total = lista.reduce((suma, item) => suma + item.cantidad, 0);
    lista.forEach((item) => {
        const porcentaje = total > 0 ? Math.round((item.cantidad / total) * 100) : 0;

        const fila = document.createElement('div');
        fila.className = 'c-grafico-fila';
        const etiqueta = document.createElement('span');
        etiqueta.textContent = item.etiqueta;
        const fondo = document.createElement('div');
        fondo.className = 'c-barra-fondo';
        const barra = document.createElement('div');
        barra.className = 'c-barra';
        barra.style.width = porcentaje + '%';
        fondo.appendChild(barra);
        const cantidad = document.createElement('span');
        cantidad.textContent = item.cantidad + ' (' + porcentaje + '%)';

        fila.appendChild(etiqueta);
        fila.appendChild(fondo);
        fila.appendChild(cantidad);
        contenedor.appendChild(fila);
    });
}
