# Presentation-First 12-Hour Plan

## Objective

The presentation is the immediate constraint. The 7 October WBS date is the
feature-delivery milestone, not the presentation deadline.

## Phase 1 - tonight / before presentation

### 1. Prepare workspace

Create a fresh clone in a sibling directory, for example:

```text
D:\Projects\SIMAGANG
D:\Projects\SIMAGANG-old-testing
```

Do not put the new clone inside the old clone.

### 2. Add the context pack

Copy `AGENTS.md` to the repository root and copy `docs/` next to it.

### 3. Open the repository in Antigravity 2.0

Use the repository root as the project/workspace.

### 4. Give the analysis-only prompt

Use the analysis-only prompt from the chat response.

### 5. Capture the analysis

The minimum useful presentation evidence is:

- current Laravel version
- current API modules
- database/model overview
- ADM task scope
- identified gaps
- Laravel 13 migration plan
- Postman testing plan
- known risks/conflicts

### 6. Do not perform a risky mass refactor before presenting

The presentation should be supported by a reproducible baseline and a clear plan.
Do not create a large unreviewed migration immediately before presenting.

## Phase 2 - after presentation

1. Establish clean Laravel 11 baseline.
2. Upgrade 11 -> 12 -> 13 in staged commits.
3. Finish ADM Magang.
4. Finish ADM Presentasi PI + Dashboard.
5. Build the current Postman collection.
6. Run positive + negative + authorization tests.
7. Update documentation and decision log.

## Presentation script

A useful 60-90 second summary:

> SIMAGANG adalah sistem informasi magang existing yang awalnya dibangun untuk
> TRPL. Saya melanjutkan backend existing, bukan membuat sistem baru. Untuk
> pengembangan saat ini saya memisahkan tiga hal: stabilisasi dan upgrade
> Laravel, penyelesaian task ADM sesuai WBS sampai 7 Oktober, serta validasi API
> dengan Postman. Dokumen 2026 menjadi acuan kebutuhan terbaru, sedangkan SRS,
> ERD, dan Postman 2025 digunakan sebagai historical reference. Dari analisis
> awal ditemukan beberapa ketidaksesuaian antara dokumen dan implementasi,
> termasuk model role dosbing, asumsi semester 6/7, dan kebutuhan isolasi prodi.
> Karena itu perubahan besar dilakukan secara bertahap dan dicatat agar tidak
> merusak API yang sudah ada.
