## Рекомендації з покращень та оптимізацій (Veldrin WooCommerce)

Нижче — пріоритезований список покращень для Docker-оточення, WordPress-конфігурації та теми `veldrin`. Акцент на продуктивність, безпеку, стабільність та розробницький DX.

**Стан проекту (поточний):**
- ✅ Docker середовище налаштовано (MySQL 5.7 + WordPress PHP 8.3 Apache)
- ✅ Тема Veldrin з підтримкою WooCommerce
- ✅ Gulp build система (Sass + esbuild + LiveReload)
- ✅ Оптимізація зображень (2GB uploads, покращено з 55K+ файлів)
- ⚠️ Hardcoded security keys у `wp-config.php` (критично для продакшену)
- ⚠️ Застарілий MySQL 5.7 (підтримка закінчилася у 2023)
- ⚠️ Відсутній окремий `docker-compose.prod.yml`

### Швидкі перемоги (1–2 години)
- **Секретні ключі та соли**: зараз є хардкод у `wp-config.php`. Винести всі значення у `.env` і прибрати дефолти. Згенерувати нові ключі (`curl -s https://api.wordpress.org/secret-key/1.1/salt/`).
- **Оновити MySQL**: `mysql:5.7` застарілий. Перейти на `mysql:8.0` або `mariadb:10.6+` (перевірити сумісність).
- **Логи**: `src/wp-content/debug.log` великий. Увімкнути ротацію логів (на рівні контейнера) і вимкнути логування у проді.

### Docker/Інфраструктура
- **Фіксовані версії образів**: замість `wordpress:latest` використовувати конкретну мажорну/мінорну (`wordpress:6.6-php8.2-fpm` + окремий `nginx:alpine`). Це зменшує «дрейф» середовища.
- **FPM + Nginx**: рознести PHP-FPM і Nginx у різні сервіси для кращого кешування (FastCGI), керування заголовками і TLS. Додати healthchecks.
- **Object cache (Redis)**: додати контейнер `redis:alpine` і підключити WordPress як persistent object cache (див. конфіг нижче).
- **WP Cron**: у проді вимкнути вбудований `WP_CRON` і виконувати cron-задачі системним планувальником (контейнер `cron` або cron хоста).
- **Оптимізація PHP**: додати свій `php.ini` (opcache, memory limits, upload/post size). Для Apache образу — через `PHP_INI_*` директиви або volume з кастомним ini.
- **Том для uploads**: якщо в проді не мапите весь `src/`, винести `wp-content/uploads` у окремий volume/бакет (або S3-сумісне сховище) для легшого масштабування.

### WordPress конфіг (`src/wp-config.php`)
- **ENV-орієнтована конфігурація**: додати/використати:
  - `WP_ENVIRONMENT_TYPE` = development/staging/production
  - `DISALLOW_FILE_EDIT` = true (прод)
  - `DISALLOW_FILE_MODS` = true (прод, якщо оновлення/встановлення робите через CI/CD)
  - `FORCE_SSL_ADMIN` = true
  - `WP_HOME`, `WP_SITEURL` — з `.env` для узгодженості URL
  - `WP_MEMORY_LIMIT=256M`, `WP_MAX_MEMORY_LIMIT=512M`
  - `WP_POST_REVISIONS=10`, `AUTOSAVE_INTERVAL=120`, `EMPTY_TRASH_DAYS=7`
  - `IMAGE_EDIT_OVERWRITE=true`, `BIG_IMAGE_SIZE_THRESHOLD=0` (за потреби)
- **Солі/ключі**: читати з ENV, не тримати дефолти у коді.

Приклад блоку для `wp-config.php`:
```php
// Environment type
define('WP_ENVIRONMENT_TYPE', getenv('WP_ENVIRONMENT_TYPE') ?: 'development');

// URLs
if (getenv('WP_HOME'))    define('WP_HOME', getenv('WP_HOME'));
if (getenv('WP_SITEURL')) define('WP_SITEURL', getenv('WP_SITEURL'));

// Debug
define('WP_DEBUG', filter_var(getenv('WORDPRESS_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_LOG', filter_var(getenv('WORDPRESS_DEBUG_LOG') ?: false, FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_DISPLAY', filter_var(getenv('WORDPRESS_DEBUG_DISPLAY') ?: false, FILTER_VALIDATE_BOOLEAN));

// Security & admin
define('DISALLOW_FILE_EDIT', filter_var(getenv('DISALLOW_FILE_EDIT') ?: false, FILTER_VALIDATE_BOOLEAN));
define('DISALLOW_FILE_MODS', filter_var(getenv('DISALLOW_FILE_MODS') ?: false, FILTER_VALIDATE_BOOLEAN));
define('FORCE_SSL_ADMIN', true);

// Limits
define('WP_MEMORY_LIMIT', getenv('WP_MEMORY_LIMIT') ?: '256M');
define('WP_MAX_MEMORY_LIMIT', getenv('WP_MAX_MEMORY_LIMIT') ?: '512M');
define('WP_POST_REVISIONS', intval(getenv('WP_POST_REVISIONS') ?: 10));
define('AUTOSAVE_INTERVAL', intval(getenv('AUTOSAVE_INTERVAL') ?: 120));
define('EMPTY_TRASH_DAYS', intval(getenv('EMPTY_TRASH_DAYS') ?: 7));

// Images
define('IMAGE_EDIT_OVERWRITE', true);
if (getenv('BIG_IMAGE_SIZE_THRESHOLD') === '0') {
  add_filter('big_image_size_threshold', '__return_zero');
}
```

### Кешування (Redis, сторінковий кеш, OPCache)
- **Object cache**: встановити плагін «Redis Object Cache» або «Object Cache Pro». Додати у `.env`:
```bash
WP_REDIS_HOST=redis
WP_REDIS_PORT=6379
WP_REDIS_MAXTTL=3600
```
Та увімкнути в адмінці.
- **Сторінковий кеш**: якщо переходите на Nginx+FPM — варто ввімкнути FastCGI cache (або CDN/Cloudflare cache) для гостьових сторінок/каталогу.
- **OPcache**: у `php.ini` (або `custom.ini`) встановити, наприклад:
```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

### Медіа та зображення
- **Обмеження розмірів**: додати фільтри `intermediate_image_sizes_advanced` для вимкнення непотрібних розмірів, `big_image_size_threshold`.
- **Компресія та WebP**: плагін для компресії (Imagify/ShortPixel/Smush) і автогенерацію WebP, якщо CDN/Nginx не робить перекодування.

### Тема `veldrin`
- **package.json**: Назва та опис вказують "uarchery" замість "veldrin" — варто оновити метадані проекту.
- **Скрипти/стилі**: зараз використовується `filemtime` для версіонування — це добре. Перевірити залежності та умовне підключення (на сторінках без потреби — не вантажити зайве).
- ~~**JavaScript**: В даний момент лише один рядок `console.log('Veldrin ultrin!')` у `js/index.js`~~ ✅ **ВИПРАВЛЕНО (2025-01-30)** - додано повну функціональність hamburger menu з keyboard accessibility
- **Sass структура**: Добре організована (base/components/helpers) але відсутні коментарі та документація у компонентах.

#### ✅ Нещодавні покращення безпеки та accessibility (2025-01-30)
1. **SVG Security Hardening**
   - Додано `wp_kses` sanitization для всіх SVG іконок в `functions.php`
   - Захист від XSS атак через скомпрометовані SVG файли
   - Whitelist безпечних SVG тегів та атрибутів

2. **Keyboard Accessibility**
   - Hamburger menu тепер `<button>` замість `<div>`
   - Повна клавіатурна підтримка: Enter, Space, Escape
   - Динамічне оновлення `aria-expanded` атрибуту
   - ARIA labels для screen readers

3. **Production Readiness**
   - Видалено `console.log()` з JavaScript
   - Рефакторинг коду для кращої структури (toggleMenu/closeMenu функції)

### WooCommerce
- **Крон і планувальник**: переконатися, що працює `Action Scheduler` (критично для WC). Використовувати системний cron у проді.
- **HPOS**: увімкнути High-Performance Order Storage (WC 7.1+), якщо плагіни сумісні.
- **Каталог і кеш**: застосувати сторінковий кеш для категорій/пошуку (гостьові), обережно з корзиною/кабінетом (виключення з кешу).
- **Медіа WC**: задати реальні розміри прев’ю, уникати створення надлишкових розмірів.

### Безпека
- **XML-RPC**: вимкнути або обмежити доступ (якщо не використовується інтеграціями).
- **Авторизація**: захист `wp-login.php` rate-limit’ом/Cloudflare Turnstile/reCAPTCHA.
- **Оновлення**: регулярні оновлення ядра/плагінів/тем. У проді краще через CI/CD, а не через адмінку (`DISALLOW_FILE_MODS=true`).
- **Резервні копії**: автоматичні бекапи БД і файлів, шифрування артефактів, перевірка відновлення.

### Моніторинг і спостережність
- **Логи**: ротація логів PHP/NGINX, окремі volume для логів, централізований збір (ELK/Vector+Loki/Grafana, за потреби).
- **Debug log**: В даний момент `debug.log` порожній (0 bytes) — добре для перформансу, але варто моніторити у проді.
- **Профілювання**: «Query Monitor» у dev/stage (не у проді), `New Relic`/`Blackfire` для глибокого профілювання.

### Додаткові рекомендації (виявлені під час аналізу)

#### Docker та інфраструктура
1. **Відсутній `.dockerignore`**: створити файл для виключення непотрібних файлів при білдах
2. **External network "proxy"**: Docker compose використовує зовнішню мережу `proxy` (ймовірно для Traefik) — документувати це у README
3. **Platform lock**: `platform: linux/x86_64` для MySQL може бути проблемою на ARM Mac — розглянути умовне визначення
4. **Container naming**: Контейнери мають різні імена (`veldrin` та `veldrin-db`) — варто узгодити паттерн

#### Безпека та конфігурація
1. **wp-config.php hardcoded keys**: КРИТИЧНО! Замінити усі дефолтні ключі на читання з ENV:
   ```php
   define('AUTH_KEY', getenv_docker('WORDPRESS_AUTH_KEY', ''));
   define('SECURE_AUTH_KEY', getenv_docker('WORDPRESS_SECURE_AUTH_KEY', ''));
   // і т.д. — БЕЗ fallback значень у проді
   ```
2. **WORDPRESS_CONFIG_EXTRA**: У `wp-config.php` є `eval($configExtra)` — потенційна вразливість якщо ENV заповнений зловмисником
3. **Missing security headers**: Додати у конфігурацію веб-сервера (X-Frame-Options, X-Content-Type-Options, CSP)

#### Тема Veldrin
1. **WooCommerce templates**: Немає кастомних overrides WooCommerce шаблонів у `veldrin/woocommerce/` — якщо потрібна кастомізація, створити
2. **Theme metadata**: `style.css` містить коректну інформацію про тему, але `package.json` має застарілі дані
3. ~~**Accessibility**: Є гарна робота з ARIA labels але варто провести повний аудит~~ ✅ **ПОКРАЩЕНО (2025-01-30)** - додано keyboard accessibility, hamburger тепер `<button>` з ARIA. Залишається: повний WCAG 2.1 AA аудит
4. ~~**Mobile menu**: Є `.hamburger` клас у header але немає відповідної JS логіки**~~ ✅ **ВИПРАВЛЕНО (2025-01-30)** - повна функціональність з keyboard support
5. **Favicon handling**: Перевірити чи існує `img/logo_600.png` або налаштувати через WordPress Site Icon
6. **Internationalization**: Частково реалізовано `__()` але не всі рядки обгорнуті — завершити i18n
7. ~~**SVG Security**: Немає sanitization для SVG іконок~~ ✅ **ВИПРАВЛЕНО (2025-01-30)** - додано wp_kses sanitization

#### Performance
1. **Assets загрузка**:
   - jQuery завантажується для `main.min.js` але не використовується у `index.js`
   - Розглянути видалення залежності від jQuery
2. **CSS sourcemaps**: Генеруються навіть для production build — додати умовне генерування
3. **Image lazy loading**: Немає нативного `loading="lazy"` у шаблонах
4. **No CDN configuration**: Розглянути інтеграцію з CDN для статичних ресурсів

### Прод-крон (приклад)
```bash
*/5 * * * * docker compose exec -T wordpress wp cron event run --due-now > /dev/null 2>&1
```

---

Якщо потрібно — можу одразу підготувати PR з правками `docker-compose`, `wp-config.php`, темою `veldrin` (окремі коміти під кожен блок), або налаштувати Redis/Nginx-конфіг і WP-CLI команди.


