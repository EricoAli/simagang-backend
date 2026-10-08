# Current Requirements - 2026

## Requirement gathering

The current requirement-gathering document is dated 3 September 2026 and
describes SIMAGANG as a web-based internship information system for DTEDI.
The immediate documented focus was initially TRPL, while the broader project
context is intended to evolve further for other DTEDI study programs.

## Core goals

- track student internship status
- store internship-place information
- centralize internship documents
- let students report internship activity
- support monitoring by the Prodi
- collect internship reports
- publish Presentasi PI schedules
- support company assessment
- preserve internship history/documentation

## User capabilities

### Admin / Prodi

- view/manage students
- view/manage companies/partners
- manage dosen pembimbing master data
- monitor internship progress
- manage internship data
- manage reports/documents
- manage Presentasi PI schedules
- inspect partner assessment results
- maintain internship history/documentation

### Mahasiswa

- login
- report internship place after acceptance
- fill company/internship information
- upload LoA / proof of acceptance
- record logbook
- upload final report
- view report status
- view Presentasi PI schedule
- submit activity documentation
- view internship status

### Perusahaan / Mitra

- login
- see students related to the company
- enter student assessments
- use a simple assessment format

## Business flow

1. Student gets an internship place.
2. Student reports the place in SIMAGANG.
3. Student uploads proof of acceptance.
4. Internship is carried out.
5. Student records logbook activity.
6. Student completes the internship.
7. Student prepares and uploads the report.
8. Student participates in Presentasi PI.
9. Company provides assessment.
10. Prodi/Dosen handles academic conversion according to Prodi rules.

## Non-functional expectations

- role-based authentication and authorization
- data access restricted to the appropriate party
- centralized document management
- reliable save/update behavior
- clear validation and error feedback
- reasonable response times for common operations

## Important contradiction to preserve

The current requirement-gathering/MVP documents describe a TRPL-focused scope and
explicitly keep some items out of scope, including Pra-KRS. The edge-case
analysis, however, already plans for multi-prodi DTEDI and identifies cross-prodi
isolation as a critical issue.

Do not erase this contradiction from documentation. Record it as an architectural
transition: current baseline -> multi-prodi-ready target.
