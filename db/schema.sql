-- Stars & Premium sotish boti uchun MySQL sxema (v2 — hamyon tizimi)
-- Import: mysql -u USER -p DB_NAME < schema.sql

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNSIGNED NOT NULL UNIQUE,
    username VARCHAR(64) DEFAULT NULL,
    first_name VARCHAR(128) DEFAULT NULL,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    referred_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Foydalanuvchi bot bilan muloqot bosqichini saqlash uchun
CREATE TABLE IF NOT EXISTS user_states (
    telegram_id BIGINT UNSIGNED PRIMARY KEY,
    state VARCHAR(64) NOT NULL DEFAULT 'idle',
    data TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tahrirlanadigan narxlar va umumiy sozlamalar (admin panel orqali o'zgartiriladi)
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('price_per_star', '195'),
    ('premium_3_price', '210000'),
    ('premium_6_price', '340000'),
    ('premium_12_price', '520000'),
    ('bot_active', '1'),
    ('method_card_enabled', '1'),
    ('method_click_enabled', '0'),
    ('method_payme_enabled', '0'),
    ('card_number', '0000 0000 0000 0000'),
    ('card_holder', 'F.I.Sh.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Majburiy obuna kanallari
CREATE TABLE IF NOT EXISTS required_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(64) NOT NULL,        -- @channel_username yoki -100xxxxxxxxxx
    title VARCHAR(128) DEFAULT NULL,
    invite_link VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hisobni to'ldirish (topup) tranzaksiyalari — Click/Payme (avtomatik) yoki Karta (qo'lda tasdiqlanadi)
CREATE TABLE IF NOT EXISTS topups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    method ENUM('click', 'payme', 'card') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    external_transaction_id VARCHAR(128) DEFAULT NULL,
    proof_file_id VARCHAR(255) DEFAULT NULL,   -- 'card' usulida foydalanuvchi yuborgan chek skrinshoti (Telegram file_id)
    status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stars / Premium xaridlari (balansdan bir zumda yechiladi)
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('stars', 'premium') NOT NULL,
    target_username VARCHAR(64) NOT NULL,
    amount INT NOT NULL,                 -- stars soni YOKI premium oy soni
    price DECIMAL(14,2) NOT NULL,
    status ENUM('completed', 'awaiting_manual', 'failed') NOT NULL DEFAULT 'awaiting_manual',
    reseller_order_id VARCHAR(128) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS broadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    sent_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
