## Рекомендації з покращень та оптимізацій (Veldrin WooCommerce)

Нижче — пріоритезований список покращень для Docker-оточення, WordPress-конфігурації та теми `veldrin`. Акцент на продуктивність, безпеку, стабільність та розробницький DX.

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

Приклад уривка сервісів (скорочено):
```yaml
services:
  wordpress:
    image: wordpress:6.6-php8.2-fpm
    env_file: .env
    volumes:
      - ./src:/var/www/html
      - ./docker/php/conf.d/custom.ini:/usr/local/etc/php/conf.d/custom.ini:ro
    depends_on:
      - db
      - redis
    healthcheck:
      test: ["CMD", "php", "-v"]
      interval: 30s
      timeout: 5s
      retries: 5

  nginx:
    image: nginx:alpine
    volumes:
      - ./src:/var/www/html:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
    ports:
      - "8000:80"
    depends_on:
      - wordpress

  db:
    image: mysql:8.0
    env_file: .env
    command: ["--default-authentication-plugin=mysql_native_password"]
    volumes:
      - store_db_data:/var/lib/mysql

  redis:
    image: redis:alpine
    command: ["redis-server", "--maxmemory", "256mb", "--maxmemory-policy", "allkeys-lru"]

volumes:
  store_db_data:
```

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
- **Замість фронтової регенерації**: функція `auto_regenerate_missing_sizes()` зараз виконується на `init` (навіть у фронті для адмінів). Рекомендується перенести масову регенерацію у WP-CLI/адмін-сторінку/окремий action, щоб не створювати навантаження під час перегляду сайту.
- **Обмеження розмірів**: додати фільтри `intermediate_image_sizes_advanced` для вимкнення непотрібних розмірів, `big_image_size_threshold`.
- **Компресія та WebP**: плагін для компресії (Imagify/ShortPixel/Smush) і автогенерацію WebP, якщо CDN/Nginx не робить перекодування.
- **Offload (опційно)**: винести медіа у S3-сумісне сховище для масштабування.

### Тема `veldrin`
- **Child theme і батьківські стилі**: у `style.css` зазначено `Template: twentytwentyfour` (блок-тема). Перевірити, чи потрібне підключення стилів батьківської теми (для блокових тем зазвичай керує `theme.json`). Якщо планується «класична» ієрархія — додати підключення parent-style.
- **Theme supports**: додати `title-tag`, `html5`, `post-thumbnails`, `custom-logo` за потреби. WooCommerce підтримка вже є.
- **Скрипти/стилі**: зараз використовується `filemtime` для версіонування — це добре. Перевірити залежності та умовне підключення (на сторінках без потреби — не вантажити зайве).
- **Адмін-посилання і безпека**: у `admin_regeneration_notice()` екранізувати URL через `esc_url()`, додати перевірку nonce для адмін-дій.
- **Резервні файли**: видалити `functions.php.backup`.
- **assets-пайплайн**: у темі є `gulpfile.js`, `package.json` і `pnpm-lock.yaml`. Уніфікувати інструмент (npm/yarn/pnpm), додати чіткі скрипти (`build`, `dev`, `watch`).

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
- **Профілювання**: «Query Monitor» у dev/stage (не у проді), `New Relic`/`Blackfire` для глибокого профілювання.

### Документація та відповідність репозиторію
- **README vs реальність**: у README згадані `docker-compose.dev.yml` та `docker-compose.prod.yml`, їх нема в репозиторії. Або додайте ці файли, або оновіть README для актуальності.
- **.env.example**: додати приклад `.env` з усіма потрібними ключами.
- **.gitignore**: гарантувати ігнор `src/wp-content/debug.log`, бекапів, артефактів збірки.

### Приклад `.env` (скорочено)
```bash
# DB
MYSQL_ROOT_PASSWORD=change_me
MYSQL_DATABASE=veldrin
MYSQL_USER=veldrin
MYSQL_PASSWORD=change_me

# WP
WORDPRESS_DB_HOST=db
WORDPRESS_DB_USER=veldrin
WORDPRESS_DB_PASSWORD=change_me
WORDPRESS_DB_NAME=veldrin

WP_ENVIRONMENT_TYPE=production
WORDPRESS_DEBUG=false
WORDPRESS_DEBUG_LOG=false
WORDPRESS_DEBUG_DISPLAY=false

WP_HOME=https://example.com
WP_SITEURL=https://example.com
WP_MEMORY_LIMIT=256M
WP_MAX_MEMORY_LIMIT=512M
WP_POST_REVISIONS=10
AUTOSAVE_INTERVAL=120
EMPTY_TRASH_DAYS=7

# Redis
WP_REDIS_HOST=redis
WP_REDIS_PORT=6379

# Keys & salts (обов’язково перегенерувати!)
WORDPRESS_AUTH_KEY=...
WORDPRESS_SECURE_AUTH_KEY=...
# ... інші ключі
```

### Прод-крон (приклад)
```bash
*/5 * * * * docker compose exec -T wordpress wp cron event run --due-now > /dev/null 2>&1
```

---

Якщо потрібно — можу одразу підготувати PR з правками `docker-compose`, `wp-config.php`, темою `veldrin` (окремі коміти під кожен блок), або налаштувати Redis/Nginx-конфіг і WP-CLI команди.


