## Аналіз теми Veldrin (класична, самостійна)

Оновлено: 2025‑10‑14

### Загальна картина
- **Тип теми**: класична самостійна (батьківська залежність прибрано).
- **Підхід**: класичні шаблони (`index.php`, `single.php`, `page.php`, `404.php`, `search.php`, `archive.php`, `author.php`), класичні частини (`header.php`, `footer.php`, `sidebar.php`).

### Що знайдено (основні файли та поведінка)
- `style.css` — прибрано `Template: twentytwentyfour`.
- `functions.php`:
  - Підключає стилі/скрипти з версіонуванням через `filemtime` (`css/main.min.css`, `js/main.min.js`).
  - У режимі `WP_DEBUG` підключає LiveReload скрипт (порт 35729).
  - `add_theme_support('menus')`, підтримка **WooCommerce** і галереї продуктів.
  - Реєструє меню: `header`, `footer`, `footer_shop`, `bottom` (зараз використовуються у класичних `header.php`/`footer.php`).
  - Фільтр `wp_get_attachment_image_src` — «fallback» на оригінал, якщо немає потрібного розміру (зменшує 404 по зображеннях).
- `header.php` (класичний): додано `body_class()`, `wp_body_open()`, прибрано ручний `<title>`, додано семантику `role="banner"` і `<nav aria-label>`.
- `footer.php` (класичний): семантика `role="contentinfo"`, меню обгорнуті у `<nav aria-label>`; є індикатор debug.
- `articles.php` — класичний шаблон сторінки, викликає `get_header()/get_footer()` з теми, тому відповідні класичні частини застосовуються тільки на сторінках із цим шаблоном.
- `patterns/footer.php` — один block pattern футера, доступний у редакторі.
- `admin/` — кастомізації адмінки (футер, прихований логін‑логотип, віджет), сторінки налаштувань теми (зокрема соцмережі), підключення адмін‑CSS.
- `admin/security_hooks.php` — вимкнення self‑ping, приховування генератора версії.
- Збірка активів: `gulpfile.js` (Sass Embedded + autoprefixer + clean‑css; esbuild для JS; livereload). `package.json` містить `build`, `watch`, `dev`.

### Примітки щодо архітектури
- Тема працює як класична і не залежить від блокової батьківської.

### Ризики та зауваження
- Адмін‑форми (соцмережі, general) — ЗАХИЩЕНО: додано перевірку прав `manage_options`, nonce, санітизацію/ескейпінг.
- SEO/Title — ЗРОБЛЕНО: додано `add_theme_support('title-tag')`, ручний `<title>` прибрано.
- Розмітка `<body>` — ЗРОБЛЕНО: додано `body_class()` і `wp_body_open()`.
- I18n — ВИРІВНЯНО: text domain `Veldrin` у меню.
- Фавікон — перевірити наявність файлу `img/favicon.png` або замінити шлях на актуальний (SVG чи ICO).

### Рекомендації (пріоритет)
1) Перевірити фавікон: додати файл або оновити шлях у `header.php`.
2) Додати окремі шаблони за потреби: `category.php`, `tag.php`, `taxonomy.php`, `date.php`.
3) Додати базові стилі для `comments.php` і `sidebar.php` у Sass.
4) i18n: по можливості обгорнути всі рядки у `__()`/`_e()` з `Veldrin`.
5) Активи/збірка: перевірити `pnpm run dev` у локальному середовищі.

### План кроків (чекліст)
- [x] Прибрати залежність від батьківської теми.
- [x] Додати `add_theme_support('title-tag')` і прибрати ручний `<title>`.
- [x] Додати `body_class()` і `wp_body_open()` у `header.php`.
- [x] Привести text domain меню до `Veldrin`.
- [x] Захистити адмін‑форми (nonce, права, санітизація/ескейпінг).
- [x] Ескейпінг значень у фронт‑виводі соцмереж.
- [x] Додати класичні шаблони: `index`, `single`, `page`, `404`, `search`, `archive`, `author`.
- [x] Додати `comments.php` і `sidebar.php`, зареєструвати сайдбар.
- [ ] Перевірити/додати `favicon` файл або оновити шлях у `header.php`.
- [ ] Додати стилі для коментарів/сайдбара у `sass/`.

### Швидкі код‑фрагменти (для старту)
1) Додати підтримку заголовка сторінки (в `functions.php`):
```php
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
});
```

2) Оновити `header.php` (фрагмент тіла):
```php
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
```

3) Ескейпінг у фронт‑виводі соцмереж (`partials/social.php`, приклад):
```php
<?php
if ($social) {
  echo '<div class="social-box">';
  foreach ($social as $key => $value) {
    if ($value) {
      $class = 'social ' . sanitize_html_class($key);
      $url   = esc_url($value);
      $title = esc_attr($key);
      echo '<a class="' . $class . '" href="' . $url . '" target="_blank" title="' . $title . '"></a>';
    }
  }
  echo '</div>';
}
```

4) Перевірка прав + nonce + санітизація у формі (`partials/admin/social.php`, концептуально):
```php
if ($_POST && current_user_can('manage_options')) {
    check_admin_referer('veldrin_social_settings');

    $social_options = [];
    $fields = ['facebook','twitter','instagram','youtube'];
    foreach ($fields as $field) {
        $social_options[$field] = isset($_POST[$field]) ? esc_url_raw($_POST[$field]) : '';
    }

    update_option('social_options', $social_options);
}
```

---

Примітка: якщо стратегічна ціль — «все через блоки», варто сфокусуватись на `theme.json` + `parts/*.html` + `templates/*.html`. Класичні `header.php`/`footer.php` залишити лише як тимчасовий перехідний етап або під специфічні шаблони.


