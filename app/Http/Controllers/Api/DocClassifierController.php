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
        $documents = $documents->map(function ($doc) {
            $doc->support_files = $this->getSupportFiles($doc->scan_id);
            return $doc;
        });
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
        $userId = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
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
        if ($fromDate) {
            $query->whereDate('s.classified_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('s.classified_date', '<=', $toDate);
        }
        $query->orderBy('s.classified_date_datetime', 'DESC');
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        $documents = $documents->map(function ($doc) {
            $doc->support_files = $this->getSupportFiles($doc->scan_id);
            return $doc;
        });
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

    public function getVerifiedProcessedList(Request $request)
    {
        $yearId = $request->input('year_id');
        $docTypeId = $request->input('doc_type_id');
        $departmentId = $request->input('department_id');
        $subDepartmentId = $request->input('sub_department_id');
        $userId = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page');
        $query = DB::table("y{$yearId}_scan_file as s")->select(['s.scan_id', 'g.group_name', 'md.file_type', 's.extract_status', 'core_department.department_name', 'core_sub_department.sub_department_name', 'l.location_name', 's.document_name', 's.file_path', 's.file_name', 's.classified_date', 's.verified_date', 's.document_received_date', 's.is_document_verified', DB::raw("IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date) AS scan_date"), DB::raw("IF(s.is_temp_scan = 'Y', CONCAT(sb.first_name, ' ', sb.last_name), CONCAT(ba.first_name, ' ', ba.last_name)) AS scanned_by")])->leftJoin('master_group as g', 'g.group_id', '=', 's.group_id')->leftJoin('master_doctype as md', 'md.type_id', '=', 's.doc_type_id')->leftJoin('master_work_location as l', 'l.location_id', '=', 's.location_id')->leftJoin('users as ba', 'ba.user_id', '=', 's.bill_approver_id')->leftJoin('users as sb', 'sb.user_id', '=', 's.temp_scan_by')->leftJoin('core_department', 'core_department.api_id', '=', 's.department_id')->leftJoin('core_sub_department', 'core_sub_department.api_id', '=', 's.sub_department_id')->where('s.is_classified', 'Y')->where('s.is_deleted', 'N')->where('s.classified_by', $userId)->where('s.is_document_verified', 'Y');
        if ($docTypeId) {
            $query->where('s.doc_type_id', $docTypeId);
        }
        if ($departmentId) {
            $query->where('s.department_id', $departmentId);
        }
        if ($subDepartmentId) {
            $query->where('s.sub_department_id', $subDepartmentId);
        }
        if ($fromDate) {
            $query->whereDate('s.classified_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('s.classified_date', '<=', $toDate);
        }
        $query->orderBy('s.classified_date_datetime', 'DESC');
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        $documents = $documents->map(function ($doc) {
            $doc->support_files = $this->getSupportFiles($doc->scan_id);
            return $doc;
        });
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

    public function getNotVerifiedProcessedList(Request $request)
    {
        $yearId = $request->input('year_id');
        $docTypeId = $request->input('doc_type_id');
        $departmentId = $request->input('department_id');
        $subDepartmentId = $request->input('sub_department_id');
        $userId = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page');
        $query = DB::table("y{$yearId}_scan_file as s")->select(['s.scan_id', 'g.group_name', 'md.file_type', 's.extract_status', 'core_department.department_name', 'core_sub_department.sub_department_name', 'l.location_name', 's.document_name', 's.file_path', 's.file_name', 's.classified_date', 's.verified_date', 's.document_received_date', 's.is_document_verified', DB::raw("IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date) AS scan_date"), DB::raw("IF(s.is_temp_scan = 'Y', CONCAT(sb.first_name, ' ', sb.last_name), CONCAT(ba.first_name, ' ', ba.last_name)) AS scanned_by")])->leftJoin('master_group as g', 'g.group_id', '=', 's.group_id')->leftJoin('master_doctype as md', 'md.type_id', '=', 's.doc_type_id')->leftJoin('master_work_location as l', 'l.location_id', '=', 's.location_id')->leftJoin('users as ba', 'ba.user_id', '=', 's.bill_approver_id')->leftJoin('users as sb', 'sb.user_id', '=', 's.temp_scan_by')->leftJoin('core_department', 'core_department.api_id', '=', 's.department_id')->leftJoin('core_sub_department', 'core_sub_department.api_id', '=', 's.sub_department_id')->where('s.is_classified', 'Y')->where('s.is_deleted', 'N')->where('s.classified_by', $userId)->where('s.is_document_verified', 'N');
        if ($docTypeId) {
            $query->where('s.doc_type_id', $docTypeId);
        }
        if ($departmentId) {
            $query->where('s.department_id', $departmentId);
        }
        if ($subDepartmentId) {
            $query->where('s.sub_department_id', $subDepartmentId);
        }
        if ($fromDate) {
            $query->whereDate('s.classified_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('s.classified_date', '<=', $toDate);
        }
        $query->orderBy('s.classified_date_datetime', 'DESC');
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        $documents = $documents->map(function ($doc) {
            $doc->support_files = $this->getSupportFiles($doc->scan_id);
            return $doc;
        });
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
        $userId = $request->input('user_id');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page');
        $query = DB::table("y{$yearId}_scan_file as s")->select(['s.scan_id', 's.classifion_reject_date', 's.classifion_reject_remark', 'md.file_type', 's.extract_status', 's.document_name', 's.file_name', 's.file_path', DB::raw("IF(s.is_temp_scan = 'Y', s.temp_scan_date, s.scan_date) AS scan_date"), DB::raw("IF(s.is_temp_scan = 'Y', CONCAT(sb.first_name, ' ', sb.last_name), '') AS scanned_by")])->leftJoin('master_doctype as md', 'md.type_id', '=', 's.doc_type_id')->leftJoin('users as sb', 'sb.user_id', '=', 's.temp_scan_by')->where('s.document_name', '!=', '')->where('s.extract_status', 'Y')->where('s.is_deleted', 'N')->where('s.is_classified', 'Y')->where('s.is_classifion_reject', 'Y')->where('s.classified_by', $userId);
        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $lastPage = ceil($total / $perPage);
        $documents = $documents->map(function ($doc) {
            $doc->support_files = $this->getSupportFiles($doc->scan_id);
            return $doc;
        });
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

    public function getDashboardCounters(Request $request)
    {
        $yearId = $request->input('year_id');
        $userId = $request->input('user_id');
        $table = "y{$yearId}_scan_file";
        $queuedScanIds = DB::table('tbl_queues')->where('status', 'pending')->pluck('scan_id')->toArray();
        $classificationQuery = DB::table("{$table} as s")->where('s.extract_status', 'P')->where('s.is_classified', 'N')->where('s.is_final_submitted', 'Y')->where('s.is_temp_scan_rejected', 'N')->where('s.is_deleted', 'N');
        if (!empty($queuedScanIds)) {
            $classificationQuery->whereNotIn('s.scan_id', $queuedScanIds);
        }
        $processedBase = DB::table("{$table} as s")->where('s.is_classified', 'Y')->where('s.is_deleted', 'N')->where('s.classified_by', $userId);
        $rejectedCount = DB::table("{$table} as s")->where('s.document_name', '!=', '')->where('s.extract_status', 'Y')->where('s.is_deleted', 'N')->where('s.is_classified', 'Y')->where('s.is_classifion_reject', 'Y')->where('s.classified_by', $userId)->count();
        return $this->successResponse([
            'classification_list' => (clone $classificationQuery)->count(),
            'processed' => (clone $processedBase)->count(),
            'verified_processed' => (clone $processedBase)->where('s.is_document_verified', 'Y')->count(),
            'not_verified_processed' => (clone $processedBase)->where('s.is_document_verified', 'N')->count(),
            'rejected_classifications' => $rejectedCount,
        ]);
    }

    private function getSupportFiles(int $scanId): array
    {
        return DB::table('support_file')->select('supp_document_type_master.DocTypeName', 'support_file.file_path')->leftJoin('supp_document_type_master', 'supp_document_type_master.DocTypeId', '=', 'support_file.supp_doc_type_id')->where('support_file.scan_id', $scanId)->get()->toArray();
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
                'classified_date' => null,
                'classified_date_datetime' => null,
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
                'document_name' => $documentName
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

    public function getDocTypes(Request $request)
    {
        try {
            $userId = $request->input('user_id');
            $docTypes = DB::table('master_doctype')->select('type_id', 'file_type', 'short_name', 'status')->where('status', 'A')->whereIn('type_id', function ($query) use ($userId) {
                $query->select('permission_value')->from('tbl_user_permissions')->where('user_id', $userId)->where('permission_type', 'Document');
            })->orderBy('file_type', 'ASC')->get();
            return $this->successResponse($docTypes);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch document types: ' . $e->getMessage(), 500);
        }
    }

    public function getDepartments(Request $request)
    {
        try {
            $userId = $request->input('user_id');
            $departments = DB::table('core_department')->select('api_id', 'department_name', 'department_code')->whereIn('api_id', function ($query) use ($userId) {
                $query->select('permission_value')->from('tbl_user_permissions')->where('user_id', $userId)->where('permission_type', 'Department');
            })->get();
            return $this->successResponse($departments);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch departments: ' . $e->getMessage(), 500);
        }
    }

    public function getSubDepartments(Request $request)
    {
        try {
            $departmentId = $request->input('department_id');
            if (!$departmentId) {
                return $this->errorResponse('department_id is required', 400);
            }
            $subDepartments = DB::table('core_fun_vertical_dept_mapping as fvdm')->distinct()->select('sd.id as sub_department_id', 'sd.sub_department_name')->join('core_department_subdepartment_mapping as dsm', 'dsm.fun_vertical_dept_id', '=', 'fvdm.api_id')->join('core_sub_department as sd', 'sd.id', '=', 'dsm.sub_department_id')->where('fvdm.department_id', $departmentId)->get();
            return $this->successResponse($subDepartments);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch sub-departments: ' . $e->getMessage(), 500);
        }
    }

    public function getBillApprovers(Request $request)
    {
        $typeId = (int) $request->input('type_id');
        $havingMultipleDept = $request->input('having_multiple_dept', false);
        if ($havingMultipleDept) {
            $approvers = DB::table('users as u')->select('u.user_id', DB::raw("CONCAT(TRIM(u.first_name),' ',TRIM(u.last_name)) as full_name"), 'u.emp_code')->whereRaw('FIND_IN_SET(4, u.role_id) > 0')->where('u.status', 'A')->groupBy('u.user_id', 'u.first_name', 'u.last_name', 'u.emp_code')->get();
        } else {
            $approvers = DB::table('tbl_approval_matrix as am')->join('users as u', DB::raw("FIND_IN_SET(u.user_id, REPLACE(am.l1_approver,' ',''))"), '>', DB::raw('0'))->select('u.user_id', DB::raw("CONCAT(TRIM(u.first_name),' ',TRIM(u.last_name)) as full_name"), 'u.emp_code')->whereRaw("FIND_IN_SET($typeId, am.bill_type) > 0")->groupBy('u.user_id', 'u.first_name', 'u.last_name', 'u.emp_code')->get();
        }
        return $this->successResponse($approvers);
    }

    public function getAutoApproveReasons()
    {
        $reasons = DB::table('tbl_auto_approve_reason')->select('id', 'reason_name', 'description')->where('status', '1')->get();
        return response()->json(['status' => 200, 'success' => true, 'data' => $reasons]);
    }

    public function extractDetails(Request $request)
    {
        $scanId = $request->input('scan_id');
        $typeId = $request->input('type_id');
        $department = $request->input('department');
        $subDept = $request->input('subdepartment');
        $billApprover = $request->input('bill_approver');
        $autoReason = $request->input('auto_reason');
        $yearId = $request->input('year_id');
        $userId = $request->input('user_id');
        $multiDeptRaw = strtolower(trim((string) $request->input('multi_dept')));
        $autoApproveRaw = strtolower(trim((string) $request->input('auto_approve')));
        $multiDept = $multiDeptRaw === 'yes' ? 'Y' : 'N';
        $autoApprove = $autoApproveRaw === 'yes' ? 'Y' : 'N';
        if (empty($scanId) || empty($typeId)) {
            return response()->json(['status' => 'error', 'message' => 'Document Type is required.']);
        }
        if ($multiDept === 'N' && empty($department)) {
            return response()->json(['status' => 'error', 'message' => 'Department is required.']);
        }
        if ($multiDept === 'Y' && empty($billApprover)) {
            return response()->json(['status' => 'error', 'message' => 'Please select Bill Approver.']);
        }
        if ($autoApprove === 'Y' && empty($autoReason)) {
            return response()->json(['status' => 'error', 'message' => 'Please select Auto Approve Reason.']);
        }
        $table = "y{$yearId}_scan_file";
        $additionalTable = "y{$yearId}_tbl_additional_information_details";
        $data = ['is_classified' => 'Y', 'classified_by' => $userId, 'classified_date' => now()->format('Y-m-d'), 'classified_date_datetime' => now(), 'doc_type_id' => $typeId, 'department_id' => $department, 'sub_department_id' => $subDept, 'having_multiple_dep' => $multiDept, 'l1_approved_by' => $billApprover ?: null, 'l1_approved_status' => 'N', 'is_auto_approve' => $autoApprove, 'auto_approve_reason_id' => $autoReason ?: null, 'is_classifion_reject' => 'N', 'is_file_punched' => 'N'];
        DB::beginTransaction();
        try {
            $updated = DB::table($table)->where('scan_id', $scanId)->update($data);
            if (!$updated) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Failed to update document details.']);
            }
            if (!empty($department)) {
                $exists = DB::table($additionalTable)->where('scan_id', $scanId)->exists();
                if (!$exists) {
                    DB::table($additionalTable)->insert(['scan_id' => $scanId, 'department_id' => $department, 'sub_department_id' => $subDept, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $existing = DB::table('tbl_queues')->where('scan_id', $scanId)->where('status', 'pending')->first();
            if (!$existing) {
                DB::table('tbl_queues')->insert(['scan_id' => $scanId, 'type_id' => $typeId, 'status' => 'pending', 'created_at' => now(), 'created_by' => $userId]);
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Document updated and added to queue successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Something went wrong.', 'error' => $e->getMessage()]);
        }
    }
}
