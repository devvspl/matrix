<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PunchEntryController extends Controller
{
    use ApiResponse;

    public function getScanDetail(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|integer',
            'year_id' => 'required|integer',
        ]);
        $scanId = $request->input('scan_id');
        $yearId = $request->input('year_id');
        $scanTable = "y{$yearId}_scan_file";
        try {
            $scan = DB::table($scanTable)->where('scan_id', $scanId)->first();
            if (!$scan) {
                return $this->notFoundResponse('Scan file not found');
            }
            $docTypeId = $scan->doc_type_id;
            $punchTable = "y{$yearId}_punchdata_{$docTypeId}";
            $punchDetailTable = "y{$yearId}_punchdata_{$docTypeId}_details";
            $punchData = Schema::hasTable($punchTable) ? DB::table($punchTable)->where('scan_id', $scanId)->first() : null;
            $punchDetails = Schema::hasTable($punchDetailTable) ? DB::table($punchDetailTable)->where('scan_id', $scanId)->get() : [];
            return $this->successResponse([
                'scan' => $scan,
                'punch_data' => $punchData,
                'punch_details' => $punchDetails,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch scan detail: ' . $e->getMessage(), 500);
        }
    }
}
