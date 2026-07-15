# YOleotard Checkout

WordPress-плагин для сайта [yoleotard.com](https://yoleotard.com), который добавляет полноценный checkout для продажи готовых купальников и платьев YOleotard напрямую с сайта.

Текущая версия плагина: **4.0.75**.

## Что делает плагин

Плагин превращает карточки товаров YOOtheme в рабочий процесс покупки:

- добавляет кнопки покупки и корзину на карточки товаров;
- сохраняет товары в локальной корзине покупателя;
- резервирует выбранные товары на время оформления;
- показывает скидки товара и промокодные скидки;
- рассчитывает доставку;
- создает локальный заказ в WordPress;
- создает или обновляет заказ в KeyCRM;
- запускает оплату картой через Monobank или Western Bid;
- создает банковский invoice для SEPA/SWIFT оплаты;
- отправляет покупателю email с подтверждением или invoice;
- после успешной оплаты переводит checkout на Step 4;
- скрывает проданные товары на сайте;
- помечает товары с успешным bank invoice как зарезервированные через стиль YOOtheme `Card Default`;
- ведет админские отчеты и диагностические записи.

## Основные сценарии покупки

### Оплата картой

Покупатель добавляет товар в корзину, вводит контактные данные, выбирает доставку и оплачивает картой. В зависимости от настроек и страны покупателя плагин использует Monobank или Western Bid.

После подтверждения оплаты плагин:

- проверяет статус платежа;
- финализирует заказ;
- создает или обновляет заказ в KeyCRM;
- отправляет email покупателю;
- скрывает купленный товар;
- показывает экран успешной покупки с номером заказа.

### Банковский invoice

Покупатель может выбрать оплату банковским переводом. Плагин создает invoice, сохраняет его в HTML/PDF формате, отправляет копию на email и показывает покупателю ссылку на invoice.

Банковский invoice не включает комиссию карточного платежа.

## Интеграции

### Monobank

- поддержка live/test токенов;
- сохранение режима токена на уровне заказа;
- создание invoice для карточной оплаты;
- проверка статуса invoice;
- проверка подписи webhook `X-Sign`;
- сверка суммы и валюты, если эти данные пришли от провайдера.

### Western Bid

- поддержка Stripe и PayPal через Western Bid;
- генерация платежной формы;
- открытие платежной страницы в отдельном окне;
- проверка hash notify/webhook;
- обработка особенностей Western Bid invoice ID и суммы;
- ожидание webhook/final status перед Step 4.

### KeyCRM

- создание покупателя;
- создание и обновление заказа;
- синхронизация товаров в заказе;
- запись оплат;
- комментарии к заказам;
- защита от дублей при повторных запросах.

### Email и invoices

- HTML email для банковского invoice;
- HTML email после успешной карточной оплаты;
- invoice HTML/PDF файлы;
- единый вывод товаров, скидок, доставки и итогов.

## Админка

Плагин добавляет отдельное верхнее меню **YOleotard Checkout** в WordPress.

В админке доступны:

- настройки платежей;
- настройки доставки;
- настройки KeyCRM;
- настройки email;
- настройки промокода;
- настройка GIF-подсказки для зеленого бейджа скидки;
- настройки уведомления корзины;
- отчет покупок **Purchases Report**;
- диагностические данные для скрытия проданных товаров и checkout-debug.

## Промокоды и GIF tooltip

Во вкладке промокода можно настроить:

- код промокода;
- тип и размер скидки;
- дату окончания;
- текст и стиль зеленого бейджа;
- GIF из Media Library, который объясняет, как применить скидку.

На сайте GIF должен показываться через UIkit Tooltip при наведении, фокусе, касании или клике по всему зеленому бейджу скидки.

## Фильтр готовых товаров

На главной странице используется YOOtheme/UIkit фильтр готовых товаров по росту гимнастки.

В v4.0.66 плагин добавляет desktop CSS override для блока `body.home .yo-height-filter`:

- фильтр стал шире и ближе к ширине основного контента сайта;
- уменьшены внешние и внутренние отступы;
- уменьшены расстояния между кнопками;
- desktop sticky-состояние стало компактнее;
- мобильная сетка фильтра не менялась.

## Bank invoice reserve state

В v4.0.67 после успешного создания bank invoice и отправки invoice email купленные через invoice товары получают в YOOtheme Builder стиль `Card Default`. В v4.0.68 витрина дополнительно сверяет видимые карточки с серверной проверкой доступности и оставляет invoice-reserved товары серыми и неактивными даже после повторного рендера/обновления DOM темы. В v4.0.70 запись переведена на реальное значение конструктора `Panel > Style > Card Default` (`panel_style = card-default`), поэтому YOOtheme должен выбрать пункт `Card Default`, добавить `uk-card-default`, а ручной возврат стиля в `None` снова разблокирует карточку.

На фронтенде такие карточки:

- становятся серыми и некликабельными;
- показывают по центру надпись резервирования так же, как обычный временный резерв при добавлении в корзину;
- не показывают таймер обратного отсчета;
- снова становятся покупаемыми, если в YOOtheme вручную вернуть стиль карточки на `None`.

## Отчет покупок

Раздел **Purchases Report** показывает локальные checkout-заказы:

- покупатель;
- контакты;
- товары;
- суммы;
- способ оплаты;
- provider IDs;
- статус;
- checkout snapshot, сохраненный перед оплатой.

Отчет поддерживает фильтр по статусу и пагинацию 10/20/50 записей на странице.

## Western Bid disclaimer in Step 2

В v4.0.75 на втором шаге checkout, внутри информационного блока с расчетом card payment, добавлен disclaimer Western Bid. Покупатель видит, что магазин интегрирован с Western Bid e-commerce platform, Western Bid, Inc. является Merchant of Record, а в PayPal/card statement получателем платежа будет указано `WESTERN BID`.

## KeyCRM order ID in Monobank

Начиная с v4.0.74 перед созданием Monobank invoice плагин создает или переиспользует card order в KeyCRM. Реальный номер KeyCRM передается в назначение, комментарий, reference и basket code Monobank вместо локального WordPress ID `WEB-*`. После успешной оплаты существующий финализатор обновляет тот же KeyCRM order, добавляет платеж и устанавливает paid status. Неоплаченный предсозданный order обрабатывается существующей двухчасовой отменой.

## Tracking Notifications

В меню `YOleotard Checkout > Tracking Notifications` выводятся успешно оплаченные карточкой заказы и оформленные bank invoice заказы. Строка заказа раскрывает данные клиента и товары, содержит поле для HTTP(S) tracking URL и защищенную кнопку отправки. Клиент получает английское письмо в том же фирменном стиле, что invoice/paid emails: с логотипом, номером заказа в теме и заголовке, tracking-кнопкой, резервной ссылкой и ссылками Facebook, Instagram и TikTok. Отправитель: `YOleotard <no-reply@yoleotard.com>`. Статус `Sent` сохраняется только после успешного `wp_mail()`.

## Подтвержденные рабочие зоны

На момент v4.0.75 защищенными рабочими зонами считаются:

- Monobank card payment;
- Western Bid Stripe/PayPal payment;
- Bank invoice checkout;
- KeyCRM order creation;
- customer email sending;
- Step 4 success flow;
- sold-item auto-hide;
- Shipping Calculator;
- Monobank live/test token switch;
- Monobank webhook signature verification;
- host-safe test ZIP packaging.

Эти зоны нельзя менять без прямой необходимости.

## Текущие test-candidate зоны

Некоторые свежие функции требуют живой проверки после установки тестового архива:

- per-order guest access tokens;
- Purchases Report и checkout snapshots;
- promo badge GIF tooltip;
- desktop Ready-to-Ship height filter spacing.
- bank-invoice Card Default reservation state.

## Структура проекта

Главный файл:

- `yoleotard-checkout-invoice.php`

Ключевые backend-классы:

- `includes/class-yo-checkout-promo.php`
- `includes/class-yo-checkout-monobank.php`
- `includes/class-yo-checkout-western-bid.php`
- `includes/class-yo-checkout-keycrm.php`
- `includes/class-yo-checkout-email.php`
- `includes/class-yo-checkout-order-access.php`
- `includes/class-yo-checkout-product-catalog.php`
- `includes/class-yo-checkout-product-identity.php`
- `includes/class-yo-checkout-sold-items.php`
- `includes/class-yo-checkout-purchase-report.php`

Frontend:

- `assets/yo-checkout.js`
- `assets/yo-checkout.css`

Проектная документация:

- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `REGRESSION_BASELINE.md`

## Проверки перед релизом

Минимальные локальные проверки:

```powershell
php -l yoleotard-checkout-invoice.php
Get-ChildItem includes -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets/yo-checkout.js
git diff --check
```

Если менялся только CSS, дополнительно нужно визуально проверить desktop/mobile отображение на сайте после установки тестового архива.

## Тестовый архив

Тестовый ZIP создается только по прямому запросу.

Правила архива:

- путь: `plugin-archives/yoleotard-checkout-invoice.zip`;
- внутри должен быть один верхний каталог `yoleotard-checkout-invoice/`;
- пути внутри ZIP должны использовать `/`, не `\`;
- перед созданием нового exact-name ZIP предыдущий архив сохраняется как версия, например `yoleotard-checkout-invoice-v4.0.65.zip`;
- `Compress-Archive` не используется для installable ZIP;
- `plugin-archives/` не коммитится в Git.

## Разработка

Главный принцип проекта: не ломать рабочие checkout-функции.

Новые backend-функции нужно выносить в отдельные классы в `includes/`. Главный PHP-файл должен оставаться bootstrap/composition слоем настолько, насколько это безопасно для текущей задачи.

После каждой runtime-доработки обновляются:

- `CHANGELOG.txt`;
- `DEVELOPMENT_LOG.md`;
- `PLUGIN_MAP.md`, если изменилась структура или поведение assets;
- `KNOWN_ISSUES.md` или `KNOWN_WORKING_FEATURES.md`, если изменился статус функции.
