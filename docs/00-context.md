# SIMAGANG - Project Context

## Purpose

SIMAGANG is a web-based internship management system for DTEDI Universitas
Gadjah Mada. The existing application was developed primarily for TRPL and is
being continued by the current team.

The current 2026 requirements describe a system that centralizes internship
tracking, internship-place reporting, documents, logbook, final report,
Presentasi PI scheduling, partner assessment, and monitoring.

## Current team objective

For the immediate development cycle:

1. Continue the existing backend rather than starting from scratch.
2. Upgrade Laravel 11.x to Laravel 13.x safely.
3. Complete the assigned ADM backend tasks due 7 October 2026.
4. Validate those APIs with Postman.
5. Produce current API/documentation artifacts.
6. Continue toward an architecture that can support DTEDI multi-prodi without
   breaking the existing flow.

## Main users in current 2026 MVP

- Admin / Prodi
- Mahasiswa
- Mitra / Company

Dosen Pembimbing is represented as master data in the current MVP rather than
as a separate login role.

## Main process

Mahasiswa memperoleh tempat magang
-> melaporkan data magang
-> mengunggah bukti penerimaan/LoA
-> melaksanakan magang
-> mengisi logbook
-> mengunggah laporan
-> Mitra memberikan penilaian
-> Admin mengelola jadwal Presentasi PI
-> Mahasiswa melihat jadwal
-> Admin melakukan monitoring

## Important scope boundaries in current documents

Out of scope / external to SIMAGANG include:
- Pra-KRS / academic process handled through SIMASTER
- internship vacancy search/recruitment
- direct integration with SIMASTER
- independent Dosen Pembimbing login in the current MVP

## Historical vs current

The 2025 SRS and ERD are useful for reconstructing intended historical
behavior and schema decisions. They are not guaranteed to match the current
repository or the 2026 requirements.

The old Postman documentation should be treated as a reference only. The
current collection should eventually be generated/updated from the actual
current routes, request validation, response shape, and final decisions.
