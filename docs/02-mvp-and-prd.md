# MVP and PRD - 2026

## MVP roles

The current MVP defines three login roles:

| Role | Main responsibility |
|---|---|
| Admin | Master-data management and monitoring |
| Mahasiswa | Manage personal internship process |
| Mitra | Provide student assessment |

Dosen Pembimbing is master data in the current MVP, not a separate login role.

## MVP modules

### Admin

- Dashboard
- Student management
- Dosen Pembimbing management
- Mitra management
- Internship management
- Presentasi PI
- Monitoring

### Mahasiswa

- Dashboard
- Persiapan Magang
- Pelaksanaan Magang / Logbook
- Laporan Magang
- Presentasi PI

### Mitra

- Dashboard
- Student list for assessment
- Assessment entry/upload/detail

## Feature IDs

### Admin

- ADM-DB.01 - Dashboard
- ADM-MHS.01..04 - Student management
- ADM-DP.01..04 - Dosen Pembimbing management
- ADM-MTR.01..04 - Mitra management
- ADM-MG.01..04 - Internship management
- ADM-PI.01..02 - Presentasi PI

### Mahasiswa

- MHS-DB.01 - Dashboard/progress
- MHS-PM.01..07 - Internship preparation
- MHS-PL.01..04 - Logbook
- MHS-LM.01..02 - Report
- MHS-PI.01 - Presentasi PI

### Mitra

- MTR-DB.01 - Dashboard
- MTR-PN.01..05 - Assessment

## Acceptance criteria pattern

A feature is considered complete when:

1. accessible by the intended role
2. main user story works
3. input validation exists
4. data saves/updates correctly
5. stored data can be retrieved
6. invalid conditions have error handling
7. UI follows the agreed prototype where applicable
8. functional testing has been performed
9. no critical bug blocks the main flow
10. UAT requirements are satisfied

## Current priority logic

The MVP dependency order is:

1. master data and authentication
2. internship preparation
3. logbook / execution
4. report
5. partner assessment
6. Presentasi PI

Do not treat all feature IDs as equally urgent. The WBS is the scheduling source
for the current sprint.
