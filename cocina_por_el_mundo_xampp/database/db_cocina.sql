CREATE DATABASE IF NOT EXISTS db_cocina
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_cocina;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS compra_detalles;
DROP TABLE IF EXISTS candidaturas;
DROP TABLE IF EXISTS inscripciones;
DROP TABLE IF EXISTS recetas;
DROP TABLE IF EXISTS compras;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS proveedores;
DROP TABLE IF EXISTS cursos;
DROP TABLE IF EXISTS cocineros;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    rol ENUM('alumno','chef','admin') NOT NULL DEFAULT 'alumno',
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE cocineros (
    id_cocinero INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    especialidad VARCHAR(100) NOT NULL,
    biografia TEXT NOT NULL,
    nota DECIMAL(2,1) NOT NULL DEFAULT 5.0,
    imagen VARCHAR(255) NULL,
    id_user INT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cocinero_usuario FOREIGN KEY (id_user)
      REFERENCES usuarios(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    nombre_curso VARCHAR(120) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    cocina VARCHAR(70) NOT NULL,
    descripcion TEXT NOT NULL,
    nivel ENUM('basico','intermedio','avanzado') NOT NULL DEFAULT 'basico',
    duracion VARCHAR(60) NOT NULL,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    nota DECIMAL(2,1) NOT NULL DEFAULT 5.0,
    imagen VARCHAR(255) NULL,
    destacado TINYINT(1) NOT NULL DEFAULT 0,
    id_cocinero INT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_curso_cocinero FOREIGN KEY (id_cocinero)
      REFERENCES cocineros(id_cocinero) ON DELETE SET NULL,
    INDEX idx_cursos_nombre (nombre_curso),
    INDEX idx_cursos_cocina (cocina)
) ENGINE=InnoDB;

CREATE TABLE proveedores (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    nombre_empresa VARCHAR(100) NOT NULL,
    contacto VARCHAR(100) NULL,
    telefono VARCHAR(20) NULL
) ENGINE=InnoDB;

CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_proveedor INT NOT NULL,
    fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NULL,
    CONSTRAINT fk_pedido_proveedor FOREIGN KEY (id_proveedor)
      REFERENCES proveedores(id_proveedor) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE compras (
    id_compra INT AUTO_INCREMENT PRIMARY KEY,
    codigo_compra VARCHAR(32) NOT NULL UNIQUE,
    id_user INT NOT NULL,
    nombre_cliente VARCHAR(120) NOT NULL,
    email_cliente VARCHAR(150) NOT NULL,
    telefono_cliente VARCHAR(25) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    codigo_descuento VARCHAR(30) NULL,
    estado ENUM('simulado_pagado') NOT NULL DEFAULT 'simulado_pagado',
    fecha_compra TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_compra_usuario FOREIGN KEY (id_user)
      REFERENCES usuarios(id_user) ON DELETE RESTRICT,
    INDEX idx_compras_email (email_cliente),
    INDEX idx_compras_fecha (fecha_compra)
) ENGINE=InnoDB;

CREATE TABLE compra_detalles (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_compra INT NOT NULL,
    id_curso INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_detalle_compra FOREIGN KEY (id_compra)
      REFERENCES compras(id_compra) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_curso FOREIGN KEY (id_curso)
      REFERENCES cursos(id_curso) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE inscripciones (
    id_ins INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_curso INT NOT NULL,
    id_cocinero INT NULL,
    id_compra INT NULL,
    fecha_inscripcion DATE NOT NULL,
    CONSTRAINT fk_ins_user FOREIGN KEY (id_user) REFERENCES usuarios(id_user),
    CONSTRAINT fk_ins_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
    CONSTRAINT fk_ins_chef FOREIGN KEY (id_cocinero) REFERENCES cocineros(id_cocinero) ON DELETE SET NULL,
    CONSTRAINT fk_ins_compra FOREIGN KEY (id_compra) REFERENCES compras(id_compra) ON DELETE SET NULL,
    INDEX idx_inscripciones_fecha (fecha_inscripcion)
) ENGINE=InnoDB;

CREATE TABLE recetas (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    nombre_receta VARCHAR(120) NOT NULL,
    instrucciones TEXT NULL,
    CONSTRAINT fk_receta_curso FOREIGN KEY (id_curso)
      REFERENCES cursos(id_curso) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE candidaturas (
    id_candidatura INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(25) NOT NULL,
    especialidad VARCHAR(100) NOT NULL,
    experiencia VARCHAR(100) NOT NULL,
    motivacion TEXT NOT NULL,
    cv_path VARCHAR(255) NOT NULL,
    cv_original VARCHAR(255) NOT NULL,
    cv_mime VARCHAR(100) NOT NULL,
    portfolio_path VARCHAR(255) NULL,
    portfolio_original VARCHAR(255) NULL,
    estado ENUM('pendiente','revisada','descartada','aceptada') NOT NULL DEFAULT 'pendiente',
    fecha_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_caducidad DATE NOT NULL,
    INDEX idx_candidaturas_estado (estado),
    INDEX idx_candidaturas_caducidad (fecha_caducidad)
) ENGINE=InnoDB;

INSERT INTO cocineros (nombre, especialidad, biografia, nota, imagen) VALUES
('Zendaya Campbel', 'Cocina mexicana y española', 'Cocina de mercado, salsas profundas y recetas que reúnen a todo el mundo en la mesa.', 5.0, 'assets/images/CHEF1.jpg'),
('Mark Rodriguez', 'Cocina coreana', 'Enseña fermentos, fuego y equilibrio para cocinar Corea con confianza.', 4.8, 'assets/images/CHEF2.jpg'),
('Han Xiwanmu', 'Cocina china', 'Especialista en wok y en convertir ingredientes cotidianos en platos memorables.', 4.9, 'assets/images/CHEF3.jpg'),
('Dina Sato', 'Cocina japonesa', 'Precisión, sencillez y respeto por el ingrediente son sus tres reglas.', 4.9, 'assets/images/CHEF4.jpg');

INSERT INTO cursos (nombre_curso, slug, cocina, descripcion, nivel, duracion, precio, nota, imagen, destacado, id_cocinero) VALUES
('Cocina coreana tradicional', 'cocina-coreana-tradicional', 'Coreana', 'Domina los sabores de Corea preparando kimchi, bibimbap y bulgogi desde cero.', 'intermedio', '6 semanas', 59.00, 4.9, 'assets/images/comida_corea.jpg', 1, 2),
('Fundamentos de cocina japonesa', 'fundamentos-cocina-japonesa', 'Japonesa', 'Técnicas esenciales para ramen, sushi casero y los caldos que definen Japón.', 'basico', '5 semanas', 54.00, 4.8, 'assets/images/comida_japo.jpg', 1, 4),
('Cocina mexicana de raíz', 'cocina-mexicana-raiz', 'Mexicana', 'Un recorrido por salsas, maíz y recetas familiares llenas de carácter.', 'intermedio', '4 semanas', 49.00, 4.7, NULL, 0, 1),
('Clásicos de la cocina española', 'clasicos-cocina-espanola', 'Española', 'Aprende a cocinar tapas, arroces y guisos que saben a sobremesa.', 'basico', '5 semanas', 52.00, 4.9, 'assets/images/comida_españa.jpg', 0, 1),
('Secretos de la cocina china', 'secretos-de-la-cocina-china', 'China', 'Salteados, dumplings y equilibrio de sabores con técnicas sencillas y precisas.', 'avanzado', '7 semanas', 64.00, 5.0, 'assets/images/comida_china.jpg', 0, 3);
