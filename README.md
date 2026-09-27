# ⭐ Stars & Premium sotish boti (PHP) — Hamyon (balans) tizimi

Foydalanuvchi hisobini karta orqali (qo'lda tasdiqlash) yoki Click/Payme orqali (avtomatik,
YaTT ochilgach yoqiladi) to'ldiradi, so'ng Stars/Premium'ni balansdan xarid qiladi.
Admin panel orqali narxlar, kanallar, to'lov so'rovlari va xabar yuborish boshqariladi.

## 📁 Tuzilma

```
stars_premium_bot/
├── config.php                ← bot token, DB, admin panel login/parol, Click/Payme kalitlari
├── webhook.php                 ← Telegram bot asosiy logikasi
├── set_webhook.php             ← webhookni ulash (bir marta ishlatiladi)
├── db/schema.sql               ← MySQL jadval tuzilmasi
├── includes/                   ← PHP logika fayllari (db, telegram, settings, channels, helpers, reseller_api)
├── payments/
│   ├── click.php                 ← Click.uz webhook (hisobni to'ldirish)
│   └── payme.php                 ← Payme.uz webhook (hisobni to'ldirish)
├── admin/
│   ├── login.php, logout.php       ← kirish (config.php dagi login/parol bilan)
│   ├── index.php                   ← statistika (dashboard)
│   ├── orders.php                  ← buyurtmalar + "Bajarildi" (qo'lda tasdiqlash)
│   ├── topups.php                  ← karta orqali to'lov so'rovlari (chek rasmi + tasdiqlash)
│   ├── users.php                   ← foydalanuvchilar + balansni qo'lda tuzatish
│   ├── settings.php                ← narxlar, to'lov usullari, karta raqami
│   ├── channels.php                ← majburiy obuna kanallari
│   └── broadcast.php               ← barcha foydalanuvchilarga xabar yuborish
└── logs/                        ← xatolik va to'lov loglari
```

## 🚀 O'rnatish — 5 ta oddiy qadam

### 1. Fayllarni serverga yuklash
`stars_premium_bot.zip`ni oching, ichidagi hammasini hosting root papkasiga (myxvest.ru
boshqaruv panelidagi fayl menejeri orqali) yuklang. PHP 7.4+ va MySQL kerak.

### 2. Bazani yaratish
Hosting panelida yangi MySQL baza oching, so'ng `db/schema.sql` faylini shu bazaga import qiling
(hosting panelidagi "phpMyAdmin" yoki "Import" tugmasi orqali — fayl yuklab, "Bajarish"ni bosasiz).

### 3. `config.php`ni to'ldirish
Fayl menejeridan `config.php`ni oching va faqat quyidagilarni o'zingiznikiga almashtiring:
- `BOT_TOKEN` — @BotFather'dan olgan bot tokeningiz
- `ADMIN_CHAT_IDS` — sizning Telegram ID raqamingiz (@userinfobot'ga yozib bilib oling)
- `ADMIN_PANEL_USERNAME`, `ADMIN_PANEL_PASSWORD` — admin panelga kirish uchun o'zingiz
  xohlagan login va kuchli parol (boshqa hech qanday qo'shimcha amal shart emas!)
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — 2-qadamda yaratgan baza ma'lumotlari
- `SITE_URL` — domeningiz (https bilan, oxirida `/` bo'lmasin)

Boshqa maydonlarga (`CLICK_*`, `PAYME_*`, `RESELLER_*`) hozircha tegmang — ular keyinroq kerak bo'ladi.

### 4. Webhookni ulash
Brauzerda bitta marta oching: `https://domeningiz.uz/set_webhook.php`
`{"ok":true,...}` chiqsa tayyor — botga `/start` yozib sinab ko'rishingiz mumkin.

### 5. Admin panelga kirish
Endi hech narsa yaratish/o'chirish shart emas — to'g'ridan-to'g'ri kiraverasiz:
`https://domeningiz.uz/admin/login.php`
Login va parol — 3-qadamda `config.php`ga o'zingiz yozgan qiymatlar.

Yoki botga `/panel` deb yozing — bot sizga admin panelni ochadigan tugma yuboradi
(Telegram ichida ochiladi, brauzerga chiqish shart emas).

## ⚙️ Ishga tushirgandan keyin — birinchi sozlashlar

Admin panel → **Narxlar** bo'limiga kiring:
1. Stars va Premium narxlarini tekshiring/o'zgartiring
2. "Karta orqali qo'lda" katagini belgilangan qoldiring (hozircha yagona ishlaydigan usul)
3. Karta raqamingiz va F.I.Sh.ni kiriting — bular foydalanuvchiga "Hisob to'ldirish"da ko'rsatiladi
4. Saqlang — bot darhol shu ma'lumotlar bilan ishlay boshlaydi

Shu bilan bot **to'liq ishga tushadi**: foydalanuvchi hisobini to'ldiradi (karta orqali, siz
admin panelda yoki botning o'zida "✅ Tasdiqlash" tugmasi bilan tasdiqlaysiz), keyin
Stars/Premium xarid qiladi.

## 🔌 Keyinroq: Click/Payme va hamkor API ulash

- **YaTT ochib, Click/Payme merchant oldingizdan so'ng:** `config.php`da `CLICK_*`/`PAYME_*`
  kalitlarini kiriting, Click/Payme kabinetida webhook manzillarini ko'rsating
  (`/payments/click.php`, `/payments/payme.php`), so'ng admin panel → Narxlar'da
  tegishli katakchalarni belgilang — hisob to'ldirish avtomatlashadi.
- **Stars/Premium'ni avtomatik yuborish uchun:** `config.php`dagi `RESELLER_API_KEY`ga
  hamkor API kalitini kiriting. API manzili hujjatdagi v2 endpointiga sozlangan; xaridlar
  `X-API-Key` va takroriy xaridni cheklovchi idempotency kaliti bilan yuboriladi. Narxlar
  admin panelda boshqariladi. Hamkor javobi noaniq bo'lsa, buyurtma admin tekshiruvi uchun
  kutilmoqda holatida qoladi. Hujjatdagi sovg'a va raqam xizmatlari bot menyusiga hozircha ulanmagan.

## 🖥 Admin panel bo'limlari

| Bo'lim | Nima qiladi |
|---|---|
| 📊 Statistika | Foydalanuvchilar, buyurtmalar, tushum, 7 kunlik jadval |
| 📦 Buyurtmalar | Xaridlar ro'yxati + "✅ Bajarildi" (Stars/Premium qo'lda yuborilganda) |
| 💳 To'lov so'rovlari | Karta orqali yuborilgan chek rasmlari + Tasdiqlash/Rad etish |
| 👥 Foydalanuvchilar | Qidiruv, balansni qo'lda tuzatish |
| ⚙️ Narxlar | Stars/Premium narxlari, to'lov usullari, karta raqami |
| 📢 Kanallar | Majburiy obuna kanallarini boshqarish |
| 📣 Xabar yuborish | Barcha foydalanuvchilarga bir vaqtda xabar |

## ⚠️ Xavfsizlik eslatmalari
- `config.php`ni ochiq joyga (masalan GitHub'ga) yuklamang — u login/parol va tokenlarni saqlaydi.
- Admin panel parolini kuchli qiling (harflar + raqam + belgi, kamida 10 ta belgi).
- `set_webhook.php`ni ishlatgach o'chirsangiz ham bo'ladi (majburiy emas, lekin toza turadi).
