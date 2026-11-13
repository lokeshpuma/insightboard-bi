# InsightBoard API Documentation (v1)

Base URL: `/api/v1`

## Response Format

### Success Response
```json
{
  "success": true,
  "data": {},
  "message": "Request completed successfully"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Detailed error summary",
  "errors": {}
}
```

---

## Endpoints

### 1. Health Check
- **`GET /api/v1/health`**
  - Returns service status and timestamp.

### 2. Workspaces
- **`GET /api/v1/workspaces`**: List active workspaces for authenticated user.
- **`POST /api/v1/workspaces`**: Create new workspace.
- **`GET /api/v1/workspaces/{workspace}`**: Get workspace details.
- **`PATCH /api/v1/workspaces/{workspace}`**: Update workspace settings.
- **`GET /api/v1/workspaces/{workspace}/members`**: List workspace members.
- **`GET /api/v1/workspaces/{workspace}/overview`**: Real-time counts (datasets, rows, dashboards, reports).
- **`GET /api/v1/workspaces/{workspace}/audit-logs`**: Audit trail events (owner/admin only).

### 3. Datasets
- **`GET /api/v1/workspaces/{workspace}/datasets`**: List datasets in workspace.
- **`POST /api/v1/workspaces/{workspace}/datasets`**: Multipart CSV upload. Dispatches background import job. Returns `202 Accepted`.
- **`GET /api/v1/datasets/{dataset}`**: Dataset details.
- **`DELETE /api/v1/datasets/{dataset}`**: Delete dataset and underlying records.
- **`GET /api/v1/datasets/{dataset}/columns`**: Inferred column metadata.
- **`GET /api/v1/datasets/{dataset}/preview`**: First 10 rows preview.
- **`GET /api/v1/datasets/{dataset}/rows`**: Paginated rows with `filter_col`, `filter_op`, `filter_val`, `sort_col`, `sort_dir`.
- **`GET /api/v1/datasets/{dataset}/profile`**: Statistical profile summary, quality metrics, and correlations.
- **`POST /api/v1/datasets/{dataset}/reprofile`**: Enqueue reprofile job. Returns `202 Accepted`.

### 4. Dashboards & Widgets
- **`GET /api/v1/workspaces/{workspace}/dashboards`**: List dashboards.
- **`POST /api/v1/workspaces/{workspace}/dashboards`**: Create dashboard.
- **`GET /api/v1/dashboards/{dashboard}`**: Dashboard details and widget list.
- **`PATCH /api/v1/dashboards/{dashboard}`**: Update dashboard.
- **`DELETE /api/v1/dashboards/{dashboard}`**: Delete dashboard.
- **`POST /api/v1/dashboards/{dashboard}/widgets`**: Add chart/KPI widget.
- **`PATCH /api/v1/dashboard-widgets/{widget}`**: Update widget configuration.
- **`DELETE /api/v1/dashboard-widgets/{widget}`**: Delete widget.
- **`POST /api/v1/dashboard-widgets/{widget}/data`**: Query aggregated Chart.js formatted data with optional filter parameters.

### 5. Reports & Exports
- **`GET /api/v1/workspaces/{workspace}/reports`**: List saved reports.
- **`POST /api/v1/workspaces/{workspace}/reports`**: Save report definition.
- **`GET /api/v1/reports/{report}`**: Report details.
- **`DELETE /api/v1/reports/{report}`**: Delete report.
- **`POST /api/v1/exports`**: Start CSV export job (`resource_type`: `dataset` | `report`).
- **`GET /api/v1/exports/{exportJob}`**: Check export status and download readiness.

