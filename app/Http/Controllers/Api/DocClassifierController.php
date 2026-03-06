<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanFile;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocClassifierController extends Controller
{
    use ApiResponse;

    public function getClassificationList(Request $request)
    {
        $yearId = $request->input('year_id');
        $tempScanBy = $request->input('temp_scan_by');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $userId = $request->input('user_id');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page');
        $table = "y{$yearId}_scan_file";
        $queuedScanIds = DB::table('tbl_queues')->where('status', 'pending')->pluck('scan_id')->toArray();
        $query = DB::table("{$table} as s")->select(['s.scan_id', 's.document_name', 's.file_path', 's.file_name', DB::raw("IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date) AS scan_date"), DB::raw("IF(s.is_temp_scan = 'Y', CONCAT(sb.first_name, ' ', sb.last_name), CONCAT(ba.first_name, ' ', ba.last_name)) AS scanned_by")])->leftJoin('master_group as g', 'g.group_id', '=', 's.group_id')->leftJoin('users as ba', 'ba.user_id', '=', 's.bill_approver_id')->leftJoin('users as sb', 'sb.user_id', '=', 's.temp_scan_by')->where('s.extract_status', 'P')->where('s.is_classified', 'N')->where('s.is_final_submitted', 'Y')->where('s.is_temp_scan_rejected', 'N')->where('s.is_deleted', 'N');
        if (!empty($queuedScanIds)) {
            $query->whereNotIn('s.scan_id', $queuedScanIds);
        }
        if ($tempScanBy) {
            $query->where('s.temp_scan_by', $tempScanBy);
        }
        if ($fromDate) {
            $query->whereRaw("DATE(IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date)) >= ?", [$fromDate]);
        }
        if ($toDate) {
            $query->whereRaw("DATE(IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date)) <= ?", [$toDate]);
        }
        $query->orderBy('s.scan_id', 'DESC');
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $documents,
            'pagination' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => (int) $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ]);
    }

    public function getProcessedList(Request $request)
    {
        $yearId = $request->input('year_id');
        $docTypeId = $request->input('doc_type_id');
        $departmentId = $request->input('department_id');
        $subDepartmentId = $request->input('sub_department_id');
        $isVerified = $request->input('is_verified');
        $userId = $request->input('user_id');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page');
        $query = DB::table("y{$yearId}_scan_file as s")->select(['s.scan_id', 'g.group_name', 'md.file_type', 's.extract_status', 'core_department.department_name', 'core_sub_department.sub_department_name', 'l.location_name', 's.document_name', 's.file_path', 's.file_name', 's.classified_date', 's.verified_date', 's.document_received_date', 's.is_document_verified', DB::raw("IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date) AS scan_date"), DB::raw("IF(s.is_temp_scan = 'Y', CONCAT(sb.first_name, ' ', sb.last_name), CONCAT(ba.first_name, ' ', ba.last_name)) AS scanned_by")])->leftJoin('master_group as g', 'g.group_id', '=', 's.group_id')->leftJoin('master_doctype as md', 'md.type_id', '=', 's.doc_type_id')->leftJoin('master_work_location as l', 'l.location_id', '=', 's.location_id')->leftJoin('users as ba', 'ba.user_id', '=', 's.bill_approver_id')->leftJoin('users as sb', 'sb.user_id', '=', 's.temp_scan_by')->leftJoin('core_department', 'core_department.api_id', '=', 's.department_id')->leftJoin('core_sub_department', 'core_sub_department.api_id', '=', 's.sub_department_id')->where('s.is_classified', 'Y')->where('s.is_deleted', 'N')->where('s.classified_by', $userId);
        if ($docTypeId) {
            $query->where('s.doc_type_id', $docTypeId);
        }
        if ($departmentId) {
            $query->where('s.department_id', $departmentId);
        }
        if ($subDepartmentId) {
            $query->where('s.sub_department_id', $subDepartmentId);
        }
        if ($isVerified !== null) {
            $query->where('s.is_document_verified', $isVerified);
        }
        $query->orderBy('s.classified_date_datetime', 'DESC');
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $documents,
            'pagination' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => (int) $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ]);
    }

    public function getRejectedClassifications(Request $request)
    {
        $yearId = $request->input('year_id');
        $groupId = $request->input('group_id');
        $locationId = $request->input('location_id');
        $userId = $request->input('user_id');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page');
        $query = DB::table("y{$yearId}_scan_file as s")->select(['s.scan_id', 's.classifion_reject_date', 's.classifion_reject_remark', 'md.file_type', 's.extract_status', 's.document_name', 's.file_name', 's.file_path', DB::raw("IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date) AS scan_date"), DB::raw("IF(s.is_temp_scan = 'Y', CONCAT(sb.first_name, ' ', sb.last_name), '') AS scanned_by")])->leftJoin('master_doctype as md', 'md.type_id', '=', 's.doc_type_id')->leftJoin('users as sb', 'sb.user_id', '=', 's.temp_scan_by')->where('s.document_name', '!=', '')->where('s.extract_status', 'Y')->where('s.is_deleted', 'N')->where('s.is_classified', 'Y')->where('s.is_classifion_reject', 'Y')->where('s.classified_by', $userId);
        if ($groupId) {
            $query->where('s.group_id', $groupId);
        }
        if ($locationId) {
            $query->where('s.location_id', $locationId);
        }
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $documents,
            'pagination' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => (int) $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ]);
    }

    public function rejectScannedBill(Request $request, $scanId)
    {
        $request->validate([
            'remark' => 'required|string',
            'user_id' => 'required|integer',
            'year_id' => 'required|integer',
        ]);
        $userId = $request->input('user_id');
        $remark = trim($request->input('remark'));
        $yearId = $request->input('year_id');
        DB::beginTransaction();
        try {
            $table = "y{$yearId}_scan_file";
            $data = [
                'is_temp_scan_rejected' => 'Y',
                'temp_scan_rejected_by' => $userId,
                'temp_scan_reject_remark' => $remark,
                'temp_scan_reject_date' => now(),
            ];
            DB::table($table)->where('scan_id', $scanId)->update($data);
            DB::table('tbl_scan_rejections')->insert([
                'scan_id' => $scanId,
                'temp_scan_reject_remark' => $remark,
                'temp_scan_rejected_by' => $userId,
                'temp_scan_reject_date' => now(),
                'created_at' => now(),
            ]);
            DB::commit();
            return $this->successResponse(null, 'Bill rejected and logged successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to reject bill: ' . $e->getMessage(), 500);
        }
    }

    public function moveToClassification(Request $request, $scanId)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'year_id' => 'required|integer',
        ]);
        $userId = $request->input('user_id');
        $yearId = $request->input('year_id');
        DB::beginTransaction();
        try {
            $table = "y{$yearId}_scan_file";
            $data = [
                'is_classifion_reject' => 'N',
                'extract_status' => 'P',
                'is_classified' => 'N',
                'classified_by' => 0,
                'classified_date' => '0000-00-00',
            ];
            DB::table($table)->where('scan_id', $scanId)->update($data);
            DB::table('tbl_classification_move_log')->insert([
                'scan_id' => $scanId,
                'moved_by' => $userId,
                'moved_to_classification_date' => now(),
                'created_at' => now(),
            ]);
            DB::commit();
            return $this->successResponse(null, 'Document moved to classification list successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to move document: ' . $e->getMessage(), 500);
        }
    }

    public function updateDocumentName(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|integer',
            'document_name' => 'required|string',
            'year_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);
        $scanId = $request->input('scan_id');
        $documentName = trim($request->input('document_name'));
        $yearId = $request->input('year_id');
        $userId = $request->input('user_id');
        $documentName = preg_replace('/[^a-z0-9]+/i', '_', $documentName);
        $documentName = preg_replace('/_+/', '_', $documentName);
        $documentName = trim($documentName, '_');
        $documentName = implode('_', array_map(function ($part) {
            return ctype_digit($part) ? $part : ucfirst(strtolower($part));
        }, explode('_', $documentName)));
        try {
            $table = "y{$yearId}_scan_file";
            $data = [
                'document_name' => $documentName,
                'updated_at' => now(),
            ];
            DB::table($table)->where('scan_id', $scanId)->update($data);
            return $this->successResponse(['document_name' => $documentName], 'Document name updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update document name: ' . $e->getMessage(), 500);
        }
    }

    public function updateReceivedStatus(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|integer',
            'received_date' => 'required|date',
            'year_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);
        $scanId = $request->input('scan_id');
        $receivedDate = $request->input('received_date');
        $yearId = $request->input('year_id');
        $userId = $request->input('user_id');
        try {
            $table = "y{$yearId}_scan_file";
            $data = [
                'document_received_date' => $receivedDate,
                'is_document_verified' => 'Y',
                'verified_by' => $userId,
                'verified_date' => now()->format('Y-m-d'),
            ];
            DB::table($table)->where('scan_id', $scanId)->update($data);
            return $this->successResponse(null, 'Document status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update status: ' . $e->getMessage(), 500);
        }
    }
}
