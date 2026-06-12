# Architecture

LaraWebhook follows a pragmatic architecture inspired by Domain Driven Design, hexagonal architecture and CQRS.

The goal is not to implement a pure architecture for its own sake. The goal is to keep the package robust, readable, testable and explicit as it grows.

LaraWebhook should make it easy to understand:

- which business flows exist;
- where critical rules are enforced;
- which responsibilities belong to each part of the system;
- how webhook consistency, idempotency, replay and auditability are protected.

## Architectural principles

LaraWebhook follows these principles:

- business rules should be explicit;
- controllers, middleware and console commands should stay thin;
- domain objects should protect critical invariants;
- application use cases should orchestrate workflows;
- infrastructure should contain Laravel, Eloquent, HTTP, console, queues and provider-specific technical concerns;
- abstractions should be introduced only when they clarify responsibilities or improve testability;
- the architecture should remain understandable by a senior Laravel developer in a few minutes.

## Pragmatism rule

LaraWebhook intentionally avoids unnecessary architectural ceremony.

Do not introduce an interface, value object, aggregate, event, command bus or query bus unless it protects a real rule, clarifies a boundary, enables extension, or improves testability.

Architecture should serve the codebase, not the opposite.

## Bounded contexts

LaraWebhook is organized around a small set of bounded contexts.

The initial contexts are:

- Ingestion Context;
- Processing Context;
- Audit Context;
- Shared Context.

A Delivery Context may be introduced later if LaraWebhook adds asynchronous dispatch, retry policies, handler delivery tracking, backoff strategies or dead-letter behavior.

### Ingestion Context

The Ingestion Context is responsible for receiving webhook requests from external providers.

It covers:

- receiving raw HTTP webhook requests;
- extracting provider information;
- extracting signatures and headers;
- validating signatures;
- normalizing incoming webhook data;
- rejecting invalid webhooks before they enter processing.

Examples of future classes:

text src/Ingestion/Application/Commands/ReceiveWebhookCommand.php src/Ingestion/Application/UseCases/ReceiveWebhook.php src/Ingestion/Infrastructure/Laravel/Middleware/ValidateWebhook.php src/Ingestion/Infrastructure/Validation/WebhookValidatorFactory.php

The Ingestion Context should not contain dashboard logic, pruning logic or long-term audit queries.

### Processing Context

The Processing Context is responsible for deciding whether a webhook event can be processed, replayed, or rejected from a business-flow perspective.

It covers:

- idempotency;
- replay rules;
- webhook processing state;
- business-level processing invariants;
- coordination with handlers when processing is introduced.

Examples of future classes:

text src/Processing/Application/Commands/ReplayWebhookCommand.php src/Processing/Application/UseCases/ReplayWebhook.php src/Processing/Application/Ports/IdempotencyResolver.php src/Processing/Infrastructure/Idempotency/DefaultIdempotencyResolver.php src/Processing/Domain/Entities/WebhookEvent.php

The Processing Context protects rules such as:

- a webhook should not be processed twice;
- a webhook without an available payload cannot be replayed;
- an invalid webhook should not enter processing;
- a terminal webhook event should not transition to another state unexpectedly.

### Audit Context

The Audit Context is responsible for observability, persisted logs, payload storage, retention, pruning and read-side history.

It covers:

- webhook logs;
- payload storage mode;
- redaction;
- retention;
- pruning;
- replay history;
- dashboard/API read models;
- failure details.

Examples of future classes:

text src/Audit/Application/Commands/PruneWebhookLogsCommand.php src/Audit/Application/UseCases/PruneWebhookLogs.php src/Audit/Application/Queries/ListWebhookLogs.php src/Audit/Infrastructure/Laravel/Persistence/Models/WebhookLog.php src/Audit/Infrastructure/Laravel/Console/PruneWebhookLogsConsoleCommand.php src/Audit/Infrastructure/Payload/PayloadRedactor.php

The Audit Context may read from persistence models directly for query use cases when no business invariant is involved.

### Shared Context

The Shared Context contains cross-context building blocks.

It may contain:

- generic domain event interfaces;
- shared application contracts;
- generic infrastructure adapters;
- small shared value objects only when they are truly generic.

The Shared Context should stay small. It must not become a dumping ground for unrelated services.

## Layers

Each context may contain three layers:

text Domain Application Infrastructure

Not every context needs every layer immediately. Layers should be introduced as needed.

### Domain layer

The Domain layer contains business concepts and rules.

It may contain:

- entities;
- value objects;
- aggregates;
- domain events;
- domain exceptions;
- invariants.

The Domain layer must not depend on Laravel, Eloquent, HTTP requests, queues, configuration files or service providers.

Domain code should be framework-independent.

Examples:

text src/Processing/Domain/Entities/WebhookEvent.php src/Processing/Domain/ValueObjects/IdempotencyKey.php src/Audit/Domain/Exceptions/PayloadNotAvailable.php

### Application layer

The Application layer contains use cases and ports.

It orchestrates domain objects and dependencies.

It may contain:

- commands;
- command use cases;
- queries;
- query handlers;
- application ports;
- result objects.

The Application layer defines what the system does, but not the technical details of how persistence, HTTP or Laravel work.

Examples:

text src/Ingestion/Application/UseCases/ReceiveWebhook.php src/Processing/Application/UseCases/ReplayWebhook.php src/Audit/Application/UseCases/PruneWebhookLogs.php src/Audit/Application/Queries/ListWebhookLogs.php

### Infrastructure layer

The Infrastructure layer contains technical implementation details.

It may contain:

- Laravel controllers;
- middleware;
- Artisan commands;
- Eloquent models;
- repository implementations;
- provider-specific validators;
- service providers;
- configuration adapters;
- queue jobs;
- event bus adapters.

Infrastructure may depend on Laravel and external libraries.

Examples:

text src/Ingestion/Infrastructure/Laravel/Middleware/ValidateWebhook.php src/Audit/Infrastructure/Laravel/Http/Controllers/WebhookLogController.php src/Audit/Infrastructure/Laravel/Persistence/Models/WebhookLog.php src/Shared/Infrastructure/Laravel/Providers/LarawebhookServiceProvider.php

## CQRS

LaraWebhook applies CQRS pragmatically at the application layer.

Commands represent write operations.

Queries represent read operations.

At this stage, LaraWebhook does not require:

- a command bus;
- a query bus;
- a separate read database;
- event sourcing;
- asynchronous projections.

### Commands

Commands change system state.

Examples:

text ReceiveWebhook ValidateWebhook ProcessWebhook ReplayWebhook PruneWebhookLogs RecordWebhookLog MarkWebhookAsFailed

A command flow usually consists of:

text Command DTO → Use case → Domain rules → Ports / infrastructure → Result

Examples:

text src/Ingestion/Application/Commands/ReceiveWebhookCommand.php src/Ingestion/Application/UseCases/ReceiveWebhook.php  src/Processing/Application/Commands/ReplayWebhookCommand.php src/Processing/Application/UseCases/ReplayWebhook.php  src/Audit/Application/Commands/PruneWebhookLogsCommand.php src/Audit/Application/UseCases/PruneWebhookLogs.php

Commands should protect business invariants and should not contain dashboard-specific read logic.

### Queries

Queries only read state.

Examples:

text GetWebhookStatus ListWebhookLogs GetWebhookLogDetails GetFailureDetails GetDeliveryHistory ListFailedWebhooks ListReplayableWebhooks

A query flow usually consists of:

text Query DTO → Query handler → Read model / projection → Result

Examples:

text src/Audit/Application/Queries/ListWebhookLogsQuery.php src/Audit/Application/Queries/ListWebhookLogs.php  src/Audit/Application/Queries/GetWebhookLogDetailsQuery.php src/Audit/Application/Queries/GetWebhookLogDetails.php

Queries must not mutate state.

Queries may use Eloquent or optimized read models directly when no domain invariant is involved. This is acceptable for dashboard/API reads.

At this stage, the same database table may be used for writes and reads. Dedicated read projections may be introduced later if needed.

## Hexagonal architecture

LaraWebhook uses a ports-and-adapters approach where it provides clarity.

Application use cases depend on ports when they need external capabilities.

Examples of ports:

text WebhookLogRepository IdempotencyResolver SignatureValidator PayloadRedactor PayloadStorageResolver EventBus

Infrastructure provides implementations.

Examples:

text EloquentWebhookLogRepository DefaultIdempotencyResolver WebhookValidatorFactory PayloadRedactor PayloadStorageResolver LaravelEventBus

Ports should be introduced only when they create a useful boundary.

Do not create an interface for every class by default.

## Domain invariants

Critical rules should be protected outside controllers and middleware.

Examples of invariants:

- an invalid webhook cannot enter processing;
- a webhook cannot be processed twice;
- a webhook without a stored payload cannot be replayed;
- a terminal webhook event cannot transition unexpectedly;
- payload storage mode must be explicit;
- redaction must be applied before payload persistence when redacted mode is enabled.

Controllers, middleware and console commands are adapters. They translate transport concerns into application use cases.

They should not own core business rules.

## Event-driven design

LaraWebhook may use internal domain events to make flows traceable and extensible.

Possible events:

text WebhookReceived WebhookValidated WebhookRejected WebhookProcessed WebhookFailed WebhookReplayed WebhookLogged WebhookPruned

Events should contain minimal non-sensitive data.

Events must not expose raw payloads, provider secrets or sensitive headers.

Domain events can be introduced gradually. A custom event bus is not required until it provides clear value.

## Current migration strategy

The architecture should be introduced progressively.

Recommended order:

1. Document the architecture.
2. Create bounded context folders.
3. Move Laravel infrastructure into context infrastructure folders.
4. Move technical services into their contexts.
5. Introduce command/query application structure.
6. Extract ReplayWebhook use case.
7. Extract PruneWebhookLogs use case.
8. Extract read queries for logs.
9. Extract ReceiveWebhook use case.
10. Introduce domain value objects where they protect real rules.
11. Introduce domain events.
12. Add architecture tests.

The package should remain functional after each step.

No big-bang rewrite is required.

## Current source structure

The package is being progressively migrated toward the following bounded context structure:

```text
src/
├── Ingestion/
├── Processing/
├── Audit/
└── Shared/
```

Each context may contain:
```text
Domain/
Application/
Infrastructure/
```

Empty folders may temporarily contain .gitkeep files during the migration. These placeholders should be removed once 
real classes are introduced.

## Dependency rules

The intended dependency direction is:

text Infrastructure → Application → Domain

Allowed:

text Infrastructure depends on Application Infrastructure depends on Domain Application depends on Domain Application depends on ports it defines

Forbidden:

text Domain depends on Infrastructure Domain depends on Laravel Domain depends on Eloquent Domain depends on config() Application depends on HTTP controllers Application depends on Blade/views Application depends on console commands

Infrastructure is allowed to depend on Laravel.

## Testing strategy

The architecture should make testing easier.

Recommended test levels:

- Domain tests for invariants;
- Application tests for use cases;
- Infrastructure tests for Laravel adapters;
- Feature tests for HTTP and Artisan behavior;
- Architecture tests for dependency rules.

Examples:

text tests/Unit/Processing/Domain/Entities/WebhookEventTest.php tests/Unit/Processing/Application/UseCases/ReplayWebhookTest.php tests/Unit/Audit/Application/UseCases/PruneWebhookLogsTest.php tests/Feature/Audit/Infrastructure/Laravel/Console/PruneWebhookLogsConsoleCommandTest.php tests/Feature/Ingestion/Infrastructure/Laravel/Middleware/ValidateWebhookTest.php

## Naming conventions

Use explicit names.

Prefer:

text ReplayWebhook ReplayWebhookCommand PruneWebhookLogs PruneWebhookLogsCommand ListWebhookLogs ListWebhookLogsQuery WebhookLogRepository

Avoid vague names such as:

text Manager Helper Processor Service Handler

unless the responsibility is genuinely generic.

## What is intentionally not implemented yet

LaraWebhook does not currently need:

- full event sourcing;
- a custom command bus;
- a custom query bus;
- a separate read database;
- four fully isolated bounded contexts with duplicated abstractions;
- a Delivery Context without real delivery/retry behavior;
- repositories for every model;
- interfaces for every service.

These may be introduced later if the product scope justifies them.

## Summary

LaraWebhook architecture should make the package easier to reason about.

The goal is to clearly answer:

- What are the business flows?
- Where are the critical rules?
- What changes state?
- What only reads state?
- Where are Laravel-specific details isolated?
- How are payload storage, idempotency, replay and auditability protected?

The architecture must remain pragmatic, explicit and useful.
