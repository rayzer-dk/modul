# Журнал аудита качества кода

## 2026-11-20 — настройки независимого аудита main

Репозиторий modul содержит модули OpenCart/ocStore, тему и OCMOD. Существующая матрица проверки синтаксиса PHP 7.4–8.5, PHPStan dead-code fixture и Twig-контракты оставлена без изменения.

Добавлены tools/quality/audit_cross_references.py и .github/workflows/source-quality-deep.yml: контроль возможных неиспользуемых CSS-классов и JS-функций, обзор обходных комментариев и jscpd по исходникам modules и theme/source с порогом 15%.

GitHub Actions: https://github.com/rayzer-dk/modul/actions/workflows/source-quality-deep.yml

Отчёты сохраняются в artifacts, запуск — push, pull_request и workflow_dispatch. Дублирование и кандидаты на неиспользуемый код пока advisory. Проверка OCMOD install.xml, загрузчиков, динамических имен моделей, тем, Events и тесты на чистой установке обязательны до удаления любого кода, вызываемого косвенно.

## Подтверждённый результат CI

Запуск 2026-11-20: https://github.com/rayzer-dk/modul/actions/runs/44708463166 — SUCCESS для обоих заданий. Просканировано 149 файлов; 200 кандидатов на неиспользуемые CSS-классы; jscpd: 107 дублирующихся блоков. Все находки остаются advisory до проверки Twig, OCMOD, событий и внешних вызовов.
