<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'year_id' => 'required|integer',
        ]);
        $username = $request->input('username');
        $password = $request->input('password');
        $yearId = $request->input('year_id');
        try {
            $user = DB::table('users')->leftJoin('core_employee_tools', 'core_employee_tools.employee_id', '=', 'users.username')->select('users.user_id', 'users.status', 'core_employee_tools.emp_contact', 'core_employee_tools.emp_name', 'core_employee_tools.emp_code')->where('core_employee_tools.emp_code', $username)->where('core_employee_tools.emp_contact', $password)->where('users.status', 'A')->first();
            if (!$user) {
                return $this->errorResponse('Invalid credentials or inactive account', 401);
            }
            $financialYear = DB::table('financial_years')->where('id', $yearId)->first();
            if (!$financialYear) {
                return $this->errorResponse('Invalid financial year', 400);
            }
            return $this->successResponse([
                'user_id' => $user->user_id,
                'emp_code' => $user->emp_code,
                'emp_name' => $user->emp_name,
                'emp_contact' => $user->emp_contact,
                'status' => $user->status,
                'year_id' => $yearId,
                'year_label' => $financialYear->label,
                'start_date' => $financialYear->start_date,
                'end_date' => $financialYear->end_date,
                'is_current' => $financialYear->is_current,
            ], 'Login successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage(), 500);
        }
    }

    public function getFinancialYears()
    {
        try {
            $years = DB::table('financial_years')->select('id', 'label', 'start_date', 'end_date', 'is_current')->orderBy('id', 'DESC')->get();
            return $this->successResponse($years);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch financial years: ' . $e->getMessage(), 500);
        }
    }
}
