# InsightBoard — Business Intelligence & Analytics Platform

[![Live Demo](https://img.shields.io/badge/Live%20Demo-GitHub%20Pages-6366f1?style=for-the-badge&logo=github)](https://lokeshpuma.github.io/insightboard-bi/)
[![PHPUnit Tests](https://img.shields.io/badge/PHPUnit-24%20Passed%20(126%20assertions)-brightgreen?style=for-the-badge&logo=php)](tests/)
[![Python Tests](https://img.shields.io/badge/pytest-7%20Passed-brightgreen?style=for-the-badge&logo=python)](python-analytics/tests/)
[![Stack](https://img.shields.io/badge/Stack-Laravel%2013%20%7C%20MySQL%209.3%20%7C%20FastAPI%20%7C%20Chart.js-blue?style=for-the-badge)]()

InsightBoard is a full-stack, enterprise-grade Business Intelligence (BI) and data analytics web platform. It empowers teams to ingest raw CSV data, perform automated computational profiling and schema inference via an isolated Python analytics microservice, run sub-second dynamic aggregations, build interactive multi-widget dashboards with Chart.js, and export formula-safe analytical reports.

---

## 🌟 Interactive Live Showcase (GitHub Pages)

Experience InsightBoard right in your browser without installing local dependencies:
👉 **[Launch InsightBoard GitHub Pages Showcase](https://lokeshpuma.github.io/insightboard-bi/)**

### Sample Demo Login Credentials
| Role | Email | Password | Access Level |
|---|---|---|---|
| **Workspace Owner** | `demo@insightboard.local` | `password` | Full administration, dataset upload, dashboard editing, member invitations, formula-safe exports |
| **Workspace Admin** | `admin@insightboard.local` | `password` | Dataset management, dashboard building, and member management |
| **Workspace Viewer** | `viewer@insightboard.local` | `password` | Read-only access to dashboards, reports, and data profiles |

---

## 📑 Table of Contents
1. [System Architecture](#1-system-architecture)
2. [Technology Stack](#2-technology-stack)
3. [Core Capabilities & Features](#3-core-capabilities--features)
4. [Step-by-Step Installation on Any Machine](#4-step-by-step-installation-on-any-machine)
   - [macOS](#macos-setup)
   - [Ubuntu / Debian Linux](#ubuntu--debian-linux-setup)
   - [Windows (WSL2 / Native)](#windows-setup)
5. [Starting the Application](#5-starting-the-application)
   - [Option A: One-Command Start Script](#option-a-one-command-start-script-recommended)
   - [Option B: Multi-Terminal Launch](#option-b-multi-terminal-launch)
   - [Option C: Docker Compose](#option-c-docker-compose)
6. [Complete Step-by-Step User Walkthrough](#6-complete-step-by-step-user-walkthrough)
7. [API Reference](#7-api-reference)
8. [Automated Testing Suite](#8-automated-testing-suite)
9. [Deploying Frontend to GitHub Pages](#9-deploying-frontend-to-github-pages)
10. [Repository Structure & Git Setup](#10-repository-structure--git-setup)

---

## 1. System Architecture

InsightBoard separates high-concurrency web requests, RBAC, and data visualization from heavy statistical compute through a decoupled two-tier microservice architecture:

```text
                                Browser Client (User)
                                          │
                                          │ HTTPS (Session, Blade UI, Chart.js)
                                          ▼
                             Laravel Web App (PHP 8.5)
                 ┌──────────────────────────────────────────────────┐
                 │ • Authentication & Multi-Tenant Workspace RBAC   │
                 │ • CSV Pre-validation & Delimiter Detection       │
                 │ • Async Queue Jobs (ImportDatasetJob)            │
                 │ • Dynamic Parameterized Aggregation Engine       │
                 │ • Interactive Dashboard & Widget Configuration   │
                 │ • Formula Injection Protected CSV Exporter       │
                 └──────────────┬───────────────────┬───────────────┘
                                │                   │
               Local Disk / NAS │                   │ SQL / JSON Ingestion
        ┌───────────────────────┴──┐     ┌──────────┴───────────────────────┐
        │ Private Storage Disk     │     │ MySQL 9.3 Database Server        │
        │ • Raw uploaded CSVs      │     │ • users, workspaces, memberships │
        │ • Generated export files │     │ • datasets, dataset_columns      │
        └──────────────────────────┘     │ • dataset_rows (JSON records)    │
                                ▲        │ • dashboards, widgets, reports   │
                 Multipart File │        │ • audit_logs, export_jobs        │
                                │        └──────────────────────────────────┘
                 Laravel Queue  │
                 Import Job     │ POST /internal/v1/analyze
                                │ Authorization: Bearer <TOKEN>
                                ▼
                   Python FastAPI Analytics Service
        ┌───────────────────────────────────────────────────────────┐
        │ • Internal microservice (Port 8001, private network)      │
        │ • pandas & NumPy computational pipeline                   │
        │ • Auto schema type inference (int, decimal, date, etc.)   │
        │ • Missing value, duplicate, and null ratio analysis       │
        │ • Descriptive statistics (mean, median, stddev, IQR)      │
        │ • Frequency distributions & Pearson correlation matrix   │
        │ • Zero persistent storage (stateless processing)         │
        └───────────────────────────────────────────────────────────┘
```

---

## 2. Technology Stack

| Layer | Component | Version | Purpose |
|---|---|---|---|
| **Backend** | PHP / Laravel | PHP 8.5.12 / Laravel 13.42 | Authentication, RBAC, API endpoints, background queue, aggregation builder |
| **Database** | MySQL | MySQL 9.3.0 | Relational schemas, workspace isolation, chunked JSON dataset rows |
| **Analytics Engine** | Python / FastAPI | Python 3.13.7 / FastAPI 0.162 | High-performance computational data profiling and statistical matrix generation |
| **Statistical Libraries**| pandas / NumPy | pandas 2.3.3 / numpy 2.5.4 | Chunked parsing, IQR outlier calculation, Pearson correlation |
| **CSV Processing** | League CSV | League CSV 9.29 | Memory-safe chunked streaming upload and formula-sanitized streaming export |
| **Frontend UI** | Blade + Vanilla JS + CSS | Custom Modern UI | Clean responsive layout with sidebar navigation and modal builders |
| **Visualizations** | Chart.js | Chart.js 4.4.x | Line, Bar, Horizontal Bar, Area, Pie, Doughnut, Scatter, and KPI metric cards |
| **Testing** | PHPUnit & pytest | PHPUnit 12.5 / pytest 8.4 | Unit, feature, integration, and E2E regression verification |

---

## 3. Core Capabilities & Features

### 📁 1. Intelligent Dataset Ingestion
- Supports `.csv` and `.txt` files up to 50 MB, 100,000 rows, and 100 columns.
- Auto-detects delimiters (`,`, `;`, `\t`, `|`).
- Real-time pre-import preview showing the first 5 parsed rows and detected column headers.
- Background asynchronous processing with job progress states (`pending` ➔ `processing` ➔ `ready` / `failed`).
- Strict storage privacy: raw uploaded files are stored on Laravel's private storage disk and are inaccessible via public URLs.

### 🧠 2. Automated Python Statistical Profiling
When a dataset finishes parsing, the background worker invokes the Python microservice to generate an in-depth statistical profile:
- **Column Type Inference:** Identifies `integer`, `decimal`, `boolean`, `date`, `datetime`, `string`, and `identifier`.
- **Quality Metrics:** Missing counts, null ratios, unique values, and row duplication metrics.
- **Descriptive Statistics:** Calculates mean, median, standard deviation, minimum, maximum, 25th percentile (Q1), and 75th percentile (Q3).
- **Categorical Distribution:** Top value frequency distributions.
- **Correlation Matrix:** Pearson correlation coefficients across numerical columns.
- **Outlier Detection:** Highlights candidate anomalies using the Interquartile Range (IQR) rule ($1.5 \times \text{IQR}$).

### 🔍 3. Data Explorer
- Search and sort across any column with paginated views.
- Real-time parameterized filters (`equals`, `contains`, `greater than`, `less than`).
- Clean inspection of raw records and data distributions.

### 📊 4. Visual Dashboard Builder & Chart Query Engine
- Design flexible, multi-widget dashboards with 8 visualization formats:
  - **KPI Metric Card** (with customizable trend comparison)
  - **Vertical Bar Chart** & **Horizontal Bar Chart**
  - **Line Chart** & **Area Chart**
  - **Pie Chart** & **Doughnut Chart**
  - **Scatter Plot**
- Secure query builder supporting 6 aggregations: `count`, `distinct_count`, `sum`, `avg`, `min`, and `max`.
- Dynamic grouping across dimensions and temporal bucketing (`day`, `week`, `month`, `year`).
- **Interactive Global Dashboard Filter Bar:** Filter by territory, category, or date range, instantly updating all compatible widgets.

### 📑 5. Saved Reports & Secure Formula-Safe CSV Export
- Save specific chart configurations, views, and aggregate summaries as reusable reports.
- Asynchronously generate downloadable CSV exports.
- **CSV Formula Injection Mitigation:** Neutralizes formula injection exploits (e.g. `=cmd|' /C calc'!A0`, `+`, `-`, `@`) by automatically prefixing suspicious text cells with a single quote (`'`), while leaving valid numerical negative and positive values intact.

### 👥 6. Multi-Tenant Workspaces & Audit History
- Workspaces provide full data isolation between client organizations or teams.
- Granular Role-Based Access Control:
  - **Owner:** Full ownership, billing, member management, dataset deletion.
  - **Admin:** Upload datasets, build dashboards, manage reports, invite members.
  - **Analyst:** Upload datasets, create widgets, run queries, export reports.
  - **Viewer:** Read-only access to dashboards, reports, and profiles.
- Comprehensive audit trail recording user logins, file uploads, dataset deletions, dashboard updates, and member invitations.

---

## 4. Step-by-Step Installation on Any Machine

Follow the instructions below matching your operating system.

### macOS Setup

#### 1. Install System Dependencies
Make sure you have [Homebrew](https://brew.sh) installed, then run:
```bash
brew update
brew install php composer mysql python@3.13
```

#### 2. Start MySQL
```bash
brew services start mysql
```

#### 3. Create MySQL Databases
Log into your local MySQL instance:
```bash
mysql -u root
```
Run the following SQL commands:
```sql
CREATE DATABASE insightboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE insightboard_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

---

### Ubuntu / Debian Linux Setup

#### 1. Install System Dependencies
```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring \
    php8.3-curl php8.3-zip composer mysql-server python3 python3-venv python3-pip git
```

#### 2. Start MySQL & Configure User
```bash
sudo systemctl start mysql
sudo mysql -u root
```
Create databases and grant access:
```sql
CREATE DATABASE insightboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE insightboard_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
FLUSH PRIVILEGES;
EXIT;
```

---

### Windows Setup

#### Recommended: WSL2 (Ubuntu)
Run WSL2 in Windows Terminal and follow the [Ubuntu / Debian Linux Setup](#ubuntu--debian-linux-setup) instructions above.

#### Native Windows:
1. Install **PHP 8.2+** from [windows.php.net](https://windows.php.net/download/) and enable `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `zip` in `php.ini`.
2. Install **Composer** via [getcomposer.org](https://getcomposer.org/Composer-Setup.exe).
3. Install **MySQL Server 8.0+ / 9.x** via MySQL Installer.
4. Install **Python 3.11+** from [python.org](https://www.python.org/downloads/) (check "Add Python to PATH").

---

### Project Setup (All Operating Systems)

#### 1. Clone Repository
```bash
git clone https://github.com/lokeshpuma/insightboard-bi.git
cd insightboard-bi
```

#### 2. Install PHP Dependencies
```bash
composer install
```

#### 3. Configure Environment File
```bash
cp .env.example .env
php artisan key:generate
```
Open `.env` and verify your database and analytics token settings:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=insightboard
DB_USERNAME=root
DB_PASSWORD=

# Python Analytics Microservice Configuration
ANALYTICS_SERVICE_URL=http://127.0.0.1:8001
ANALYTICS_SERVICE_TOKEN=insightboard-internal-secret-token-change-in-production
```

#### 4. Run Migrations & Database Seeders
```bash
php artisan migrate
php artisan db:seed
```
*This seeds demo workspaces, users (`demo@insightboard.local`), realistic sales transaction datasets, and pre-built interactive dashboards.*

#### 5. Setup Python Analytics Virtual Environment
```bash
cd python-analytics
python3 -m venv venv

# On macOS / Linux:
source venv/bin/activate
# On Windows PowerShell:
# .\venv\Scripts\Activate.ps1

pip install -r requirements.txt
cd ..
```

---

## 5. Starting the Application

You can start the application using any of the three options below:

### Option A: One-Command Start Script (Recommended)

On macOS and Linux, start all three services simultaneously with the included `run.sh` script:
```bash
chmod +x run.sh
./run.sh
```
This automatically:
1. Checks that MySQL is running.
2. Spawns the Python Analytics Microservice on `http://127.0.0.1:8001`.
3. Starts the Laravel Queue Worker for async ingestion and exports.
4. Launches the Laravel Web Server at `http://localhost:8000`.
5. Gracefully terminates all background processes upon pressing `Ctrl+C`.

---

### Option B: Multi-Terminal Launch

Run each component in its own terminal window:

**Terminal 1 — Python Analytics Microservice:**
```bash
cd python-analytics
./venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8001
```

**Terminal 2 — Laravel Queue Worker:**
```bash
php artisan queue:work --sleep=2 --tries=3
```

**Terminal 3 — Laravel Web Application:**
```bash
php artisan serve --port=8000
```

Now open your browser and navigate to: **`http://localhost:8000`**

---

### Option C: Docker Compose

For a zero-dependency containerized setup:
```bash
docker compose up -d --build
```
This provisions:
- `insightboard-app`: PHP 8.3 FPM application container
- `insightboard-web`: Nginx web server on `http://localhost:8000`
- `insightboard-queue`: Dedicated queue worker container
- `insightboard-analytics`: Internal FastAPI analytics microservice
- `insightboard-db`: MySQL 9.3 database container

---

## 6. Complete Step-by-Step User Walkthrough

### 1. Signing In
1. Open `http://localhost:8000/login`.
2. Enter the demo credentials:
   - **Email:** `demo@insightboard.local`
   - **Password:** `password`
3. Click **Sign In** to access the Acme Analytics workspace.

### 2. Uploading a New Dataset
1. Click **Datasets** in the left sidebar, then click **+ Upload Dataset**.
2. Drag & drop any CSV file (or select one from your machine).
3. The client previews the first rows and detects the delimiter.
4. Give your dataset a descriptive name (e.g. *Q4 Global Sales Performance*).
5. Click **Confirm & Upload**. The file is saved to private storage and queued for ingestion.
6. Refresh the page or view the status badge: once processing completes, it changes to **Ready**.

### 3. Reviewing Automated Statistical Profiles
1. In the Datasets list, click on any dataset name.
2. Click the **Profile** tab to inspect:
   - Total rows, columns, and file size.
   - Per-column type inference and missing value percentages.
   - Numerical statistics: Mean, Median, Standard Deviation, Min, Max, and IQR.
   - Categorical top-value frequency tables.
   - Correlation Matrix highlighting strong positive/negative relationships between numerical fields.

### 4. Exploring Raw Data with the Data Explorer
1. Click the **Data Explorer** tab on the dataset page.
2. Type in the filter boxes to filter by value (e.g., `territory equals EMEA` or `amount greater than 500`).
3. Click column headers to sort ascending or descending.

### 5. Building Interactive Dashboards & Widgets
1. Click **Dashboards** in the left sidebar, then click **+ New Dashboard**.
2. Enter a title (e.g. *Executive Sales Dashboard*) and click **Create**.
3. Click **+ Add Widget**:
   - Choose a title (e.g. *Revenue by Region*).
   - Select visualization type: **Bar Chart**, **Line Chart**, **Pie Chart**, **KPI Card**, etc.
   - Select the target dataset.
   - Choose your **Metric** (e.g. `sum` of `amount`).
   - Choose your **Dimension / Group By** (e.g. `territory`).
   - (Optional) Select a date column and interval (`month`, `quarter`, etc.).
4. Click **Create Widget**. The chart renders immediately using Chart.js.

### 6. Using Dynamic Dashboard Filters
1. Notice the **Filter Bar** at the top of your dashboard.
2. Select a territory (e.g., `North America`) or category.
3. Click **Apply Filters**: all charts linked to that dataset refresh their aggregations in real time.

### 7. Generating & Downloading Formula-Safe Reports
1. Click **Reports** in the left sidebar.
2. View existing reports or click **+ New Report** to save a reusable snapshot.
3. Click **Export CSV**: the export job runs in the background.
4. When finished, click **Download**. Open the CSV in Excel or Google Sheets — all formula injection vectors (`=`, `+`, `-`, `@`) have been safely neutralized.

---

## 7. API Reference

All requests must include an authenticated session cookie or API token.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/datasets` | List workspace datasets |
| `POST` | `/datasets` | Upload and queue CSV file |
| `GET` | `/datasets/{id}` | Retrieve dataset metadata & columns |
| `GET` | `/datasets/{id}/profile` | View statistical profile generated by Python service |
| `GET` | `/datasets/{id}/rows` | Paginated rows with parameterized filtering |
| `POST` | `/dashboards` | Create a new dashboard |
| `POST` | `/dashboards/{id}/widgets` | Add a chart widget |
| `POST` | `/api/query` | Execute dynamic aggregation query for widgets |
| `POST` | `/reports` | Save reusable report |
| `POST` | `/reports/{id}/export` | Queue formula-safe CSV export job |
| `GET` | `/exports/{id}/download` | Download completed export CSV |

---

## 8. Automated Testing Suite

InsightBoard includes automated tests for both the Laravel backend and the Python analytics microservice.

### Run Laravel Backend Tests (PHPUnit)
```bash
php artisan test
```
**Test Summary (24 tests, 126 assertions):**
- `Tests\Feature\AuthTest`: Login, logout, session security.
- `Tests\Feature\PasswordResetTest`: Password reset token lifecycle, expiration & secure updates.
- `Tests\Feature\ApiTokenTest`: Laravel Sanctum API token issuance, Bearer authentication & revocation.
- `Tests\Feature\WorkspaceAccessTest`: Multi-tenant isolation and RBAC role boundaries.
- `Tests\Feature\CsvImportTest`: Upload validation, delimiter detection, chunked ingestion.
- `Tests\Feature\StatisticalAccuracyTest`: Mathematical accuracy of mean, median, quartiles & IQR outlier detection.
- `Tests\Feature\ChartQueryEngineTest`: Aggregations (`sum`, `avg`, `min`, `max`, `count`, `median`), temporal grouping.
- `Tests\Feature\DashboardTest`: Dashboard creation, widget updates, layout reordering, deletion policies.
- `Tests\Feature\ExportTest`: CSV streaming, spreadsheet formula injection neutralization.
- `Tests\Feature\FilteredExportTest`: Filter-aware CSV exports matching report criteria.
- `Tests\Feature\IntegrationE2ETest`: Full end-to-end pipeline verification.

### Run Python Analytics Tests (pytest)
```bash
cd python-analytics
PYTHONPATH=. ./venv/bin/pytest tests/
cd ..
```
**Test Summary (7 tests):**
- Schema type inference (`integer`, `decimal`, `date`, `string`).
- Descriptive statistics and quartile calculations.
- Missing value, duplicate, and null percentage metrics.
- Pearson correlation matrix correctness.
- IQR anomaly detection logic.
- Bearer token microservice security.

---

## 9. Deploying Frontend to GitHub Pages

InsightBoard provides a static interactive showcase located in the [`docs/`](docs/) directory, ready to deploy to GitHub Pages:

### Step 1: Verify Assets in `/docs`
The `/docs` directory includes:
- `docs/index.html`: Interactive client showcase with demo login, datasets, explorer, dynamic Chart.js dashboards, and CSV export.
- `docs/.nojekyll`: Instructs GitHub Pages to serve static files without Jekyll processing.
- `docs/assets/`: Embedded styles, favicon, and Chart.js bundle.

### Step 2: Enable GitHub Pages on Your Repository
1. Go to your GitHub repository: [github.com/lokeshpuma/insightboard-bi](https://github.com/lokeshpuma/insightboard-bi)
2. Click on **Settings** (top navigation bar).
3. In the left sidebar, click **Pages** (under the "Code and automation" section).
4. Under **Build and deployment** > **Source**, select **Deploy from a branch**.
5. Under **Branch**:
   - Branch: `main`
   - Folder: `/docs`
6. Click **Save**.
7. In 1–2 minutes, your live demo will be published at:
   **`https://lokeshpuma.github.io/insightboard-bi/`**

---

## 10. Repository Structure & Git Setup

```text
insightboard-bi/
├── app/                        # Laravel application controllers, models, jobs, policies
│   ├── Http/Controllers/      # Dataset, Dashboard, Query, Report, Workspace controllers
│   ├── Jobs/                   # ImportDatasetJob, ExportReportJob
│   ├── Models/                 # Eloquent models (Dataset, Widget, Report, AuditLog, etc.)
│   ├── Policies/               # Granular RBAC policies for resources
│   └── Services/               # AnalyticsClient, ChartQueryEngine, CsvProcessor
├── bootstrap/                  # Framework bootstrapping & providers
├── config/                     # Application, database, queue, and service configs
├── database/
│   ├── migrations/             # Schema definitions for MySQL
│   └── seeders/                # Demo data seeder (users, datasets, dashboards)
├── docs/                       # GitHub Pages live deployment directory
│   ├── index.html              # Standalone interactive showcase with demo credentials
│   ├── .nojekyll               # GitHub Pages Jekyll bypass flag
│   └── assets/                 # Chart.js, custom stylesheet, and vector logos
├── docker/                     # Dockerfile and Nginx configuration
├── compose.yaml                # Multi-container Docker Compose file
├── public/                     # Public web assets (favicon.svg, app.css, app.js)
├── python-analytics/           # Python FastAPI Analytics Microservice
│   ├── app/                    # FastAPI routes, schemas, profiler engine
│   ├── tests/                  # Pytest test suite for statistical calculations
│   └── requirements.txt        # Python package dependencies
├── resources/views/            # Blade UI templates (dashboards, datasets, reports)
├── routes/web.php              # Web and API routing definitions
├── run.sh                      # One-command developer startup script
├── tests/                      # PHPUnit feature and integration test suite
└── README.md                   # Project documentation & operational manual
```

### Git Remote Configuration
To push this project to the official GitHub repository:
```bash
git init -b main
git remote add origin https://github.com/lokeshpuma/insightboard-bi.git
git add .
git commit -m "feat: complete InsightBoard BI platform and GitHub Pages showcase"
git push -u origin main
```

---

## 🔒 Security & Privacy Notice
- **Formula Injection Mitigation:** InsightBoard protects spreadsheet users by prefixing formula-triggering characters (`=`, `+`, `-`, `@`) with `'`.
- **Private Data Storage:** Ingested CSV files and export archives are strictly isolated in `storage/app/private/` with no direct public access.
- **Service Isolation:** The Python analytics microservice runs as an internal compute worker requiring a shared secret `Bearer` token.
