# InsightBoard Security Architecture

## 1. Authentication & Multi-Tenant Authorization
- **Session Protection:** Session-based authentication with CSRF tokens on all state-changing POST/PATCH/DELETE requests.
- **Strict Role-Based Access Control (RBAC):**
  - Roles: `owner`, `admin`, `analyst`, `viewer`.
  - Enforced via Laravel Policies (`WorkspacePolicy`, `DatasetPolicy`, `DashboardPolicy`, `ReportPolicy`, `ExportJobPolicy`).
- **Insecure Direct Object Reference (IDOR) Mitigation:**
  - Resource access is strictly scoped to the active workspace membership of the authenticated user.
  - UUID identifiers prevent enumerable resource scanning.

## 2. CSV File Upload & Parsing Safeguards
- **File Constraints:**
  - Configurable file size limits (`CSV_MAX_FILE_SIZE_MB=50`).
  - Row limits (`CSV_MAX_ROWS=100000`) and column limits (`CSV_MAX_COLUMNS=100`).
  - Allowed extension: strictly `.csv`.
- **Private Storage:**
  - Uploaded files are stored outside the public document root in `storage/app/private/datasets/`.
  - Original filenames are sanitized and files are referenced by UUIDs.
- **No Formula / Script Execution:**
  - Uploaded files are treated purely as passive tabular text. No macros or formulas are parsed or executed during ingestion.

## 3. Spreadsheet Formula Injection Neutralization (CSV Export)
- Untrusted text cells that begin with formula execution trigger characters (`=`, `+`, `-`, `@`, `\t`, `\r`) are automatically escaped by prefixing them with a single quote `'` in `ExportDatasetJob`.
- Legitimate numeric values (such as negative numbers `-5.25`) are type-checked and preserved without corruption.

## 4. Database Query Engine & Parameterization
- **Strict Whitelist Field Resolution:** Dynamic dimensions and measures in `ChartQueryEngine` are verified against `dataset_columns` records belonging to the specific dataset.
- **No Raw SQL Concatenation:** All user-supplied filter values and JSON path targets are bound using PDO parameterized statements.

## 5. Microservice Token Protection
- The Python FastAPI analytics service requires server-to-server token authentication (`Authorization: Bearer <TOKEN>` or `X-Analytics-Token`).
- Constant-time string comparison (`hmac.compare_digest`) prevents timing side-channel attacks.
- Internal error responses are sanitized to prevent leakage of internal server paths or stack traces.

