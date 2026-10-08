# Edge-Case Analysis - Current 2026

## Executive metrics

The current edge-case document identifies:

- 78 edge cases
- 19 affected pages/views (12 existing + 7 recommended/architectural)
- 5 affected role categories in the analysis
- priorities: P0 = 28, P1 = 29, P2 = 16, P3 = 5

## Critical P0 themes

### Authentication / authorization

- manual access to another role's URL must be blocked
- expired session/token needs graceful handling
- role guard must not redirect to missing routes

### Multi-prodi isolation

- Prodi admin must not access students from another prodi
- student must not access another student's logbook/detail
- dosen/mentor access must remain bounded to the correct relationship
- Mitra must only see students belonging to that Mitra/company

### Workflow integrity

- Admin needs actionable verification/approval/rejection/revision controls
  where the intended workflow requires them
- student editing must be blocked/controlled after approval or final state
- duplicate submissions and race conditions must be guarded

### Runtime bugs already identified

The edge-case analysis explicitly calls out the existing frontend logbook delete
bug (`axios.delete` used where the shared `api` client should be used) and other
workflow/validation problems.

## P1 themes

- form validation
- file size/type validation
- upload disconnect/retry behavior
- empty/loading states
- status synchronization
- error feedback

## Priority guidance

Do not attempt all 78 fixes in the urgent window.

For the immediate ADM milestone, focus first on edge cases that affect:

1. authorization
2. data ownership
3. API correctness
4. CRUD validation
5. Presentasi PI integrity
6. dashboard query correctness

Treat cosmetic and lower-priority UI polish as later backlog.
