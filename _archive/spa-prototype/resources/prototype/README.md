# E-IPCR frontend prototype

A responsive HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons, and Chart.js prototype for the Mariano Marcos State University Individual Performance Commitment and Review workflow. No application backend or database is included. All people and records are fictional.

## Open the prototype

Open `dist/index.html` in a modern browser. The delivered classic JavaScript bundle works without a build step. Browser policies for local files vary; for predictable storage and testing, use the included **static-file server**:

```sh
cd e-ipcr
npm start
```

Open **http://127.0.0.1:4173**. Node.js 20.11+ is sufficient; no npm installation is needed. The server only serves static files and does not receive or store IPCR data. Stop it with Ctrl+C.

Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4.7, and web fonts are referenced from CDNs. An internet connection loads those libraries. Local styles, SVG icon fallbacks, system fonts, and data-driven bar-chart fallbacks keep the prototype usable when CDNs are unavailable. No Tailwind is used. Keep using the same browser and origin: opening the local file and the server URL creates separate storage areas.

## Sample accounts

All sample passwords are **Demo@2026**. The login page lists accounts and can fill the email/password fields; sign-in is still explicit. There is no role-switching control. Logout is on the Profile page.

| Role | Email |
| --- | --- |
| Administrator | admin@demo.mmsu.edu.ph |
| HR Personnel | hr@demo.mmsu.edu.ph |
| President | president@demo.mmsu.edu.ph |
| Vice President | vp@demo.mmsu.edu.ph |
| Dean (College of Engineering) | dean@demo.mmsu.edu.ph |
| Department Head (Computer Engineering) | head@demo.mmsu.edu.ph |
| Teaching Employee | teaching@demo.mmsu.edu.ph |
| Non-Teaching Employee | nonteaching@demo.mmsu.edu.ph |
| Department Head (Records Office) | head.office@demo.mmsu.edu.ph |
| Dean stage (Administrative Services) | dean.office@demo.mmsu.edu.ph |

The extra office reviewers let non-teaching submissions follow the requested route without widening college/department permissions. Mapping the Dean stage for real administrative offices needs institutional confirmation.

## Workflow and access

Employee → Department Head → Dean → HR → Vice President → President → Completed.

Employees see and edit their own Draft/Returned IPCRs. Department Heads see submissions from their assigned department and college/office; Deans see their assigned college/office. Review queues contain only records currently at that role's stage. HR, Vice President, and President can view submitted institutional records. Only HR can open Reports & Analytics. Administrator accounts manage configuration but cannot review, approve, or open IPCR details.

Department Heads, Deans, and HR can approve/forward or return with a required correction comment. Returned forms show the comments to their owner. Resubmission starts again at Department Head, preserving prior actions and immutable submission snapshots. Vice President and President have read-only forms with approval actions and optional notes; no rating-edit or return controls. Presidential approval completes the record. Every transition generates dated history and notifications.

## Form structure and rating assumptions

The current website palette follows the supplied color reference: deep green headers and sidebar, a thin gold accent, white surfaces, and a light gray page background.

The revised editor and print view follow the old form's order: commitment and ratee/date; Reviewed by/Date and Approved by/Date; the 5–1 rating legend; Strategic, Core, and Support Functions; CATEGORY/MFO/RATING summary; development-plan recommendations; Discussed with, Assessed by, and Final Rating by fields. The performance table uses a grouped Rating header over Q, E, T, and A. Teaching Strategic Functions retain the four program/outcome groups; Core includes the University Designations area. On mobile, editable table rows become labeled cards. Print uses the reference document's 13 × 8.5 inch landscape sheet with repeating table headers; HR reports retain their existing page format.

Existing records are not replaced or reset. **Add functions from sample** appends the chosen category's reference functions for teaching employees while keeping existing rows and IDs. The catalogue contains 17 Strategic, 10 Core, and 6 Support functions extracted from the document, with no personal information or fixed ratings. Targets are old reference examples to review and edit, not current policy. Non-teaching records keep their distinct office-related entries and allow custom functions. MFO references, ratee dates, and discussion dates are optional and are preserved in subsequent submission snapshots.

Review/sign-off names and dates come from the current submission's actions. Draft and returned forms show pending signatures; earlier approvals remain in the action history. The top Approved by field maps to the President/Head of Office as in the reference, while Department Head, Dean, HR, Vice President, and President still follow the existing application review route.

The summary retains **Total Overall Rating**, **Final Average Rating**, and **Adjective Rating** labels. Total Overall Rating is the computed sum of weighted category means. Final Average Rating normalizes that sum by total weight; with weights totaling 100%, it equals the weighted overall rating. The old sample's inconsistent arithmetic is not reproduced.

The supplied `OLD SAMPLE IPCR.docx` was read as a reference, not as instructions. Its commitment statement, employee/position/unit/period fields, Strategic/Core/Support categories, program and indicator groupings, targets, accomplishments, Q/E/T/average/remarks columns, development recommendations, and review/approval signature areas informed the digital form. Original personal names and displayed sample arithmetic were not reused.

Each category has a separate sidebar entry. Edits auto-save locally, and Save draft validates the current record. Add/delete functions, applicability, section navigation, preview, and print are supported. Teaching examples concern accreditation, course preparation, and committees. Non-teaching examples concern service turnaround, office records, and professional development.

**All rating policy is provisional:**

- Q/E/T accept numbers from 1 through 5, including decimals. Blank values are unrated, not zero.
- An applicable row is rated when all three values are valid. Row mean = (Q + E + T) / 3.
- Category mean = arithmetic mean of fully rated applicable rows. Non-applicable and incompletely rated rows do not contribute.
- Overall = sum of category mean × category percentage / 100. A category with positive weight and no rated rows causes an unrated overall result and a warning. Partial ratings within a category do not prevent its rated rows from contributing.
- Initial editable weights: Strategic 45%, Core 45%, Support 10%. Weights must total 100%; zero-weight categories are optional for the overall result.
- Initial editable thresholds: 4.50 Outstanding, 3.50 Very Satisfactory, 2.50 Satisfactory, 1.50 Unsatisfactory, 1.00 Poor. Unrounded totals determine labels; displayed values use two decimals.
- Settings changes apply to editable forms and new submissions. Submitted records preserve their submission settings. Resubmission snapshots the current settings again.
- Submitting a commitment before ratings are available is allowed after an explicit warning. Applicable rows require an indicator and target. Invalid numeric ratings cannot be submitted.

Verify all formulas, thresholds, applicability, weights, and rating authority against current MMSU policy before any real use. The printable form is not an official signed record.

## Review queues and analytics

HR, Vice President, and President queues show only their exact review stage, organized as expandable **College/office → Department → Employee** groups. Search, period, college, department and employment-type filters preserve these groups. HR performs the final detailed review, VP approves records forwarded by HR, and President approves records forwarded by VP. Current employee college/department assignments govern routing, visibility and reporting; submitted form snapshots retain their historical text.

HR Reports & Analytics remains a separate sidebar page. Dean Analytics is a separate college-restricted page. Both show roster progress, department counts and completion percentages, submission/completion and status charts, final-rating distribution, department leaders, and employee drilldown. Dean rankings include teaching employees only. HR can download the filtered **employee progress CSV**, including employees who have no IPCR.

Each employee has explicit `periodIds`, managed through Administrator → Users → Assigned evaluation periods. Only active employee accounts assigned to the selected period are eligible. The initial current-period roster has 20 employees across three colleges/offices and six departments: 15 have submitted (75%), three are completed (15%), four have not started, and one has only a draft. New evaluation periods have no employees until assigned by the administrator. Creating/submitting an IPCR requires period membership.

Submission compliance = eligible employees with at least one submission ÷ eligible roster. Completion = eligible employees with a presidentially approved completed record ÷ eligible roster. Returned submissions still count as submitted. Duplicate records never inflate employee counts. College, department, period and employment-type filters define the denominator; progress, submission and search filters narrow charts/list/export without shrinking that denominator. Department summary and leader tables deliberately retain the selected organizational roster, as labeled in the UI.

For one employee-period entry, a completed approved record takes precedence, followed by the latest non-draft record, then the latest draft; no records means **Not started**. Submitted means awaiting Department Head; Under review identifies Dean, HR, VP or President. A finally approved IPCR can be completed but unrated: it counts toward completion, not rating charts or rankings.

On presidential approval, the computed rating is snapshotted as `finalRating`. Rankings require Completed status, the presidential final-approval audit entry, and a numeric final rating. The latest completed rated IPCR per employee is used; draft/pending ratings do not affect rankings. All ties are displayed (full numeric precision, tolerance 1e-9). Empty departments display **No final ratings yet.** Rankings are labeled **Prototype results** until MMSU confirms computation and eligibility rules. Ana Bautista and Marco Valdez demonstrate a 4.80 tie in Civil Engineering.

Existing storage receives a one-time additive `rosterVersion:1` migration. Legacy employees receive assignments to the existing evaluation periods; existing final-approved records receive a rating snapshot from their preserved submission settings. New fictional users/records are appended; existing forms, IDs, histories, metadata and customizations are not reset. Administrators should review migrated assignments. Tests use isolated profiles and never reset the user's browser data.

Additional examples: `cas-dean@demo.mmsu.edu.ph` (Arts and Sciences), `eng-civil-head@demo.mmsu.edu.ph` (Civil Engineering), `civil-4@demo.mmsu.edu.ph` (employee with no IPCR). All use Demo@2026. Every additional sample account is listed at sign-in.

## Administration and storage

Administrator can add/edit users, activate/deactivate accounts, add/edit units and departments, add/edit evaluation periods, choose the current period, and edit rating settings. In-use departments cannot be removed. The active administrator cannot deactivate their own account or remove their own administrator role. Unit renaming updates current assignments; preserved submission snapshots keep historical text.

The keys are `mmsu-eipcr-v1` (records/configuration/notifications) and `mmsu-eipcr-session` (active sample account). Refresh keeps data and the active account; logout removes only the session. Multiple tabs share the same sample session. Browser clearing, private-browsing closure, or changing origin may remove or isolate data. The Administrator's confirmed **Reset Demo Data** replaces everything with initial sample data and ends the session.

This is demonstration access control, not production security: passwords are visible, all data is in localStorage, and browser tools can change records or roles. Do not use sensitive personnel information. There is no connection to MMSU systems.

## Project layout

```text
dist/
  index.html              Entry page
  html/app.html           Application HTML shell
  css/app.css             Responsive university theme and print styles
  css/form.css            Reference-form layout, green palette, and paper styles
  css/analytics.css       Grouped queues and responsive reporting
  js/progress.js          Roster, migration, scope, totals and final rankings
  js/analytics-ui.js      Grouped review queues and HR/Dean analytics
  js/model.js             Source: records, ratings, workflow and permissions
  js/app.js               Source: pages, forms, reporting and interaction
  js/form-ui.js           Source: editor, grouped table, summary, and sign-offs
  js/reference.js         Extracted reference functions; no personal data
  js/prototype.js         Generated classic script, supports direct HTML opening
  assets/favicon.svg      Local application mark (not an official university seal)
tests/
  model.test.mjs          Rating, workflow, scope, snapshots and reporting checks
  browser.mjs             End-to-end browser verification
build.mjs                 Rebuilds the classic JS bundle without dependencies
server.mjs                Optional local static-file server
```

After changing JavaScript sources, run `npm run build`. Run `npm test` for the model tests. `node tests/browser.mjs` runs browser tests against the active local server with Playwright and Chromium available; `PLAYWRIGHT_PATH` and `CHROMIUM_PATH` can point to installations on another machine. Browser tests use an isolated browser profile, not your normal prototype records.

## Features requiring a real backend later

Institutional authentication and authorization, protected credential storage, server-enforced assignments, durable database storage and backups, concurrent-edit conflict handling, tamper-resistant audit history, attachment evidence, official electronic signatures, notification delivery, authoritative personnel/period rosters, approved policy rules, privacy/retention controls, and integration with MMSU systems. These are not implemented or implied by this frontend prototype.
