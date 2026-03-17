<?php

use App\Http\Controllers\Api\ScanFileController;
use App\Http\Controllers\Api\DocClassifierController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/financial-years', [AuthController::class, 'getFinancialYears']);
// Scan file routes
Route::get('/dashboard', [ScanFileController::class, 'dashboard']);
Route::get('/scan-files', [ScanFileController::class, 'index']);
Route::post('/scan-files/upload', [ScanFileController::class, 'uploadMain']);
Route::delete('/scan-files/{scanId}', [ScanFileController::class, 'deleteScanFile']);
Route::get('/scan-files/{scanId}/details', [ScanFileController::class, 'getScanDetails']);
Route::get('/scan-files/{scanId}/support-files', [ScanFileController::class, 'getSupportFiles']);
Route::post('/scan-files/support-files/upload', [ScanFileController::class, 'uploadSupporting']);
Route::delete('/scan-files/support-files/{supportId}', [ScanFileController::class, 'deleteSupportFile']);
Route::get('/document-types', [ScanFileController::class, 'getDocumentTypes']);
Route::post('/scan-files/final-submit', [ScanFileController::class, 'finalSubmit']);
// Document Classifier routes
Route::get('/classification/list', [DocClassifierController::class, 'getClassificationList']);
Route::get('/classification/processed', [DocClassifierController::class, 'getProcessedList']);
Route::get('/classification/verified', [DocClassifierController::class, 'getVerifiedProcessedList']);
Route::get('/classification/not-verified', [DocClassifierController::class, 'getNotVerifiedProcessedList']);
Route::get('/classification/doc-types', [DocClassifierController::class, 'getDocTypes']);
Route::get('/classification/departments', [DocClassifierController::class, 'getDepartments']);
Route::get('/classification/sub-departments', [DocClassifierController::class, 'getSubDepartments']);
Route::get('/classification/bill-approvers', [DocClassifierController::class, 'getBillApprovers']);
Route::get('/classification/auto-approve-reasons', [DocClassifierController::class, 'getAutoApproveReasons']);
Route::post('/classification/extract-details', [DocClassifierController::class, 'extractDetails']);
Route::get('/classification/rejected', [DocClassifierController::class, 'getRejectedClassifications']);
Route::get('/classification/dashboard-counters', [DocClassifierController::class, 'getDashboardCounters']);
Route::get('/classification/rejected-by-me', [DocClassifierController::class, 'getRejectedByMe']);
Route::post('/classification/reject/{scanId}', [DocClassifierController::class, 'rejectScannedBill']);
Route::post('/classification/move/{scanId}', [DocClassifierController::class, 'moveToClassification']);
Route::post('/classification/update-document-name', [DocClassifierController::class, 'updateDocumentName']);
Route::post('/classification/update-received-status', [DocClassifierController::class, 'updateReceivedStatus']);
