CREATE DATABASE IF NOT EXISTS db_cocina;
USE db_cocina;

-- Tabla de Usuarios
CREATE TABLE usuarios (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash CHAR(64) NOT NULL,
    rol ENUM('alumno', 'chef', 'admin') DEFAULT 'alumno',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Cocineros
CREATE TABLE cocineros (
    id_cocinero INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    especialidad VARCHAR(50),
    id_user INT,
    FOREIGN KEY (id_user) REFERENCES usuarios(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de Proveedores
CREATE TABLE proveedores (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    nombre_empresa VARCHAR(100) NOT NULL,
    contacto VARCHAR(50),
    telefono VARCHAR(20)
) ENGINE=InnoDB;

-- Tabla de Cursos
CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    nombre_curso VARCHAR(100) NOT NULL,
    descripcion TEXT,
    nivel ENUM('basico', 'intermedio', 'avanzado')
) ENGINE=InnoDB;

-- Tabla de Pedidos (Relacion con Proveedores)
CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_proveedor INT NOT NULL,
    fecha_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10, 2),
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Inscripciones (Relacion triple: User, Curso, Cocinero)
CREATE TABLE inscripciones (
    id_ins INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_curso INT NOT NULL,
    id_cocinero INT NOT NULL,
    fecha_inscripcion DATE,
    FOREIGN KEY (id_user) REFERENCES usuarios(id_user),
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
    FOREIGN KEY (id_cocinero) REFERENCES cocineros(id_cocinero)
) ENGINE=InnoDB;

-- Tabla de Recetas (Relacion 1:N con Cursos)
CREATE TABLE recetas (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    nombre_receta VARCHAR(100) NOT NULL,
    instrucciones TEXT,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE
) ENGINE=InnoDB;
