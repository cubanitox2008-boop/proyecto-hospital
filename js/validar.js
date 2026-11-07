const Validar = {
    cedula: (valor) => /^\d{7,8}$/.test(valor),

    nombre: (valor) => valor.length >= 2 && valor.length <= 100 && /^[\p{L}][\p{L} .'-]*$/u.test(valor),

    texto: (valor, minimo, maximo) => valor.length >= minimo && valor.length <= maximo,

    hora: (valor) => /^([01]\d|2[0-3]):[0-5]\d$/.test(valor)
};
