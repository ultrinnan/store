- `functions.php`:
  - Підключає стилі/скрипти з версіонуванням через `filemtime` (`css/main.min.css`, `js/main.min.js`).
  - У режимі `WP_DEBUG` підключає LiveReload скрипт (порт 35729).
  - `add_theme_support('menus')`, підтримка **WooCommerce** і галереї продуктів.
  - Реєструє меню: `header`, `footer`, `footer_shop`, `bottom` (зараз використовуються у класичних `header.php`/`footer.php`).
  - Фільтр `wp_get_attachment_image_src` — «fallback» на оригінал, якщо немає потрібного розміру (зменшує 404 по зображеннях).
- `footer.php` (класичний): семантика `role="contentinfo"`, меню обгорнуті у `<nav aria-label>`; є індикатор debug.
- `articles.php` — класичний шаблон сторінки, викликає `get_header()/get_footer()` з теми, тому відповідні класичні частини застосовуються тільки на сторінках із цим шаблоном.
- `patterns/footer.php` — один block pattern футера, доступний у редакторі.
- `admin/` — кастомізації адмінки (футер, прихований логін‑логотип, віджет), сторінки налаштувань теми (зокрема соцмережі), підключення адмін‑CSS.
- `admin/security_hooks.php` — вимкнення self‑ping, приховування генератора версії.
- Збірка активів: `gulpfile.js` (Sass Embedded + autoprefixer + clean‑css; esbuild для JS; livereload). `package.json` містить `build`, `watch`, `dev`.

### Рекомендації (пріоритет)

2. Додати окремі шаблони за потреби: `category.php`, `tag.php`, `taxonomy.php`, `date.php`.
3. Додати базові стилі для `comments.php` і `sidebar.php` у Sass.
4. i18n: по можливості обгорнути всі рядки у `__()`/`_e()` з `Veldrin`.

### План кроків (чекліст)

- [x] Ескейпінг значень у фронт‑виводі соцмереж.
- [x] Додати класичні шаблони: `index`, `single`, `page`, `404`, `search`, `archive`, `author`.
- [x] Додати `comments.php` і `sidebar.php`, зареєструвати сайдбар.
- [ ] Перевірити/додати `favicon` файл або оновити шлях у `header.php`.
- [ ] Додати стилі для коментарів/сайдбара у `sass/`.
