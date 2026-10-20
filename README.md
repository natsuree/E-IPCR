# E-IPCR · Mariano Marcos State University

E-IPCR is the web system for preparing, submitting, reviewing and approving the **Individual Performance Commitment and Review (IPCR)** form at Mariano Marcos State University (MMSU), Batac City, Ilocos Norte.

**Approval route:** Employee → Department Head → Dean/Director → HR → Completed. HR gives the final approval. A returned IPCR goes back to the employee and starts again at the Department Head when resubmitted.

**Stack:** Laravel 12 (PHP 8.2+), Blade, Bootstrap 5 with SCSS through Vite, Chart.js, `barryvdh/laravel-dompdf` for PDFs. It runs on SQLite locally; MySQL or MariaDB is recommended for production.

---

## Local setup

```bash
composer install
npm install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

In `.env`, keep `APP_ENV=local`, `DB_CONNECTION=sqlite`, `MAIL_MAILER=log` and `QUEUE_CONNECTION=database`. Create an empty `database/database.sqlite` file if it does not exist, then build the database:

```bash
php artisan migrate --seed      # reference data + demo data (demo only runs when APP_ENV is local or testing)
npm run dev                      # keep running: Vite serves the CSS and JS
php artisan serve                # http://127.0.0.1:8000
php artisan queue:work           # optional: sends queued emails (to storage/logs/laravel.log locally)
```

`composer run dev` starts the server, queue worker, log viewer and Vite together.

To rebuild the local database from scratch (this **deletes every table**, so never do it on a database with real entries):

```bash
php artisan migrate:fresh --seed
```

### Seeders

| Seeder | Runs | Contents |
|---|---|---|
| `ReferenceDataSeeder` | every environment; safe to run again | Starting colleges, departments and offices; rating scale and rating settings; the first rating period (July – December 2026, open); a published Teaching template (Version 1) and an empty Non-teaching draft |
| `DemoDataSeeder` | `local` and `testing` only; refuses to run in production | Demo accounts, past rating periods (January – June 2025 to January – June 2026) with completed IPCRs, and current-period IPCRs at every step |

The reference data includes only the units used in the demo. The Administrator adds the rest of MMSU's colleges, offices and departments under **Colleges & Offices**, or before go-live you can add them to `ReferenceDataSeeder::UNITS`.

### Demo accounts (local only)

Password for every demo account: **`password`**. The login page lists them in a "Sample accounts" panel only when `APP_ENV=local`.

| Role | Name | Email | Unit |
|---|---|---|---|
| Teaching Employee | Sofia Mendoza | sofia.mendoza@mmsu.test | College of Engineering · Computer Engineering |
| Non-Teaching Employee | Luis Navarro | luis.navarro@mmsu.test | Records Office |
| Department Head | Daniel Ramos | daniel.ramos@mmsu.test | College of Engineering · Computer Engineering |
| Dean/Director | Elena Flores | elena.flores@mmsu.test | College of Engineering |
| HR Personnel | Mika Santos | mika.santos@mmsu.test | Human Resource Management Office |
| Administrator | Alex Rivera | alex.rivera@mmsu.test | System administration |

Also: Diana Torres, Leo Pascual, Mia Domingo (Computer Engineering), Nina Aquino (Civil Engineering), Bea Fernandez (Mathematics) and Grace Abad (Finance Office), all `first.last@mmsu.test`.

### Demo walkthrough

1. **Submit.** Log in as Sofia → **Create IPCR** → fill in every Actual Accomplishment → **Save draft** → **Submit IPCR** → **Confirm and submit**. Status Tracking shows the Department Head as the current step.
2. **Approve.** As Daniel Ramos, then Elena Flores: Review Queue → **Review** → rate every indicator → **Approve & forward**. As Mika Santos: Review Queue → Review → **Approve (final)**. The IPCR is **Completed** with a final rating; Sofia gets a notification at each step.
3. **Return and resubmit.** As Daniel, review Diana Torres's IPCR, write remarks and click **Return for correction**. As Diana, Status Tracking shows the remarks and **Revise and resubmit**; submitting again sends it back to the Department Head.
4. **Records.** Open **IPCR Records** as Daniel (15 records), Elena (25) and Mika (28). Each sees only their own scope. View a record and click **Download PDF**.
5. **Analytics.** As Daniel (`depthead.analytics`) or Elena (`dean.analytics`), open **Analytics**: summary cards, submission progress and deadline, rating distribution, section averages, the five lowest-rated indicators, trends across periods, your own review step, a department comparison (Dean/Director), and the employee and not-submitted tables. Filters stay in the address bar; **Export CSV** and **Print / Save as PDF** use the same filters.
6. **Template versions.** As Mika, **IPCR Templates** → **Start new draft** → **+ Add indicator** → **Publish template**. A new IPCR (log in as `mia.domingo@mmsu.test` → Create IPCR) uses the new version; Sofia's IPCR keeps the version it was created with.
7. **HR settings.** **Rating Periods** (create, open, close; one period open at a time) and **Rating Settings** (method, section weights and adjectival ranges, with change history).
8. **Administration.** As Alex: **Users** (add, edit, deactivate, resend the set-password email, **Import CSV** with a row-by-row preview), **Colleges & Offices**, and the **Audit Log**.

### Tests

```bash
php artisan test
```

The tests cover the approval chain, return and resubmit, double-approval protection, role access and other users' IPCR IDs, unit scope, template versioning, the rating computation and settings, CSV import, accounts and passwords, rating periods and deadline reminders, PDFs, and the audit log. They use an in-memory SQLite database and the demo data.

---

## Production deployment

Requirements: PHP 8.2+ with `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `dom`, `gd`, `zip`; Composer; Node.js 20+ (build step only); a web server pointing at `public/`; HTTPS.

1. **Environment.** Copy `.env.example` to `.env` and set at least:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://eipcr.example.edu.ph
   APP_TIMEZONE=Asia/Manila
   DB_CONNECTION=mysql   # plus DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   SESSION_SECURE_COOKIE=true
   QUEUE_CONNECTION=database
   MAIL_MAILER=smtp      # plus MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS
   ```

   With `APP_ENV=production` the app forces HTTPS links. Keep `.env` out of version control and readable only by the web server user.

2. **Install and build.**

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate          # first deployment only
   npm ci && npm run build
   php artisan migrate --force
   php artisan db:seed --class=ReferenceDataSeeder --force   # first deployment only; safe to rerun
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```

   Never run `migrate:fresh` or the demo seeder in production.

3. **First Administrator.** It asks for name, email and password; nothing is hard-coded:

   ```bash
   php artisan eipcr:create-admin
   ```

   The Administrator then adds units and accounts (one at a time or by CSV). Every new user gets an email with a link to set their own password.

4. **Queue worker** (sends notification emails). Run it under Supervisor or systemd so it restarts automatically:

   ```bash
   php artisan queue:work --tries=3 --max-time=3600
   ```

   After each deployment: `php artisan queue:restart`.

5. **Scheduler** (daily deadline reminders at 8:00 AM, and clean-up of expired password links). Add one cron entry for the web server user:

   ```cron
   * * * * * cd /path/to/E-IPCR && php artisan schedule:run >> /dev/null 2>&1
   ```

6. **Each later deployment:** `git pull`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache && php artisan route:cache && php artisan view:cache`, `php artisan queue:restart`.

7. **MMSU seal.** Put the official seal at `public/images/mmsu-seal.png` (square PNG). Until then a lettered placeholder is shown. The PDF uses the same file.

---

## Database backups

The database holds personal and performance data covered by the Data Privacy Act of 2012 (RA 10173). Back it up every day, keep copies off the server, encrypt them, and limit who can read them.

- **MySQL / MariaDB:**

  ```bash
  mysqldump --single-transaction --routines --triggers -u BACKUP_USER -p eipcr | gzip > /backups/eipcr-$(date +%F).sql.gz
  ```

  Run it from cron (for example at 1:00 AM) and delete copies older than your retention period (for example 30 daily and 12 monthly).

- **SQLite:** use `sqlite3 database/database.sqlite ".backup '/backups/eipcr-$(date +%F).sqlite'"` rather than copying the file while the app is running.
- Also back up `.env` (it holds `APP_KEY`; without it, encrypted values and sessions cannot be read) and `storage/app` if files are added later.
- **Test a restore** at least once each semester on a separate machine.

---

## How the rules are enforced

- **Roles:** each role has its own route group (`employee.*`, `depthead.*`, `dean.*`, `hr.*`, `admin.*`) behind the `role` middleware. Other roles get 403; guests are sent to the login page; deactivated users are signed out.
- **Analytics:** `App\Services\IpcrAnalytics` (grouped SQL counts and averages) with `App\Services\AnalyticsScope` serves HR Reports, the Department Head and Dean/Director Analytics pages and the dashboards. The scope comes from the signed-in user’s own unit and is applied in the query; a department or college ID outside it returns 403. "Employees" means active Teaching and Non-Teaching accounts in the scope that existed by the end of the rating period. Averages use the final rating stored on completed IPCRs only.
- **Scope:** `App\Policies\IpcrPolicy` plus the `visibleTo` / `awaiting` query scopes. Employees see only their own IPCRs; Department Heads their department; Deans/Directors their college; HR all units; the Administrator no IPCR content. Changing an ID in the URL returns 403.
- **Queues follow the role, not the person.** A queue belongs to whoever currently holds the Department Head or Dean/Director role for that unit. When the Administrator reassigns the role, waiting IPCRs move with it.
- **Units with no Department Head or Dean/Director account** skip that step, and the review history says why. HR always reviews.
- **Workflow safety:** submit, approve, return and publish each run in a database transaction, re-read the record, and change the status only if it is still what it was (a conditional update). A double click or two reviewers acting at once cannot act on the same step twice.
- **Template versions:** published versions are never edited. Changes go into a new draft version. Creating an IPCR copies the template's items into `ipcr_items`, so later template changes never alter it.
- **Final rating:** computed from the HR-editable **Rating Settings** (method, section weights, adjectival ranges) and stored on the IPCR, together with the settings used, when HR approves it. Later settings changes do not alter completed IPCRs.
- **Notifications:** every workflow event creates an in-app notification at once and a queued email.
- **Audit log:** sign-ins (including failed attempts and lockouts), account and unit changes, every IPCR action, template changes, rating period and settings changes. Passwords and tokens are never recorded.
- **Soft deletes** on users, templates and IPCRs; units and users are deactivated, not deleted.
