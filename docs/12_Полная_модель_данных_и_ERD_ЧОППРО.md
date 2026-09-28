# Полная модель данных и ERD ЧОППРО

## 1. Назначение

Документ фиксирует целевую модель данных MVP до начала массовой реализации Этапов 2-4. Модель является архитектурной основой и не означает, что все перечисленные таблицы уже реализованы на Этапе 1.

## 2. Общие правила

Основные бизнес-сущности используют UUID. Все tenant-зависимые таблицы содержат tenant_id и индекс по tenant_id совместно с основными полями поиска. Сервер не принимает tenant_id клиента как доверенный источник контекста. Временные значения хранятся в UTC с отдельным часовым поясом объекта или tenant там, где он необходим для бизнес-расчета. Исторические события attendance, обходов, происшествий, аудита и публикаций не перезаписываются без отдельной версии или override-записи.

## 3. Домен доступа и организации

Tenant, User, Role, Permission, RoleAssignment, AuthSession, Device, FeatureFlag, AuditLog. RoleAssignment поддерживает object_scope и valid_until. AuthSession хранит хэш токена и возможность отзыва по пользователю или устройству.

## 4. Сотрудники и документы

Employee, Employment, Qualification, EmployeeQualification, DocumentType, EmployeeDocument, DocumentVerification. Документ хранит метаданные, срок действия, статус, ссылку на файл и историю проверок. Файл физически размещается в S3-совместимом хранилище.

## 5. Compliance и договоры

SecurityLicense, LicensedServiceType, Contract, ContractService, OwnershipProof, ComplianceRuleVersion, ComplianceTask, SubmissionReceipt, GuardCardRegisterEntry. Версионность правил обязательна: изменение текущего справочника не должно менять исторический расчет сроков и состояние старых договоров.

## 6. Заказчики, объекты и посты

Customer, CustomerContact, CustomerPortalUser, Facility, FacilityGeofence, Post, PostInstruction, PatrolRoute, Checkpoint, CheckpointToken. FacilityGeofence хранит геометрию/координаты в формате, совместимом с MySQL 8; первичная реализация может использовать POINT/POLYGON и прикладную проверку расстояния.

## 7. Графики и присутствие

ShiftTemplate, Shift, ShiftAssignment, ReplacementRequest, AttendanceEvent, AttendanceReview, Timesheet. AttendanceEvent хранит client_time и server_time отдельно, координаты, accuracy, device_id, network_state, QR token version и correlation_id. Ручное решение оформляется отдельной AttendanceReview/MANUAL_OVERRIDE и не изменяет исходное событие.

## 8. Обходы

PatrolSchedule, PatrolRun, CheckpointScan. CheckpointScan имеет idempotency_key, client_time, server_time, координаты, accuracy и ссылку на версию контрольной точки.

## 9. Происшествия

Incident, IncidentAction, IncidentAttachment, IncidentComment, Escalation. Публикуемая заказчику часть отделяется от внутренних комментариев. Исправление опубликованной информации создает новую версию, а не переписывает историю.

## 10. Уведомления, отчеты и интеграции

Notification, NotificationTemplate, NotificationDelivery, CustomerReport, CustomerReportVersion, ReportAcknowledgement, ExportJob, OutboxEvent, IntegrationDelivery. Для внешних каналов хранится минимальный payload и статус доставки.

## 11. Диаграммы

Полная модель разделена на несколько ERD, чтобы диаграммы оставались читаемыми: доступ/HR/compliance, операционный контур, отчетность/интеграции. Исходники находятся в docs/диаграммы и имеют формат PUML; рядом находятся SVG и PNG-рендеры.
