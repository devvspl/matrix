DMS (Document Management System) API

## Table of Contents

1. [Project Description](#project-description)
2. [Features](#features)
3. [Tech Stack](#tech-stack)
4. [Project Structure](#project-structure)
5. [Installation Guide](#installation-guide)
6. [Environment Configuration](#environment-configuration)
7. [API Documentation](#api-documentation)
   - [Base URL & Headers](#base-url--headers)
   - [Authentication](#authentication)
   - [Auth Endpoints](#1-auth-endpoints)
   - [Scan File Endpoints](#2-scan-file-endpoints)
   - [Document Classification Endpoints](#3-document-classification-endpoints)
   - [Filter Endpoints](#4-filter-endpoints)
   - [Punch Entry Endpoints](#5-punch-entry-endpoints)
   - [Bill Approver Endpoints](#6-bill-approver-endpoints)
8. [Database Structure](#database-structure)
9. [Authentication Flow](#authentication-flow)
10. [Error Handling Format](#error-handling-format)
11. [Testing Instructions](#testing-instructions)
12. [Deployment Instructions](#deployment-instructions)
13. [Known Issues / Limitations](#known-issues--limitations)
14. [Future Improvements](#future-improvements)

---

## Project Description

A Laravel 12 REST API backend for a **Document Management System (DMS)**. The system manages the complete lifecycle of scanned financial documents — from upload and classification to multi-level bill approval and structured data entry (punch entry).

It is designed for organizations that process large volumes of physical documents (invoices, bills, expense claims, purchase orders, etc.) across multiple departments and financial years.

**Document Lifecycle:**

```
Upload (Scanner) → Classify (Classifier) → Punch Entry (Data Entry) → Bill Approval (L1/L2/L3) → Finance Action
```

---

## Features

- Employee-based login with financial year selection
- Dynamic year-scoped database tables (`y{year_id}_scan_file`)
- Main document upload to AWS S3 (up to 10MB)
- Supporting file upload and soft-delete management
- Document classification: assign doc type, department, sub-department, and approver
- Multi-level bill approval workflow (L1 / L2 / L3) with approve/reject and remarks
- Finance punch rejection tracking
- Punch entry detail retrieval for 25+ document types with structured field/item data
- Dashboard counters for scanners, classifiers, and bill approvers
- Paginated, filterable document lists with search
- Extraction queue management for AI/OCR processing
- Role-based data filtering (doc types and departments per user permissions)
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
└── api.php                                 # All API route definitions
```

> **Note:** Database migrations are not present. Tables are assumed to be pre-existing or managed by a separate system.

---

## Installation Guide

**Prerequisites:** PHP >= 8.2, Composer, MySQL, AWS S3 bucket, Node.js & npm

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

# 5. Configure .env (database, AWS — see section below)

# 6. Run migrations
php artisan migrate

# 7. Build frontend assets
npm install && npm run build

# 8. Start development server
php artisan serve
```

Or use the built-in setup script:

```bash
composer run setup
```

---

## Environment Configuration

```dotenv
APP_NAME=Laravel
APP_ENV=local                    # Change to "production" on server
APP_KEY=                         # Auto-generated via php artisan key:generate
APP_DEBUG=true                   # Set to false in production
APP_URL=http://localhost

# Database — change DB_CONNECTION to mysql for production
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=root
DB_PASSWORD=your_password

# AWS S3 — required for all file uploads
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
AWS_USE_PATH_STYLE_ENDPOINT=false

# Queue — required for extraction queue feature
QUEUE_CONNECTION=database

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

> **Important:** The `.env.example` defaults to `DB_CONNECTION=sqlite`. You must change this to `mysql` for production.

---

## API Documentation

### Base URL & Headers

```
Base URL:  http://your-domain.com/api

Headers (for all JSON requests):
  Content-Type: application/json
  Accept: application/json

Headers (for file upload requests):
  Content-Type: multipart/form-data
  Accept: application/json
```

### Authentication

> There is **no token-based authentication** (no Sanctum/JWT). All routes are currently open. After login, the server returns `user_id` and `year_id` which the client must pass as parameters on every subsequent request.

---

## 1. Auth Endpoints

These endpoints handle employee login and financial year selection. They are the entry point for all users.

---

### 1.1 Get Financial Years

Fetches all available financial years. This should be called **before login** so the user can select a year from a dropdown.

```
GET /api/financial-years
```

**Request Parameters:** None

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/financial-years" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "label": "2024-25",
      "start_date": "2024-04-01",
      "end_date": "2025-03-31",
      "is_current": 1
    },
    {
      "id": 2,
      "label": "2023-24",
      "start_date": "2023-04-01",
      "end_date": "2024-03-31",
      "is_current": 0
    }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `id` | integer | Financial year ID — used as `year_id` in all other APIs |
| `label` | string | Human-readable label e.g. `2024-25` |
| `start_date` | date | Start date of the financial year |
| `end_date` | date | End date of the financial year |
| `is_current` | integer | `1` = current active year, `0` = past year |

---

### 1.2 Login

Authenticates an employee using their employee code and contact number, and binds the session to a selected financial year.

```
POST /api/login
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `username` | string | Yes | Employee code (e.g. `EMP001`) — maps to `emp_code` in `core_employee_tools` |
| `password` | string | Yes | Employee contact number — used as password |
| `year_id` | integer | Yes | Financial year ID obtained from `/ai/financia-yers` |

**URL Exampl:**
```bash
curl -X POST "http://localost:8000/api/login" \
  -H "Cntent-Type: application/json" \
  -H "Accept: appication/json" \
  - '{
    "usrname": "EMP001",
    "password": "9876543210",
    "year_id": 1
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user_id": 42,
    "emp_code": "EMP001",
    "emp_name": "John Doe",
    "emp_contact": "9876543210",
    "status": "A",
    "roles": ["Classifier", "Bill Appover"],
    "year_id": 1,
    "year_label": "2024-25",
    "start_date": "2024-04-01",
    "end_date": "2025-03-31",
    "is_current": true
  }
}
```

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `user_id` | integer | Unique user ID — **pass this in all subsequent API calls** |
| `emp_code` | string | Employee code |
| `emp_name` | string | Full employee name |
| `emp_contact` | string | Employee contact number |
| `status` | string | `A` = Active |
| `roles` | array | List of roles assigned to the user (excludes Admin/Super Admin/DMS Punching) |
| `year_id` | integer | Selected financial year ID — **pass this in all subsequent API calls** |
| `year_label` | string | Financial year label |
| `start_date` | date | Year start date |
| `end_date` | date | Year end date |
| `is_current` | boolean | Whether this is the current financial year |

**Error Responses:**

| HTTP Code | Condition | Message |
|---|---|---|
| `401` | Wrong credentials or inactive account | `Invalid credentials or inactive account` |
| `400` | `year_id` does not exist | `Invalid financial year` |
| `422` | Missing required fields | Laravel validation error |
| `500` | Database error | `Login failed: <error>` |

---

## 2. Scan File Endpoints

These endpoints are used by **scanners** — employees who physically scan documents and upload them to the system.

---

### 2.1 Scanner Dashboard Counters

Returns summary counts of scan files for a specific user and financial year. Used to populate the scanner's home dashboard.

```
GET /api/dashboard
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | No | Filter counts to a specific scanner. If omitted, counts all users |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/dashboard?year_id=1&user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:**
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

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `total_scanned_files` | integer | All scan files uploaded by this user |
| `final_submitted` | integer | Files marked as final submitted (`is_final_submitted = Y`) |
| `pending_submission` | integer | Files uploaded but not yet submitted (`is_final_submitted = N`) |
| `rejected_scans` | integer | Files rejected by the classifier (`is_temp_scan_rejected = Y`) |
| `deleted_scans` | integer | Soft-deleted files (`is_deleted = Y`) |

---

### 2.2 List Scan Files

Returns a paginated, filterable list of scan files. Used on the scanner's file listing screen.

```
GET /api/scan-files
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | No | Filter by scanner user ID |
| `status` | string | No | Filter by status: `submitted`, `pending`, `rejected`, `deleted`. Default: all non-deleted |
| `document_name` | string | No | Search by document name or file name (partial match) |
| `from_date` | date | No | Filter scan date from (format: `YYYY-MM-DD`) |
| `to_date` | date | No | Filter scan date to (format: `YYYY-MM-DD`) |
| `per_page` | integer | No | Records per page. Default: `10` |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/scan-files?year_id=1&user_id=42&status=pending&per_page=10" \
  -H "Accept: application/json"
```

**Success Response `200`:**
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
      "temp_scan_date_datetime": "2024-04-01 12:00:00",
      "is_scan_complete": "N",
      "is_final_submitted": "N",
      "is_temp_scan_rejected": "N",
      "temp_scan_reject_remark": null,
      "temp_scan_reject_date": null,
      "scan_status": "pending",
      "actions": ["edit", "delete"]
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 10,
    "current_page": 1,
    "last_page": 5,
    "from": 1,
    "to": 10
  }
}
```

**`scan_status` Values:**

| Value | Meaning |
|---|---|
| `pending` | Uploaded but not yet finally submitted |
| `submitted` | Finally submitted for classification |
| `rejected` | Rejected by the classifier — needs re-submission |
| `deleted` | Soft deleted |

**`actions` Values:**

| Value | When shown |
|---|---|
| `edit` | File is pending or rejected (not deleted, not submitted) |
| `delete` | Same as edit condition |

---

### 2.3 Upload Main Scan File

Uploads a scanned document file to AWS S3 and creates a new scan record in the database. This is the first step in the document lifecycle.

```
POST /api/scan-files/upload
Content-Type: multipart/form-data
```

**Request Parameters (form-data):**

| Field | Type | Required | Description |
|---|---|---|---|
| `main_file` | file | Yes | The scanned document file. Max size: **10MB** |
| `user_id` | integer | Yes | ID of the scanner uploading the file |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/scan-files/upload" \
  -H "Accept: application/json" \
  -F "main_file=@/path/to/invoice.pdf" \
  -F "user_id=42" \
  -F "year_id=1"
```

**Success Response `201`:**
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": {
    "scan_id": 102,
    "document_name": "102_42_Invoice_April_2024_01042024_120001",
    "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000001.pdf",
    "file_name": "1714000001.pdf"
  }
}
```

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `scan_id` | integer | Newly created scan record ID — save this for all subsequent calls |
| `document_name` | string | Auto-generated name: `{scan_id}_{user_id}_{original_name}_{timestamp}` |
| `file_path` | string | Full S3 URL of the uploaded file |
| `file_name` | string | Stored file name on S3 (timestamp-based) |

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `422` | Missing required fields or file too large |
| `500` | S3 upload failed or database insert failed |

---

### 2.4 Get Scan File Details

Returns full details of a single scan file including its supporting files and available document types. Used when opening a scan record for editing.

```
GET /api/scan-files/details
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/scan-files/details?scan_id=101&year_id=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": {
    "main_file": {
      "scan_id": 101,
      "file_name": "1714000000.pdf",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000000.pdf",
      "document_name": "101_42_Invoice_01042024_120000",
      "is_final_submitted": "N",
      "is_temp_scan_rejected": "N"
    },
    "supporting_files": [
      {
        "support_id": 5,
        "scan_id": 101,
        "supp_doc_type_id": 2,
        "doc_type_name": "Purchase Order",
        "doc_type_code": "PO",
        "file_name": "1714000002.pdf",
        "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000002.pdf",
        "uploaded_date": "2024-04-01 12:05:00"
      }
    ],
    "document_types": [
      { "DocTypeId": 1, "DocTypeName": "Purchase Order", "DocTypeCode": "PO" },
      { "DocTypeId": 2, "DocTypeName": "Delivery Challan", "DocTypeCode": "DC" }
    ]
  }
}
```

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `404` | Scan file not found or is deleted |

---

### 2.5 Final Submit

Marks a scan file as finally submitted, making it available for the classifier. If the file was previously rejected, this also clears the rejection status.

```
POST /api/scan-files/final-submit
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID |
| `document_name` | string | Yes | Document name to save (can be edited before submission) |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/scan-files/final-submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "document_name": "Invoice_April_2024",
    "year_id": 1
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Final submission completed.",
  "data": null
}
```

> If the file was previously rejected, the message will be: `"Scan rejection reset and final submission completed."`

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `404` | Scan file not found or is deleted |
| `422` | Missing required fields |
| `500` | Database error |

---

### 2.6 Delete Scan File

Soft-deletes a scan file (sets `is_deleted = Y`). The file is not physically removed from S3.

```
DELETE /api/scan-files
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID to delete |
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | ID of the user performing the deletion |

**cURL Example:**
```bash
curl -X DELETE "http://localhost:8000/api/scan-files" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "year_id": 1,
    "user_id": 42
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Scan file deleted successfully",
  "data": null
}
```

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `400` | `year_id` is missing |
| `404` | Scan file not found |
| `500` | Database error |

---

### 2.7 Get Supporting Files

Returns all supporting files attached to a scan record.

```
GET /api/scan-files/support-files
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/scan-files/support-files?scan_id=101" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "support_id": 5,
      "scan_id": 101,
      "supp_doc_type_id": 2,
      "doc_type_name": "Purchase Order",
      "doc_type_code": "PO",
      "file_name": "1714000002.pdf",
      "file_extension": "pdf",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000002.pdf",
      "uploaded_date": "2024-04-01 12:05:00"
    }
  ]
}
```

---

### 2.8 Upload Supporting File

Uploads a supporting document (e.g. purchase order, delivery challan) linked to a main scan file.

```
POST /api/scan-files/support-files/upload
Content-Type: multipart/form-data
```

**Request Parameters (form-data):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The main scan record ID this file belongs to |
| `supp_doc_type_id` | integer | Yes | Supporting document type ID (from `/api/document-types`) |
| `support_file` | file | Yes | The supporting document file. Max size: **10MB** |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/scan-files/support-files/upload" \
  -H "Accept: application/json" \
  -F "scan_id=101" \
  -F "supp_doc_type_id=2" \
  -F "support_file=@/path/to/purchase_order.pdf"
```

**Success Response `201`:**
```json
{
  "success": true,
  "message": "Supporting file uploaded successfully",
  "data": {
    "support_id": 6,
    "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000003.pdf",
    "file_name": "1714000003.pdf"
  }
}
```

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `422` | Missing required fields or file too large |
| `500` | S3 upload failed or database error |

---

### 2.9 Delete Supporting File

Soft-deletes a supporting file (sets `is_deleted = Y`).

```
DELETE /api/scan-files/support-files
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `support_id` | integer | Yes | The supporting file record ID |

**cURL Example:**
```bash
curl -X DELETE "http://localhost:8000/api/scan-files/support-files" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{ "support_id": 6 }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Supporting file deleted successfully",
  "data": null
}
```

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `400` | `support_id` is missing |
| `404` | Supporting file not found or already deleted |
| `500` | Database error |

---

### 2.10 Get Supporting Document Types

Returns all active supporting document types. Used to populate the document type dropdown when uploading a supporting file.

```
GET /api/document-types
```

**Request Parameters:** None

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/document-types" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "DocTypeId": 1, "DocTypeName": "Purchase Order", "DocTypeCode": "PO" },
    { "DocTypeId": 2, "DocTypeName": "Delivery Challan", "DocTypeCode": "DC" },
    { "DocTypeId": 3, "DocTypeName": "GRN", "DocTypeCode": "GRN" }
  ]
}
```

---

## 3. Document Classification Endpoints

These endpoints are used by **classifiers** — employees who review uploaded scans and assign document type, department, and approver information before the document moves to punch entry.

---

### 3.1 Classifier Dashboard Counters

Returns summary counts for the classifier's dashboard — how many documents are pending, classified, rejected, etc.

```
GET /api/classification/dashboard-counters
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | Classifier's user ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/dashboard-counters?year_id=1&user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:**
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

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `pending_for_classification` | integer | Documents submitted by scanners, not yet classified, not in queue |
| `total_classified` | integer | Total documents classified by this user |
| `classification_rejected` | integer | Documents this user classified but were later rejected |
| `total_scans_rejected_by_me` | integer | Scanned bills this user rejected before classification |
| `document_not_received` | integer | Classified docs where physical document not yet received |
| `document_received` | integer | Classified docs where physical document has been received |

---

### 3.2 Get Classification List

Returns documents that are ready to be classified — submitted by scanners, not yet classified, and not currently in the extraction queue.

```
GET /api/classification/list
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `temp_scan_by` | integer | No | Filter by scanner user ID |
| `from_date` | date | No | Filter by scan date from (format: `YYYY-MM-DD`) |
| `to_date` | date | No | Filter by scan date to (format: `YYYY-MM-DD`) |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/list?year_id=1&from_date=2024-04-01&to_date=2024-04-30" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "status": 200,
  "success": true,
  "data": [
    {
      "scan_id": 101,
      "document_name": "101_42_Invoice_01042024_120000",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000000.pdf",
      "file_name": "1714000000.pdf",
      "scan_date": "2024-04-01",
      "scanned_by": "John Doe",
      "support_files": [
        {
          "DocTypeName": "Purchase Order",
          "DocTypeCode": "PO",
          "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000002.pdf"
        }
      ]
    }
  ]
}
```

> Each document includes its `support_files` array so the classifier can view all attached documents.

---

### 3.3 Classify a Document (Extract Details)

The core classification action. Assigns document type, department, sub-department, and bill approver to a scan. Also adds the document to the extraction queue for AI/OCR processing.

```
POST /api/classification/extract-details
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID to classify |
| `type_id` | integer | Yes | Document type ID (from `/api/filters/doc-types`) |
| `department` | integer | Conditional | Department ID. Required if `multi_dept = "no"` |
| `subdepartment` | integer | No | Sub-department ID |
| `bill_approver` | integer | Conditional | Bill approver user ID. Required if `multi_dept = "yes"` |
| `multi_dept` | string | No | `"yes"` if document spans multiple departments, `"no"` otherwise. Default: `"no"` |
| `auto_approve` | string | No | `"yes"` to auto-approve, `"no"` for normal approval. Default: `"no"` |
| `auto_reason` | integer | Conditional | Auto-approve reason ID. Required if `auto_approve = "yes"` |
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | Classifier's user ID |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/classification/extract-details" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "type_id": 23,
    "department": 5,
    "subdepartment": 12,
    "bill_approver": 7,
    "multi_dept": "no",
    "auto_approve": "no",
    "auto_reason": null,
    "year_id": 1,
    "user_id": 42
  }'
```

**Success Response `200`:**
```json
{
  "status": "success",
  "message": "Document updated and added to queue successfully."
}
```

**Error Responses:**

| Condition | Response |
|---|---|
| `type_id` is missing | `{ "status": "error", "message": "Document Type is required." }` |
| `department` missing when `multi_dept = no` | `{ "status": "error", "message": "Department is required." }` |
| `bill_approver` missing when `multi_dept = yes` | `{ "status": "error", "message": "Please select Bill Approver." }` |
| `auto_reason` missing when `auto_approve = yes` | `{ "status": "error", "message": "Please select Auto Approve Reason." }` |
| Database error | `{ "status": "error", "message": "Something went wrong.", "error": "..." }` |

---

### 3.4 Get Processed (Classified) List

Returns documents that have already been classified by the current user.

```
GET /api/classification/processed
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | Classifier's user ID |
| `doc_type_id` | integer | No | Filter by document type ID |
| `department_id` | integer | No | Filter by department ID |
| `sub_department_id` | integer | No | Filter by sub-department ID |
| `from_date` | date | No | Filter by classified date from |
| `to_date` | date | No | Filter by classified date to |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/processed?year_id=1&user_id=42&doc_type_id=23" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "status": 200,
  "success": true,
  "data": [
    {
      "scan_id": 101,
      "group_name": "Finance",
      "file_type": "Purchase Invoice",
      "extract_status": "Y",
      "department_name": "Accounts",
      "sub_department_name": "Payables",
      "location_name": "Head Office",
      "document_name": "101_42_Invoice_01042024_120000",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000000.pdf",
      "file_name": "1714000000.pdf",
      "classified_date": "2024-04-02",
      "verified_date": "2024-04-03",
      "document_received_date": "2024-04-03",
      "is_document_verified": "Y",
      "scan_date": "2024-04-01",
      "scanned_by": "John Doe",
      "support_files": []
    }
  ]
}
```

---

### 3.5 Get Verified Documents List

Returns classified documents where the physical document has been confirmed as received (`is_document_verified = Y`).

```
GET /api/classification/verified
```

**Query Parameters:** Same as [3.4 Get Processed List](#34-get-processed-classified-list)

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/verified?year_id=1&user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:** Same structure as [3.4](#34-get-processed-classified-list) but only includes records where `is_document_verified = Y`.

---

### 3.6 Get Not-Verified Documents List

Returns classified documents where the physical document has **not** yet been received (`is_document_verified = N`).

```
GET /api/classification/not-verified
```

**Query Parameters:** Same as [3.4 Get Processed List](#34-get-processed-classified-list)

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/not-verified?year_id=1&user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:** Same structure as [3.4](#34-get-processed-classified-list) but only includes records where `is_document_verified = N`.

---

### 3.7 Update Document Received Status

Marks a classified document as physically received and records the received date.

```
POST /api/classification/update-received-status
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID |
| `received_date` | date | Yes | Date the physical document was received (format: `YYYY-MM-DD`) |
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | User marking the document as received |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/classification/update-received-status" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "received_date": "2024-04-03",
    "year_id": 1,
    "user_id": 42
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Document status updated successfully",
  "data": null
}
```

---

### 3.8 Update Document Name

Updates the document name for a classified scan. The name is auto-formatted: special characters are replaced with underscores and each word is title-cased.

```
POST /api/classification/update-document-name
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID |
| `document_name` | string | Yes | New document name (will be auto-formatted) |
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | User making the update |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/classification/update-document-name" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "document_name": "Purchase Invoice April 2024",
    "year_id": 1,
    "user_id": 42
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Document name updated successfully",
  "data": {
    "document_name": "Purchase_Invoice_April_2024"
  }
}
```

> The name `"Purchase Invoice April 2024"` becomes `"Purchase_Invoice_April_2024"` after formatting.

---

### 3.9 Reject a Scanned Bill

Allows a classifier to reject a scanned document before classification (e.g. poor scan quality, wrong document). The rejection is logged in `tbl_scan_rejections`.

```
POST /api/classification/reject
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID to reject |
| `remark` | string | Yes | Reason for rejection |
| `user_id` | integer | Yes | Classifier's user ID |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/classification/reject" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "remark": "Document is blurry and unreadable",
    "user_id": 42,
    "year_id": 1
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Bill rejected and logged successfully",
  "data": null
}
```

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `422` | Missing required fields |
| `500` | Database transaction failed |

---

### 3.10 Get Rejected Classifications

Returns documents that were classified by this user but later had their classification rejected.

```
GET /api/classification/rejected
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | Classifier's user ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/rejected?year_id=1&user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "status": 200,
  "success": true,
  "data": [
    {
      "scan_id": 98,
      "classifion_reject_date": "2024-04-05",
      "classifion_reject_remark": "Wrong department assigned",
      "file_type": "Purchase Invoice",
      "extract_status": "Y",
      "document_name": "98_42_Invoice_01042024_110000",
      "file_name": "1713990000.pdf",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1713990000.pdf",
      "scan_date": "2024-04-01",
      "scanned_by": "Jane Smith",
      "support_files": []
    }
  ]
}
```

---

### 3.11 Get Scans Rejected By Me

Returns a list of scanned bills that the current classifier has rejected (before classification).

```
GET /api/classification/rejected-by-me
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | Classifier's user ID |
| `from_date` | date | No | Filter by rejection date from |
| `to_date` | date | No | Filter by rejection date to |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/rejected-by-me?year_id=1&user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "status": 200,
  "success": true,
  "data": [
    {
      "id": 3,
      "scan_id": 95,
      "remark": "Document is blurry and unreadable",
      "rejected_at": "2024-04-02",
      "document_name": "95_40_Bill_01042024_090000",
      "file_name": "1713960000.pdf",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1713960000.pdf",
      "scan_date": "2024-04-01",
      "scanned_by": "John Doe"
    }
  ]
}
```

---

### 3.12 Move Document Back to Classification

Moves a previously rejected classification back into the pending classification queue so it can be re-classified. Logs the action in `tbl_classification_move_log`.

```
POST /api/classification/move
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID to move back |
| `user_id` | integer | Yes | User performing the action |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X POST "http://localhost:8000/api/classification/move" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 98,
    "user_id": 42,
    "year_id": 1
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Document moved to classification list successfully",
  "data": null
}
```

---

### 3.13 Get Extraction Queue

Returns the list of documents in the AI/OCR extraction processing queue for the current user.

```
GET /api/classification/extraction-queue
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |
| `user_id` | integer | Yes | User's ID |
| `status` | string | No | Filter by queue status: `pending`, `processing`, `completed`, `failed`, `all`. Default: `pending` |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/extraction-queue?year_id=1&user_id=42&status=all" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": {
    "queues": [
      {
        "id": 10,
        "scan_id": 101,
        "type_id": 23,
        "status": "completed",
        "created_at": "2024-04-02 10:00:00",
        "created_by": 42,
        "document_name": "101_42_Invoice_01042024_120000",
        "file_type": "Purchase Invoice"
      }
    ],
    "status_counts": {
      "pending": 2,
      "processing": 1,
      "completed": 15,
      "failed": 0,
      "all": 18
    },
    "selected_status": "all"
  }
}
```

**`status` Values:**

| Value | Meaning |
|---|---|
| `pending` | Queued, waiting to be processed |
| `processing` | Currently being processed by the extraction engine |
| `completed` | Successfully extracted |
| `failed` | Extraction failed |

---

### 3.14 Get Bill Approvers

Returns a list of available bill approvers for a given document type. Used to populate the approver dropdown during classification.

```
GET /api/classification/bill-approvers
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `type_id` | integer | Conditional | Document type ID. Required if `having_multiple_dept = false` |
| `having_multiple_dept` | boolean | No | `true` = return all users with Bill Approver role. `false` = return approvers from the approval matrix for this doc type |

**cURL Example:**
```bash
# For a specific document type
curl -X GET "http://localhost:8000/api/classification/bill-approvers?type_id=23&having_multiple_dept=false" \
  -H "Accept: application/json"

# For multi-department documents
curl -X GET "http://localhost:8000/api/classification/bill-approvers?having_multiple_dept=true" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "user_id": 7, "full_name": "Rajesh Kumar", "emp_code": "MGR001" },
    { "user_id": 8, "full_name": "Priya Sharma", "emp_code": "MGR002" }
  ]
}
```

---

### 3.15 Get Auto-Approve Reasons

Returns the list of valid reasons for auto-approving a document. Used when `auto_approve = "yes"` during classification.

```
GET /api/classification/auto-approve-reasons
```

**Request Parameters:** None

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/classification/auto-approve-reasons" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "status": 200,
  "success": true,
  "data": [
    { "id": 1, "reason_name": "Petty Cash", "description": "Small value petty cash expenses" },
    { "id": 2, "reason_name": "Pre-approved Vendor", "description": "Vendor is on pre-approved list" }
  ]
}
```

---

## 4. Filter Endpoints

These endpoints return dropdown/filter data used across the application. All are permission-aware — results are filtered based on what the user is allowed to see.

---

### 4.1 Get Document Types

Returns document types the user has permission to access. Used in filter dropdowns across classification, bill approver, and punch entry screens.

```
GET /api/filters/doc-types
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | User ID — only returns doc types this user has permission for |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/filters/doc-types?user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "type_id": 1, "file_type": "Fuel Bill", "short_name": "FB", "status": "A" },
    { "type_id": 23, "file_type": "Purchase Invoice", "short_name": "PI", "status": "A" }
  ]
}
```

---

### 4.2 Get Departments

Returns departments the user has permission to access.

```
GET /api/filters/departments
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | User ID — only returns departments this user has permission for |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/filters/departments?user_id=42" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "api_id": 5, "department_name": "Accounts", "department_code": "ACC" },
    { "api_id": 6, "department_name": "Operations", "department_code": "OPS" }
  ]
}
```

---

### 4.3 Get Sub-Departments

Returns sub-departments that belong to a given department. Used to populate the sub-department dropdown after a department is selected.

```
GET /api/filters/sub-departments
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `department_id` | integer | Yes | Department ID (the `api_id` from `/api/filters/departments`) |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/filters/sub-departments?department_id=5" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "sub_department_id": 12, "sub_department_name": "Payables" },
    { "sub_department_id": 13, "sub_department_name": "Receivables" }
  ]
}
```

**Error Response:**

| HTTP Code | Condition |
|---|---|
| `400` | `department_id` is missing |

---

### 4.4 Get Scanners

Returns a list of users who have scanned at least one document in the given financial year. Used in filter dropdowns on the bill approver and classifier screens.

```
GET /api/filters/scanners
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/filters/scanners?year_id=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "user_id": 40, "scanner_name": "John Doe" },
    { "user_id": 41, "scanner_name": "Jane Smith" }
  ]
}
```

---

### 4.5 Get Classifiers

Returns a list of users who have classified at least one document in the given financial year.

```
GET /api/filters/classifiers
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/filters/classifiers?year_id=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "user_id": 42, "classifier_name": "Alice Johnson" },
    { "user_id": 43, "classifier_name": "Bob Williams" }
  ]
}
```

---

### 4.6 Get Punched-By Users

Returns a list of users who have punched (data-entered) at least one document in the given financial year.

```
GET /api/filters/punched-by
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/filters/punched-by?year_id=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": [
    { "user_id": 50, "punched_by_name": "Charlie Brown" }
  ]
}
```

---

## 5. Punch Entry Endpoints

These endpoints are used by **data entry operators** who enter structured data from the physical document into the system.

---

### 5.1 Get Scan Detail with Punch Data

Returns the full scan metadata along with the structured punch entry data for a specific document. The punch data structure varies depending on the document type (`doc_type_id`).

```
GET /api/punch-entry/scan-detail
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/punch-entry/scan-detail?scan_id=101&year_id=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "success": true,
  "data": {
    "scan": [
      { "label": "Scan ID",        "key": "scan_id",            "value": 101 },
      { "label": "Document Type",  "key": "doc_type",           "value": "Purchase Invoice" },
      { "label": "Department",     "key": "department_name",    "value": "Accounts" },
      { "label": "Sub Department", "key": "sub_department_name","value": "Payables" },
      { "label": "File Name",      "key": "file_name",          "value": "1714000000.pdf" },
      { "label": "Document Name",  "key": "document_name",      "value": "101_42_Invoice_01042024_120000" },
      { "label": "File Path",      "key": "file_path",          "value": "https://s3.us-east-1.amazonaws.com/..." },
      { "label": "Scan Date",      "key": "scan_date",          "value": "2024-04-01" },
      { "label": "Scanned By",     "key": "scanned_by",         "value": "John Doe" },
      { "label": "Classified Date","key": "classified_date",    "value": "2024-04-02" },
      { "label": "Classified By",  "key": "classified_by",      "value": "Alice Johnson" },
      { "label": "Punched Date",   "key": "punched_date",       "value": null },
      { "label": "Punched By",     "key": "punched_by",         "value": null }
    ],
    "punch_detail": {
      "fields": [
        { "label": "Invoice No.",   "key": "invoice_no",   "value": "INV-2024-001" },
        { "label": "Invoice Date",  "key": "invoice_date", "value": "2024-03-28" },
        { "label": "Vendor",        "key": "vendor_name",  "value": "ABC Suppliers Pvt Ltd" },
        { "label": "Grand Total",   "key": "grand_total",  "value": "15000.00" }
      ],
      "item_columns": [
        { "label": "#",          "key": "sr_no" },
        { "label": "Particular", "key": "particular" },
        { "label": "HSN",        "key": "hsn" },
        { "label": "Qty",        "key": "qty" },
        { "label": "Unit",       "key": "unit" },
        { "label": "MRP",        "key": "mrp" },
        { "label": "Amount",     "key": "amt" },
        { "label": "GST %",      "key": "cgst" },
        { "label": "Total Amt",  "key": "total_amt" }
      ],
      "items": [
        {
          "sr_no": 1,
          "particular": "Office Chair",
          "hsn": "9401",
          "qty": 5,
          "unit": "Nos",
          "mrp": "2500.00",
          "amt": "12500.00",
          "cgst": "9",
          "total_amt": "13625.00"
        }
      ],
      "additional_details": {}
    }
  }
}
```

**Response Structure:**

| Key | Description |
|---|---|
| `scan` | Array of label/key/value objects describing the scan metadata |
| `punch_detail.fields` | Header-level fields of the punched document (invoice no., dates, totals, etc.) |
| `punch_detail.item_columns` | Column definitions for the line-items table |
| `punch_detail.items` | Array of line-item rows |
| `punch_detail.additional_details` | Extra department-specific or type-specific data |

**Supported Document Types (`doc_type_id`):**

| Type ID | Document Type |
|---|---|
| 1 | Fuel / Vehicle Bill |
| 6 | Type 6 |
| 7 | Type 7 |
| 13 | Type 13 |
| 17 | Type 17 |
| 20 | Type 20 |
| 22 | Type 22 |
| 23 | Purchase Invoice |
| 27 | Type 27 |
| 28 | Type 28 |
| 29 | Type 29 |
| 31 | Type 31 |
| 42 | Type 42 |
| 43 | Type 43 |
| 44 | Type 44 |
| 46 | Type 46 |
| 47 | Type 47 |
| 48 | Type 48 |
| 50 | Type 50 |
| 51 | Type 51 |
| 56 | Type 56 |
| 61 | Type 61 |
| 62 | Debit Note |
| 63 | Employee Expense Claim |
| 65 | Farm Labour Bill |

> If `doc_type_id` is not in the supported list, `punch_detail` will be `null`.

**Error Responses:**

| HTTP Code | Condition |
|---|---|
| `404` | Scan file not found |
| `422` | Missing `scan_id` or `year_id` |
| `500` | Database error |

---

## 6. Bill Approver Endpoints

These endpoints are used by **bill approvers** — managers who approve or reject bills after punch entry. The system supports up to **3 levels of approval** (L1 → L2 → L3). Each approver only sees and acts on bills at their own level.

**Approval Level Logic:**
- L1 approver acts first. If approved, it moves to L2.
- L2 approver acts next. If approved, it moves to L3.
- L3 approver gives final approval.
- Any level can reject — rejection stops the chain.
- Finance team can also reject after all approvals (`finance_punch_status = R`).

---

### 6.1 Bill Approver Dashboard Counters

Returns summary counts for the bill approver's dashboard — how many bills are pending, approved, rejected, and finance-rejected.

```
GET /api/bill-approver/dashboard-counters
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Bill approver's user ID |
| `year_id` | integer | Yes | Financial year ID |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/bill-approver/dashboard-counters?user_id=7&year_id=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
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

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `pending` | integer | Bills waiting for this user's approval at their level |
| `approved` | integer | Bills this user has approved |
| `rejected` | integer | Bills this user has rejected |
| `finance_rejected` | integer | Bills this user approved but were later rejected by finance |

---

### 6.2 Get Bill Approver List

Returns a paginated, filterable list of bills for the current approver. Shows bills at the approver's level based on their `user_id` being in `l1_approved_by`, `l2_approved_by`, or `l3_approved_by`.

```
GET /api/bill-approver/list
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Bill approver's user ID |
| `year_id` | integer | Yes | Financial year ID |
| `status` | string | No | `pending` (default), `approved`, or `rejected` |
| `from_date` | date | No | Filter by punched date from (format: `YYYY-MM-DD`) |
| `to_date` | date | No | Filter by punched date to (format: `YYYY-MM-DD`) |
| `doc_type` | integer | No | Filter by document type ID |
| `department` | integer | No | Filter by department ID |
| `sub_department` | integer | No | Filter by sub-department ID |
| `scan_by` | integer | No | Filter by scanner user ID |
| `classify_by` | integer | No | Filter by classifier user ID |
| `punched_by` | integer | No | Filter by punch entry user ID |
| `search` | string | No | Search across document name, file name, department, sub-department, doc type |
| `per_page` | integer | No | Records per page. Default: `10` |
| `page` | integer | No | Page number. Default: `1` |

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/bill-approver/list?user_id=7&year_id=1&status=pending&per_page=10&page=1" \
  -H "Accept: application/json"
```

**Success Response `200`:**
```json
{
  "status": 200,
  "success": true,
  "data": [
    {
      "scan_id": 101,
      "doc_type_id": 23,
      "document_name": "101_42_Invoice_01042024_120000",
      "file_name": "1714000000.pdf",
      "file_path": "https://s3.us-east-1.amazonaws.com/bucket/uploads/temp/1714000000.pdf",
      "doc_type_name": "Purchase Invoice",
      "department_name": "Accounts",
      "sub_department_name": "Payables",
      "scanned_by_name": "John Doe",
      "scan_date": "01-04-2024",
      "classified_by_name": "Alice Johnson",
      "classified_date": "02-04-2024",
      "punched_by_name": "Charlie Brown",
      "punched_date": "03-04-2024"
    }
  ],
  "pagination": {
    "total": 12,
    "per_page": 10,
    "current_page": 1,
    "last_page": 2,
    "from": 1,
    "to": 10
  }
}
```

**Response Fields:**

| Field | Type | Description |
|---|---|---|
| `scan_id` | integer | Scan record ID |
| `doc_type_id` | integer | Document type ID |
| `document_name` | string | Document name |
| `file_name` | string | File name on S3 |
| `file_path` | string | Full S3 URL |
| `doc_type_name` | string | Human-readable document type |
| `department_name` | string | Department name |
| `sub_department_name` | string | Sub-department name |
| `scanned_by_name` | string | Full name of the scanner |
| `scan_date` | string | Scan date in `DD-MM-YYYY` format |
| `classified_by_name` | string | Full name of the classifier |
| `classified_date` | string | Classification date in `DD-MM-YYYY` format |
| `punched_by_name` | string | Full name of the punch entry operator |
| `punched_date` | string | Punch date in `DD-MM-YYYY` format |

---

### 6.3 Get Finance-Rejected Bills

Returns bills that were approved by this user but subsequently rejected by the finance team (`finance_punch_status = R`). This helps approvers track bills that came back after their approval.

```
GET /api/bill-approver/finance-rejected
```

**Query Parameters:** Same as [6.2 Get Bill Approver List](#62-get-bill-approver-list) (except `status` is not applicable here)

**cURL Example:**
```bash
curl -X GET "http://localhost:8000/api/bill-approver/finance-rejected?user_id=7&year_id=1&per_page=10&page=1" \
  -H "Accept: application/json"
```

**Success Response `200`:** Same structure as [6.2](#62-get-bill-approver-list).

---

### 6.4 Approve or Reject a Bill

The core approval action. Approves or rejects a bill at the current user's approval level. The system automatically detects which level (L1/L2/L3) the user belongs to for this bill and updates accordingly.

```
POST /api/bill-approver/action
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `scan_id` | integer | Yes | The scan record ID to act on |
| `user_id` | integer | Yes | Bill approver's user ID |
| `year_id` | integer | Yes | Financial year ID |
| `action` | string | Yes | `"approve"` or `"reject"` |
| `remark` | string | No | Optional remark/comment for the action |

**cURL Example — Approve:**
```bash
curl -X POST "http://localhost:8000/api/bill-approver/action" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "user_id": 7,
    "year_id": 1,
    "action": "approve",
    "remark": "Verified and approved"
  }'
```

**cURL Example — Reject:**
```bash
curl -X POST "http://localhost:8000/api/bill-approver/action" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scan_id": 101,
    "user_id": 7,
    "year_id": 1,
    "action": "reject",
    "remark": "Amount does not match PO"
  }'
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Bill approved successfully",
  "data": null
}
```

> Message will be `"Bill rejected successfully"` for reject action.

**Error Responses:**

| HTTP Code | Condition | Message |
|---|---|---|
| `404` | `scan_id` not found | `Record not found` |
| `400` | User has no pending action at any level | `No pending approval found for you` or `No pending rejection found for you` |
| `422` | Missing or invalid fields | Laravel validation error |

**How Level Detection Works:**

The system checks L1 → L2 → L3 in order. For each level it checks:
1. Is the `user_id` in the `l{n}_approved_by` comma-separated list?
2. Is the previous level approved (or is this L1)?
3. Is the current level status still `N` (pending)?

The first matching level gets updated. This means a user only ever acts on one level per bill.

---

---

## Database Structure

> **Note:** No migration files are present in the codebase. The following is derived from query analysis across all controllers.

### Core Tables

| Table | Description |
|---|---|
| `users` | System users: `user_id`, `first_name`, `last_name`, `emp_code`, `role_id`, `status` |
| `core_employee_tools` | Employee master: `employee_id`, `emp_code`, `emp_contact`, `emp_name` |
| `financial_years` | Financial years: `id`, `label`, `start_date`, `end_date`, `is_current` |
| `tbl_roles` | Role definitions: `id`, `role_name` |
| `tbl_user_permissions` | Per-user permissions: `user_id`, `permission_type` (Document/Department), `permission_value` |
| `master_doctype` | Document type master: `type_id`, `file_type`, `short_name`, `status` |
| `core_department` | Department master: `api_id`, `department_name`, `department_code` |
| `core_sub_department` | Sub-department master: `id`, `sub_department_name` |
| `master_group` | Document group master |
| `master_work_location` | Work location master |
| `master_employee` | Employee master for punch data: `id`, `emp_name`, `emp_code` |
| `master_firm` | Vendor/buyer firm master: `firm_id`, `firm_name`, `address` |
| `master_unit` | Unit of measurement master |

### Document Tables

| Table | Description |
|---|---|
| `y{year_id}_scan_file` | Year-scoped main scan file table (one per financial year) |
| `support_file` | Supporting documents linked to a scan |
| `supp_document_type_master` | Supporting document type master: `DocTypeId`, `DocTypeName`, `DocTypeCode` |

### Workflow Tables

| Table | Description |
|---|---|
| `tbl_queues` | Extraction/OCR processing queue: `scan_id`, `type_id`, `status`, `created_by` |
| `tbl_scan_rejections` | Log of scan rejections by classifiers |
| `tbl_classification_move_log` | Log of documents moved back to classification queue |
| `tbl_approval_matrix` | Defines L1/L2/L3 approvers per bill type |
| `tbl_auto_approve_reason` | Auto-approve reason master |

### Punch Data Tables (Year + Type Scoped)

| Table Pattern | Description |
|---|---|
| `y{year_id}_punchdata_{type_id}` | Header-level punch data for a specific doc type and year |
| `y{year_id}_punchdata_{type_id}_details` | Line-item punch data for a specific doc type and year |
| `y{year_id}_tbl_additional_information_details` | Additional classification info per scan |

### `y{year_id}_scan_file` — Key Columns

| Column | Type | Description |
|---|---|---|
| `scan_id` | int | Primary key |
| `doc_type_id` | int | FK → `master_doctype.type_id` |
| `department_id` | int | FK → `core_department.api_id` |
| `sub_department_id` | int | FK → `core_sub_department.api_id` |
| `temp_scan_by` | int | FK → `users.user_id` (scanner) |
| `classified_by` | int | FK → `users.user_id` (classifier) |
| `punched_by` | int | FK → `users.user_id` (punch operator) |
| `file_name` | varchar | Stored file name on S3 |
| `file_path` | varchar | Full S3 URL |
| `document_name` | varchar | Human-readable document name |
| `is_temp_scan` | char(1) | `Y` = temp scan |
| `temp_scan_date` | date | Date of scan |
| `is_final_submitted` | char(1) | `Y` = submitted for classification |
| `is_classified` | char(1) | `Y` = classified |
| `classified_date` | date | Date of classification |
| `is_file_punched` | char(1) | `Y` = punch entry done |
| `punched_date` | date | Date of punch entry |
| `is_deleted` | char(1) | `Y` = soft deleted |
| `is_temp_scan_rejected` | char(1) | `Y` = rejected by classifier |
| `is_document_verified` | char(1) | `Y` = physical document received |
| `extract_status` | char(1) | `P` = pending extraction, `Y` = extracted |
| `l1_approved_by` | varchar | Comma-separated L1 approver user IDs |
| `l1_approved_status` | char(1) | `N` = pending, `Y` = approved, `R` = rejected |
| `l2_approved_by` | varchar | Comma-separated L2 approver user IDs |
| `l2_approved_status` | char(1) | `N` / `Y` / `R` |
| `l3_approved_by` | varchar | Comma-separated L3 approver user IDs |
| `l3_approved_status` | char(1) | `N` / `Y` / `R` |
| `finance_punch_status` | char(1) | `N` = normal, `R` = rejected by finance |
| `is_auto_approve` | char(1) | `Y` = auto-approved |
| `having_multiple_dep` | char(1) | `Y` = spans multiple departments |

---

## Authentication Flow

```
Step 1: GET /api/financial-years
        → User selects a year from the list

Step 2: POST /api/login  { username, password, year_id }
        → Server validates emp_code + emp_contact against DB
        → Checks user status = 'A' (Active)
        → Returns user_id, roles, year info

Step 3: Client stores user_id + year_id locally

Step 4: All subsequent API calls pass user_id and year_id
        as query params (GET) or body params (POST)
```

> **No token is issued.** There is no server-side session validation on any route. This is a known limitation — see [Known Issues](#known-issues--limitations).

---

## Error Handling Format

All responses use a consistent JSON structure from the `ApiResponse` trait.

**Success `200`:**
```json
{
  "success": true,
  "message": "Optional success message",
  "data": { }
}
```

**Created `201`:**
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": { "id": 1 }
}
```

**Bad Request `400`:**
```json
{
  "success": false,
  "message": "year_id is required"
}
```

**Not Found `404`:**
```json
{
  "success": false,
  "message": "Scan file not found"
}
```

**Validation Error `422`:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "scan_id": ["The scan id field is required."],
    "year_id": ["The year id field is required."]
  }
}
```

**Server Error `500`:**
```json
{
  "success": false,
  "message": "Failed to upload file: Connection refused"
}
```

**Paginated Response** includes an extra `pagination` object:
```json
{
  "success": true,
  "data": [ ],
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

### Recommended Flow (Postman or cURL)

Follow this order to test the full document lifecycle:

**Step 1 — Get financial years:**
```bash
curl -X GET "http://localhost:8000/api/financial-years" -H "Accept: application/json"
```

**Step 2 — Login:**
```bash
curl -X POST "http://localhost:8000/api/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"EMP001","password":"9876543210","year_id":1}'
```
> Save the `user_id` from the response.

**Step 3 — Upload a scan file:**
```bash
curl -X POST "http://localhost:8000/api/scan-files/upload" \
  -F "main_file=@/path/to/invoice.pdf" \
  -F "user_id=42" \
  -F "year_id=1"
```
> Save the `scan_id` from the response.

**Step 4 — Final submit the scan:**
```bash
curl -X POST "http://localhost:8000/api/scan-files/final-submit" \
  -H "Content-Type: application/json" \
  -d '{"scan_id":102,"document_name":"Invoice_April_2024","year_id":1}'
```

**Step 5 — Classify the document:**
```bash
curl -X POST "http://localhost:8000/api/classification/extract-details" \
  -H "Content-Type: application/json" \
  -d '{"scan_id":102,"type_id":23,"department":5,"subdepartment":12,"bill_approver":7,"multi_dept":"no","auto_approve":"no","year_id":1,"user_id":42}'
```

**Step 6 — Approve the bill:**
```bash
curl -X POST "http://localhost:8000/api/bill-approver/action" \
  -H "Content-Type: application/json" \
  -d '{"scan_id":102,"user_id":7,"year_id":1,"action":"approve","remark":"Approved"}'
```

**Step 7 — Check dashboard counters:**
```bash
curl "http://localhost:8000/api/bill-approver/dashboard-counters?user_id=7&year_id=1"
```

### Running PHPUnit Tests

```bash
php artisan test
```

> **Note:** No feature or unit tests are present beyond the default Laravel scaffolding.

---

## Deployment Instructions

```bash
# 1. Set production environment
APP_ENV=production
APP_DEBUG=false

# 2. Install production dependencies only
composer install --no-dev --optimize-autoloader

# 3. Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Run database migrations
php artisan migrate --force

# 5. Build frontend assets
npm run build

# 6. Set correct file permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 7. Start queue worker (required for extraction queue)
php artisan queue:work --tries=3 --timeout=60
```

> Use **Supervisor** to keep the queue worker running in production. Use **Nginx + PHP-FPM** as the web server.

**Nginx config snippet:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## Known Issues / Limitations

- **No authentication middleware** — All API routes are publicly accessible. `user_id` is passed as a plain parameter with no server-side identity verification.
- **SQL injection risk** — `user_id` is interpolated directly into raw `FIND_IN_SET` SQL strings in `BillApproverController`. Should use parameterized queries.
- **Dynamic table names from user input** — `y{year_id}_scan_file` is built from user-supplied `year_id`. While cast to integer, this bypasses Laravel's query builder protections.
- **No database migrations** — Schema is not version-controlled in this repository.
- **No automated tests** — No feature or unit tests beyond default Laravel stubs.
- **`PunchEntryController` is 1800+ lines** — 25+ document types handled with duplicated code patterns.
- **`.env.example` defaults to SQLite** — Must be manually changed to MySQL for production.
- **No rate limiting** — Login endpoint has no brute-force protection.

---

## Future Improvements

- Add **Laravel Sanctum** token authentication and protect all routes with `auth:sanctum` middleware.
- Replace raw SQL `FIND_IN_SET` interpolation with parameterized bindings or a proper pivot table.
- Add **database migrations** to version-control the schema.
- Refactor `PunchEntryController` using a **Strategy Pattern** — one class per document type.
- Add **feature tests** for login, upload, classification, and approval flows.
- Add a **queue status polling endpoint** so clients can track extraction progress in real time.
- Add **rate limiting** on the login endpoint (`throttle:5,1` — 5 attempts per minute).
- Implement **API versioning** (`/api/v1/`) for future backward-compatible changes.
- Use **Laravel Form Requests** (`php artisan make:request`) to move validation out of controllers.
- Add **API response caching** for filter endpoints (doc types, departments) which rarely change.
