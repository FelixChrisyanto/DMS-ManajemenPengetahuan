-- PT Lintas Nusantara Ekspedisi DMS SQLite Schema

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    name TEXT NOT NULL,
    role TEXT CHECK(role IN ('admin', 'staff', 'finance')) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Routes Table (Tariff Logic)
CREATE TABLE IF NOT EXISTS routes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    origin TEXT NOT NULL,
    destination TEXT NOT NULL,
    price_per_kg DECIMAL(10, 2) NOT NULL,
    estimated_duration TEXT
);

-- Train Schedules Table
CREATE TABLE IF NOT EXISTS train_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    train_name TEXT NOT NULL,
    route_id INTEGER,
    departure_time TEXT NOT NULL,
    arrival_time TEXT NOT NULL,
    capacity_kg INTEGER NOT NULL,
    status TEXT CHECK(status IN ('available', 'full')) DEFAULT 'available',
    FOREIGN KEY (route_id) REFERENCES routes(id)
);

-- Shipments Table
CREATE TABLE IF NOT EXISTS shipments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resi_number TEXT UNIQUE NOT NULL,
    sender_name TEXT NOT NULL,
    sender_phone TEXT,
    receiver_name TEXT NOT NULL,
    receiver_phone TEXT,
    goods_type TEXT,
    goods_photo TEXT,
    quantity INTEGER DEFAULT 1,
    weight_kg DECIMAL(10, 2) NOT NULL,
    route_id INTEGER,
    train_id INTEGER,
    status TEXT CHECK(status IN ('waiting', 'shipped', 'transit', 'arrived')) DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (route_id) REFERENCES routes(id),
    FOREIGN KEY (train_id) REFERENCES train_schedules(id)
);

-- Shipment Items Table
CREATE TABLE IF NOT EXISTS shipment_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shipment_id INTEGER NOT NULL,
    item_name TEXT NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit TEXT DEFAULT 'Koli',
    weight_kg DECIMAL(10, 2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
);

-- Documents Table (Soft Delete support)
CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shipment_id INTEGER NULL,
    reference_resi TEXT NULL,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    category TEXT CHECK(category IN ('surat_jalan', 'resi', 'invoice', 'payment_proof', 'operational', 'bast', 'manifest')) NOT NULL,
    version INTEGER DEFAULT 1,
    uploaded_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shipment_id INTEGER UNIQUE,
    invoice_number TEXT UNIQUE NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status TEXT CHECK(status IN ('paid', 'unpaid')) DEFAULT 'unpaid',
    due_date TEXT NOT NULL,
    payment_proof TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id)
);

-- Seed Initial Data
INSERT OR IGNORE INTO users (username, password, name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Aditya Pratama', 'admin');

INSERT OR IGNORE INTO routes (origin, destination, price_per_kg) VALUES 
('Jakarta', 'Surabaya', 5000),
('Jakarta', 'Bandung', 2500),
('Surabaya', 'Semarang', 3000);
