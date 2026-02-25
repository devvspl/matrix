<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanFile;
use App\Models\SupportDocumentType;
use App\Models\SupportFile;
use App\Services\S3UploadService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScanFileController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $yearId = $request->input('year_id');
        $status = $request->input('status');
        $documentName = $request->input('document_name');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $perPage = $request->input('per_page', 10);
        $query = ScanFile::forYear($yearId)->select([
            'scan_id',
            'file_name',
            'file_path',
            'document_name',
            'temp_scan_date',
            'temp_scan_date_datetime',
            'is_scan_complete',
            'is_final_submitted',
            'is_temp_scan_rejected',
            'temp_scan_reject_remark',
            'temp_scan_reject_date',
            'temp_scan_reject_date_datetime',
            'is_deleted',
        ])->whereNotNull('temp_scan_date')->where('temp_scan_date', '!=', '0000-00-00');
        // Filter by user if provided
        $userId = $request->input('user_id');
        if ($userId) {
            $query->where('temp_scan_by', $userId);
        }
        // Apply status filters
        $query = $this->applyStatusFilter($query, $status);
        // Search by document name or file name
        if ($documentName) {
            $query->where(function ($q) use ($documentName) {
                $q->where('document_name', 'like', "%{$documentName}%")->orWhere('file_name', 'like', "%{$documentName}%");
            });
        }
        // Date range filters
        if ($fromDate) {
            $query->whereDate('temp_scan_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('temp_scan_date', '<=', $toDate);
        }
        $query->orderBy('scan_id', 'desc');
        // Paginate results
        $scanFiles = $query->paginate($perPage);
        $formattedData = $this->formatScanFiles($scanFiles->items());
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'total' => $scanFiles->total(),
                'per_page' => $scanFiles->perPage(),
                'current_page' => $scanFiles->currentPage(),
                'last_page' => $scanFiles->lastPage(),
                'from' => $scanFiles->firstItem(),
                'to' => $scanFiles->lastItem(),
            ],
        ]);
    }

    public function dashboard(Request $request)
    {
        $yearId = $request->input('year_id');
        $userId = $request->input('user_id');
        $groupId = $request->input('group_id');
        $query = ScanFile::forYear($yearId)->whereNotNull('temp_scan_date')->where('temp_scan_date', '!=', '0000-00-00');
        if ($userId) {
            $query->where('temp_scan_by', $userId);
        }
        $baseQuery = clone $query;
        $totalScanned = (clone $baseQuery)->count();
        $finalSubmitted = (clone $baseQuery)->where('is_final_submitted', 'Y')->where('is_deleted', 'N')->count();
        $pendingSubmission = (clone $baseQuery)->where('is_final_submitted', 'N')->where('is_deleted', 'N')->count();
        $rejectedScans = (clone $baseQuery)->where('is_temp_scan_rejected', 'Y')->where('is_deleted', 'N')->count();
        $deletedScans = (clone $baseQuery)->where('is_deleted', 'Y')->count();
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => [
                'total_scanned_files' => $totalScanned,
                'final_submitted' => $finalSubmitted,
                'pending_submission' => $pendingSubmission,
                'rejected_scans' => $rejectedScans,
                'deleted_scans' => $deletedScans,
            ],
        ]);
    }

    private function formatScanFiles($scanFiles)
    {
        return collect($scanFiles)->map(function ($file) {
            return [
                'scan_id' => $file->scan_id ?? '',
                'file_name' => $file->file_name ?? '',
                'file_path' => $file->file_path ?? '',
                'document_name' => $file->document_name ?? '',
                'temp_scan_date' => $file->temp_scan_date ?? '',
                'temp_scan_date_datetime' => $file->temp_scan_date_datetime ?? '',
                'is_scan_complete' => $file->is_scan_complete ?? 0,
                'is_final_submitted' => $file->is_final_submitted ?? 0,
                'is_temp_scan_rejected' => $file->is_temp_scan_rejected ?? 0,
                'temp_scan_reject_remark' => $file->temp_scan_reject_remark ?? '',
                'temp_scan_reject_date' => $file->temp_scan_reject_date ?? '',
                'temp_scan_reject_date_datetime' => $file->temp_scan_reject_date_datetime ?? '',
                'scan_status' => $this->getScanStatus($file),
                'actions' => $file ? $this->getAvailableActions($file) : [],
            ];
        })->toArray();
    }

    private function getScanStatus($file)
    {
        if ($file->is_deleted === 'Y') {
            return 'deleted';
        }
        if ($file->is_temp_scan_rejected === 'Y') {
            return 'rejected';
        }
        if ($file->is_final_submitted === 'Y') {
            return 'submitted';
        }
        if ($file->is_final_submitted === 'N') {
            return 'pending';
        }
        return 'unknown';
    }

    private function getAvailableActions($file)
    {
        $actions = [];
        // Determine if edit and delete buttons should be shown
        $showEditDelete = false;
        if ($file->is_deleted !== 'Y') {
            if ($file->is_temp_scan_rejected === 'Y') {
                $showEditDelete = true;
            } elseif ($file->is_final_submitted === 'N') {
                $showEditDelete = true;
            }
        }
        // View button - always show if not finally submitted or if rejected
        if ($file->is_final_submitted != 'Y' || $file->is_temp_scan_rejected === 'Y') {
            // $actions[] = 'view';
        }
        // Edit button
        if ($showEditDelete) {
            $actions[] = 'edit';
        }
        // Delete button
        if ($showEditDelete) {
            $actions[] = 'delete';
        }
        return $actions;
    }

    private function applyStatusFilter($query, $status)
    {
        switch ($status) {
            case 'submitted':
                return $query->where('is_final_submitted', ScanFile::STATUS_YES)->where('is_deleted', ScanFile::STATUS_NO);
            case 'pending':
                return $query->where('is_final_submitted', ScanFile::STATUS_NO)->where('is_deleted', ScanFile::STATUS_NO);
            case 'rejected':
                return $query->where('is_temp_scan_rejected', ScanFile::STATUS_YES)->where('is_deleted', ScanFile::STATUS_NO);
            case 'deleted':
                return $query->where('is_deleted', ScanFile::STATUS_YES);
            default:
                return $query->where('is_deleted', ScanFile::STATUS_NO);
        }
    }

    public function deleteScanFile(Request $request, $scanId)
    {
        $yearId = $request->input('year_id', 1);
        $userId = $request->input('user_id');

        if (!$yearId) {
            return $this->errorResponse('year_id is required', 400);
        }

        DB::beginTransaction();

        try {
            $scanFile = ScanFile::forYear($yearId)
                ->where('scan_id', $scanId)
                ->first();

            if (!$scanFile) {
                return $this->notFoundResponse('Scan file not found');
            }

            $scanFile->update([
                'is_deleted' => ScanFile::STATUS_YES,
                'deleted_date' => now(),
                'deleted_date_datetime' => now(),
                'deleted_by' => $userId,
            ]);

            DB::commit();

            return $this->successResponse(null, 'Scan file deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete scan file: ' . $e->getMessage(), 500);
        }
    }

    public function uploadMain(Request $request)
    {
        $request->validate([
            'main_file' => 'required|file|max:10240',  // 10MB max
            'user_id' => 'required|integer',
            'year_id' => 'required|integer',
        ]);
        $userId = $request->input('user_id');
        $yearId = $request->input('year_id');
        $file = $request->file('main_file');
        // Get file details
        $originalName = $file->getClientOriginalName();
        $fileExt = $file->getClientOriginalExtension();
        $year = date('Y');
        $varTempName = time() . '.' . $fileExt;
        $uploadPath = 'uploads/temp/';
        // Upload to S3
        $s3Service = new \App\Services\S3UploadService();
        $uploadResult = $s3Service->uploadFile($file, $varTempName, $uploadPath);
        if (!$uploadResult['success']) {
            return $this->errorResponse('S3 Upload Error: ' . $uploadResult['error'], 500);
        }
        // Prepare data for insertion
        $data = [
            'group_id' => $request->input('group_id', 1),
            'temp_scan_by' => $userId,
            'is_temp_scan' => 'Y',
            'is_scan_complete' => 'N',
            'file_name' => $varTempName,
            'file_extension' => $fileExt,
            'file_path' => $uploadResult['url'],
            'secondary_file_path' => $uploadPath . $varTempName,
            'year' => $year,
            'temp_scan_date' => date('Y-m-d'),
            'temp_scan_date_datetime' => date('Y-m-d H:i:s'),
        ];
        try {
            // Insert into database
            $scanFile = ScanFile::forYear($yearId);
            $scanFile->fill($data);
            $scanFile->save();
            $insertId = $scanFile->scan_id;
            // Generate document name
            $fileOrgName = pathinfo($originalName, PATHINFO_FILENAME);
            $fileOrgName = preg_replace('/[^A-Za-z0-9]+/', ' ', $fileOrgName);
            $fileOrgName = trim(preg_replace('/\s+/', ' ', $fileOrgName));
            $fileOrgName = ucwords(strtolower($fileOrgName));
            $fileOrgName = str_replace(' ', '_', $fileOrgName);
            $fileOrgName = preg_replace('/_+/', '_', $fileOrgName);
            $formattedDate = date('dmY_His');
            $documentName = $insertId . '_' . $userId . '_' . $fileOrgName . '_' . $formattedDate;
            // Update document name
            $scanFile->update(['document_name' => $documentName]);
            return $this->createdResponse([
                'scan_id' => $insertId,
                'document_name' => $documentName,
                'file_path' => $uploadResult['url'],
                'file_name' => $varTempName,
            ], 'File uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload file: ' . $e->getMessage(), 500);
        }
    }

    public function getSupportFiles(Request $request, $scanId)
    {
        $supportFiles = SupportFile::with('documentType')->byScanId($scanId)->notDeleted()->get();
        $formattedFiles = $supportFiles->map(function ($file) {
            return [
                'support_id' => $file->support_id,
                'scan_id' => $file->scan_id,
                'supp_doc_type_id' => $file->supp_doc_type_id,
                'doc_type_name' => $file->documentType->DocTypeName ?? null,
                'doc_type_code' => $file->documentType->DocTypeCode ?? null,
                'file_name' => $file->file_name,
                'file_extension' => $file->file_extension,
                'file_path' => $file->file_path,
                'uploaded_date' => $file->uploaded_date,
            ];
        });
        return $this->successResponse($formattedFiles);
    }

    public function getDocumentTypes()
    {
        $documentTypes = SupportDocumentType::active()->select('DocTypeId', 'DocTypeName', 'DocTypeCode')->orderBy('DocTypeName')->get();
        return $this->successResponse($documentTypes);
    }

    public function uploadSupporting(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|integer',
            'supp_doc_type_id' => 'required|integer',
            'support_file' => 'required|file|max:10240',
        ]);
        $scanId = $request->input('scan_id');
        $suppDocTypeId = $request->input('supp_doc_type_id');
        $file = $request->file('support_file');
        $fileExt = $file->getClientOriginalExtension();
        $varTempName = time() . '.' . $fileExt;
        $uploadPath = 'uploads/temp/';
        $s3Service = new S3UploadService();
        $uploadResult = $s3Service->uploadFile($file, $varTempName, $uploadPath);
        if (!$uploadResult['success']) {
            return $this->errorResponse('S3 Upload Error: ' . $uploadResult['error'], 500);
        }
        try {
            $supportFile = new SupportFile();
            $supportFile->scan_id = $scanId;
            $supportFile->supp_doc_type_id = $suppDocTypeId;
            $supportFile->file_name = $varTempName;
            $supportFile->file_extension = $fileExt;
            $supportFile->file_path = $uploadResult['url'];
            $supportFile->secondary_file_path = $uploadPath . $varTempName;
            $supportFile->file_name_old = '';
            $supportFile->file_extension_old = '';
            $supportFile->file_path_old = '';
            $supportFile->is_main_file = 'N';
            $supportFile->uploaded_date = now();
            $supportFile->is_deleted = 'N';
            $supportFile->save();
            return $this->createdResponse([
                'support_id' => $supportFile->support_id,
                'file_path' => $uploadResult['url'],
                'file_name' => $varTempName,
            ], 'Supporting file uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload supporting file: ' . $e->getMessage(), 500);
        }
    }

    public function deleteSupportFile(Request $request, $supportId)
    {
        try {
            $supportFile = SupportFile::find($supportId);
            if (!$supportFile) {
                return $this->notFoundResponse('Supporting file not found');
            }
            $supportFile->delete();
            return $this->successResponse(null, 'Supporting file deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete supporting file: ' . $e->getMessage(), 500);
        }
    }

    public function getScanDetails(Request $request, $scanId)
    {
        $yearId = $request->input('year_id');
        $mainFile = ScanFile::forYear($yearId)->where('scan_id', $scanId)->where('is_deleted', 'N')->first();
        if (!$mainFile) {
            return $this->notFoundResponse('Scan file not found');
        }
        $supportFiles = SupportFile::with('documentType')->byScanId($scanId)->notDeleted()->get();
        $documentTypes = SupportDocumentType::active()->select('DocTypeId', 'DocTypeName', 'DocTypeCode')->orderBy('DocTypeName')->get();
        return $this->successResponse([
            'main_file' => $mainFile,
            'supporting_files' => $supportFiles,
            'document_types' => $documentTypes,
        ]);
    }

    public function finalSubmit(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|integer',
            'document_name' => 'required|string',
            'year_id' => 'required|integer',
        ]);
        $scanId = $request->input('scan_id');
        $documentName = $request->input('document_name');
        $yearId = $request->input('year_id');
        DB::beginTransaction();
        try {
            $scanDetail = ScanFile::forYear($yearId)->where('scan_id', $scanId)->where('is_deleted', 'N')->first();
            if (!$scanDetail) {
                return $this->notFoundResponse('Scan not found');
            }
            $updateData = ['document_name' => $documentName];
            $message = 'Final submission completed.';
            if ($scanDetail->is_temp_scan_rejected == 'Y') {
                $updateData = array_merge($updateData, [
                    'is_final_submitted' => 'Y',
                    'is_temp_scan_rejected' => 'N',
                    'temp_scan_reject_remark' => null,
                    'temp_scan_rejected_by' => null,
                    'temp_scan_reject_date' => null,
                ]);
                $message = 'Scan rejection reset and final submission completed.';
            } else {
                $updateData['is_final_submitted'] = 'Y';
            }
            $scanDetail->update($updateData);
            DB::commit();
            return $this->successResponse(null, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to submit scan: ' . $e->getMessage(), 500);
        }
    }
}
