PRAGMA foreign_keys = ON;

CREATE TABLE organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    public_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE invoice_presets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    public_id TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    unit_price_cents INTEGER NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    total_cents INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE invoice_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    preset_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price_cents INTEGER NOT NULL,
    subtotal_cents INTEGER NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (preset_id) REFERENCES invoice_presets(id)
);

CREATE TABLE api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    key_prefix TEXT NOT NULL,
    key_hash TEXT NOT NULL UNIQUE,
    scopes_json TEXT NOT NULL,
    revoked_at TEXT NULL,
    last_used_at TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE idempotency_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    api_key_id INTEGER NOT NULL,
    idempotency_key TEXT NOT NULL,
    request_hash TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('processing', 'completed')),
    resource_id INTEGER NULL,
    response_status INTEGER NULL,
    response_json TEXT NULL,
    created_at TEXT NOT NULL,
    completed_at TEXT NULL,
    UNIQUE (organization_id, api_key_id, idempotency_key),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
);
