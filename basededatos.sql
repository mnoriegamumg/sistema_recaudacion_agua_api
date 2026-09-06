
-- ============================================
-- BASE DE DATOS COMPLETA - SISTEMA DE AGUA
-- ============================================

CREATE DATABASE IF NOT EXISTS municipalidad_santa_barbara;
USE municipalidad_santa_barbara;

-- ============================================
-- TABLA: usuarios_sistema (Personal Municipal)
-- ============================================
CREATE TABLE usuarios_sistema (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    nombre_completo VARCHAR(100) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'tesorero', 'cajero') DEFAULT 'cajero',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios_sistema(id_usuario),
    FOREIGN KEY (updated_by) REFERENCES usuarios_sistema(id_usuario)
);

-- ============================================
-- TABLA: contadores (Medidores de agua)
-- ============================================
CREATE TABLE contadores (
    id_contador INT PRIMARY KEY AUTO_INCREMENT,
    codigo_contador VARCHAR(20) UNIQUE NOT NULL COMMENT 'Código único del medidor',
    dpi VARCHAR(13) NULL COMMENT 'DPI del propietario (opcional)',
    nit VARCHAR(15) NULL COMMENT 'NIT del propietario (opcional)',
    nombre_propietario VARCHAR(100) NOT NULL COMMENT 'Nombre del dueño del contador',
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo',
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    
    FOREIGN KEY (created_by) REFERENCES usuarios_sistema(id_usuario),
    FOREIGN KEY (updated_by) REFERENCES usuarios_sistema(id_usuario)
);

-- Índices para búsquedas rápidas
CREATE INDEX idx_codigo_contador ON contadores(codigo_contador);
CREATE INDEX idx_dpi ON contadores(dpi);
CREATE INDEX idx_nit ON contadores(nit);

-- ============================================
-- TABLA: clientes (Datos específicos del cliente)
-- ============================================
CREATE TABLE clientes (
    id_cliente INT PRIMARY KEY AUTO_INCREMENT,
    id_contador INT NOT NULL COMMENT 'Contador asociado',
    direccion VARCHAR(200) NULL,
    correo VARCHAR(100) NULL,
    telefono VARCHAR(15) NULL,
    telefono_alternativo VARCHAR(15) NULL,
    referencia_direccion VARCHAR(200) NULL,
    comunidad ENUM('cabecera_municipal', 'caserio_san_pablo', 'finca_san_francisco') NOT NULL,
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    
    FOREIGN KEY (id_contador) REFERENCES contadores(id_contador) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES usuarios_sistema(id_usuario),
    FOREIGN KEY (updated_by) REFERENCES usuarios_sistema(id_usuario)
);

-- Índices
CREATE INDEX idx_cliente_contador ON clientes(id_contador);
CREATE INDEX idx_cliente_comunidad ON clientes(comunidad);

-- ============================================
-- TABLA: pagos (Registro de pagos mensuales)
-- ============================================
CREATE TABLE pagos (
    id_pago INT PRIMARY KEY AUTO_INCREMENT,
    id_contador INT NOT NULL COMMENT 'Contador asociado',
    id_usuario_sistema INT NOT NULL COMMENT 'Usuario que registró el pago',
    
    -- Datos del pago
    monto DECIMAL(10,2) NOT NULL COMMENT 'Monto en Quetzales',
    mes_pagado INT NOT NULL COMMENT 'Mes del pago (1-12)',
    ano_pagado INT NOT NULL COMMENT 'Año del pago',
    periodo_inicio DATE NOT NULL COMMENT 'Fecha inicio del período pagado',
    periodo_fin DATE NOT NULL COMMENT 'Fecha fin del período pagado',
    es_pago_anual BOOLEAN DEFAULT FALSE COMMENT 'Indica si es pago de año completo',
    
    -- Datos de quién pagó
    pagado_por VARCHAR(100) NOT NULL COMMENT 'Nombre de quien realizó el pago',
    identificacion VARCHAR(20) NULL COMMENT 'DPI o identificación de quien pagó',
    observaciones TEXT NULL,
    
    -- Estado del pago
    estado_pago ENUM('pagado', 'anulado', 'reembolsado') DEFAULT 'pagado',
    numero_recibo VARCHAR(20) UNIQUE NOT NULL COMMENT 'Número de recibo generado',
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    
    FOREIGN KEY (id_contador) REFERENCES contadores(id_contador) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_sistema) REFERENCES usuarios_sistema(id_usuario),
    FOREIGN KEY (created_by) REFERENCES usuarios_sistema(id_usuario),
    FOREIGN KEY (updated_by) REFERENCES usuarios_sistema(id_usuario)
);

-- Índices
CREATE INDEX idx_pago_contador ON pagos(id_contador);
CREATE INDEX idx_pago_fecha ON pagos(periodo_inicio, periodo_fin);
CREATE INDEX idx_pago_recibo ON pagos(numero_recibo);
CREATE INDEX idx_pago_mes_ano ON pagos(mes_pagado, ano_pagado);
CREATE INDEX idx_pago_estado ON pagos(estado_pago);

-- ============================================
-- TABLA: morosidad (Estado de morosidad por mes)
-- ============================================
CREATE TABLE morosidad (
    id_morosidad INT PRIMARY KEY AUTO_INCREMENT,
    id_contador INT NOT NULL,
    mes INT NOT NULL,
    ano INT NOT NULL,
    estado ENUM('solvente', 'moroso', 'suspendido') DEFAULT 'solvente',
    saldo_adeudado DECIMAL(10,2) DEFAULT 0.00,
    fecha_calculo DATE NOT NULL COMMENT 'Fecha en que se calculó',
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_contador) REFERENCES contadores(id_contador) ON DELETE CASCADE,
    UNIQUE KEY unique_contador_mes_ano (id_contador, mes, ano)
);

-- Índices
CREATE INDEX idx_morosidad_contador ON morosidad(id_contador);
CREATE INDEX idx_morosidad_mes_ano ON morosidad(mes, ano);
CREATE INDEX idx_morosidad_estado ON morosidad(estado);

-- ============================================
-- TABLA: tarifas (Configuración de tarifas mensuales)
-- ============================================
CREATE TABLE tarifas (
    id_tarifa INT PRIMARY KEY AUTO_INCREMENT,
    comunidad ENUM('cabecera_municipal', 'caserio_san_pablo', 'finca_san_francisco') NOT NULL,
    tarifa_mensual DECIMAL(10,2) NOT NULL COMMENT 'Tarifa mensual en Quetzales',
    tarifa_anual DECIMAL(10,2) NOT NULL COMMENT 'Tarifa anual (con descuento)',
    descuento_anual DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Porcentaje de descuento por pago anual',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL COMMENT 'NULL = vigente actualmente',
    activo BOOLEAN DEFAULT TRUE,
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    
    FOREIGN KEY (created_by) REFERENCES usuarios_sistema(id_usuario),
    FOREIGN KEY (updated_by) REFERENCES usuarios_sistema(id_usuario)
);

-- ============================================
-- DATOS DE PRUEBA
-- ============================================

-- Usuario administrador (password: admin123)
INSERT INTO usuarios_sistema (nombre_completo, correo, password_hash, rol) 
VALUES ('Administrador', 'admin@municipalidad.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Tarifas por comunidad
INSERT INTO tarifas (comunidad, tarifa_mensual, tarifa_anual, descuento_anual, fecha_inicio, activo) VALUES
('cabecera_municipal', 125.00, 1350.00, 10.00, CURDATE(), 1),
('caserio_san_pablo', 100.00, 1080.00, 10.00, CURDATE(), 1),
('finca_san_francisco', 85.00, 918.00, 10.00, CURDATE(), 1);

-- Contadores de ejemplo
INSERT INTO contadores (codigo_contador, dpi, nit, nombre_propietario, estado) VALUES
('M001', '1234567890101', '1234567-8', 'María García Pérez', 'activo'),
('M002', '9876543210909', '7654321-9', 'José Hernández López', 'activo'),
('M003', NULL, NULL, 'Ana Rodríguez Méndez', 'activo');

-- Clientes de ejemplo
INSERT INTO clientes (id_contador, direccion, correo, telefono, comunidad) VALUES
(1, 'Calle Principal #45, Zona 1', 'maria.garcia@email.com', '5551-1234', 'cabecera_municipal'),
(2, 'Caserío San Pablo, Lote 12', 'jose.hernandez@email.com', '5552-5678', 'caserio_san_pablo'),
(3, 'Finca San Francisco, Parcela 8', 'ana.rodriguez@email.com', '5553-9012', 'finca_san_francisco');

-- Pagos de ejemplo
INSERT INTO pagos (id_contador, id_usuario_sistema, monto, mes_pagado, ano_pagado, 
                   periodo_inicio, periodo_fin, es_pago_anual, pagado_por, 
                   estado_pago, numero_recibo) VALUES
(1, 1, 125.00, 1, 2026, '2026-01-01', '2026-01-31', 0, 'María García Pérez', 'pagado', 'REC-202601-001'),
(1, 1, 125.00, 2, 2026, '2026-02-01', '2026-02-28', 0, 'Carlos Pérez', 'pagado', 'REC-202602-001'),
(2, 1, 100.00, 1, 2026, '2026-01-01', '2026-01-31', 0, 'José Hernández López', 'pagado', 'REC-202601-002');