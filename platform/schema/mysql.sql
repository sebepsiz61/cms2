-- Uretim semasi. cPanel > phpMyAdmin uzerinden ya da bin/install.php ile calistirilir.
-- Tum para alanlari tam sayi kurustur; DECIMAL kullanilmaz.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120)    NOT NULL,
    email          VARCHAR(190)    NOT NULL,
    password_hash  VARCHAR(255)    NOT NULL,
    role           ENUM('admin','reseller','customer') NOT NULL DEFAULT 'customer',
    status         ENUM('active','suspended')          NOT NULL DEFAULT 'active',
    balance_minor  BIGINT          NOT NULL DEFAULT 0,
    api_token      CHAR(64)        NULL,
    created_at     DATETIME        NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_users_email (email),
    UNIQUE KEY ux_users_api_token (api_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cuzdan defteri. Bakiye bu tablonun toplamidir; users.balance_minor onbellektir.
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            VARCHAR(20)     NOT NULL,
    amount_minor    BIGINT          NOT NULL,
    direction       TINYINT         NOT NULL,
    balance_after   BIGINT          NOT NULL,
    idempotency_key VARCHAR(191)    NOT NULL,
    reference_type  VARCHAR(40)     NULL,
    reference_id    BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_wallet_idempotency (idempotency_key),
    KEY ix_wallet_user (user_id, id),
    KEY ix_wallet_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS countries (
    code       VARCHAR(8)   NOT NULL,
    name       VARCHAR(80)  NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
    code       VARCHAR(40)  NOT NULL,
    name       VARCHAR(80)  NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Saglayicinin kendi ulke/servis kodlari ile bizim kanonik kodlarimizin eslesmesi.
CREATE TABLE IF NOT EXISTS provider_codes (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider       VARCHAR(40)  NOT NULL,
    kind           ENUM('country','service') NOT NULL,
    provider_code  VARCHAR(80)  NOT NULL,
    canonical_code VARCHAR(40)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_provider_codes (provider, kind, provider_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eslesmeyen kodlar atilmaz; yonetici panelinde elle eslenmek uzere birikir.
CREATE TABLE IF NOT EXISTS unmapped_codes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider      VARCHAR(40) NOT NULL,
    kind          VARCHAR(10) NOT NULL,
    provider_code VARCHAR(80) NOT NULL,
    seen_at       DATETIME    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_unmapped (provider, kind, provider_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Katalog senkronunun yazdigi anlik maliyet ve stok.
CREATE TABLE IF NOT EXISTS provider_offers (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider      VARCHAR(40) NOT NULL,
    country_code  VARCHAR(8)  NOT NULL,
    service_code  VARCHAR(40) NOT NULL,
    operator      VARCHAR(40) NULL,
    cost_minor    BIGINT      NOT NULL,
    currency      VARCHAR(8)  NOT NULL,
    stock         INT         NOT NULL DEFAULT 0,
    provider_country VARCHAR(80) NOT NULL,
    provider_service VARCHAR(80) NOT NULL,
    synced_at     DATETIME    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_offer (provider, country_code, service_code, operator),
    KEY ix_offer_lookup (country_code, service_code, stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ulke/servis bazli marj istisnalari. Bos ise config'teki genel marj gecerlidir.
CREATE TABLE IF NOT EXISTS pricing_rules (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_code   VARCHAR(8)  NULL,
    service_code   VARCHAR(40) NULL,
    margin_percent INT         NULL,
    fixed_price_minor BIGINT   NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_pricing_scope (country_code, service_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS number_orders (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           BIGINT UNSIGNED NOT NULL,
    type              ENUM('activation','rental') NOT NULL DEFAULT 'activation',
    provider          VARCHAR(40)  NOT NULL,
    provider_order_id VARCHAR(80)  NOT NULL,
    country_code      VARCHAR(8)   NOT NULL,
    service_code      VARCHAR(40)  NOT NULL,
    phone             VARCHAR(32)  NOT NULL,
    -- Maliyet ve satis fiyati siparis aninda dondurulur; katalog degisse de degismez.
    cost_minor        BIGINT       NOT NULL,
    cost_currency     VARCHAR(8)   NOT NULL,
    price_minor       BIGINT       NOT NULL,
    status            VARCHAR(20)  NOT NULL,
    code              VARCHAR(20)  NULL,
    purchased_at      DATETIME     NOT NULL,
    expires_at        DATETIME     NOT NULL,
    completed_at      DATETIME     NULL,
    last_polled_at    DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_provider_order (provider, provider_order_id),
    KEY ix_orders_user (user_id, id),
    KEY ix_orders_open (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_messages (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    BIGINT UNSIGNED NOT NULL,
    sender      VARCHAR(80)  NOT NULL,
    body        TEXT         NOT NULL,
    code        VARCHAR(20)  NULL,
    received_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_messages_order (order_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_requests (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        BIGINT UNSIGNED NOT NULL,
    amount_minor   BIGINT       NOT NULL,
    reference_code VARCHAR(20)  NOT NULL,
    receipt_path   VARCHAR(255) NULL,
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by    BIGINT UNSIGNED NULL,
    admin_note     VARCHAR(255) NULL,
    created_at     DATETIME     NOT NULL,
    resolved_at    DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_payment_reference (reference_code),
    KEY ix_payment_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NULL,
    action     VARCHAR(60)  NOT NULL,
    detail     TEXT         NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_log_action (action, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
