-- schema/mysql.sql dosyasindan uretildi. Elle duzenlemeyin.
-- Yeniden uretmek icin: php bin/make-sqlite-schema.php

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name           TEXT    NOT NULL,
    email          TEXT    NOT NULL,
    password_hash  TEXT    NOT NULL,
    role           TEXT NOT NULL DEFAULT 'customer',
    status         TEXT          NOT NULL DEFAULT 'active',
    balance_minor  INTEGER          NOT NULL DEFAULT 0,
    api_token      TEXT        NULL,
    created_at     TEXT        NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_users_email ON users (email);

CREATE UNIQUE INDEX IF NOT EXISTS ux_users_api_token ON users (api_token);

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    type            TEXT     NOT NULL,
    amount_minor    INTEGER          NOT NULL,
    direction       INTEGER         NOT NULL,
    balance_after   INTEGER          NOT NULL,
    idempotency_key TEXT    NOT NULL,
    reference_type  TEXT     NULL,
    reference_id    INTEGER NULL,
    created_at      TEXT        NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_wallet_idempotency ON wallet_transactions (idempotency_key);

CREATE INDEX IF NOT EXISTS ix_wallet_user ON wallet_transactions (user_id, id);

CREATE INDEX IF NOT EXISTS ix_wallet_reference ON wallet_transactions (reference_type, reference_id);

CREATE TABLE IF NOT EXISTS countries (
    code       TEXT   NOT NULL,
    name       TEXT  NOT NULL,
    is_active  INTEGER   NOT NULL DEFAULT 1,
    PRIMARY KEY (code)
);

CREATE TABLE IF NOT EXISTS services (
    code       TEXT  NOT NULL,
    name       TEXT  NOT NULL,
    is_active  INTEGER   NOT NULL DEFAULT 1,
    PRIMARY KEY (code)
);

CREATE TABLE IF NOT EXISTS provider_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider       TEXT  NOT NULL,
    kind           TEXT NOT NULL,
    provider_code  TEXT  NOT NULL,
    canonical_code TEXT  NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_provider_codes ON provider_codes (provider, kind, provider_code);

CREATE TABLE IF NOT EXISTS unmapped_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider      TEXT NOT NULL,
    kind          TEXT NOT NULL,
    provider_code TEXT NOT NULL,
    seen_at       TEXT    NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_unmapped ON unmapped_codes (provider, kind, provider_code);

CREATE TABLE IF NOT EXISTS provider_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider      TEXT NOT NULL,
    country_code  TEXT  NOT NULL,
    service_code  TEXT NOT NULL,
    operator      TEXT NULL,
    cost_minor    INTEGER      NOT NULL,
    currency      TEXT  NOT NULL,
    stock         INTEGER         NOT NULL DEFAULT 0,
    provider_country TEXT NOT NULL,
    provider_service TEXT NOT NULL,
    synced_at     TEXT    NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_offer ON provider_offers (provider, country_code, service_code, operator);

CREATE INDEX IF NOT EXISTS ix_offer_lookup ON provider_offers (country_code, service_code, stock);

CREATE TABLE IF NOT EXISTS pricing_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    country_code   TEXT  NULL,
    service_code   TEXT NULL,
    margin_percent INTEGER         NULL,
    fixed_price_minor INTEGER   NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_pricing_scope ON pricing_rules (country_code, service_code);

CREATE TABLE IF NOT EXISTS number_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id           INTEGER NOT NULL,
    type              TEXT NOT NULL DEFAULT 'activation',
    provider          TEXT  NOT NULL,
    provider_order_id TEXT  NOT NULL,
    country_code      TEXT   NOT NULL,
    service_code      TEXT  NOT NULL,
    phone             TEXT  NOT NULL,

    cost_minor        INTEGER       NOT NULL,
    cost_currency     TEXT   NOT NULL,
    price_minor       INTEGER       NOT NULL,
    status            TEXT  NOT NULL,
    code              TEXT  NULL,
    purchased_at      TEXT     NOT NULL,
    expires_at        TEXT     NOT NULL,
    completed_at      TEXT     NULL,
    last_polled_at    TEXT     NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_provider_order ON number_orders (provider, provider_order_id);

CREATE INDEX IF NOT EXISTS ix_orders_user ON number_orders (user_id, id);

CREATE INDEX IF NOT EXISTS ix_orders_open ON number_orders (status, expires_at);

CREATE TABLE IF NOT EXISTS order_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id    INTEGER NOT NULL,
    sender      TEXT  NOT NULL,
    body        TEXT         NOT NULL,
    code        TEXT  NULL,
    received_at TEXT     NOT NULL
);

CREATE INDEX IF NOT EXISTS ix_messages_order ON order_messages (order_id, id);

CREATE TABLE IF NOT EXISTS payment_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL,
    amount_minor   INTEGER       NOT NULL,
    reference_code TEXT  NOT NULL,
    receipt_path   TEXT NULL,
    status         TEXT NOT NULL DEFAULT 'pending',
    approved_by    INTEGER NULL,
    admin_note     TEXT NULL,
    created_at     TEXT     NOT NULL,
    resolved_at    TEXT     NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_payment_reference ON payment_requests (reference_code);

CREATE INDEX IF NOT EXISTS ix_payment_status ON payment_requests (status, id);

CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NULL,
    action     TEXT  NOT NULL,
    detail     TEXT         NULL,
    ip         TEXT  NULL,
    created_at TEXT     NOT NULL
);

CREATE INDEX IF NOT EXISTS ix_log_action ON activity_log (action, id);
