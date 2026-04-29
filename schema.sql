-- PT Lintas Nusantara Ekspedisi DMS MySQL Schema
-- Optimized for InfinityFree / Standard Shared Hosting

CREATE DATABASE IF NOT EXISTS dms_logistik;
USE dms_logistik;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff', 'finance') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Routes Table (Tariff Logic)
CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origin VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    price_per_kg DECIMAL(10, 2) NOT NULL,
    estimated_duration VARCHAR(50)
) ENGINE=InnoDB;

-- Train Schedules Table
CREATE TABLE IF NOT EXISTS train_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    train_name VARCHAR(100) NOT NULL,
    route_id INT,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    capacity_kg INT NOT NULL,
    status ENUM('available', 'full') DEFAULT 'available',
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Shipments Table
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resi_number VARCHAR(20) UNIQUE NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20),
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20),
    goods_type VARCHAR(100),
    goods_photo VARCHAR(255),
    quantity INT DEFAULT 1,
    weight_kg DECIMAL(10, 2) NOT NULL,
    route_id INT,
    train_id INT,
    status ENUM('waiting', 'shipped', 'transit', 'arrived') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
    FOREIGN KEY (train_id) REFERENCES train_schedules(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Shipment Items Table
CREATE TABLE IF NOT EXISTS shipment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'Koli',
    weight_kg DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Documents Table
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NULL,
    reference_resi VARCHAR(50) NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    category ENUM('surat_jalan', 'resi', 'invoice', 'payment_proof', 'operational', 'bast', 'manifest') NOT NULL,
    version INT DEFAULT 1,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNIQUE,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    due_date DATE NOT NULL,
    payment_proof VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed Initial Data
INSERT IGNORE INTO users (username, password, name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Aditya Pratama', 'admin');

INSERT IGNORE INTO routes (origin, destination, price_per_kg) VALUES 
('Jakarta', 'Surabaya', 5000),
('Jakarta', 'Bandung', 2500),
('Surabaya', 'Semarang', 3000);
