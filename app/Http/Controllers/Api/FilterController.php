<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FilterController extends Controller
{
    use ApiResponse;

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

    public function getScanners(Request $request)
    {
        try {
            $yearId = $request->input('year_id');
            $scanTable = "y{$yearId}_scan_file";

            if (!Schema::hasTable($scanTable)) {
                return $this->successResponse([]);
            }

            $scanners = DB::table('users as u')
                ->select('u.user_id', DB::raw("CONCAT(u.first_name, ' ', u.last_name) as scanner_name"))
                ->join("{$scanTable} as sf", 'u.user_id', '=', 'sf.temp_scan_by')
                ->where('sf.is_temp_scan', 'Y')
                ->where('u.status', 'A')
                ->groupBy('u.user_id', 'u.first_name', 'u.last_name')
                ->orderBy('scanner_name', 'ASC')
                ->get();

            return $this->successResponse($scanners);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch scanners: ' . $e->getMessage(), 500);
        }
    }

    public function getClassifiers(Request $request)
    {
        try {
            $yearId = $request->input('year_id');
            $scanTable = "y{$yearId}_scan_file";

            if (!Schema::hasTable($scanTable)) {
                return $this->successResponse([]);
            }

            $classifiers = DB::table('users as u')
                ->select('u.user_id', DB::raw("CONCAT(u.first_name, ' ', u.last_name) as classifier_name"))
                ->join("{$scanTable} as sf", 'u.user_id', '=', 'sf.classified_by')
                ->where('sf.is_classified', 'Y')
                ->where('u.status', 'A')
                ->groupBy('u.user_id', 'u.first_name', 'u.last_name')
                ->orderBy('classifier_name', 'ASC')
                ->get();

            return $this->successResponse($classifiers);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch classifiers: ' . $e->getMessage(), 500);
        }
    }

    public function getPunchedBy(Request $request)
    {
        try {
            $yearId = $request->input('year_id');
            $scanTable = "y{$yearId}_scan_file";

            if (!Schema::hasTable($scanTable)) {
                return $this->successResponse([]);
            }

            $users = DB::table('users as u')
                ->select('u.user_id', DB::raw("CONCAT(u.first_name, ' ', u.last_name) as punched_by_name"))
                ->join("{$scanTable} as sf", 'u.user_id', '=', 'sf.punched_by')
                ->where('sf.is_file_punched', 'Y')
                ->where('sf.punched_by', '>', 0)
                ->where('u.status', 'A')
                ->groupBy('u.user_id', 'u.first_name', 'u.last_name')
                ->orderBy('punched_by_name', 'ASC')
                ->get();

            return $this->successResponse($users);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch punched by users: ' . $e->getMessage(), 500);
        }
    }
}
