# Decision Log

Use this file to record decisions that should not be rediscovered by the agent.

## D-001 - Current scope date

**Decision:** The 7 October 2026 date refers to the WBS ETA for the assigned
ADM/MHS feature slice, not full project completion.

**Status:** Confirmed by current project context.

## D-002 - Historical Postman documentation

**Decision:** The 2025/older Postman documentation is reference-only because the
team itself warned it may not reflect the latest ERD/API state.

**Status:** Confirmed for current workflow.

## D-003 - Current MVP role model

**Decision:** The current MVP describes login roles as Admin, Mahasiswa, and Mitra.
Dosen Pembimbing is master data in this scope.

**Status:** Confirmed by 2026 MVP/PRD; repository discrepancy remains to be
investigated because the backend still includes `dosbing` as a role.

## D-004 - Multi-prodi

**Decision:** Multi-prodi support is a current architectural target, but the
2026 MVP documents still contain TRPL-focused scope statements.

**Status:** Treat as a transition target. Do not invent final schema without a
review of current data + requirements.

## D-005 - Pra-KRS

**Decision:** Pra-KRS is handled through SIMASTER and is outside SIMAGANG in the
current 2026 requirements.

**Status:** Current.
