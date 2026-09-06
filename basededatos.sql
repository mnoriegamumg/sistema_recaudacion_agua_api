CREATE DATABASE IF NOT EXISTS municipalidad_santa_barbara;
USE municipalidad_santa_barbara;

-- Tabla de Usuarios del Sistema (Personal Municipal)
CREATE TABLE usuarios_sistema (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    nombre_completo VARCHAR(100) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'tesorero', 'cajero') DEFAULT 'cajero',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL
);

-- Tabla de Usuarios del Servicio de Agua (Vecinos)
CREATE TABLE usuarios_agua (
    id_usuario_agua INT PRIMARY KEY AUTO_INCREMENT,
    nombre_completo VARCHAR(100) NOT NULL,
    numero_medidor VARCHAR(20) UNIQUE NOT NULL,
    direccion VARCHAR(200) NOT NULL,
    telefono VARCHAR(15),
    comunidad ENUM('cabecera_municipal', 'caserio_san_pablo', 'finca_san_francisco') NOT NULL,
    saldo_pendiente DECIMAL(10,2) DEFAULT 0.00,
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Registro de Pagos
CREATE TABLE pagos (
    id_pago INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario_agua INT NOT NULL,
    id_usuario_sistema INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    numero_recibo VARCHAR(20) UNIQUE NOT NULL,
    FOREIGN KEY (id_usuario_agua) REFERENCES usuarios_agua(id_usuario_agua),
    FOREIGN KEY (id_usuario_sistema) REFERENCES usuarios_sistema(id_usuario)
);

-- Insertar usuario administrador por defecto
INSERT INTO usuarios_sistema (nombre_completo, correo, password_hash, rol) 
VALUES ('Administrador', 'admin@municipalidad.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Contraseña: password

-- Datos de prueba (vecinos)
INSERT INTO usuarios_agua (nombre_completo, numero_medidor, direccion, telefono, comunidad) VALUES
('María García', 'M001', 'Calle Principal #45, Cabecera Municipal', '5551-2345', 'cabecera_municipal'),
('José Pérez', 'M002', 'Caserío San Pablo, Lote 12', '5552-3456', 'caserio_san_pablo'),
('Ana López', 'M003', 'Finca San Francisco, Parcela 8', '5553-4567', 'finca_san_francisco');