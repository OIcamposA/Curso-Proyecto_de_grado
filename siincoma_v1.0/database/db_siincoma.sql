-- SIINCOMA · Esquema MySQL 8.0.16+ (InnoDB, utf8mb4)
CREATE DATABASE IF NOT EXISTS siincoma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siincoma;

CREATE TABLE configuracion (clave VARCHAR(40) PRIMARY KEY, valor VARCHAR(255) NOT NULL DEFAULT '');
CREATE TABLE rol (id_rol INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE);
CREATE TABLE especie (id_especie INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE);
CREATE TABLE tipo_alerta (id_tipo INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE);
CREATE TABLE estado_alerta (id_estado INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE);

-- El número de apartamento es el usuario de acceso, por eso es único en todo el conjunto.
CREATE TABLE unidad_residencial (
  id_unidad INT AUTO_INCREMENT PRIMARY KEY,
  torre_casa VARCHAR(30) NOT NULL,
  apartamento VARCHAR(10) NOT NULL UNIQUE
);

CREATE TABLE usuario (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  id_rol INT NOT NULL, id_unidad INT NOT NULL,
  nombres VARCHAR(80) NOT NULL, apellidos VARCHAR(80) NOT NULL,
  documento VARCHAR(20) NOT NULL UNIQUE, correo VARCHAR(120) NOT NULL UNIQUE,
  telefono VARCHAR(20),
  tipo_residente ENUM('Propietario','Arrendatario') NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  estado ENUM('Pendiente','Activo','Inactivo') NOT NULL DEFAULT 'Pendiente',
  autoriza_datos TINYINT(1) NOT NULL DEFAULT 0,
  fecha_autorizacion DATETIME NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_rol) REFERENCES rol(id_rol),
  FOREIGN KEY (id_unidad) REFERENCES unidad_residencial(id_unidad),
  CONSTRAINT ck_autorizacion CHECK (estado = 'Pendiente' OR (autoriza_datos = 1 AND fecha_autorizacion IS NOT NULL))
);

CREATE TABLE login_intento (
  apartamento VARCHAR(10) PRIMARY KEY,
  intentos INT NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL
);

CREATE TABLE mascota (
  id_mascota INT AUTO_INCREMENT PRIMARY KEY,
  id_tutor INT NOT NULL, id_especie INT NOT NULL,
  nombre VARCHAR(60) NOT NULL, raza VARCHAR(60),
  sexo ENUM('Hembra','Macho') NOT NULL,
  fecha_nacimiento_aprox DATE, color_senas VARCHAR(150), condiciones_especiales VARCHAR(200),
  foto_url VARCHAR(255),
  vacunas_al_dia TINYINT(1) NOT NULL DEFAULT 0,
  esterilizada TINYINT(1) NOT NULL DEFAULT 0,
  microchip TINYINT(1) NOT NULL DEFAULT 0,
  estado ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_tutor) REFERENCES usuario(id_usuario),
  FOREIGN KEY (id_especie) REFERENCES especie(id_especie)
);

CREATE TABLE alerta (
  id_alerta INT AUTO_INCREMENT PRIMARY KEY,
  id_mascota INT NOT NULL, id_tipo INT NOT NULL, id_estado INT NOT NULL, id_creador INT NULL,
  origen ENUM('Manual','Automática') NOT NULL DEFAULT 'Manual',
  descripcion TEXT,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_cierre DATETIME NULL,
  FOREIGN KEY (id_mascota) REFERENCES mascota(id_mascota),
  FOREIGN KEY (id_tipo) REFERENCES tipo_alerta(id_tipo),
  FOREIGN KEY (id_estado) REFERENCES estado_alerta(id_estado),
  FOREIGN KEY (id_creador) REFERENCES usuario(id_usuario)
);

CREATE TABLE seguimiento_alerta (
  id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
  id_alerta INT NOT NULL, id_usuario INT NOT NULL, id_estado INT NOT NULL,
  observacion TEXT, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_alerta) REFERENCES alerta(id_alerta) ON DELETE CASCADE,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
  FOREIGN KEY (id_estado) REFERENCES estado_alerta(id_estado)
);

CREATE TABLE notificacion (
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_alerta INT NOT NULL, id_destinatario INT NOT NULL,
  canal VARCHAR(20) NOT NULL DEFAULT 'Correo', asunto VARCHAR(150),
  enviada TINYINT(1) NOT NULL DEFAULT 0, fecha_envio DATETIME NULL,
  FOREIGN KEY (id_alerta) REFERENCES alerta(id_alerta) ON DELETE CASCADE,
  FOREIGN KEY (id_destinatario) REFERENCES usuario(id_usuario)
);

CREATE TABLE reporte (
  id_reporte INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  tipo ENUM('Censo','Estado sanitario','Alertas') NOT NULL,
  fecha_desde DATE NULL, fecha_hasta DATE NULL,
  formato ENUM('Pantalla','PDF','Excel','CSV') NOT NULL DEFAULT 'Pantalla',
  fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
  CONSTRAINT ck_periodo CHECK (fecha_hasta IS NULL OR fecha_desde IS NULL OR fecha_hasta >= fecha_desde)
);

CREATE TABLE auditoria (
  id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NULL, entidad VARCHAR(30) NOT NULL, id_entidad INT NULL,
  accion ENUM('INSERT','UPDATE','DELETE') NOT NULL, detalle TEXT,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

CREATE INDEX ix_mascota_tutor ON mascota(id_tutor);
CREATE INDEX ix_alerta_mascota ON alerta(id_mascota);
CREATE INDEX ix_alerta_estado ON alerta(id_estado);

INSERT INTO configuracion (clave, valor) VALUES ('nombre_conjunto', 'Conjunto residencial'), ('logo', '');
INSERT INTO rol (nombre) VALUES ('Administrador'), ('Residente');
INSERT INTO especie (nombre) VALUES ('Perro'), ('Gato'), ('Otra');
INSERT INTO tipo_alerta (nombre) VALUES ('Extravío'), ('Mudanza'), ('Vacunación');
INSERT INTO estado_alerta (nombre) VALUES ('Activa'), ('En revisión'), ('Pendiente'), ('Resuelta');
