<?php
require_once __DIR__ . '/db.php';

function get_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function price_per_star(): float
{
    return (float) get_setting('price_per_star', 195);
}

function premium_prices(): array
{
    return [
        3  => (float) get_setting('premium_3_price', 210000),
        6  => (float) get_setting('premium_6_price', 340000),
        12 => (float) get_setting('premium_12_price', 520000),
    ];
}

function is_method_enabled(string $method): bool
{
    return get_setting("method_{$method}_enabled", '0') === '1';
}

function enabled_topup_methods(): array
{
    $methods = [];
    if (is_method_enabled('card')) $methods[] = 'card';
    if (is_method_enabled('click')) $methods[] = 'click';
    if (is_method_enabled('payme')) $methods[] = 'payme';
    return $methods;
}

function card_number(): string
{
    return get_setting('card_number', '0000 0000 0000 0000');
}

function card_holder(): string
{
    return get_setting('card_holder', "F.I.Sh.");
}

function format_sum(float $sum): string
{
    return number_format($sum, 0, '.', ' ') . " so'm";
}
