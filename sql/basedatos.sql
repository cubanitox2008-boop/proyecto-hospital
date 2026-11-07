SET NAMES utf8mb4;

DROP DATABASE IF EXISTS basedatos;
CREATE DATABASE basedatos DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE basedatos;

CREATE TABLE CATEGORIA (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ambito VARCHAR(20) NOT NULL DEFAULT 'documento'
);

CREATE TABLE FUNCIONARIO (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL DEFAULT '',
    CI_funcionario VARCHAR(20) NOT NULL UNIQUE,
    cedula VARCHAR(20) GENERATED ALWAYS AS (CI_funcionario) VIRTUAL,
    pass VARCHAR(255) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'funcionario',
    CONSTRAINT chk_funcionario_tipo CHECK (tipo IN ('admin','enfermero','chofer','funcionario'))
);

CREATE TABLE ENFERMERO (
    identificacion INT PRIMARY KEY,
    CONSTRAINT fk_enfermero_funcionario
        FOREIGN KEY (identificacion) REFERENCES FUNCIONARIO(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE CHOFER (
    identificacion INT PRIMARY KEY,
    CONSTRAINT fk_chofer_funcionario
        FOREIGN KEY (identificacion) REFERENCES FUNCIONARIO(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE OTRO (
    identificacion INT PRIMARY KEY,
    cargo VARCHAR(100) NOT NULL,
    funcionario INT,
    CONSTRAINT fk_otro_funcionario
        FOREIGN KEY (identificacion) REFERENCES FUNCIONARIO(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_otro_relacion_funcionario
        FOREIGN KEY (funcionario) REFERENCES FUNCIONARIO(identificacion)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE PACIENTE (
    cedula VARCHAR(20) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    enfermero INT,
    datos_paciente TEXT,
    CONSTRAINT fk_paciente_enfermero
        FOREIGN KEY (enfermero) REFERENCES ENFERMERO(identificacion)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE VEHICULO (
    matricula VARCHAR(20) PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL
);

CREATE TABLE DOCUMENTO (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT,
    archivo_nombre VARCHAR(255) NOT NULL,
    archivo_ruta VARCHAR(255) NOT NULL,
    fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    categoria_id INT NOT NULL,
    paciente_cedula VARCHAR(20) NULL,
    subido_por INT NULL,
    CONSTRAINT fk_documento_categoria
        FOREIGN KEY (categoria_id) REFERENCES CATEGORIA(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_documento_paciente
        FOREIGN KEY (paciente_cedula) REFERENCES PACIENTE(cedula)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_documento_funcionario
        FOREIGN KEY (subido_por) REFERENCES FUNCIONARIO(identificacion)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE ENCUESTA (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    categoria_id INT NOT NULL,
    CONSTRAINT fk_encuesta_categoria
        FOREIGN KEY (categoria_id) REFERENCES CATEGORIA(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE PREGUNTA (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(30) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    texto VARCHAR(255) NOT NULL,
    opciones TEXT NULL,
    encuesta_id INT NOT NULL,
    CONSTRAINT fk_pregunta_encuesta
        FOREIGN KEY (encuesta_id) REFERENCES ENCUESTA(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE RESPUESTA (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    texto TEXT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pregunta_id INT NOT NULL,
    CONSTRAINT fk_respuesta_pregunta
        FOREIGN KEY (pregunta_id) REFERENCES PREGUNTA(identificacion)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE TRASLADO (
    identificacion INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    origen VARCHAR(255) NOT NULL,
    destino VARCHAR(255) NOT NULL,
    acompanante VARCHAR(100) NULL,
    hora_salida TIME NOT NULL,
    hora_llegada TIME NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    chofer INT NOT NULL,
    enfermero INT NULL,
    paciente_cedula VARCHAR(20) NOT NULL,
    vehiculo_matricula VARCHAR(20) NOT NULL,
    CONSTRAINT chk_traslado_estado CHECK (estado IN ('Pendiente','En curso','En destino','Retorno','Finalizado')),
    CONSTRAINT fk_traslado_chofer
        FOREIGN KEY (chofer) REFERENCES CHOFER(identificacion)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_traslado_enfermero
        FOREIGN KEY (enfermero) REFERENCES ENFERMERO(identificacion)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_traslado_paciente
        FOREIGN KEY (paciente_cedula) REFERENCES PACIENTE(cedula)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_traslado_vehiculo
        FOREIGN KEY (vehiculo_matricula) REFERENCES VEHICULO(matricula)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE VIEW V_TRASLADOS_DETALLE AS
SELECT t.identificacion, t.estado, t.origen, t.destino, t.acompanante,
       t.hora_salida, t.hora_llegada, t.fecha_creacion,
       t.vehiculo_matricula, v.tipo AS vehiculo_tipo,
       t.chofer AS chofer_id, fc.nombre AS chofer_nombre,
       t.enfermero AS enfermero_id, fe.nombre AS enfermero_nombre,
       t.paciente_cedula, p.nombre AS paciente_nombre, p.datos_paciente
FROM TRASLADO t
JOIN VEHICULO v      ON v.matricula = t.vehiculo_matricula
JOIN FUNCIONARIO fc  ON fc.identificacion = t.chofer
LEFT JOIN FUNCIONARIO fe ON fe.identificacion = t.enfermero
JOIN PACIENTE p      ON p.cedula = t.paciente_cedula;

CREATE VIEW V_DOCUMENTOS_DETALLE AS
SELECT d.identificacion, d.titulo, d.descripcion, d.archivo_nombre, d.fecha_subida,
       d.categoria_id, c.nombre AS categoria_nombre,
       d.paciente_cedula, p.nombre AS paciente_nombre,
       d.subido_por, f.nombre AS subido_por_nombre
FROM DOCUMENTO d
JOIN CATEGORIA c        ON c.identificacion = d.categoria_id
LEFT JOIN PACIENTE p    ON p.cedula = d.paciente_cedula
LEFT JOIN FUNCIONARIO f ON f.identificacion = d.subido_por;

CREATE VIEW V_RESULTADOS_ENCUESTA AS
SELECT p.encuesta_id, p.clave, r.texto AS valor, COUNT(*) AS cantidad
FROM RESPUESTA r
JOIN PREGUNTA p ON p.identificacion = r.pregunta_id
WHERE p.tipo IN ('seleccion','puntuacion')
GROUP BY p.encuesta_id, p.clave, r.texto;

CREATE VIEW V_COMENTARIOS_ENCUESTA AS
SELECT p.encuesta_id, r.texto, r.fecha
FROM RESPUESTA r
JOIN PREGUNTA p ON p.identificacion = r.pregunta_id
WHERE p.tipo = 'texto';

CREATE USER IF NOT EXISTS 'sigsm_admin'@'%' IDENTIFIED BY 'Admin_Sigsm_2026!';
GRANT ALL PRIVILEGES ON basedatos.* TO 'sigsm_admin'@'%';

CREATE USER IF NOT EXISTS 'sigsm_app'@'%' IDENTIFIED BY 'App_Sigsm_2026!';
GRANT SELECT ON basedatos.* TO 'sigsm_app'@'%';
GRANT INSERT, UPDATE, DELETE ON basedatos.FUNCIONARIO TO 'sigsm_app'@'%';
GRANT INSERT, UPDATE, DELETE ON basedatos.ENFERMERO   TO 'sigsm_app'@'%';
GRANT INSERT, UPDATE, DELETE ON basedatos.CHOFER      TO 'sigsm_app'@'%';
GRANT INSERT, UPDATE, DELETE ON basedatos.PACIENTE    TO 'sigsm_app'@'%';
GRANT INSERT, UPDATE, DELETE ON basedatos.TRASLADO    TO 'sigsm_app'@'%';
GRANT INSERT, UPDATE, DELETE ON basedatos.DOCUMENTO   TO 'sigsm_app'@'%';
GRANT INSERT ON basedatos.RESPUESTA TO 'sigsm_app'@'%';
FLUSH PRIVILEGES;

INSERT INTO CATEGORIA (identificacion, nombre, ambito) VALUES
 (1, 'Pediatría',    'documento'),
 (2, 'Cardiología',  'documento'),
 (3, 'Psiquiatría',  'documento'),
 (4, 'Satisfacción', 'encuesta');

INSERT INTO FUNCIONARIO (identificacion, nombre, CI_funcionario, pass, tipo) VALUES
 (1, 'Administrador Sistema', '11111111', 'Admin2026',  'admin'),
 (2, 'Laura Fernández',       '22222222', 'Prueba2026', 'enfermero'),
 (3, 'Martín Silva',          '22222223', 'Prueba2026', 'enfermero'),
 (4, 'Carlos Pereira',        '33333333', 'Prueba2026', 'chofer'),
 (5, 'Ana Rodríguez',         '33333334', 'Prueba2026', 'chofer'),
 (6, 'Sofía Méndez',          '44444444', 'Prueba2026', 'funcionario');

INSERT INTO ENFERMERO (identificacion) VALUES (2), (3);
INSERT INTO CHOFER (identificacion) VALUES (4), (5);

INSERT INTO VEHICULO (matricula, tipo) VALUES
 ('SAB 1001', 'Emergencia Avanzada'),
 ('SAB 1002', 'Emergencia Avanzada'),
 ('SAB 2001', 'Traslado Común'),
 ('SAB 2002', 'Traslado Común'),
 ('SAB 3001', 'Unidad Pediátrica'),
 ('SAB 3002', 'Unidad Pediátrica'),
 ('SAB 4001', 'Unidad Especial'),
 ('SAB 4002', 'Unidad Especial');

INSERT INTO PACIENTE (cedula, nombre, enfermero, datos_paciente) VALUES
 ('50123456', 'Juan Pérez',  NULL, 'Paciente adulto, requiere oxígeno durante el traslado.'),
 ('48765432', 'María López', NULL, 'Control cardiológico programado.');

INSERT INTO TRASLADO (estado, origen, destino, acompanante, hora_salida, hora_llegada, chofer, enfermero, paciente_cedula, vehiculo_matricula) VALUES
 ('Finalizado', 'Hospital de Clínicas - Piso 3', 'Sanatorio Americano', 'Hijo del paciente', '09:00', '09:40', 4, 2, '50123456', 'SAB 1001'),
 ('En curso',   'Hospital de Clínicas - Urgencias', 'Domicilio (Av. Italia 3200)', NULL, '14:30', NULL, 5, 3, '48765432', 'SAB 2001'),
 ('Pendiente',  'Hospital de Clínicas - Piso 5', 'Hospital Pereira Rossell', NULL, '17:00', NULL, 4, 2, '50123456', 'SAB 3001');

INSERT INTO ENCUESTA (identificacion, nombre, categoria_id) VALUES (1, 'Encuesta de Satisfacción', 4);

INSERT INTO PREGUNTA (identificacion, clave, tipo, texto, opciones, encuesta_id) VALUES
 (1, 'sector',       'seleccion',  '¿Qué sector visitó hoy?',
     'urgencias:Urgencias / Guardia|consultorio:Consultorios Externos|ambulancia:Servicio de Ambulancias|administracion:Administración', 1),
 (2, 'calificacion', 'puntuacion', '¿Cómo califica la atención recibida?',
     '1:Mala|2:Regular|3:Buena|4:Excelente', 1),
 (3, 'comentario',   'texto',      'Deje su comentario o sugerencia', NULL, 1);

INSERT INTO RESPUESTA (texto, pregunta_id) VALUES
 ('urgencias', 1), ('4', 2), ('Muy buena atención del personal.', 3),
 ('consultorio', 1), ('3', 2),
 ('ambulancia', 1), ('2', 2), ('La ambulancia demoró más de lo esperado.', 3);
