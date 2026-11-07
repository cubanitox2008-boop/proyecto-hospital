document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formRegistro');
    const mensaje = document.getElementById('mensaje');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombre = document.getElementById('nombre').value.trim();
        const ci = document.getElementById('ci').value.trim();
        const pass = document.getElementById('password').value;
        const pass2 = document.getElementById('password2').value;
        mensaje.textContent = '';

        if (!Validar.nombre(nombre)) {
            mensaje.textContent = 'Ingresá un nombre válido (solo letras y espacios)';
            return;
        }
        if (!Validar.cedula(ci)) {
            mensaje.textContent = 'La cédula debe tener 7 u 8 números, sin puntos ni guiones';
            return;
        }
        if (pass.length < 6 || pass.length > 72) {
            mensaje.textContent = 'La contraseña debe tener entre 6 y 72 caracteres';
            return;
        }
        if (pass !== pass2) {
            mensaje.textContent = 'Las contraseñas no coinciden';
            return;
        }

        try {
            const respuesta = await fetch('php/api/registro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre: nombre, ci: ci, pass: pass })
            });
            const datos = await respuesta.json();

            if (!respuesta.ok) {
                mensaje.textContent = datos.error || 'No se pudo crear el usuario';
                return;
            }

            alert('Usuario creado. Ahora iniciá sesión.');
            window.location.href = 'login.html';
        } catch (error) {
            console.error(error);
            mensaje.textContent = 'No se pudo conectar con el servidor';
        }
    });
});
