# Спецификация API ЧОППРО

## 1. Общие правила

Префикс API - `/api/v1`. Формат - JSON UTF-8. Ошибки - `application/problem+json`. Tenant определяется по авторизованной сессии. Check-in/out, checkpoint scan, создание происшествия и mobile sync используют `Idempotency-Key`.

## 2. Группы endpoint

Auth: `/auth/login`, `/auth/otp/request`, `/auth/otp/verify`, `/auth/mfa`, `/auth/refresh`, `/auth/logout`.

Users: `/tenant`, `/users`, `/roles`, `/role-assignments`.

HR: `/employees`, `/documents`, `/qualifications`, `/document-types`.

Compliance: `/licenses`, `/contracts`, `/compliance/rules`, `/compliance/tasks`.

Facilities: `/customers`, `/facilities`, `/posts`, `/routes`, `/checkpoints`.

Scheduling: `/shifts`, `/assignments`, `/replacements`, `/timesheets`.

Mobile: `/mobile/bootstrap`, `/mobile/sync`, `/attendance/check-in`, `/attendance/check-out`.

Operations: `/patrol-runs`, `/checkpoint-scans/batch`, `/incidents`, `/escalations`.

Client: `/client/dashboard`, `/client/reports`, `/client/incidents`.

Reporting: `/reports`, `/exports`, `/audit-logs`.

## 3. Этап 1

В каркасе реализованы `GET /api/v1/health` и `GET /api/v1/version`. Остальные endpoint вводятся по Этапам 2-4.
