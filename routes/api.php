<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillApproverController;
use App\Http\Controllers\Api\DocClassifierController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\PunchEntryController;
use App\Http\Controllers\Api\ScanFileController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/financial-years', [AuthController::class, 'getFinancialYears']);
// Scan file routes
Route::get('/dashboard', [ScanFileController::class, 'dashboard']);
Route::get('/scan-files', [ScanFileController::class, 'index']);
Route::post('/scan-files/upload', [ScanFileController::class, 'uploadMain']);
Route::delete('/scan-files', [ScanFileController::class, 'deleteScanFile']);
Route::get('/scan-files/details', [ScanFileController::class, 'getScanDetails']);
Route::get('/scan-files/support-files', [ScanFileController::class, 'getSupportFiles']);
Route::post('/scan-files/support-files/upload', [ScanFileController::class, 'uploadSupporting']);
Route::delete('/scan-files/support-files', [ScanFileController::class, 'deleteSupportFile']);
Route::get('/document-types', [ScanFileController::class, 'getDocumentTypes']);
Route::post('/scan-files/final-submit', [ScanFileController::class, 'finalSubmit']);
// Document Classifier routes
Route::get('/classification/dashboard-counters', [DocClassifierController::class, 'getDashboardCounters']);
Route::get('/classification/list', [DocClassifierController::class, 'getClassificationList']);
Route::get('/classification/processed', [DocClassifierController::class, 'getProcessedList']);
Route::get('/classification/extraction-queue', [DocClassifierController::class, 'getQueueList']);
Route::get('/classification/verified', [DocClassifierController::class, 'getVerifiedProcessedList']);
Route::get('/classification/not-verified', [DocClassifierController::class, 'getNotVerifiedProcessedList']);
Route::get('/classification/bill-approvers', [DocClassifierController::class, 'getBillApprovers']);
Route::get('/classification/auto-approve-reasons', [DocClassifierController::class, 'getAutoApproveReasons']);
Route::post('/classification/extract-details', [DocClassifierController::class, 'extractDetails']);
Route::get('/classification/rejected', [DocClassifierController::class, 'getRejectedClassifications']);
Route::get('/classification/rejected-by-me', [DocClassifierController::class, 'getRejectedByMe']);
Route::post('/classification/reject', [DocClassifierController::class, 'rejectScannedBill']);
Route::post('/classification/move', [DocClassifierController::class, 'moveToClassification']);
Route::post('/classification/update-document-name', [DocClassifierController::class, 'updateDocumentName']);
Route::post('/classification/update-received-status', [DocClassifierController::class, 'updateReceivedStatus']);
// Filter routes
Route::get('/filters/doc-types', [FilterController::class, 'getDocTypes']);
Route::get('/filters/departments', [FilterController::class, 'getDepartments']);
Route::get('/filters/sub-departments', [FilterController::class, 'getSubDepartments']);
Route::get('/filters/scanners', [FilterController::class, 'getScanners']);
Route::get('/filters/classifiers', [FilterController::class, 'getClassifiers']);
Route::get('/filters/punched-by', [FilterController::class, 'getPunchedBy']);
// Punch Entry routes
Route::get('/punch-entry/scan-detail', [PunchEntryController::class, 'getScanDetail']);

// Bill Approver routes
Route::get('/bill-approver/list', [BillApproverController::class, 'getList']);
Route::get('/bill-approver/finance-rejected', [BillApproverController::class, 'getFinanceRejected']);
Route::get('/bill-approver/dashboard-counters', [BillApproverController::class, 'getDashboardCounters']);
Route::post('/bill-approver/action', [BillApproverController::class, 'action']);
