# ADR-0001

## Title

Adopt Domain Driven Design (DDD) Architecture

## Status

Accepted

## Context

The CGMFPFED Welfare Platform contains multiple business domains:

- Scholarship
- Beema
- Future Welfare Schemes

Each domain has its own workflow, documents, payment lifecycle, integrations and audit trail.

A traditional CRUD architecture would tightly couple business logic with persistence and become difficult to maintain.

## Decision

The platform will adopt Domain Driven Design.

Each business domain will contain its own:

- Contracts
- DTO
- Services
- Repositories
- Controllers
- Requests
- Resources
- Policies
- Models
- Enums

Shared infrastructure will remain under App.

## Consequences

Advantages

- High maintainability
- Easy testing
- Better scalability
- Strong separation of concerns
- Future schemes can be added with minimal impact
