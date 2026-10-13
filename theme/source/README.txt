CodeCart PRO Theme 8.7.7
Author: CodeCart PRO | https://codecartpro.com
Target: CodeCart PRO 3.0.6.0 / ocStore 3.0.4.1 / OpenCart 3.x, PHP 8.1–8.3.
System download: https://github.com/CodeCartPro/CodeCartPro-3.0.6.0/releases/download/CodeCart_PRO_3.0.6.0/CodeCart_PRO_3.0.6.0.zip

RU — установка и обновление
Перед установкой/обновлением создайте полную резервную копию всех файлов сайта и базы данных. Сначала установите архив на отдельной копии магазина.
1. В Расширения → Установка расширений загрузите Theme_CodeCart_Pro_v8.7.7.ocmod.zip.
2. В Расширения → Модификаторы нажмите Обновить. Через панель разработчика очистите кеш Twig и темы.
3. В Расширения → Темы откройте CodeCart PRO. Новая установка выключена по умолчанию. Проверьте настройки и вручную включите тему, затем сохраните.
4. В настройках магазина выберите CodeCart PRO и нужный каталог темы. Проверьте главную, каталог, товар, корзину и реальное тестовое оформление заказа.
5. При обновлении не сбрасывайте существующие настройки и не удаляйте контент. Экспорт настроек полезен, но не заменяет полную резервную копию.
6. Оформление админки применяется только к страницам настроек темы, её блога и отзывов; стандартная админка не заменяется.
7. Для удаления сначала выберите штатную тему магазина, выключите CodeCart PRO и сохраните, затем удалите расширение и обновите модификаторы/кеш. Данные блога/отзывов сохраняются; не удаляйте таблицы вручную без отдельного резервного копирования.
8. Для отката восстановите согласованную резервную копию файлов и БД. Один старый ZIP не является полноценным откатом миграции БД.

EN — installation / update / removal
Back up all site files and the database before installation or update. Validate on a separate staging copy first.
Upload this OCMOD ZIP through Extensions → Installer; refresh Modifications, then clear Twig/theme cache. Open the theme settings, verify them and explicitly enable/save; a fresh installation is disabled by default. Select CodeCart PRO in store settings. Preserve existing settings/content during updates. Admin styling applies only to the theme settings, blog and review pages.
Before removal, switch the store to its default theme, disable/save CodeCart PRO, uninstall and refresh modifications/cache. Retained blog/review data should not be deleted without a separate backup. Roll back using a matching backup of both files and database.

Release validation / ограничения проверки
Status: not ready for an unconditional production deployment; packaged for staging validation.
PHP 8.1/8.2/8.3, Twig, JS, XML, UTF-8, languages and ZIP integrity are checked for this archive. Previous local HTTP lifecycle and storefront checks and measured responsive admin/storefront cases are documented in VALIDATION.txt.
Root browser checks confirm desktop checkout sticky scrolling/footer stop, dark toggle, full popup CTA and Escape focus. Remaining gallery/video interactions, all licensed module integrations and a completed real test order are NOT RUN. Hanging UI processes were skipped by user instruction; this is not a passing test result.
The full text of checkout buttons and summary/footer fixes are implemented; final target-store regression validation is still required.
All third-party licenses remain intact. The theme is independent of UniShop2 and cannot be simultaneously selected as the store theme with UniShop2.
