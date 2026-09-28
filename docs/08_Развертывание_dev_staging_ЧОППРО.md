# Развертывание dev/staging ЧОППРО

Локальная среда запускается Docker Compose и включает PHP 8.3/Apache, MySQL 8 и Redis 7. HTTP root - `backend/public`.

Рабочий `.env` создается на основе `.env.example`; production/staging секреты в Git не помещаются.

Первый запуск: создать `.env`, установить уникальные пароли и TOKEN_PEPPER, выполнить `docker compose up -d --build`, затем `docker compose exec app php backend/scripts/migrate.php`, после чего проверить `/api/v1/health`.

Staging использует отдельную БД, отдельные секреты и HTTPS. Production данные не копируются в staging без обезличивания. Изменения попадают в staging после CI и review.
