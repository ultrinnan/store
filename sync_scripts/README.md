# Product Import System

Система для імпорту товарів з дилерських та рітейл джерел у WooCommerce магазин.

## Структура проекту

```
sync_scripts/
├── 01_fetch_products.sh          # Stage 1: Завантаження даних
├── 02_analyze_products.sh        # Stage 2: Аналіз даних
├── 03_prepare_categories_attributes.php  # Stage 3: Створення категорій та брендів
├── 04_split_products.php         # Stage 4: Розділення товарів на прості та змінні
├── 05_import_simple_products.php # Stage 5: Імпорт простих товарів
├── 05_import_simple_products_test.php # Тестовий скрипт для Stage 5
├── main_import.sh                # Основний скрипт для запуску всіх етапів
└── README.md                     # Цей файл
```

## Етапи імпорту

### ✅ Stage 1: Завантаження даних (`01_fetch_products.sh`)
- Завантажує повні списки товарів з дилерського та рітейл сайтів
- Обробляє пагінацію
- Зберігає дані як `dealers_products_{date}.json` та `retail_products_{date}.json`

### ✅ Stage 2: Аналіз даних (`02_analyze_products.sh`)
- Аналізує завантажені дані
- Показує всі теги, вендори, атрибути
- Створює звіти аналізу

### ✅ Stage 3: Створення категорій та брендів (`03_prepare_categories_attributes.php`)
- Створює категорії з тегів товарів (повні теги без розділення)
- Створює бренди з вендорів
- Зберігає мапінг у `categories_created.json`, `brands_created.json`

### ✅ Stage 4: Розділення товарів (`04_split_products.php`)
- Аналізує складність товарів
- Розділяє на прості та змінні товари
- Зберігає у `simple_products_{date}.json` та `variable_products_{date}.json`

### ✅ Stage 5: Імпорт простих товарів (`05_import_simple_products.php`)
- Імпортує прості товари в WooCommerce
- Правильна логіка цін: рітейл ціна - 10% (з конвертацією USD→EUR)
- Встановлює SKU, категорії, бренди, зображення
- Оновлює існуючі товари

### 🔄 Stage 6: Імпорт змінних товарів (в розробці)
- Імпорт товарів з варіаціями
- Створення атрибутів та варіацій
- Планується

## Логіка цін

Система використовує наступну логіку для розрахунку цін:

1. **Дилерська ціна** (в євро) - закупівельна ціна
2. **Рітейл ціна** (в доларах) - ціна в інших магазинах
3. **Наша ціна** = рітейл ціна в євро - 10% (конкурентна ціна)
4. **Якщо рітейл ціни немає** = дилерська ціна + 30% markup

Конвертація валют: 1 USD = 0.92 EUR

## Використання

### Запуск повного імпорту:
```bash
cd sync_scripts
./main_import.sh
```

### Запуск окремих етапів:
```bash
# Stage 1: Завантаження даних
./01_fetch_products.sh

# Stage 2: Аналіз
./02_analyze_products.sh

# Stage 3: Категорії та бренди
docker compose exec wordpress php /var/www/html/sync_scripts/03_prepare_categories_attributes.php

# Stage 4: Розділення товарів
docker compose exec wordpress php /var/www/html/sync_scripts/04_split_products.php

# Stage 5: Імпорт простих товарів
docker compose exec wordpress php /var/www/html/sync_scripts/05_import_simple_products.php

# Тестування Stage 5
docker compose exec wordpress php /var/www/html/sync_scripts/05_import_simple_products_test.php
```

## Файли даних

### Основні файли (в `tmp/`):
- `dealers_products_latest.json` - актуальні дилерські товари
- `retail_products_latest.json` - актуальні рітейл товари
- `categories_created.json` - мапінг категорій
- `brands_created.json` - мапінг брендів
- `simple_products_20250727_130333.json` - прості товари для імпорту
- `variable_products_20250727_130333.json` - змінні товари для імпорту

### Результати імпорту:
- `simple_products_import_results_20250727_143707.json` - детальні результати
- `simple_products_import_summary_20250727_143707.json` - підсумок

## Статус проекту

- ✅ **Stage 1-5 завершені** - повний імпорт простих товарів працює
- 🔄 **Stage 6 в розробці** - імпорт змінних товарів
- 📊 **756 простих товарів** успішно імпортовано
- 💰 **Правильна логіка цін** з конвертацією валют
- 🏷️ **Категорії та бренди** правильно встановлюються

## Технічні деталі

- **WordPress + WooCommerce** для зберігання товарів
- **PHP** для інтеграції з WordPress API
- **Bash** для завантаження та аналізу даних
- **Docker** для локальної розробки
- **JSON** для зберігання проміжних даних 