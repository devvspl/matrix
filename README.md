# DMS (Document Management System) API

## Project Description

A Laravel-based REST API backend for a Document Management System (DMS). The system handles the full lifecycle of scanned financial documents — from upload and classification to multi-level bill approval and punch entry. It is designed for organizations that process large volumes of physical documents (invoices, bills, expense claims, etc.) across departments and financial years.

Key workflow:
1. **Scan** — Users upload scanned document files (stored on AWS S3)
2. **Classify** — Classifiers assign document type, department, and approver
3. **Punch Entry** — Data entry operators punch structured data from the document
4. **Bill Approval** — Up to 3-level hierarchical approval workflow
5. **Finance Action** — Finance team takes final action on approved bills

---

## Features

- Employee-based authentication with financial year selection
- Financial year-scoped dynamic database tables (`y{year_id}_scan_file`)
- Main document upload to AWS S3
- Supporting file upload and management
- Document classification with department, sub-department, and doc type assignment
- Multi-level bill approval (L1 / L2 / L3) with approve/reject actions
- Finance punch rejection tracking
- Punch entry detail retrieval for 25+ document types
- Dashboard counters for scanners, classifiers, and bill approvers
- Paginated, filterable document lists
- Extraction queue management
- Role-based data filtering (doc types, departments per user permissions)
- Soft delete for scan files and supporting files

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Database | MySQL (dynamic year-scoped tables) |
| File Storage | AWS S3 (`aws/aws-sdk-php ^3.370`) |
| S3 Filesystem | `league/flysystem-aws-s3-v3 3.0` |
| Admin Panel | Filament 5.4 |
| Testing | PHPUnit 11 |
| Dev Tools | Laravel Sail, Pint, Pail |

---

## Project Structure

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── AuthController.php          # Login, financial years
│           ├── ScanFileController.php      # Upload, list, delete scan files
│           ├── DocClassifierController.php # Classification workflow
│           ├── BillApproverController.php  # Multi-level approval workflow
│           ├── PunchEntryController.php    # Punch data retrieval per doc type
│           └── FilterController.php        # Dropdown filter data
├── Models/
│   ├── ScanFile.php                        # Dynamic year-scoped model
│   ├── SupportFile.php                     # Supporting documents
│   ├── SupportDocumentType.php             # Support doc type master
│   ├── FinancialYear.php                   # Financial year master
│   └── User.php                            # Laravel default user model
├── Services/
│   └── S3UploadService.php                 # AWS S3 upload wrapper
├── Traits/
│   └── ApiResponse.php                     # Standardized JSON response helpers
routes/
├── api.php                                 # All API route definitions
config/
├── filesystems.php                         # S3 disk configuration
database/
├── migrations/                             # (Empty — tables managed externally)
```

> **Note:** Database migrations are not present in the codebase. Tables are assumed to be pre-existing or managed by a separate system.

---

## Installation Guide

### Prerequisites

- PHP >= 8.2
- Composer
- MySQL database
- AWS S3 bucket
- Node.js & npm (for asset compilation)

### Steps

```bash
# 1. Clone the repository
git clone <repository-url>
cd <project-folder>

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Configure your .env file (see Environment Configuration below)

# 6. Run migrations (if applicable)
php artisan migrate

# 7. Install Node dependencies and build assets
npm install
npm run build

# 8. Start the development server
php artisan serve
```

Or use the built-in setup script:

```bash
composer run setup
```

---

## Environment Configuration

```dotenv
# Application
APP_NAME=Laravel
APP_ENV=local                    # local | production
APP_KEY=                         # Generated via php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql              # Change from sqlite to mysql for production
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=root
DB_PASSWORD=

# AWS S3 — Required for file uploads
AWS_ACCESS_KEY_ID=               # Your AWS access key
AWS_SECRET_ACCESS_KEY=           # Your AWS secret key
AWS_DEFAULT_REGION=us-east-1     # S3 bucket region
AWS_BUCKET=                      # S3 bucket name
AWS_USE_PATH_STYLE_ENDPOINT=false

# Queue (used for classification extraction queue)
QUEUE_CONNECTION=database

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

> **Important:** The `.env.example` defaults to `DB_CONNECTION=sqlite`. Change this to `mysql` for production use.

---

## API Documentation

### Base URL

```
http://your-domain.com/api
```

### Authentication

**Not Found in Code** — There is no token-based authentication middleware (no Sanctum, JWT, or Passport) applied to the routes. All API routes are currently unprotected. Authentication is handled manually: the login endpoint validates credentials against the database and returns user data. The client is expected to store and pass `user_id` and `year_id` as request parameters on subsequent calls.

---

### Endpoints

#### Auth

---

**POST** `/api/login`

Login with employee credentials and select a financial year.

Request body:
```json
{
  "username": "EMP001",
  "password": "1234567890",
  "year_id": 1
}
```

Sample response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user_id": 42,
    "emp_code": "EMP001",
    "emp_name": "John Doe",
    "emp_contact": "1234567890",
    "status": "A",
    "roles": ["Classifier", "Bill Approver"],
    "year_id": 1,
    "year_label": "2024-25",
    "start_date": "2024-04-01",
    "end_date": "2025-03-31",
    "is_current": true
  }
}
```

---

**GET** `/api/financial-years`

Get all available financial years.

Sample response:
```json
{
  "success": true,
  "data": [
    { "id": 1, "label": "2024-25", "start_date": "2024-04-01", "end_date": "2025-03-31", "is_current": 1 }
  ]
}
```

---

#### Scan Files

---

**GET** `/api/dashboard`

Get scan file dashboard counters for a user.

Query params: `year_id`, `user_id`

Sample response:
```json
{
  "success": true,
  "data": {
    "total_scanned_files": 120,
    "final_submitted": 95,
    "pending_submission": 20,
    "rejected_scans": 5,
    "deleted_scans": 2
  }
}
```

---

**GET** `/api/scan-files`

List scan files with filters and pagination.

Query params: `year_id`, `user_id`, `status` (submitted|pending|rejected|deleted), `document_name`, `from_date`, `to_date`, `per_page`

Sample response:
```json
{
  "success": true,
  "data": [
    {
      "scan_id": 101,
      "file_name": "1714000000.pdf",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000000.pdf",
      "document_name": "101_42_Invoice_01042024_120000",
      "temp_scan_date": "2024-04-01",
      "scan_status": "submitted",
      "actions": ["edit", "delete"]
    }
  ],
  "pagination": { "total": 50, "per_page": 10, "current_page": 1, "last_page": 5 }
}
```

---

**POST** `/api/scan-files/upload`

Upload a main scan file to S3.

Request: `multipart/form-data`

| Field | Type | Required |
|---|---|---|
| `main_file` | file | Yes (max 10MB) |
| `user_id` | integer | Yes |
| `year_id` | integer | Yes |

Sample response (201):
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": {
    "scan_id": 102,
    "document_name": "102_42_Invoice_01042024_120001",
    "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000001.pdf",
    "file_name": "1714000001.pdf"
  }
}
```

---

**DELETE** `/api/scan-files`

Soft-delete a scan file.

Request body: `scan_id`, `year_id`, `user_id`

---

**GET** `/api/scan-files/details`

Get full scan file details including supporting files and document types.

Query params: `scan_id`, `year_id`

---

**POST** `/api/scan-files/final-submit`

Final submission of a scanned document.

Request body:
```json
{ "scan_id": 101, "document_name": "Invoice_April_2024", "year_id": 1 }
```

---

**GET** `/api/scan-files/support-files`

Get supporting files for a scan.

Query params: `scan_id`

---

**POST** `/api/scan-files/support-files/upload`

Upload a supporting file to S3.

Request: `multipart/form-data` — `scan_id`, `supp_doc_type_id`, `support_file` (max 10MB)

---

**DELETE** `/api/scan-files/support-files`

Soft-delete a supporting file.

Request body: `support_id`

---

**GET** `/api/document-types`

Get all active supporting document types.

---

#### Document Classification

---

**GET** `/api/classification/dashboard-counters`

Query params: `year_id`, `user_id`

Sample response:
```json
{
  "success": true,
  "data": {
    "pending_for_classification": 15,
    "total_classified": 80,
    "classification_rejected": 3,
    "total_scans_rejected_by_me": 5,
    "document_not_received": 10,
    "document_received": 70
  }
}
```

---

**GET** `/api/classification/list`

Pending documents awaiting classification.

Query params: `year_id`, `temp_scan_by`, `from_date`, `to_date`

---

**GET** `/api/classification/processed`

Documents classified by the current user.

Query params: `year_id`, `user_id`, `doc_type_id`, `department_id`, `sub_department_id`, `from_date`, `to_date`

---

**GET** `/api/classification/verified`

Classified documents with physical document received.

Query params: same as `/classification/processed`

---

**GET** `/api/classification/not-verified`

Classified documents with physical document not yet received.

Query params: same as `/classification/processed`

---

**GET** `/api/classification/rejected`

Documents whose classification was rejected.

Query params: `year_id`, `user_id`

---

**GET** `/api/classification/rejected-by-me`

Scanned bills rejected by the current classifier.

Query params: `year_id`, `user_id`, `from_date`, `to_date`

---

**GET** `/api/classification/extraction-queue`

View the AI/extraction processing queue.

Query params: `year_id`, `user_id`, `status` (pending|processing|completed|failed|all)

---

**GET** `/api/classification/bill-approvers`

Get available bill approvers for a document type.

Query params: `type_id`, `having_multiple_dept` (boolean)

---

**GET** `/api/classification/auto-approve-reasons`

Get list of auto-approve reasons.

---

**POST** `/api/classification/extract-details`

Classify a document and add it to the extraction queue.

Request body:
```json
{
  "scan_id": 101,
  "type_id": 23,
  "department": 5,
  "subdepartment": 12,
  "bill_approver": 7,
  "auto_approve": "no",
  "auto_reason": null,
  "multi_dept": "no",
  "year_id": 1,
  "user_id": 42
}
```

---

**POST** `/api/classification/reject`

Reject a scanned bill before classification.

Request body: `scan_id`, `remark`, `user_id`, `year_id`

---

**POST** `/api/classification/move`

Move a rejected classification back to the classification queue.

Request body: `scan_id`, `user_id`, `year_id`

---

**POST** `/api/classification/update-document-name`

Update the document name for a scan.

Request body: `scan_id`, `document_name`, `year_id`, `user_id`

---

**POST** `/api/classification/update-received-status`

Mark a physical document as received.

Request body: `scan_id`, `received_date`, `year_id`, `user_id`

---

#### Filters

---

**GET** `/api/filters/doc-types` — Query: `user_id`

**GET** `/api/filters/departments` — Query: `user_id`

**GET** `/api/filters/sub-departments` — Query: `department_id`

**GET** `/api/filters/scanners` — Query: `year_id`

**GET** `/api/filters/classifiers` — Query: `year_id`

**GET** `/api/filters/punched-by` — Query: `year_id`

All filter endpoints return:
```json
{ "success": true, "data": [ { "id": 1, "name": "..." } ] }
```

---

#### Punch Entry

---

**GET** `/api/punch-entry/scan-detail`

Get full scan metadata and structured punch data for a document.

Query params: `scan_id` (required), `year_id` (required)

Sample response:
```json
{
  "success": true,
  "data": {
    "scan": [
      { "label": "Scan ID", "key": "scan_id", "value": 101 },
      { "label": "Document Type", "key": "doc_type", "value": "Purchase Invoice" }
    ],
    "punch_detail": {
      "fields": [
        { "label": "Invoice No.", "key": "invoice_no", "value": "INV-2024-001" }
      ],
      "item_columns": [ { "label": "Particular", "key": "particular" } ],
      "items": [ { "sr_no": 1, "particular": "Item A", "qty": 10 } ],
      "additional_details": {}
    }
  }
}
```

> Punch detail structure varies by `doc_type_id`. Supported types: 1, 6, 7, 13, 17, 20, 22, 23, 27, 28, 29, 31, 42, 43, 44, 46, 47, 48, 50, 51, 56, 61, 62, 63, 65.

---

#### Bill Approver

---

**GET** `/api/bill-approver/dashboard-counters`

Query params: `user_id`, `year_id`

Sample response:
```json
{
  "success": true,
  "data": {
    "pending": 12,
    "approved": 45,
    "rejected": 3,
    "finance_rejected": 2
  }
}
```

---

**GET** `/api/bill-approver/list`

Paginated list of bills pending/approved/rejected for the current approver.

Query params: `user_id`, `year_id`, `status` (pending|approved|rejected), `from_date`, `to_date`, `doc_type`, `department`, `sub_department`, `scan_by`, `classify_by`, `punched_by`, `search`, `per_page`, `page`

---

**GET** `/api/bill-approver/finance-rejected`

Bills rejected by the finance team that were previously approved by this user.

Query params: same as `/bill-approver/list`

---

**POST** `/api/bill-approver/action`

Approve or reject a bill at the current user's approval level.

Request body:
```json
{
  "scan_id": 101,
  "user_id": 42,
  "year_id": 1,
  "action": "approve",
  "remark": "Looks good"
}
```

Sample response:
```json
{ "success": true, "message": "Bill approved successfully", "data": null }
```

---

## Database Structure

> **Note:** No migration files are present in the codebase. The following is derived from query analysis.

| Table | Description |
|---|---|
| `users` | System users with `user_id`, `first_name`, `last_name`, `emp_code`, `role_id`, `status` |
| `core_employee_tools` | Employee master with `employee_id`, `emp_code`, `emp_contact`, `emp_name` |
| `financial_years` | Financial year records: `id`, `label`, `start_date`, `end_date`, `is_current` |
| `y{year_id}_scan_file` | Year-scoped scan file table (dynamic per financial year) |
| `support_file` | Supporting documents linked to a scan |
| `supp_document_type_master` | Supporting document type master |
| `master_doctype` | Document type master: `type_id`, `file_type`, `short_name`, `status` |
| `core_department` | Department master: `api_id`, `department_name`, `department_code` |
| `core_sub_department` | Sub-department master |
| `tbl_roles` | Role definitions |
| `tbl_user_permissions` | Per-user permissions for doc types and departments |
| `tbl_queues` | Extraction/processing queue |
| `tbl_scan_rejections` | Log of scan rejections |
| `tbl_classification_move_log` | Log of documents moved back to classification |
| `tbl_approval_matrix` | Approval matrix defining L1/L2/L3 approvers per bill type |
| `tbl_auto_approve_reason` | Auto-approve reason master |
| `y{year_id}_punchdata_{type_id}` | Year + doc-type scoped punch data tables |
| `y{year_id}_punchdata_{type_id}_details` | Line-item details for punch data |
| `master_employee` | Employee master for punch data |
| `master_firm` | Vendor/buyer firm master |
| `master_group` | Document group master |
| `master_work_location` | Work location master |

### `y{year_id}_scan_file` Key Columns

| Column | Description |
|---|---|
| `scan_id` | Primary key |
| `doc_type_id` | Foreign key to `master_doctype` |
| `department_id` | Foreign key to `core_department.api_id` |
| `sub_department_id` | Foreign key to `core_sub_department.api_id` |
| `is_classified` | Y/N — classification done |
| `is_file_punched` | Y/N — punch entry done |
| `is_final_submitted` | Y/N — scanner final submission |
| `is_deleted` | Y/N — soft delete flag |
| `l1/l2/l3_approved_by` | Comma-separated approver user IDs |
| `l1/l2/l3_approved_status` | N (pending) / Y (approved) / R (rejected) |
| `finance_punch_status` | N / R — finance action status |
| `extract_status` | P (pending) / Y (extracted) |
| `file_path` | S3 URL of the uploaded file |

---

## Authentication Flow

1. Client calls `GET /api/financial-years` to populate the year selection dropdown.
2. Client calls `POST /api/login` with `username` (emp_code), `password` (emp_contact), and `year_id`.
3. The server validates credentials against `users` joined with `core_employee_tools`, checks `status = 'A'`, and validates the financial year.
4. On success, the server returns user info, roles, and year details.
5. **No token is issued.** The client stores `user_id` and `year_id` locally and passes them as request parameters on every subsequent API call.
6. Roles returned exclude `Admin`, `Super Admin`, and `DMS Punching` — the client uses these to control UI access.

> **Security Note:** There is no server-side session or token validation on protected routes. All routes are publicly accessible. This should be addressed before production deployment.

---

## Error Handling Format

All responses follow a consistent JSON structure via the `ApiResponse` trait.

**Success (200):**
```json
{
  "success": true,
  "message": "Optional message",
  "data": { }
}
```

**Created (201):**
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": { }
}
```

**Client Error (400 / 404 / 422):**
```json
{
  "success": false,
  "message": "Descriptive error message",
  "errors": { }
}
```

**Server Error (500):**
```json
{
  "success": false,
  "message": "Failed to ...: <exception message>"
}
```

**Paginated responses** include an additional `pagination` object:
```json
{
  "pagination": {
    "total": 100,
    "per_page": 10,
    "current_page": 1,
    "last_page": 10,
    "from": 1,
    "to": 10
  }
}
```

---

## Testing Instructions

### Postman

Import the base URL and test the following flow:

**1. Get Financial Years**
```
GET http://localhost:8000/api/financial-years
```

**2. Login**
```
POST http://localhost:8000/api/login
Content-Type: application/json

{
  "username": "EMP001",
  "password": "9876543210",
  "year_id": 1
}
```

**3. Upload a Scan File**
```
POST http://localhost:8000/api/scan-files/upload
Content-Type: multipart/form-data

main_file: <file>
user_id: 42
year_id: 1
```

**4. Approve a Bill**
```
POST http://localhost:8000/api/bill-approver/action
Content-Type: application/json

{
  "scan_id": 101,
  "user_id": 42,
  "year_id": 1,
  "action": "approve",
  "remark": "Approved"
}
```

### cURL Examples

```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"EMP001","password":"9876543210","year_id":1}'

# Get scan files list
curl "http://localhost:8000/api/scan-files?year_id=1&user_id=42&status=pending&per_page=10"

# Get bill approver dashboard counters
curl "http://localhost:8000/api/bill-approver/dashboard-counters?user_id=42&year_id=1"
```

### Running PHPUnit Tests

```bash
php artisan test
```

> **Note:** No feature or unit tests are present in the codebase beyond the default Laravel scaffolding.

---

## Deployment Instructions

```bash
# 1. Set APP_ENV=production and APP_DEBUG=false in .env
# 2. Set correct DB credentials and AWS credentials

# 3. Install production dependencies
composer install --no-dev --optimize-autoloader

# 4. Cache config, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Run migrations
php artisan migrate --force

# 6. Build frontend assets
npm run build

# 7. Set correct file permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 8. Start queue worker (required for extraction queue)
php artisan queue:work --tries=3
```

> For production, use a process manager like **Supervisor** to keep the queue worker running, and a web server like **Nginx** or **Apache** with PHP-FPM.

---

## Known Issues / Limitations

- **No authentication middleware** — All API routes are unprotected. `user_id` and `year_id` are passed as plain request parameters with no server-side validation of identity.
- **No input sanitization on FIND_IN_SET queries** — `user_id` is interpolated directly into raw SQL strings in `BillApproverController`, which is a potential SQL injection risk.
- **Dynamic table names** — Tables like `y{year_id}_scan_file` and `y{year_id}_punchdata_{type_id}` are constructed from user input. While used as integers, this pattern bypasses Laravel's query builder protections.
- **No migrations** — The database schema is not version-controlled in this repository.
- **No tests** — No feature or unit tests exist beyond the default Laravel stubs.
- **`PunchEntryController` is very large** — 1800+ lines handling 25+ document types with duplicated patterns.
- **`.env.example` defaults to SQLite** — Must be manually changed to MySQL for production.

---

## Future Improvements

- Add **Laravel Sanctum** token authentication and apply `auth:sanctum` middleware to all protected routes.
- Replace raw SQL interpolation in `FIND_IN_SET` queries with parameterized bindings or a dedicated pivot table.
- Introduce **database migrations** to version-control the schema.
- Refactor `PunchEntryController` — extract each document type handler into its own class or use a strategy pattern.
- Add **feature tests** covering the login, upload, classification, and approval flows.
- Implement a **queue worker** status endpoint so clients can poll extraction progress.
- Add **rate limiting** to the login endpoint to prevent brute-force attacks.
- Consider **API versioning** (`/api/v1/`) for future backward-compatible changes.
- Add **request form classes** (`php artisan make:request`) to centralize validation logic out of controllers.
