<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillApproverController extends Controller
{
    use ApiResponse;

    public function getList(Request $request)
    {
        try {
            $userId      = $request->input('user_id');
            $yearId      = $request->input('year_id');
            $statusInput = $request->input('status', 'pending');
            $fromDate    = $request->input('from_date');
            $toDate      = $request->input('to_date');
            $docType     = $request->input('doc_type');
            $department  = $request->input('department');
            $subDept     = $request->input('sub_department');
            $scanBy      = $request->input('scan_by');
            $classifyBy  = $request->input('classify_by');
            $punchedBy   = $request->input('punched_by');
            $search      = $request->input('search', '');
            $perPage     = $request->input('per_page', 10);
            $page        = $request->input('page', 1);

            $status = match ($statusInput) {
                'approved' => 'Y',
                'rejected' => 'R',
                default    => 'N',
            };

            $table = "y{$yearId}_scan_file";

            $query = DB::table($table)
                ->selectRaw("
                    {$table}.scan_id,
                    {$table}.doc_type_id,
                    dept.department_name,
                    subdept.sub_department_name,
                    md.file_type AS doc_type_name,
                    {$table}.document_name,
                    {$table}.punched_date,
                    {$table}.file_name,
                    {$table}.file_path,
                    CONCAT(users.first_name,' ',users.last_name) AS punched_by_name,
                    CONCAT(sb.first_name,' ',sb.last_name) AS scanned_by_name,
                    {$table}.temp_scan_date AS scan_date,
                    CONCAT(uc.first_name,' ',uc.last_name) AS classified_by_name,
                    {$table}.classified_date
                ")
                ->leftJoin('users', 'users.user_id', '=', "{$table}.punched_by")
                ->leftJoin('users as sb', 'sb.user_id', '=', "{$table}.temp_scan_by")
                ->leftJoin('users as uc', 'uc.user_id', '=', "{$table}.classified_by")
                ->leftJoin('core_department as dept', 'dept.api_id', '=', "{$table}.department_id")
                ->leftJoin('core_sub_department as subdept', 'subdept.api_id', '=', "{$table}.sub_department_id")
                ->leftJoin('master_doctype as md', 'md.type_id', '=', "{$table}.doc_type_id")
                ->where("{$table}.is_deleted", 'N')
                ->where("{$table}.is_file_punched", 'Y')
                ->where("{$table}.punched_by", '>', 0)
                ->where("{$table}.finance_punch_status", '!=', 'R');

            $query->where(function ($q) use ($userId, $status) {
                $q->where(function ($l1) use ($userId, $status) {
                    $l1->whereRaw("FIND_IN_SET('$userId', REPLACE(l1_approved_by,' ','')) > 0");
                    if ($status === 'N') {
                        $l1->where('l1_approved_status', 'N');
                    } elseif ($status === 'Y') {
                        $l1->where('l1_approved_status', 'Y')->where('l2_approved_status', '!=', 'R');
                    } else {
                        $l1->where(function ($r) {
                            $r->where('l1_approved_status', 'R')->orWhere('finance_punch_status', 'R');
                        });
                    }
                });
                $q->orWhere(function ($l2) use ($userId, $status) {
                    $l2->whereRaw("FIND_IN_SET('$userId', REPLACE(l2_approved_by,' ','')) > 0")
                       ->where('l1_approved_status', 'Y');
                    if ($status === 'N') {
                        $l2->where('l2_approved_status', 'N');
                    } elseif ($status === 'Y') {
                        $l2->where('l2_approved_status', 'Y')->where('l3_approved_status', '!=', 'R');
                    } else {
                        $l2->where(function ($r) {
                            $r->where('l2_approved_status', 'R')->orWhere('finance_punch_status', 'R');
                        });
                    }
                });
                $q->orWhere(function ($l3) use ($userId, $status) {
                    $l3->whereRaw("FIND_IN_SET('$userId', REPLACE(l3_approved_by,' ','')) > 0")
                       ->where('l2_approved_status', 'Y');
                    if ($status === 'N') {
                        $l3->where('l3_approved_status', 'N');
                    } elseif ($status === 'Y') {
                        $l3->where('l3_approved_status', 'Y');
                    } else {
                        $l3->where('l3_approved_status', 'R');
                    }
                });
            });

            if ($fromDate)   { $query->whereRaw("DATE({$table}.punched_date) >= ?", [$fromDate]); }
            if ($toDate)     { $query->whereRaw("DATE({$table}.punched_date) <= ?", [$toDate]); }
            if ($docType)    { $query->where("{$table}.doc_type_id", $docType); }
            if ($department) { $query->where("{$table}.department_id", $department); }
            if ($subDept)    { $query->where("{$table}.sub_department_id", $subDept); }
            if ($punchedBy)  { $query->where("{$table}.punched_by", $punchedBy); }
            if ($scanBy)     { $query->where("{$table}.temp_scan_by", $scanBy); }
            if ($classifyBy) { $query->where("{$table}.classified_by", $classifyBy); }
            if ($search !== '') {
                $query->where(function ($q) use ($search, $table) {
                    $q->where("{$table}.document_name", 'like', "%{$search}%")
                      ->orWhere("{$table}.file_name", 'like', "%{$search}%")
                      ->orWhere('dept.department_name', 'like', "%{$search}%")
                      ->orWhere('subdept.sub_department_name', 'like', "%{$search}%")
                      ->orWhere('md.file_type', 'like', "%{$search}%");
                });
            }

            $total    = (clone $query)->count();
            $lastPage = (int) ceil($total / $perPage);
            $rows     = $query->orderByRaw("{$table}.punched_date DESC")
                ->skip(($page - 1) * $perPage)->take($perPage)->get();

            $data = $rows->map(fn($row) => [
                'scan_id'             => $row->scan_id,
                'doc_type_id'         => $row->doc_type_id,
                'document_name'       => $row->document_name,
                'file_name'           => $row->file_name,
                'file_path'           => $row->file_path,
                'doc_type_name'       => $row->doc_type_name ?? '',
                'department_name'     => $row->department_name ?? '',
                'sub_department_name' => $row->sub_department_name ?? '',
                'scanned_by_name'     => $row->scanned_by_name ?? '',
                'scan_date'           => $row->scan_date ? date('d-m-Y', strtotime($row->scan_date)) : '',
                'classified_by_name'  => $row->classified_by_name ?? '',
                'classified_date'     => $row->classified_date ? date('d-m-Y', strtotime($row->classified_date)) : '',
                'punched_by_name'     => $row->punched_by_name ?? '',
                'punched_date'        => $row->punched_date ? date('d-m-Y', strtotime($row->punched_date)) : '',
            ]);

            return response()->json([
                'status'  => 200,
                'success' => true,
                'data'    => $data,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $perPage,
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'from'         => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                    'to'           => $total > 0 ? min($page * $perPage, $total) : null,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch bill approver list: ' . $e->getMessage(), 500);
        }
    }

    public function getFinanceRejected(Request $request)
    {
        try {
            $userId     = $request->input('user_id');
            $yearId     = $request->input('year_id');
            $fromDate   = $request->input('from_date');
            $toDate     = $request->input('to_date');
            $docType    = $request->input('doc_type');
            $department = $request->input('department');
            $subDept    = $request->input('sub_department');
            $scanBy     = $request->input('scan_by');
            $classifyBy = $request->input('classify_by');
            $punchedBy  = $request->input('punched_by');
            $search     = $request->input('search', '');
            $perPage    = $request->input('per_page', 10);
            $page       = $request->input('page', 1);

            $table = "y{$yearId}_scan_file";

            $query = DB::table($table)
                ->selectRaw("
                    {$table}.scan_id,
                    {$table}.doc_type_id,
                    dept.department_name,
                    subdept.sub_department_name,
                    md.file_type AS doc_type_name,
                    {$table}.document_name,
                    {$table}.punched_date,
                    {$table}.file_name,
                    {$table}.file_path,
                    CONCAT(users.first_name,' ',users.last_name) AS punched_by_name,
                    CONCAT(sb.first_name,' ',sb.last_name) AS scanned_by_name,
                    {$table}.temp_scan_date AS scan_date,
                    CONCAT(uc.first_name,' ',uc.last_name) AS classified_by_name,
                    {$table}.classified_date
                ")
                ->leftJoin('users', 'users.user_id', '=', "{$table}.punched_by")
                ->leftJoin('users as sb', 'sb.user_id', '=', "{$table}.temp_scan_by")
                ->leftJoin('users as uc', 'uc.user_id', '=', "{$table}.classified_by")
                ->leftJoin('core_department as dept', 'dept.api_id', '=', "{$table}.department_id")
                ->leftJoin('core_sub_department as subdept', 'subdept.api_id', '=', "{$table}.sub_department_id")
                ->leftJoin('master_doctype as md', 'md.type_id', '=', "{$table}.doc_type_id");

            $query->where(function ($q) use ($userId) {
                $q->where(function ($g) use ($userId) {
                    $g->whereRaw("FIND_IN_SET('$userId', REPLACE(l1_approved_by_id,' ','')) > 0")
                      ->where('l1_approved_status', 'Y')
                      ->where('l2_approved_status', 'R');
                });
                $q->orWhere(function ($g) use ($userId) {
                    $g->whereRaw("FIND_IN_SET('$userId', REPLACE(l2_approved_by_id,' ','')) > 0")
                      ->where('l2_approved_status', 'Y')
                      ->where('l3_approved_status', 'R');
                });
                $q->orWhere(function ($g) use ($userId) {
                    $g->where('finance_punch_status', 'R')
                      ->where(function ($last) use ($userId) {
                          $last->where(function ($l) use ($userId) {
                              $l->whereRaw("FIND_IN_SET('$userId', REPLACE(l1_approved_by_id,' ','')) > 0")
                                ->whereRaw("(l2_approved_by_id IS NULL OR l2_approved_by_id = '')");
                          })->orWhere(function ($l) use ($userId) {
                              $l->whereRaw("FIND_IN_SET('$userId', REPLACE(l2_approved_by_id,' ','')) > 0")
                                ->whereRaw("(l3_approved_by_id IS NULL OR l3_approved_by_id = '')");
                          })->orWhere(function ($l) use ($userId) {
                              $l->whereRaw("FIND_IN_SET('$userId', REPLACE(l3_approved_by_id,' ','')) > 0");
                          });
                      });
                });
            });

            if ($fromDate)   { $query->whereRaw("DATE({$table}.punched_date) >= ?", [$fromDate]); }
            if ($toDate)     { $query->whereRaw("DATE({$table}.punched_date) <= ?", [$toDate]); }
            if ($docType)    { $query->where("{$table}.doc_type_id", $docType); }
            if ($department) { $query->where("{$table}.department_id", $department); }
            if ($subDept)    { $query->where("{$table}.sub_department_id", $subDept); }
            if ($punchedBy)  { $query->where("{$table}.punched_by", $punchedBy); }
            if ($scanBy)     { $query->where("{$table}.temp_scan_by", $scanBy); }
            if ($classifyBy) { $query->where("{$table}.classified_by", $classifyBy); }
            if ($search !== '') {
                $query->where(function ($q) use ($search, $table) {
                    $q->where("{$table}.document_name", 'like', "%{$search}%")
                      ->orWhere("{$table}.file_name", 'like', "%{$search}%")
                      ->orWhere('dept.department_name', 'like', "%{$search}%")
                      ->orWhere('subdept.sub_department_name', 'like', "%{$search}%")
                      ->orWhere('md.file_type', 'like', "%{$search}%");
                });
            }

            $total    = (clone $query)->count();
            $lastPage = (int) ceil($total / $perPage);
            $rows     = $query->orderByRaw("{$table}.punched_date DESC")
                ->skip(($page - 1) * $perPage)->take($perPage)->get();

            $data = $rows->map(fn($row) => [
                'scan_id'             => $row->scan_id,
                'doc_type_id'         => $row->doc_type_id,
                'document_name'       => $row->document_name,
                'file_name'           => $row->file_name,
                'file_path'           => $row->file_path,
                'doc_type_name'       => $row->doc_type_name ?? '',
                'department_name'     => $row->department_name ?? '',
                'sub_department_name' => $row->sub_department_name ?? '',
                'scanned_by_name'     => $row->scanned_by_name ?? '',
                'scan_date'           => $row->scan_date ? date('d-m-Y', strtotime($row->scan_date)) : '',
                'classified_by_name'  => $row->classified_by_name ?? '',
                'classified_date'     => $row->classified_date ? date('d-m-Y', strtotime($row->classified_date)) : '',
                'punched_by_name'     => $row->punched_by_name ?? '',
                'punched_date'        => $row->punched_date ? date('d-m-Y', strtotime($row->punched_date)) : '',
            ]);

            return response()->json([
                'status'  => 200,
                'success' => true,
                'data'    => $data,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $perPage,
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'from'         => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                    'to'           => $total > 0 ? min($page * $perPage, $total) : null,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch finance punch rejected list: ' . $e->getMessage(), 500);
        }
    }

    public function action(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|integer',
            'user_id' => 'required|integer',
            'year_id' => 'required|integer',
            'action'  => 'required|in:approve,reject',
        ]);

        $scanId = $request->input('scan_id');
        $userId = $request->input('user_id');
        $yearId = $request->input('year_id');
        $action = $request->input('action');
        $remark = $request->input('remark');

        $table = "y{$yearId}_scan_file";
        $bill  = DB::table($table)->where('scan_id', $scanId)->first();

        if (!$bill) {
            return $this->notFoundResponse('Record not found');
        }

        $levels   = ['l1', 'l2', 'l3'];
        $maxLevel = 0;

        foreach ($levels as $i => $level) {
            if (!empty($bill->{$level . '_approved_by'})) {
                $maxLevel = $i + 1;
            }
        }

        $updated = false;

        foreach ($levels as $i => $level) {
            $prevLevelOk = $i === 0 ? true : ($bill->{$levels[$i - 1] . '_approved_status'} === 'Y');
            $approvedBy  = str_replace(' ', '', $bill->{$level . '_approved_by'} ?? '');
            $isMyLevel   = !empty($approvedBy) && str_contains($approvedBy, (string) $userId);
            $myLevelNum  = $i + 1;
            $canAct      = $isMyLevel && $bill->{$level . '_approved_status'} === 'N' && $prevLevelOk;

            if ($bill->finance_punch_status === 'R' && $isMyLevel && $myLevelNum === $maxLevel) {
                $canAct = true;
            }

            for ($j = $myLevelNum + 1; $j <= $maxLevel; $j++) {
                if ($isMyLevel && ($bill->{'l' . $j . '_approved_status'} ?? '') === 'R') {
                    $canAct = true;
                    break;
                }
            }

            if ($canAct) {
                DB::table($table)->where('scan_id', $scanId)->update([
                    $level . '_approved_status' => $action === 'approve' ? 'Y' : 'R',
                    $level . '_approved_by_id'  => $userId,
                    $level . '_approved_date'   => now(),
                    $level . '_remark'          => $remark,
                    'finance_punch_status'      => 'N',
                ]);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $msg = $action === 'approve' ? 'Bill approved successfully' : 'Bill rejected successfully';
            return $this->successResponse(null, $msg);
        }

        $msg = $action === 'approve' ? 'No pending approval found for you' : 'No pending rejection found for you';
        return $this->errorResponse($msg, 400);
    }
}
