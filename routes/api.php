<?php

use App\Http\Controllers\Api\ScanFileController;
use App\Http\Controllers\Api\DocClassifierController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/dashboard', [ScanFileController::class, 'dashboard']);

// Scan file routes
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
Route::get('/classification/rejected', [DocClassifierController::class, 'getRejectedClassifications']);
Route::post('/classification/reject/{scanId}', [DocClassifierController::class, 'rejectScannedBill']);
Route::post('/classification/move/{scanId}', [DocClassifierController::class, 'moveToClassification']);
Route::post('/classification/update-document-name', [DocClassifierController::class, 'updateDocumentName']);
Route::post('/classification/update-received-status', [DocClassifierController::class, 'updateReceivedStatus']);
