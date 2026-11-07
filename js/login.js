document.addEventListener('DOMContentLoaded', () => {
    const formLogin = document.getElementById('formLogin');
    const mensaje = document.getElementById('mensaje');

    formLogin.addEventListener('submit', async (e) => {
        e.preventDefault();

        const usuario = document.getElementById('usuario').value.trim();
        const password = document.getElementById('password').value;
        mensaje.textContent = '';

        if (!usuario || !password) {
            mensaje.textContent = 'Ingresá usuario y contraseña';
            return;
        }
        if (!Validar.cedula(usuario)) {
            mensaje.textContent = 'La cédula debe tener 7 u 8 números, sin puntos ni guiones';
            return;
        }

        try {
            const respuesta = await fetch('php/api/login.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user: usuario, pass: password })
            });
            const datos = await respuesta.json();

            if (!respuesta.ok) {
                mensaje.textContent = datos.error || 'Usuario o contraseña incorrectos';
                return;
            }

            window.location.href = 'panel.html';
        } catch (error) {
            console.error(error);
            mensaje.textContent = 'No se pudo conectar con el servidor';
        }
    });
});
