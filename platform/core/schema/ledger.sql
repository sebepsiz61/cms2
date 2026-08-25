-- Cuzdan defteri. Bakiye asla tek basina bir kolonda tutulmaz; users.balance_minor
-- yalnizca onbellektir ve her zaman ayni islem icinde guncellenir.
--
-- Asagidaki soz dizimi hem SQLite (test) hem MySQL icin gecerlidir. MySQL'de
-- balance_minor BIGINT, id BIGINT AUTO_INCREMENT olarak uretilir.

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL,
    type             TEXT    NOT NULL,          -- load | spend | refund | adjust
    amount_minor     INTEGER NOT NULL,          -- her zaman pozitif; yon type ile belirlenir
    direction        INTEGER NOT NULL,          -- +1 alacak, -1 borc
    balance_after    INTEGER NOT NULL,
    idempotency_key  TEXT    NOT NULL,
    reference_type   TEXT    NULL,              -- number_order | payment_request | ...
    reference_id     INTEGER NULL,
    created_at       TEXT    NOT NULL
);

-- Cift kayit korumasinin tek dayanagi. Uygulama katmani degil, veritabani engeller.
CREATE UNIQUE INDEX IF NOT EXISTS ux_wallet_idempotency ON wallet_transactions (idempotency_key);
CREATE INDEX IF NOT EXISTS ix_wallet_user ON wallet_transactions (user_id, id);
CREATE INDEX IF NOT EXISTS ix_wallet_reference ON wallet_transactions (reference_type, reference_id);

CREATE TABLE IF NOT EXISTS users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    email          TEXT    NOT NULL,
    role           TEXT    NOT NULL DEFAULT 'customer',  -- admin | reseller | customer
    status         TEXT    NOT NULL DEFAULT 'active',
    balance_minor  INTEGER NOT NULL DEFAULT 0
);
