CREATE DATABASE Farmacia;
USE Farmacia;

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
    FOREIGN KEY (categoria_id) REFERENCES Categoria(id) ON DELETE SET NULL
);

CREATE TABLE Lote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    numero_lote VARCHAR(50),
    fecha_vencimiento DATE,
    cantidad_inicial INT,
    cantidad_actual INT,
    FOREIGN KEY (producto_id) REFERENCES Producto(id) ON DELETE CASCADE
);

CREATE TABLE HistorialStock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    tipo ENUM('Alta','Baja','Venta','Compra','Devolución','Vencimiento') NOT NULL,
    cantidad INT NOT NULL,
    detalle VARCHAR(255),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES Producto(id) ON DELETE CASCADE
);

CREATE TABLE Venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    usuario_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2),
    estado ENUM('Pagada','Pendiente') DEFAULT 'Pendiente',
    FOREIGN KEY (cliente_id) REFERENCES Cliente(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES Usuario(id) ON DELETE CASCADE
);

CREATE TABLE DetalleVenta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    FOREIGN KEY (venta_id) REFERENCES Venta(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES Producto(id) ON DELETE CASCADE
);

CREATE TABLE CuentaCorriente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    saldo_actual DECIMAL(10,2) DEFAULT 0,
    limite_credito DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (cliente_id) REFERENCES Cliente(id) ON DELETE CASCADE
);

CREATE TABLE MovimientoCuenta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    concepto VARCHAR(150),
    monto DECIMAL(10,2),
    FOREIGN KEY (cuenta_id) REFERENCES CuentaCorriente(id) ON DELETE CASCADE
);

CREATE TABLE OrdenCompra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT,
    fecha DATE,
    total DECIMAL(10,2),
    estado ENUM('Pendiente','Recibida','Cancelada') DEFAULT 'Pendiente',
    FOREIGN KEY (proveedor_id) REFERENCES Proveedor(id) ON DELETE CASCADE
);

CREATE TABLE DetalleOrdenCompra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    FOREIGN KEY (orden_id) REFERENCES OrdenCompra(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES Producto(id) ON DELETE CASCADE
);

CREATE TABLE Receta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    numero_receta VARCHAR(50),
    medico VARCHAR(100),
    fecha_emision DATE,
    fecha_vencimiento DATE,
    FOREIGN KEY (cliente_id) REFERENCES Cliente(id) ON DELETE CASCADE
);

CREATE TABLE Alerta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    mensaje VARCHAR(255),
    prioridad ENUM('Alta','Media','Baja'),
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES Producto(id) ON DELETE CASCADE
);
