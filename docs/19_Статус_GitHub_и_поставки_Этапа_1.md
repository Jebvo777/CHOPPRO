# Статус GitHub и поставки Этапа 1

## 1. Репозиторий

GitHub: https://github.com/Jebvo777/CHOPPRO

Основная ветка: main. Интеграционная ветка: develop. На момент подготовки дополнения базовый коммит Этапа 1: 00e25a1757512b5a872133f9ca11ae82cd408b7c.

## 2. Что находится в репозитории

Backend-каркас PHP, MySQL migrations, Docker Compose, CI workflow, web-каркасы, React Native структура, прототипы, документация Markdown, OpenAPI, PUML-диаграммы, портал приемки.

## 3. Проверки

Локально выполняются php -l для PHP файлов и smoke-тест backend/tests/smoke.php. GitHub Actions выполняет синтаксическую проверку и smoke-тест при push/PR согласно workflow.

## 4. Онлайн-статус

Портал приемки содержит модуль чтения публичного GitHub API с резервным snapshot. Если исходящий доступ хостинга к api.github.com запрещен, портал продолжает работать и показывает сохраненный commit snapshot.
