# InsightBoard — Architecture & System Design

## 1. System Overview

InsightBoard is a Business Intelligence (BI) platform enabling workspaces to upload CSV datasets, perform automated profiling and quality assessment via a Python microservice, store structured records and schema in MySQL, query data aggregations dynamically, and build interactive dashboards and reports using Chart.js.

```
                           Browser (User Client)
                                     |
                                     | HTTPS / Session & CSRF
                                     v
                        Laravel Web Application (PHP)
                  ┌────────────────────────────────────────┐
                  │ - Web UI (Blade + Vanilla JS + Chart.js)│
                  │ - Auth & Multi-tenant Workspace RBAC   │
                  │ - Dataset, Dashboard & Report APIs     │
                  │ - CSV Pre-validation & Upload Stream   │
                  │ - Database Queue Worker                │
                  │ - Dynamic Aggregation Query Builder    │
                  │ - CSV Export & Formula Injection Guard │
                  └──────┬──────────────────────┬──────────┘
                         │                      │
        Private Storage  │                      │  SQL / JSON Queries
  ┌──────────────────────┴───────┐      ┌───────┴──────────────────────┐
  │ Local Private Disk           │      │ MySQL 8+ Database            │
  │ - Original uploaded CSV files│      │ - users, workspaces, members │
  │ - Temp export files          │      │ - datasets, dataset_columns  │
  └──────────────────────────────┘      │ - dataset_rows (JSON store)  │
                         ▲              │ - dashboards, widgets        │
                         │              │ - reports, export_jobs       │
          Multipart File │              │ - audit_logs                 │
                         │              └──────────────────────────────┘
                         │
        Laravel Queue Job (ImportDatasetJob)
                         │ POST /internal/v1/analyze (Bearer Token)
                         ▼
             Python FastAPI Analytics Service
  ┌────────────────────────────────────────────────────────────┐
  │ - Internal microservice (Port 8000, private network)       │
  │ - Bearer Token Header Authentication                      │
  │ - Pandas & NumPy statistical profiling                    │
  │ - Inferred column types (integer, decimal, date, etc.)     │
  │ - Null counts, duplicate rows, descriptive stats           │
  │ - Correlation matrix, categorical frequencies             │
  │ - Strict error sanitization & no data persistence         │
  └────────────────────────────────────────────────────────────┘
```

---

## 2. Component Design

### 2.1 Web Layer (Laravel 11+ / PHP 8.x)
- **Routing & Middleware:** Web routes for Blade application, API routes under `/api/v1` for JSON endpoints. All routes guarded by session auth and `WorkspaceMembership` middleware.
- **Role-Based Access Control (RBAC):**
  - Roles: `owner`, `admin`, `analyst`, `viewer`.
  - Enforced via Laravel Policies (`WorkspacePolicy`, `DatasetPolicy`, `DashboardPolicy`, `ReportPolicy`, `ExportJobPolicy`).
- **Database Queue:** Database queue driver for processing asynchronous background jobs (`ImportDatasetJob`, `ExportDatasetJob`, `ReprofileDatasetJob`).

### 2.2 Storage & Data Persistence (MySQL 8+)
- **JSON Row Architecture:** Records from uploaded CSV files are stored as JSON documents inside `dataset_rows.row_data`. Field keys are normalized safe slugs (e.g., `col_0_order_amount`).
- **Schema Mapping:** `dataset_columns` maintains metadata: `field_key`, `original_name`, `display_name`, `inferred_type`, `semantic_role`, `null_count`, `distinct_count`, `statistics`, `sample_values`.
- **Safe Dynamic Aggregation:** The Chart Query Engine translates user-configured widget metrics (`count`, `distinct_count`, `sum`, `avg`, `min`, `max`) into parameterized MySQL JSON extraction queries (`JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.key'))`). Dynamic column names are strictly allowlisted against `dataset_columns`.

### 2.3 Analytics Service (Python FastAPI)
- **Internal API:**
  - `GET /internal/v1/health`
  - `POST /internal/v1/analyze` (multipart file upload, delimiter, encoding, bounded limits)
- **Profiling Engine:**
  - Missing value count & percentage.
  - Duplicate row count.
  - Data type inference with heuristic validation (e.g. integer vs decimal vs date vs datetime vs string).
  - Descriptive stats: count, mean, median, standard deviation, min, max, Q1, Q3.
  - Categorical value frequency distribution (limited to top-N categories).
  - Pearson correlation matrix across numeric dimensions.
- **Security:**
  - Token-protected endpoint (`X-Analytics-Token` / `Bearer`).
  - Strict upload limits (50 MB, 100,000 rows, 100 columns).
  - Non-persisted file processing (in-memory / temp spool with guaranteed cleanup).
  - Sanitized error responses without stack traces.

### 2.4 Frontend Layer
- **Blade Templating:** Semantic HTML5, accessible form controls, responsive CSS grid/flex layout with Tailwind/clean CSS.
- **Client Scripting:** Modular vanilla JavaScript.
- **Visualizations:** Chart.js 4.x handling:
  - Metric / KPI Cards
  - Bar Charts & Horizontal Bar Charts
  - Line Charts & Area Charts
  - Doughnut & Pie Charts
  - Scatter Charts
  - Interactive Filter Controller applying query parameter updates to widgets.

---

## 3. Database Schema Overview

1. `users` — Authentication credentials and user profile.
2. `workspaces` — Multi-tenant tenant accounts.
3. `workspace_members` — User-to-workspace mapping with role (`owner`, `admin`, `analyst`, `viewer`).
4. `workspace_invitations` — Tokenized workspace invitations with expiration.
5. `datasets` — Dataset metadata, status (`uploaded`, `processing`, `ready`, `failed`, `archived`), row/column counts, summary JSON.
6. `dataset_columns` — Inferred schema per column with stats and types.
7. `dataset_rows` — Chunked JSON rows (`dataset_id`, `row_number`, `row_data`).
8. `import_jobs` — Background import progress and logs.
9. `dashboards` — Dashboard records and layout configuration.
10. `dashboard_widgets` — Chart & KPI widget definitions with axis and aggregation configs.
11. `reports` — Saved report definitions with chart/table configurations.
12. `export_jobs` — Asynchronous CSV export tracking and private file references.
13. `audit_logs` — Activity trail recording actor, action, target resource, timestamp, and metadata.

---

## 4. Key Security & Reliability Measures

1. **Spreadsheet Formula Injection Neutralization:** CSV export fields starting with `=`, `+`, `-`, or `@` are safely prefixed with a single quote `'` for non-numeric text cells.
2. **Strict Parameterization & Allowlisting:** Chart queries and row filtering only accept column keys present in `dataset_columns`. Direct raw SQL is prohibited.
3. **Private File Storage:** Uploaded CSV files and export archives are kept in Laravel's non-public `storage/app/private` disk.
4. **IDOR & Multi-tenancy Scoping:** All controllers and queries scope resources through the authenticated user's active workspace membership.
5. **Idempotent Imports:** Re-running or retrying an import cleans up prior staging rows for that dataset before inserting to prevent duplication.
6. **Graceful Service Degradation:** If the Python service is offline, import jobs record a safe error and retry with bounded backoff, preventing system crashes.

