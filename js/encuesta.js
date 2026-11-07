const formEncuesta = document.getElementById('formEncuesta');
const mensaje = document.getElementById('mensaje');

formEncuesta.addEventListener('submit', async (e) => {
    e.preventDefault();

    const sector = document.getElementById('sector').value;
    const seleccionada = document.querySelector('input[name="calificacion"]:checked');
    const comentario = document.getElementById('comentario').value.trim();

    if (!sector) { mostrarMensaje(mensaje, 'Seleccioná el sector que visitó', true); return; }
    if (!seleccionada) { mostrarMensaje(mensaje, 'Seleccioná una calificación', true); return; }
    if (!Validar.texto(comentario, 0, 500)) { mostrarMensaje(mensaje, 'El comentario no puede superar los 500 caracteres', true); return; }

    const r = await apiJson('php/api/encuesta.php?accion=responder', {
        sector: sector,
        calificacion: seleccionada.value,
        comentario: comentario
    });

    mostrarMensaje(mensaje, r.ok ? r.datos.mensaje : r.datos.error, !r.ok);
    if (r.ok) { formEncuesta.reset(); }
});
