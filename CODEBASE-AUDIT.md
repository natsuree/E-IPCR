# E-IPCR implementation audit

Baseline inspected before editing. Laravel HTTP entry point `public/index.php`, CLI `artisan`, route groups in `routes/web.php`. Controllers render Blade views, Eloquent stores real SQL records, reviews, template versions, notifications and rating snapshots. Vite output in `public/build` is generated from resources; vendor and archived files are not application source. PHPUnit feature tests use in-memory SQLite. The seeded examples run the workflow service.

| Source | Expected | Observed before revision | Source file | Decision |
|---|---|---|---|---|
| Scope p.7; Fig. 8 | Employee owns the IPCR; reviewers have unit scopes | Owner, department and college policies exist | app/Policies/IpcrPolicy.php | Keep |
| Fig. 8; corrected route | VPAA approves between Dean and HR | VPAA role and queue absent | app/Enums/Role.php; routes/web.php | Add |
| Fig. 9; corrected route | Every reviewer is required | Workflow forwards Dean directly to HR and skips unstaffed steps | app/Services/IpcrWorkflow.php | Fix |
| Fig. 9 | Each reviewer can return with remarks; correction restarts at Head | Remarks required at HTTP boundary; resubmission could skip Head | app/Http/Requests/Review/ReviewDecisionRequest.php; app/Services/IpcrWorkflow.php | Fix |
| Fig. 9; p.51 | Track actual decisions and notify next reviewer | Stored reviews and notifications; timeline omits VPAA | app/Models/Ipcr.php; app/Services/IpcrWorkflow.php | Fix |
| Fig. 8 | Admin manages configuration separately; no President approval | Separate admin routes; no President role/action found | routes/web.php | Keep |
| Fig. 9; saved records | Past decisions remain attributable | Existing completed and in-flight records predate VPAA | database/migrations; app/Models/Ipcr.php | Fix |
| Form p.50; templates p.53 | HR publishes targets; employees enter accomplishments | Draft templates, publication and per-record target copies work | app/Services/TemplateManager.php; app/Services/IpcrWorkflow.php | Keep |
| Scope; form p.50 | Attach MOV to its target | No evidence field; accomplishments only | app/Http/Requests/Employee/UpdateIpcrRequest.php | Add |
| Fig. 9 | Identify which target needs correction | Per-indicator remarks available during review and on the form | resources/views/ipcr/partials/sections.blade.php | Keep |
| Form p.50 | Consistent editable and printable layout | Blade form and print/PDF use same stored indicators and template | resources/views/ipcr/partials/document.blade.php | Keep |
| Template p.53 | Separate teaching and non-teaching versions | Version immutable after publication; copied targets persist | app/Models/IpcrTemplate.php | Keep |
| Scope; template assignments | Select appropriate college/office and designation template | Selection uses period and type only; no designation mapping | app/Models/IpcrTemplate.php | Add |
| Ratings; panel guidance | Configurable weights; freeze final result at HR | Existing calculation and saved basis; official weights and eligibility rules unspecified | app/Services/FinalRating.php | Needs confirmation |
| Reports pp.54–55 | Count eligible employees assigned to period, including missing IPCRs | Left join includes missing IPCRs; eligibility inferred from current account and date | app/Services/IpcrAnalytics.php | Fix |
| Reports pp.54–55 | Returned after submission counts as submitted | Returned omitted from numerator | app/Services/IpcrAnalytics.php | Fix |
| Reports pp.54–55 | Rank tied final approved department leaders | Distribution and averages work; department leaders absent | app/Services/IpcrAnalytics.php | Add |
| Reports pp.54–55 | VPAA institution-wide aggregate; individual access limited | No VPAA report | app/Services/AnalyticsScope.php | Add |
| Reports pp.54–55 | Filters, preview and CSV agree | Employee list and CSV share query; status/search only affect table | app/Http/Controllers/Hr/ReportController.php | Fix |
| Evaluation pp.38–44; UAT TC-03 | Verify four-stage approval and HR completion | Existing suite tests three reviewers; workbook ends at VPAA | tests/Feature/ApprovalWorkflowTest.php | Fix |

The latest user correction controls the routing discrepancy in Figures 8 and 9 and UAT TC-03. The proceedings provide recommendations. The original workbook and PDFs are reference materials. Stakeholders include reviewers and administrators; employee identifies the IPCR owner.

## Implementation and verification

The employee creates a draft from a published template. Each target retains its template wording, accomplishment, optional HTTP(S) MOV link and reviewer remarks. Submitting creates a history event and queues the Department Head. Each approval records the actor and forwards the IPCR to the next stage. Returning it requires remarks. Resubmission starts a new review cycle. HR completion stores the final number, adjective, section averages and calculation settings. The printable document includes the recorded VPAA sign-off when available.

The upgrade preserves completed IPCRs and historical review events. Pending records enter the first outstanding stage supported by the current submission cycle. Unstaffed stages wait for an administrator to assign a reviewer. Historical email and notification messages retain their original wording.

HR can select period members under Rating Periods → Employee roster. Opening a new period initializes the roster from eligible active employee accounts unless HR has already selected its members. For existing periods without an authoritative roster, reports label the account-based estimate provisional until HR saves the assignments. Unit access and employee type currently follow account assignments. Historical changes to unit or employment type require institutional records to reconstruct.

Template versions can be restricted by college/office, administrative designation, or both. The most specific matching published version wins, with the latest version resolving equal specificity. Publishing moves unsubmitted drafts of matching employees to the new version, keeping their accomplishments, MOV links and employee-added targets; submitted, returned and completed IPCRs retain their version. MMSU should confirm template precedence, scoring settings and ranking eligibility. One evidence URL can reference a target's file or evidence folder. The application does not host uploaded MOV files or control permissions on linked external documents.

HR, Dean and Department Head reports use the same filtered employee population for summary cards, full print preview and CSV. Rankings include tied employees with a completed IPCR, a stored final rating and a recorded HR completion. VPAA has institution-wide progress totals and an individual queue at the VPAA stage. Any broader access to individual records or evidence needs MMSU confirmation.

PHPUnit 11.5.57, PHP 8.2.12, isolated SQLite database: **67 tests passed, 545 assertions**. Covered four sequential approvals, rejection of early HR approval, all four returns followed by resubmission, scoped access, legacy workflow reconciliation, preserved history, evidence in print, version selection, approved ties and the 4-person roster (2 submitted, 1 completed). The tests exercise Laravel routes and rendered responses. Live email delivery, external evidence permissions and institutional ratings were outside this verification.

## Run the existing installation (PowerShell)

```powershell
cd C:\xampp\htdocs\E-IPCR
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. For queued mail, run `php artisan queue:work` in a second terminal in the same folder. Run `php artisan test` to execute the test suite. The existing compiled CSS and JavaScript remain usable; the changes are Laravel and Blade source. For this existing database, assign VPAA and any missing departmental reviewers through Administrator → Users. Demo accounts in the seeder are for fresh isolated installations.
