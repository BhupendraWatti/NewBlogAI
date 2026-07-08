# Architecture Decisions

## ADR-001: Centralized Backend Authority (Source of Truth)
* **Date**: 2026-07-05
* **Decision**: All SaaS business logic, subscription limits, scheduling runs, generation pipelines, and prompt variables must be authored and evaluated strictly inside the Laravel backend. The WordPress plugin serves as an execution adapter that pulls settings, reports heartbeats, and creates posts on command.
* **Alternatives Considered**: Decentralized logic where WordPress manages its own schedules and calls Laravel on due intervals. This was rejected because it violates multi-site scaling, multi-tenant subscription tiers, and tenant security.
* **Impact**: Guaranteed consistency of limit enforcement, secure API key isolation on the backend, and central billing control.

## ADR-002: Centralized Token Revocation
* **Date**: 2026-07-05
* **Decision**: When issuing a new token via the plugin login endpoint, all previous active tokens linked to the user are rotated (marked as revoked).
* **Impact**: Improves connection security. If a token is compromised, a new wizard connection automatically revokes the old compromised credentials.

## ADR-003: Category-Driven News Generation
* **Date**: 2026-07-08
* **Decision**: Pivot the SaaS content pipeline from free-text user-authored "Topics" (which required complex database CRUD operations and validation per site) to a standard, structured set of global and local "News Categories". 
* **Alternatives Considered**: Keeping a topic-based generator while adding categories as tag parameters. This was rejected because it left the SaaS acting as a generic article writing tool rather than a specialized global news syndication/generation platform, which increased cognitive overload for users and increased prompt templating errors.
* **Impact**: Simplified the generation layout on the frontend, removed complex topic model dependency, improved query relevance, and resolved database constraint issues.