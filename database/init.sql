-- ==========================================
-- BASE DE DATOS: ACADEMIA FASHION (POSTGRESQL)
-- Script Actualizado: 140 Estudiantes + Estados Mezclados
-- ==========================================

-- 1. TABLAS ESTRUCTURALES (Sin cambios)
CREATE TABLE usuarios (
    id_usuario SERIAL PRIMARY KEY,
    usuario VARCHAR(30) NOT NULL UNIQUE,
    contraseña VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL,
    estado VARCHAR(15) DEFAULT 'Activo',
    ultimo_acceso TIMESTAMP DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (usuario, contraseña, rol, estado) 
VALUES ('admin', '123456', 'Administrador', 'Activo');

CREATE TABLE estudiantes (
    id_estudiante SERIAL PRIMARY KEY,
    cedula VARCHAR(10) NOT NULL UNIQUE,
    nombres VARCHAR(60) NOT NULL,
    apellidos VARCHAR(60) NOT NULL,
    fecha_nacimiento DATE,
    telefono VARCHAR(10),
    correo VARCHAR(100),
    direccion VARCHAR(150),
    estado VARCHAR(20) DEFAULT 'Activo'
);

CREATE TABLE docentes (
    id_docente SERIAL PRIMARY KEY,
    cedula VARCHAR(10) NOT NULL UNIQUE,
    nombres VARCHAR(60) NOT NULL,
    apellidos VARCHAR(60) NOT NULL,
    especialidad VARCHAR(80),
    telefono VARCHAR(10),
    correo VARCHAR(100)
);

CREATE TABLE categorias (
    id_categoria SERIAL PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(250)
);

INSERT INTO categorias (nombre, descripcion) VALUES
('Faciales y Corporales', 'Tratamientos faciales, masajes y depilación'),
('Peluquería', 'Cortes, colorimetría, peinados y barbería'),
('Uñas', 'Manicura, pedicura, acrílicas y nail art'),
('Maquillaje', 'Social, novias, artístico y FX'),
('Otros', 'Bioseguridad, emprendimiento y fotografía');

CREATE TABLE cursos (
    id_curso SERIAL PRIMARY KEY,
    id_categoria INT NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(250),
    duracion_horas INT,
    costo DECIMAL(10,2) NOT NULL,
    nivel VARCHAR(20) DEFAULT 'Básico',
    cupos_maximos INT DEFAULT 20,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria) ON DELETE RESTRICT
);

CREATE TABLE horarios (
    id_horario SERIAL PRIMARY KEY,
    id_curso INT NOT NULL,
    id_docente INT NOT NULL,
    dia VARCHAR(20) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    aula VARCHAR(30),
    fecha_inicio_ciclo DATE,
    fecha_fin_ciclo DATE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE RESTRICT,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE RESTRICT
);

CREATE TABLE matriculas (
    id_matricula SERIAL PRIMARY KEY,
    id_estudiante INT NOT NULL,
    id_curso INT NOT NULL,
    id_horario INT NOT NULL,
    fecha_matricula DATE DEFAULT CURRENT_DATE,
    estado VARCHAR(20) DEFAULT 'Matriculado',
    porcentaje_asistencia DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE RESTRICT,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE RESTRICT,
    FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE RESTRICT
);

CREATE TABLE asistencia (
    id_asistencia SERIAL PRIMARY KEY,
    id_matricula INT NOT NULL,
    fecha DATE NOT NULL,
    asistencia VARCHAR(10) DEFAULT 'Presente',
    FOREIGN KEY (id_matricula) REFERENCES matriculas(id_matricula) ON DELETE CASCADE
);

CREATE TABLE pagos (
    id_pago SERIAL PRIMARY KEY,
    id_matricula INT NOT NULL,
    fecha_pago DATE DEFAULT CURRENT_DATE,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(30),
    estado VARCHAR(20) DEFAULT 'Pagado',
    referencia VARCHAR(50),
    observacion TEXT,
    FOREIGN KEY (id_matricula) REFERENCES matriculas(id_matricula) ON DELETE CASCADE
);

CREATE TABLE certificados (
    id_certificado SERIAL PRIMARY KEY,
    id_matricula INT NOT NULL UNIQUE,
    fecha_emision DATE,
    numero_certificado VARCHAR(30) UNIQUE,
    qr_code VARCHAR(255),
    FOREIGN KEY (id_matricula) REFERENCES matriculas(id_matricula) ON DELETE CASCADE
);

-- ==========================================
-- GENERACIÓN MASIVA DE DATOS (140 ESTUDIANTES)
-- ==========================================

-- 1. DOCENTES (20 registros)
INSERT INTO docentes (cedula, nombres, apellidos, especialidad, telefono, correo) VALUES
('1701234567', 'María Fernanda', 'López Guzmán', 'Peluquería Avanzada', '0991234567', 'mlopez@academia.edu.ec'),
('1702345678', 'Carlos Andrés', 'Pérez Mendoza', 'Maquillaje Social', '0982345678', 'cperez@academia.edu.ec'),
('1703456789', 'Ana Lucía', 'Torres Vega', 'Uñas Acrílicas', '0973456789', 'atorres@academia.edu.ec'),
('1704567890', 'Jorge Luis', 'Ramírez Salazar', 'Barbería', '0964567890', 'jramirez@academia.edu.ec'),
('1705678901', 'Diana Patricia', 'Castro Ruiz', 'Faciales', '0955678901', 'dcastro@academia.edu.ec'),
('1706789012', 'Roberto Antonio', 'Gómez Herrera', 'Colorimetría', '0946789012', 'rgomez@academia.edu.ec'),
('1707890123', 'Gabriela Estefanía', 'Morales Ortiz', 'Nail Art', '0937890123', 'gmorales@academia.edu.ec'),
('1708901234', 'Fernando José', 'Sánchez Paredes', 'Masajes Terapéuticos', '0928901234', 'fsanchez@academia.edu.ec'),
('1709012345', 'Verónica Andrea', 'Vargas Luna', 'Peinados Novias', '0919012345', 'vvargas@academia.edu.ec'),
('1710123456', 'Diego Armando', 'Flores Castillo', 'Bioseguridad', '0900123456', 'dflores@academia.edu.ec'),
('0601234567', 'Patricia Isabel', 'Benalcázar Mora', 'Depilación', '0991112233', 'pbenalcazar@academia.edu.ec'),
('0602345678', 'Miguel Ángel', 'Chimbo Yupa', 'Cortes Masculinos', '0982223344', 'mchimbo@academia.edu.ec'),
('0101234567', 'Rosa Elena', 'Ordóñez Cuenca', 'Emprendimiento', '0973334455', 'rordonez@academia.edu.ec'),
('0102345678', 'Luis Felipe', 'Malpartida Lozano', 'Maquillaje FX', '0964445566', 'lmalpartida@academia.edu.ec'),
('0301234567', 'Jessica Marlene', 'Guaranda Silva', 'Manicura Spa', '0955556677', 'jguaranda@academia.edu.ec'),
('0302345678', 'Paúl David', 'Ambato Rivas', 'Fotografía Moda', '0946667788', 'pambato@academia.edu.ec'),
('0501234567', 'Carmen Sofía', 'Guamote Chugchilan', 'Tratamientos Corporales', '0937778899', 'cguamote@academia.edu.ec'),
('0502345678', 'Esteban Xavier', 'Riobamba Alvarado', 'Gestión Académica', '0928889900', 'eriobamba@academia.edu.ec'),
('1101234567', 'Daniela Alejandra', 'Loja Paltas', 'Estilismo Integral', '0919990011', 'dloja@academia.edu.ec'),
('1102345678', 'Sebastián Nicolás', 'Catamayo Espinosa', 'Trenzas y Recogidos', '0900001122', 'scatamayo@academia.edu.ec');

-- 2. CURSOS (10 cursos variados)
INSERT INTO cursos (id_categoria, nombre, descripcion, duracion_horas, costo, nivel) VALUES
(1, 'Facial Completo Anti-edad', 'Limpieza, exfoliación y mascarillas', 40, 150.00, 'Intermedio'),
(1, 'Masaje Relajante Profesional', 'Técnicas suecas y descontracturantes', 30, 120.00, 'Básico'),
(2, 'Colorimetría Avanzada', 'Decoloración, tintes y corrección', 60, 200.00, 'Avanzado'),
(2, 'Corte y Barbería Moderna', 'Degradados, navaja y styling', 50, 180.00, 'Intermedio'),
(3, 'Uñas Acrílicas y Gel', 'Esculpido, limado y decoración', 45, 160.00, 'Básico'),
(3, 'Nail Art Creativo', 'Diseños a mano alzada y encapsulados', 20, 90.00, 'Avanzado'),
(4, 'Maquillaje Social y Novias', 'Preparación piel y técnicas duraderas', 35, 140.00, 'Intermedio'),
(4, 'Maquillaje Artístico FX', 'Heridos, caracterización y cine', 40, 170.00, 'Avanzado'),
(5, 'Bioseguridad en Salón', 'Normas sanitarias y esterilización', 15, 50.00, 'Básico'),
(5, 'Emprendimiento Belleza', 'Costos, marketing y atención cliente', 25, 80.00, 'Básico');

-- 3. HORARIOS (Asignación de docentes a cursos)
INSERT INTO horarios (id_curso, id_docente, dia, hora_inicio, hora_fin, aula, fecha_inicio_ciclo, fecha_fin_ciclo) VALUES
(1, 5, 'Lunes', '08:00', '12:00', 'Aula Facial 1', '2026-01-15', '2026-03-15'),
(2, 8, 'Miércoles', '14:00', '18:00', 'Aula Masajes', '2026-02-01', '2026-03-20'),
(3, 6, 'Martes', '08:00', '12:00', 'Lab Peluquería', '2026-01-10', '2026-04-10'),
(4, 4, 'Jueves', '14:00', '18:00', 'Lab Barbería', '2026-02-15', '2026-04-15'),
(5, 3, 'Viernes', '08:00', '12:00', 'Lab Uñas', '2026-01-20', '2026-03-25'),
(6, 7, 'Sábado', '08:00', '12:00', 'Lab Uñas', '2026-03-01', '2026-04-05'),
(7, 2, 'Lunes', '14:00', '18:00', 'Lab Maquillaje', '2026-01-12', '2026-03-12'),
(8, 14, 'Miércoles', '14:00', '18:00', 'Lab Maquillaje', '2026-02-10', '2026-04-10'),
(9, 10, 'Viernes', '18:00', '20:00', 'Aula Teórica', '2026-01-08', '2026-02-08'),
(10, 13, 'Sábado', '14:00', '17:00', 'Aula Teórica', '2026-03-05', '2026-04-05');

-- 4. ESTUDIANTES (140 registros - Solo Región Sierra)
DO $$
DECLARE
    i INTEGER;
    v_cedula VARCHAR(10);
    v_prov INTEGER;
    v_nombre VARCHAR(60);
    v_apellido VARCHAR(60);
BEGIN
    FOR i IN 1..140 LOOP
        -- Generar cédula de provincia sierra (01,03,05,06,10,11,14,17,18)
        v_prov := (ARRAY[1,3,5,6,10,11,14,17,18])[floor(random()*9 + 1)];
        v_cedula := LPAD(v_prov::TEXT, 2, '0') || LPAD((floor(random()*9999999 + 1))::TEXT, 8, '0');
        
        -- Nombres y apellidos ecuatorianos comunes
        v_nombre := (ARRAY['María','Ana','Lucía','Sofía','Valeria','Camila','Daniela','Andrea','Paula','Karen',
                           'Juan','Carlos','José','Luis','Miguel','David','Daniel','Andrés','Pedro','Jorge'])[floor(random()*20 + 1)];
        v_apellido := (ARRAY['Pérez','González','Rodríguez','López','Martínez','Sánchez','Ramírez','Torres','Flores','Rivera',
                             'Gómez','Díaz','Cruz','Morales','Reyes','Gutiérrez','Ortiz','Ramos','Vargas','Castillo'])[floor(random()*20 + 1)];
        
        INSERT INTO estudiantes (cedula, nombres, apellidos, fecha_nacimiento, telefono, correo, direccion, estado)
        VALUES (
            v_cedula,
            v_nombre,
            v_apellido || ' ' || (ARRAY['Yupi','Guaman','Lema','Chimba','Tituaña','Calapucha','Males','Quishpe','Anaguano','Pilataxi'])[floor(random()*10 + 1)],
            DATE '1990-01-01' + (random() * (DATE '2005-12-31' - DATE '1990-01-01'))::INTEGER,
            '09' || LPAD((floor(random()*99999999 + 1))::TEXT, 8, '0'),
            LOWER(v_nombre) || '.' || LOWER(v_apellido) || i || '@gmail.com',
            (ARRAY['Quito','Ibarra','Latacunga','Ambato','Riobamba','Guaranda','Azogues','Cuenca','Loja','Otavalo'])[floor(random()*10 + 1)] || ', Sector ' || (ARRAY['Centro','Norte','Sur','La Ferroviaria','San Sebastián'])[floor(random()*5 + 1)],
            'Activo'
        );
    END LOOP;
END $$;

-- 5. MATRÍCULAS (140 registros: Estados MEZCLADOS aleatoriamente)
DO $$
DECLARE
    j INTEGER;
    v_estado VARCHAR(20);
    v_porcentaje DECIMAL(5,2);
    v_rand NUMERIC;
BEGIN
    FOR j IN 1..140 LOOP
        v_rand := random();
        
        -- Lógica de mezcla: 
        -- 0.0 a 0.4 (40%) -> En Curso
        -- 0.4 a 0.75 (35%) -> Aprobado
        -- 0.75 a 1.0 (25%) -> Reprobado
        
        IF v_rand < 0.4 THEN
            v_estado := 'En Curso';
            v_porcentaje := floor(random() * 80 + 10); -- Entre 10% y 90%
        ELSIF v_rand < 0.75 THEN
            v_estado := 'Aprobado';
            v_porcentaje := 100.00;
        ELSE
            v_estado := 'Reprobado';
            v_porcentaje := floor(random() * 50 + 10); -- Entre 10% y 60%
        END IF;
        
        INSERT INTO matriculas (id_estudiante, id_curso, id_horario, fecha_matricula, estado, porcentaje_asistencia)
        VALUES (
            j,
            (floor(random()*10 + 1))::INT,
            (floor(random()*10 + 1))::INT,
            DATE '2026-01-01' + (random() * 60)::INTEGER,
            v_estado,
            v_porcentaje
        );
    END LOOP;
END $$;

-- 6. PAGOS (Para cada matrícula)
INSERT INTO pagos (id_matricula, fecha_pago, monto, metodo_pago, estado, referencia)
SELECT 
    id_matricula,
    fecha_matricula,
    (SELECT costo FROM cursos WHERE id_curso = m.id_curso),
    (ARRAY['Transferencia','Efectivo','Tarjeta','Pichincha Pago'])[floor(random()*4 + 1)],
    'Pagado',
    'REF-' || LPAD(id_matricula::TEXT, 5, '0')
FROM matriculas m;

-- 7. CERTIFICADOS (Solo para los APROBADOS)
INSERT INTO certificados (id_matricula, fecha_emision, numero_certificado, qr_code)
SELECT 
    id_matricula,
    fecha_matricula + 30,
    'CERT-2026-' || LPAD(id_matricula::TEXT, 4, '0'),
    'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=CERT-' || id_matricula
FROM matriculas 
WHERE estado = 'Aprobado';

-- 8. ASISTENCIA (Generar 10 registros por matrícula)
DO $$
DECLARE
    rec RECORD;
    k INTEGER;
BEGIN
    FOR rec IN SELECT * FROM matriculas LOOP
        FOR k IN 1..10 LOOP
            INSERT INTO asistencia (id_matricula, fecha, asistencia)
            VALUES (
                rec.id_matricula,
                rec.fecha_matricula + k,
                CASE WHEN random() > 0.1 THEN 'Presente' ELSE 'Ausente' END
            );
        END LOOP;
    END LOOP;
END $$;