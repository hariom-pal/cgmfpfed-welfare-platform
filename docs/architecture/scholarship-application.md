# ScholarshipApplication Aggregate

## Responsibilities

- Represents one scholarship application.
- Maintains current application status.
- References applicant, scheme and session.
- Does not store workflow history.
- Does not store uploaded documents.
- Does not store payment records.
- Does not store audit logs.

## Child Entities

- ScholarshipDocument
- ScholarshipWorkflowHistory
- ScholarshipPayment
- ScholarshipPaymentBatch
- ScholarshipAuditLog
- StudentBankAccount
- CollectionHistory
