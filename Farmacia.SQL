-- ==========================
-- CREACIÓN DE TABLAS
-- ==========================

CREATE TABLE Usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('Administrador','Farmaceutico','Empleado') NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    tipoDocumento ENUM('DNI','CUIT','CUIL') NOT NULL,
    nroDocumento VARCHAR(20) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion VARCHAR(200)
);

CREATE TABLE Proveedor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razonSocial VARCHAR(150) NOT NULL,
    cuit VARCHAR(20) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion VARCHAR(200)
);

CREATE TABLE Categoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255)
);

CREATE TABLE Producto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 0,
    requiere_receta BOOLEAN DEFAULT FALSE,
    categoria_id INT,
    FOREIGN KEY (categoria_id) REFERENCES Categoria(id)
);

CREATE TABLE Lote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    numero_lote VARCHAR(50),
    fecha_vencimiento DATE,
    cantidad_inicial INT,
    cantidad_actual INT,
    FOREIGN KEY (producto_id) REFERENCES Producto(id)
);

CREATE TABLE Venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    usuario_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2),
    estado ENUM('Pagada','Pendiente') DEFAULT 'Pendiente',
    FOREIGN KEY (cliente_id) REFERENCES Cliente(id),
    FOREIGN KEY (usuario_id) REFERENCES Usuario(id)
);

CREATE TABLE DetalleVenta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    FOREIGN KEY (venta_id) REFERENCES Venta(id),
    FOREIGN KEY (producto_id) REFERENCES Producto(id)
);

CREATE TABLE CuentaCorriente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    saldo_actual DECIMAL(10,2) DEFAULT 0,
    limite_credito DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (cliente_id) REFERENCES Cliente(id)
);

CREATE TABLE MovimientoCuenta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    concepto VARCHAR(150),
    monto DECIMAL(10,2),
    FOREIGN KEY (cuenta_id) REFERENCES CuentaCorriente(id)
);

CREATE TABLE OrdenCompra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT,
    fecha DATE,
    total DECIMAL(10,2),
    estado ENUM('Pendiente','Recibida','Cancelada') DEFAULT 'Pendiente',
    FOREIGN KEY (proveedor_id) REFERENCES Proveedor(id)
);

CREATE TABLE DetalleOrdenCompra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    FOREIGN KEY (orden_id) REFERENCES OrdenCompra(id),
    FOREIGN KEY (producto_id) REFERENCES Producto(id)
);

CREATE TABLE Receta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    numero_receta VARCHAR(50),
    medico VARCHAR(100),
    fecha_emision DATE,
    fecha_vencimiento DATE,
    FOREIGN KEY (cliente_id) REFERENCES Cliente(id)
);

CREATE TABLE Alerta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    mensaje VARCHAR(255),
    prioridad ENUM('Alta','Media','Baja'),
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES Producto(id)
);

-- Usuarios
INSERT INTO Usuario (nombre, email, usuario, password, rol) VALUES
('Ana Gómez', 'ana@farmacia.com', 'agomez', '1234', 'Administrador'),
('Juan Pérez', 'juan@farmacia.com', 'jperez', '1234', 'Farmaceutico'),
('Carla Díaz', 'carla@farmacia.com', 'cdiaz', '1234', 'Empleado');

-- Clientes
INSERT INTO Cliente (nombre, apellido, tipoDocumento, nroDocumento, telefono, email, direccion) VALUES
('Luis', 'Martínez', 'DNI', '30111222', '1122334455', 'luis@mail.com', 'Calle Falsa 123'),
('María', 'López', 'DNI', '28999111', '1133445566', 'maria@mail.com', 'Av. Siempreviva 742');

-- Proveedores
INSERT INTO Proveedor (razonSocial, cuit, telefono, email, direccion) VALUES
('Droguería Central', '30-12345678-9', '1144556677', 'ventas@drogcentral.com', 'Mitre 456'),
('FarmacoPro', '30-87654321-0', '1199887766', 'contacto@farmaco.com', 'San Martín 890');

-- Categorías
INSERT INTO Categoria (nombre, descripcion) VALUES
('Medicamentos', 'Productos con receta'),
('Perfumería', 'Cosméticos y artículos de higiene');

-- Productos
INSERT INTO Producto (nombre, precio, stock_actual, stock_minimo, requiere_receta, categoria_id) VALUES
('Ibuprofeno 600mg', 1200.50, 100, 10, TRUE, 1),
('Paracetamol 500mg', 950.00, 200, 20, FALSE, 1),
('Shampoo Herbal', 1800.00, 50, 5, FALSE, 2);

-- Lotes
INSERT INTO Lote (producto_id, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual) VALUES
(1, 'L001', '2026-05-01', 100, 100),
(2, 'L002', '2025-12-01', 200, 200),
(3, 'L003', '2026-03-15', 50, 50);

-- Cuentas Corrientes
INSERT INTO CuentaCorriente (cliente_id, saldo_actual, limite_credito) VALUES
(1, 5000, 10000),
(2, 0, 5000);

-- Ventas y detalles
INSERT INTO Venta (cliente_id, usuario_id, total, estado) VALUES
(1, 2, 2400.50, 'Pagada');

INSERT INTO DetalleVenta (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 1, 1200.50, 1200.50),
(1, 2, 1, 1200.00, 1200.00);