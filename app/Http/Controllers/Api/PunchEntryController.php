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
            $scan = DB::table("{$scanTable} as st")
                ->selectRaw("
                    st.scan_id,
                    st.doc_type_id,
                    md.file_type AS doc_type,
                    cd.department_name,
                    csd.sub_department_name,
                    st.file_name,
                    st.document_name,
                    st.file_path,
                    st.temp_scan_date AS scan_date,
                    CONCAT(u.first_name, ' ', u.last_name) AS scanned_by,
                    st.classified_date,
                    CONCAT(uc.first_name, ' ', uc.last_name) AS classified_by,
                    st.punched_date,
                    CONCAT(up.first_name, ' ', up.last_name) AS punched_by
                ")
                ->leftJoin('master_doctype as md', 'md.type_id', '=', 'st.doc_type_id')
                ->leftJoin('core_department as cd', 'cd.api_id', '=', 'st.department_id')
                ->leftJoin('core_sub_department as csd', 'csd.api_id', '=', 'st.sub_department_id')
                ->leftJoin('users as u', 'u.user_id', '=', 'st.temp_scan_by')
                ->leftJoin('users as uc', 'uc.user_id', '=', 'st.classified_by')
                ->leftJoin('users as up', 'up.user_id', '=', 'st.punched_by')
                ->where('st.scan_id', $scanId)
                ->first();

            if (!$scan) {
                return $this->notFoundResponse('Scan file not found');
            }

            $punchDetail = $this->getPunchDetail($scanId, $yearId, $scan->doc_type_id);

            return $this->successResponse([
                'scan' => [
                    ['label' => 'Scan ID', 'key' => 'scan_id', 'value' => $scan->scan_id],
                    ['label' => 'Document Type', 'key' => 'doc_type', 'value' => $scan->doc_type],
                    ['label' => 'Department', 'key' => 'department_name', 'value' => $scan->department_name],
                    ['label' => 'Sub Department', 'key' => 'sub_department_name', 'value' => $scan->sub_department_name],
                    ['label' => 'File Name', 'key' => 'file_name', 'value' => $scan->file_name],
                    ['label' => 'Document Name', 'key' => 'document_name', 'value' => $scan->document_name],
                    ['label' => 'File Path', 'key' => 'file_path', 'value' => $scan->file_path],
                    ['label' => 'Scan Date', 'key' => 'scan_date', 'value' => $scan->scan_date],
                    ['label' => 'Scanned By', 'key' => 'scanned_by', 'value' => $scan->scanned_by],
                    ['label' => 'Classified Date', 'key' => 'classified_date', 'value' => $scan->classified_date],
                    ['label' => 'Classified By', 'key' => 'classified_by', 'value' => $scan->classified_by],
                    ['label' => 'Punched Date', 'key' => 'punched_date', 'value' => $scan->punched_date],
                    ['label' => 'Punched By', 'key' => 'punched_by', 'value' => $scan->punched_by],
                ],
                'punch_detail' => $punchDetail,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch scan detail: ' . $e->getMessage(), 500);
        }
    }

    private function getPunchDetail(int $scanId, int $yearId, int $docTypeId): ?object
    {
        $scanTable = "y{$yearId}_scan_file";
        $scan = DB::table($scanTable)->select('department_id')->where('scan_id', $scanId)->first();
        $deptId = (int) ($scan->department_id ?? 0);

        $detail = match ($docTypeId) {
            1 => $this->getPunchDetailType1($scanId, $yearId),
            6 => $this->getPunchDetailType6($scanId, $yearId),
            7 => $this->getPunchDetailType7($scanId, $yearId),
            13 => $this->getPunchDetailType13($scanId, $yearId),
            17 => $this->getPunchDetailType17($scanId, $yearId),
            20 => $this->getPunchDetailType20($scanId, $yearId),
            22 => $this->getPunchDetailType22($scanId, $yearId),
            23 => $this->getPunchDetailType23($scanId, $yearId),
            27 => $this->getPunchDetailType27($scanId, $yearId),
            28 => $this->getPunchDetailType28($scanId, $yearId),
            29 => $this->getPunchDetailType29($scanId, $yearId),
            31 => $this->getPunchDetailType31($scanId, $yearId),
            42 => $this->getPunchDetailType42($scanId, $yearId),
            43 => $this->getPunchDetailType43($scanId, $yearId),
            44 => $this->getPunchDetailType44($scanId, $yearId),
            46 => $this->getPunchDetailType46($scanId, $yearId),
            47 => $this->getPunchDetailType47($scanId, $yearId),
            48 => $this->getPunchDetailType48($scanId, $yearId),
            50 => $this->getPunchDetailType50($scanId, $yearId),
            51 => $this->getPunchDetailType51($scanId, $yearId),
            56 => $this->getPunchDetailType56($scanId, $yearId),
            61 => $this->getPunchDetailType61($scanId, $yearId),
            62 => $this->getPunchDetailType62($scanId, $yearId),
            63 => $this->getPunchDetailType63($scanId, $yearId),
            65 => $this->getPunchDetailType65($scanId, $yearId),
            default => null,
        };

        if ($detail) {
            $detail->additional_details = $this->getAdditionalDetails($scanId, $yearId, $docTypeId, $deptId);
        }

        return $detail;
    }

    private function getPunchDetailType1(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_1";
        $detailTable = "y{$yearId}_punchdata_1_details";
        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->leftJoin('master_employee as me', 'me.id', '=', 'p.employee_name')
                ->selectRaw('
                    me.emp_name as master_emp_name,
                    me.emp_code as master_emp_code,
                    p.emp_code,
                    p.bill_date,
                    p.vehicle_no,
                    p.vehicle_type,
                    p.location,
                    p.rs_km,
                    p.total_run_km,
                    p.total,
                    p.total_discount,
                    p.round_off_value,
                    p.round_off_type,
                    p.grand_total,
                    p.remark_comment AS remark
                ')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;
        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw('
                    d.opening_km,
                    d.closing_km,
                    d.total_km,
                    d.amount
                ')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Opening KM', 'key' => 'opening_km'],
            ['label' => 'Closing KM', 'key' => 'closing_km'],
            ['label' => 'Total KM', 'key' => 'total_km'],
            ['label' => 'Amount', 'key' => 'amount'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Employee / Payee Name', 'key' => 'employee_name', 'value' => $punch->master_emp_name ?? null],
                ['label' => 'Emp Code', 'key' => 'emp_code', 'value' => $punch->master_emp_code ?? null],
                ['label' => 'Bill Date', 'key' => 'bill_date', 'value' => $punch->bill_date ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Vehicle Type', 'key' => 'vehicle_type', 'value' => $punch->vehicle_type ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Rs/KM', 'key' => 'rs_km', 'value' => $punch->rs_km ?? null],
                ['label' => 'Total Run KM', 'key' => 'total_run_km', 'value' => $punch->total_run_km ?? null],
                ['label' => 'Total', 'key' => 'total', 'value' => $punch->total ?? null],
                ['label' => 'Total Discount', 'key' => 'total_discount', 'value' => $punch->total_discount ?? null],
                ['label' => 'Round Off', 'key' => 'round_off_value', 'value' => $punch->round_off_value ?? null],
                ['label' => 'Round Off Type', 'key' => 'round_off_type', 'value' => $punch->round_off_type ?? null],
                ['label' => 'Grand Total', 'key' => 'grand_total', 'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType23(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_23";
        $detailTable = "y{$yearId}_punchdata_23_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.invoice_no,
                    p.invoice_date,
                    p.buyers_order_no AS purchase_order_no,
                    p.buyers_order_date AS purchase_order_date,
                    mf_buyer.firm_name AS buyer_name,
                    mf_buyer.address AS buyer_address,
                    mf_vendor.firm_name AS vendor_name,
                    mf_vendor.address AS vendor_address,
                    p.dispatch_through,
                    p.delivery_note_date,
                    p.lr_number,
                    p.lr_date,
                    p.total,
                    p.sub_total,
                    p.round_off,
                    p.discount_in_mrp AS additional_discount,
                    p.grand_total,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf_buyer', 'mf_buyer.firm_id', '=', 'p.buyer')
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw('
                    d.particular,
                    d.hsn,
                    d.qty,
                    u.unit_name AS unit,
                    d.mrp,
                    d.discount_in_mrp AS dis_mrp,
                    d.amount AS amt,
                    d.gst AS cgst,
                    d.sgst,
                    d.igst,
                    d.cess,
                    d.total_amount AS total_amt
                ')
                ->leftJoin('master_unit as u', 'u.unit_id', '=', 'd.unit')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Particular', 'key' => 'particular'],
            ['label' => 'HSN', 'key' => 'hsn'],
            ['label' => 'Qty', 'key' => 'qty'],
            ['label' => 'Unit', 'key' => 'unit'],
            ['label' => 'MRP', 'key' => 'mrp'],
            ['label' => 'Dis. MRP', 'key' => 'dis_mrp'],
            ['label' => 'Amt', 'key' => 'amt'],
            ['label' => 'CGST %', 'key' => 'cgst'],
            ['label' => 'SGST %', 'key' => 'sgst'],
            ['label' => 'IGST %', 'key' => 'igst'],
            ['label' => 'Cess %', 'key' => 'cess'],
            ['label' => 'Total Amt', 'key' => 'total_amt'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Invoice No.', 'key' => 'invoice_no', 'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date', 'key' => 'invoice_date', 'value' => $punch->invoice_date ?? null],
                ['label' => 'Purchase Order No.', 'key' => 'purchase_order_no', 'value' => $punch->purchase_order_no ?? null],
                ['label' => 'Purchase Order Date', 'key' => 'purchase_order_date', 'value' => $punch->purchase_order_date ?? null],
                ['label' => 'Buyer', 'key' => 'buyer_name', 'value' => $punch->buyer_name ?? null],
                ['label' => 'Buyer Address', 'key' => 'buyer_address', 'value' => $punch->buyer_address ?? null],
                ['label' => 'Vendor', 'key' => 'vendor_name', 'value' => $punch->vendor_name ?? null],
                ['label' => 'Vendor Address', 'key' => 'vendor_address', 'value' => $punch->vendor_address ?? null],
                ['label' => 'Dispatch Through', 'key' => 'dispatch_through', 'value' => $punch->dispatch_through ?? null],
                ['label' => 'Delivery Note Date', 'key' => 'delivery_note_date', 'value' => $punch->delivery_note_date ?? null],
                ['label' => 'LR Number', 'key' => 'lr_number', 'value' => $punch->lr_number ?? null],
                ['label' => 'LR Date', 'key' => 'lr_date', 'value' => $punch->lr_date ?? null],
                ['label' => 'Total', 'key' => 'total', 'value' => $punch->total ?? null],
                ['label' => 'Sub Total', 'key' => 'sub_total', 'value' => $punch->sub_total ?? null],
                ['label' => 'Round Off', 'key' => 'round_off', 'value' => $punch->round_off ?? null],
                ['label' => 'Additional Discount', 'key' => 'additional_discount', 'value' => $punch->additional_discount ?? null],
                ['label' => 'Grand Total', 'key' => 'grand_total', 'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType65(int $scanId, int $yearId): object
    {
        $punchTable  = "y{$yearId}_punchdata_65";
        $detailTable = "y{$yearId}_punchdata_65_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw("
                    p.month, p.year, p.farmer_name, p.location,
                    cs.state_name AS state,
                    p.total_labour_light, p.total_labour_light_cost,
                    p.total_labour_hard, p.total_labour_hard_cost,
                    p.total_amount, p.total, p.additional_charges,
                    p.grand_total, p.general_remark AS remark
                ")
                ->leftJoin('core_state as cs', 'cs.api_id', '=', 'p.state')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw("
                    d.date, d.activities, d.crop,
                    d.labour_light AS light, d.labour_light_cost AS cost_light,
                    d.labour_hard AS hard, d.labour_hard_cost AS cost_hard,
                    d.labour_cost, d.item_purchased, d.row_total, d.remark
                ")
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#',             'key' => 'sr_no'],
            ['label' => 'Date',          'key' => 'date'],
            ['label' => 'Activities',    'key' => 'activities'],
            ['label' => 'Crop',          'key' => 'crop'],
            ['label' => 'Light',         'key' => 'light'],
            ['label' => 'Cost (Light)',  'key' => 'cost_light'],
            ['label' => 'Hard',          'key' => 'hard'],
            ['label' => 'Cost (Hard)',   'key' => 'cost_hard'],
            ['label' => 'Labour Cost',   'key' => 'labour_cost'],
            ['label' => 'Item Purchased','key' => 'item_purchased'],
            ['label' => 'Total',         'key' => 'row_total'],
            ['label' => 'Remark',        'key' => 'remark'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Month',                    'key' => 'month',                    'value' => $punch->month ?? null],
                ['label' => 'Year',                     'key' => 'year',                     'value' => $punch->year ?? null],
                ['label' => 'Farmer Name',              'key' => 'farmer_name',              'value' => $punch->farmer_name ?? null],
                ['label' => 'Location',                 'key' => 'location',                 'value' => $punch->location ?? null],
                ['label' => 'State',                    'key' => 'state',                    'value' => $punch->state ?? null],
                ['label' => 'Total Labour Light',       'key' => 'total_labour_light',       'value' => $punch->total_labour_light ?? null],
                ['label' => 'Total Labour Light Cost',  'key' => 'total_labour_light_cost',  'value' => $punch->total_labour_light_cost ?? null],
                ['label' => 'Total Labour Hard',        'key' => 'total_labour_hard',        'value' => $punch->total_labour_hard ?? null],
                ['label' => 'Total Labour Hard Cost',   'key' => 'total_labour_hard_cost',   'value' => $punch->total_labour_hard_cost ?? null],
                ['label' => 'Total Amount',             'key' => 'total_amount',             'value' => $punch->total_amount ?? null],
                ['label' => 'Total',                    'key' => 'total',                    'value' => $punch->total ?? null],
                ['label' => 'Additional Charges',       'key' => 'additional_charges',       'value' => $punch->additional_charges ?? null],
                ['label' => 'Grand Total',              'key' => 'grand_total',              'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment',         'key' => 'remark',                   'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items'        => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType63(int $scanId, int $yearId): object
    {
        $punchTable  = "y{$yearId}_punchdata_63";
        $detailTable = "y{$yearId}_punchdata_63_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw("
                    me.emp_name as employee_name,
                    p.employee_no,
                    p.designation,
                    p.hq,
                    p.days_worked,
                    p.period_from,
                    p.period_to,
                    p.grand_total_claimed,
                    p.grand_total_passed,
                    p.remark_comment AS remark
                ")
                ->leftJoin('master_employee as me', 'me.id', '=', 'p.employee_name')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw("
                    d.header_index,
                    d.expense_head,
                    d.header_description,
                    d.particular,
                    d.claimed_amount,
                    d.passed_amount,
                    d.header_claimed_total,
                    d.header_passed_total
                ")
                ->where('d.scan_id', $scanId)
                ->orderBy('d.header_index')
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#',                   'key' => 'sr_no'],
            ['label' => 'Expense Head',        'key' => 'expense_head'],
            ['label' => 'Header Description',  'key' => 'header_description'],
            ['label' => 'Particular',          'key' => 'particular'],
            ['label' => 'Claimed Amount',      'key' => 'claimed_amount'],
            ['label' => 'Passed Amount',       'key' => 'passed_amount'],
            ['label' => 'Header Claimed Total','key' => 'header_claimed_total'],
            ['label' => 'Header Passed Total', 'key' => 'header_passed_total'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Name',                  'key' => 'employee_name',       'value' => $punch->employee_name ?? null],
                ['label' => 'Employee No.',          'key' => 'employee_no',         'value' => $punch->employee_no ?? null],
                ['label' => 'Designation',           'key' => 'designation',         'value' => $punch->designation ?? null],
                ['label' => 'HQ',                    'key' => 'hq',                  'value' => $punch->hq ?? null],
                ['label' => 'Days Worked',           'key' => 'days_worked',         'value' => $punch->days_worked ?? null],
                ['label' => 'Period From',           'key' => 'period_from',         'value' => $punch->period_from ?? null],
                ['label' => 'Period To',             'key' => 'period_to',           'value' => $punch->period_to ?? null],
                ['label' => 'Grand Total Claimed',   'key' => 'grand_total_claimed', 'value' => $punch->grand_total_claimed ?? null],
                ['label' => 'Grand Total Passed',    'key' => 'grand_total_passed',  'value' => $punch->grand_total_passed ?? null],
                ['label' => 'Remark / Comment',      'key' => 'remark',              'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items'        => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType62(int $scanId, int $yearId): object
    {
        $punchTable  = "y{$yearId}_punchdata_62";
        $detailTable = "y{$yearId}_punchdata_62_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw("
                    p.credit_note_no AS debit_note_no,
                    p.credit_note_date AS debit_note_date,
                    mf_vendor.firm_name AS vendor_name,
                    mf_vendor.address AS vendor_address,
                    mf_buyer.firm_name AS buyer_name,
                    mf_buyer.address AS buyer_address,
                    p.mode_of_payment,
                    p.invoice_no,
                    p.invoice_date,
                    p.buyers_order_no,
                    p.buyers_order_date,
                    p.dispatch_through,
                    p.delivery_note_date,
                    p.department,
                    p.voucher_type_category,
                    p.ledger,
                    lm.sName AS location,
                    p.lr_number,
                    p.lr_date,
                    p.cartoon_number,
                    p.sub_total,
                    p.tcs_percentage,
                    p.total,
                    p.round_off,
                    p.round_off_type,
                    p.grand_total,
                    p.remark_comment AS remark
                ")
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor')
                ->leftJoin('master_firm as mf_buyer', 'mf_buyer.firm_id', '=', 'p.buyer')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw("
                    d.particular, d.hsn, d.qty, d.unit, d.mrp,
                    d.discount_in_mrp, d.price, d.amount,
                    d.gst, d.sgst, d.igst, d.cess, d.total_amount
                ")
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#',               'key' => 'sr_no'],
            ['label' => 'Particular',      'key' => 'particular'],
            ['label' => 'HSN',             'key' => 'hsn'],
            ['label' => 'Qty',             'key' => 'qty'],
            ['label' => 'Unit',            'key' => 'unit'],
            ['label' => 'MRP',             'key' => 'mrp'],
            ['label' => 'Discount in MRP', 'key' => 'discount_in_mrp'],
            ['label' => 'Price',           'key' => 'price'],
            ['label' => 'Amount',          'key' => 'amount'],
            ['label' => 'GST %',           'key' => 'gst'],
            ['label' => 'SGST %',          'key' => 'sgst'],
            ['label' => 'IGST %',          'key' => 'igst'],
            ['label' => 'Cess %',          'key' => 'cess'],
            ['label' => 'Total Amount',    'key' => 'total_amount'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Debit Note No.',        'key' => 'debit_note_no',        'value' => $punch->debit_note_no ?? null],
                ['label' => 'Debit Note Date',       'key' => 'debit_note_date',      'value' => $punch->debit_note_date ?? null],
                ['label' => 'Vendor',                'key' => 'vendor_name',          'value' => $punch->vendor_name ?? null],
                ['label' => 'Vendor Address',        'key' => 'vendor_address',       'value' => $punch->vendor_address ?? null],
                ['label' => 'Buyer',                 'key' => 'buyer_name',           'value' => $punch->buyer_name ?? null],
                ['label' => 'Buyer Address',         'key' => 'buyer_address',        'value' => $punch->buyer_address ?? null],
                ['label' => 'Mode of Payment',       'key' => 'mode_of_payment',      'value' => $punch->mode_of_payment ?? null],
                ['label' => 'Invoice No.',           'key' => 'invoice_no',           'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date',          'key' => 'invoice_date',         'value' => $punch->invoice_date ?? null],
                ['label' => "Buyer's Order No.",     'key' => 'buyers_order_no',      'value' => $punch->buyers_order_no ?? null],
                ['label' => "Buyer's Order Date",    'key' => 'buyers_order_date',    'value' => $punch->buyers_order_date ?? null],
                ['label' => 'Dispatch Through',      'key' => 'dispatch_through',     'value' => $punch->dispatch_through ?? null],
                ['label' => 'Delivery Note Date',    'key' => 'delivery_note_date',   'value' => $punch->delivery_note_date ?? null],
                ['label' => 'Department',            'key' => 'department',           'value' => $punch->department ?? null],
                ['label' => 'Voucher Type/Category', 'key' => 'voucher_type_category','value' => $punch->voucher_type_category ?? null],
                ['label' => 'Ledger',                'key' => 'ledger',               'value' => $punch->ledger ?? null],
                ['label' => 'Location',              'key' => 'location',             'value' => $punch->location ?? null],
                ['label' => 'LR Number',             'key' => 'lr_number',            'value' => $punch->lr_number ?? null],
                ['label' => 'LR Date',               'key' => 'lr_date',              'value' => $punch->lr_date ?? null],
                ['label' => 'Cartoon Number',        'key' => 'cartoon_number',       'value' => $punch->cartoon_number ?? null],
                ['label' => 'Sub Total',             'key' => 'sub_total',            'value' => $punch->sub_total ?? null],
                ['label' => 'TCS %',                 'key' => 'tcs_percentage',       'value' => $punch->tcs_percentage ?? null],
                ['label' => 'Total',                 'key' => 'total',                'value' => $punch->total ?? null],
                ['label' => 'Round Off',             'key' => 'round_off',            'value' => $punch->round_off ?? null],
                ['label' => 'Round Off Type',        'key' => 'round_off_type',       'value' => $punch->round_off_type ?? null],
                ['label' => 'Grand Total',           'key' => 'grand_total',          'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment',      'key' => 'remark',               'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items'        => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType61(int $scanId, int $yearId): object
    {
        $punchTable  = "y{$yearId}_punchdata_61";
        $detailTable = "y{$yearId}_punchdata_61_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw("
                    me.emp_name as employee_name,
                    p.department,
                    lm.sName AS location,
                    p.imprest_amount_issued,
                    p.date_of_issue,
                    p.purpose_of_imprest,
                    p.number_of_bills_attached,
                    p.voucher_numbers,
                    p.balance_opening,
                    p.total_imprest_amount,
                    p.total_expenses,
                    p.balance_amount,
                    p.comment
                ")
                ->leftJoin('master_employee as me', 'me.id', '=', 'p.employee_name')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw("d.date, d.expense_head, d.amount, d.remark")
                ->where('d.scan_id', $scanId)
                ->orderBy('d.sr_no')
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#',            'key' => 'sr_no'],
            ['label' => 'Date',         'key' => 'date'],
            ['label' => 'Expense Head', 'key' => 'expense_head'],
            ['label' => 'Amount',       'key' => 'amount'],
            ['label' => 'Remark',       'key' => 'remark'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Name',                    'key' => 'employee_name',          'value' => $punch->employee_name ?? null],
                ['label' => 'Department',              'key' => 'department',             'value' => $punch->department ?? null],
                ['label' => 'Location',                'key' => 'location',               'value' => $punch->location ?? null],
                ['label' => 'Imprest Amount Issued',   'key' => 'imprest_amount_issued',  'value' => $punch->imprest_amount_issued ?? null],
                ['label' => 'Date of Issue',           'key' => 'date_of_issue',          'value' => $punch->date_of_issue ?? null],
                ['label' => 'Purpose of Imprest',      'key' => 'purpose_of_imprest',     'value' => $punch->purpose_of_imprest ?? null],
                ['label' => 'No. of Bills Attached',   'key' => 'number_of_bills_attached','value' => $punch->number_of_bills_attached ?? null],
                ['label' => 'Voucher Numbers',         'key' => 'voucher_numbers',        'value' => $punch->voucher_numbers ?? null],
                ['label' => 'Balance Opening',         'key' => 'balance_opening',        'value' => $punch->balance_opening ?? null],
                ['label' => 'Total Imprest Amount',    'key' => 'total_imprest_amount',   'value' => $punch->total_imprest_amount ?? null],
                ['label' => 'Total Expenses',          'key' => 'total_expenses',         'value' => $punch->total_expenses ?? null],
                ['label' => 'Balance Amount',          'key' => 'balance_amount',         'value' => $punch->balance_amount ?? null],
                ['label' => 'Comment',                 'key' => 'comment',                'value' => $punch->comment ?? null],
            ],
            'item_columns' => $itemColumns,
            'items'        => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType56(int $scanId, int $yearId): object
    {
        $punchTable  = "y{$yearId}_punchdata_56";
        $detailTable = "y{$yearId}_punchdata_56_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw("
                    p.credit_note_no,
                    p.credit_note_date,
                    mf_vendor.firm_name AS vendor_name,
                    mf_vendor.address AS vendor_address,
                    mf_buyer.firm_name AS buyer_name,
                    mf_buyer.address AS buyer_address,
                    p.mode_of_payment,
                    p.invoice_no,
                    p.invoice_date,
                    p.buyers_order_no,
                    p.buyers_order_date,
                    p.dispatch_through,
                    p.delivery_note_date,
                    p.department,
                    p.voucher_type_category,
                    p.ledger,
                    lm.sName AS location,
                    p.lr_number,
                    p.lr_date,
                    p.cartoon_number,
                    p.sub_total,
                    p.total_discount,
                    p.tcs_percentage,
                    p.total,
                    p.round_off,
                    p.round_off_type,
                    p.grand_total,
                    p.remark_comment AS remark
                ")
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor')
                ->leftJoin('master_firm as mf_buyer', 'mf_buyer.firm_id', '=', 'p.buyer')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw("
                    d.particular, d.hsn, d.qty, d.unit, d.mrp,
                    d.discount_in_mrp, d.price, d.amount,
                    d.gst, d.sgst, d.igst, d.cess, d.total_amount
                ")
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#',               'key' => 'sr_no'],
            ['label' => 'Particular',      'key' => 'particular'],
            ['label' => 'HSN',             'key' => 'hsn'],
            ['label' => 'Qty',             'key' => 'qty'],
            ['label' => 'Unit',            'key' => 'unit'],
            ['label' => 'MRP',             'key' => 'mrp'],
            ['label' => 'Discount in MRP', 'key' => 'discount_in_mrp'],
            ['label' => 'Price',           'key' => 'price'],
            ['label' => 'Amount',          'key' => 'amount'],
            ['label' => 'GST %',           'key' => 'gst'],
            ['label' => 'SGST %',          'key' => 'sgst'],
            ['label' => 'IGST %',          'key' => 'igst'],
            ['label' => 'Cess %',          'key' => 'cess'],
            ['label' => 'Total Amount',    'key' => 'total_amount'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Credit Note No.',       'key' => 'credit_note_no',       'value' => $punch->credit_note_no ?? null],
                ['label' => 'Credit Note Date',      'key' => 'credit_note_date',     'value' => $punch->credit_note_date ?? null],
                ['label' => 'Vendor',                'key' => 'vendor_name',          'value' => $punch->vendor_name ?? null],
                ['label' => 'Vendor Address',        'key' => 'vendor_address',       'value' => $punch->vendor_address ?? null],
                ['label' => 'Buyer',                 'key' => 'buyer_name',           'value' => $punch->buyer_name ?? null],
                ['label' => 'Buyer Address',         'key' => 'buyer_address',        'value' => $punch->buyer_address ?? null],
                ['label' => 'Mode of Payment',       'key' => 'mode_of_payment',      'value' => $punch->mode_of_payment ?? null],
                ['label' => 'Invoice No.',           'key' => 'invoice_no',           'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date',          'key' => 'invoice_date',         'value' => $punch->invoice_date ?? null],
                ['label' => "Buyer's Order No.",     'key' => 'buyers_order_no',      'value' => $punch->buyers_order_no ?? null],
                ['label' => "Buyer's Order Date",    'key' => 'buyers_order_date',    'value' => $punch->buyers_order_date ?? null],
                ['label' => 'Dispatch Through',      'key' => 'dispatch_through',     'value' => $punch->dispatch_through ?? null],
                ['label' => 'Delivery Note Date',    'key' => 'delivery_note_date',   'value' => $punch->delivery_note_date ?? null],
                ['label' => 'Department',            'key' => 'department',           'value' => $punch->department ?? null],
                ['label' => 'Voucher Type/Category', 'key' => 'voucher_type_category','value' => $punch->voucher_type_category ?? null],
                ['label' => 'Ledger',                'key' => 'ledger',               'value' => $punch->ledger ?? null],
                ['label' => 'Location',              'key' => 'location',             'value' => $punch->location ?? null],
                ['label' => 'LR Number',             'key' => 'lr_number',            'value' => $punch->lr_number ?? null],
                ['label' => 'LR Date',               'key' => 'lr_date',              'value' => $punch->lr_date ?? null],
                ['label' => 'Cartoon Number',        'key' => 'cartoon_number',       'value' => $punch->cartoon_number ?? null],
                ['label' => 'Sub Total',             'key' => 'sub_total',            'value' => $punch->sub_total ?? null],
                ['label' => 'Total Discount',        'key' => 'total_discount',       'value' => $punch->total_discount ?? null],
                ['label' => 'TCS %',                 'key' => 'tcs_percentage',       'value' => $punch->tcs_percentage ?? null],
                ['label' => 'Total',                 'key' => 'total',                'value' => $punch->total ?? null],
                ['label' => 'Round Off',             'key' => 'round_off',            'value' => $punch->round_off ?? null],
                ['label' => 'Round Off Type',        'key' => 'round_off_type',       'value' => $punch->round_off_type ?? null],
                ['label' => 'Grand Total',           'key' => 'grand_total',          'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment',      'key' => 'remark',               'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items'        => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType51(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_51";
        $detailTable = "y{$yearId}_punchdata_51_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.mode,
                    p.agent_name,
                    p.pnr_number,
                    p.date_of_booking,
                    p.journey_date,
                    p.air_line,
                    p.ticket_number,
                    p.journey_from,
                    p.journey_upto,
                    p.travel_class,
                    lm.sName AS location,
                    p.passenger_details,
                    p.base_fare,
                    p.gst,
                    p.fees_surcharge,
                    p.cute_charge,
                    p.extra_luggage,
                    p.other,
                    p.total_fare,
                    p.remark_comment AS remark
                ')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->leftJoin('master_employee as me', 'me.id', '=', 'd.emp_name')
                ->selectRaw('
                            me.emp_code,
                            me.emp_name as master_emp_name
                        ')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Employee', 'key' => 'emp_name'],
            ['label' => 'Emp Code', 'key' => 'emp_code'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Mode', 'key' => 'mode', 'value' => $punch->mode ?? null],
                ['label' => 'Agent Name', 'key' => 'agent_name', 'value' => $punch->agent_name ?? null],
                ['label' => 'PNR Number', 'key' => 'pnr_number', 'value' => $punch->pnr_number ?? null],
                ['label' => 'Date of Booking', 'key' => 'date_of_booking', 'value' => $punch->date_of_booking ?? null],
                ['label' => 'Journey Date', 'key' => 'journey_date', 'value' => $punch->journey_date ?? null],
                ['label' => 'Air Line', 'key' => 'air_line', 'value' => $punch->air_line ?? null],
                ['label' => 'Ticket Number', 'key' => 'ticket_number', 'value' => $punch->ticket_number ?? null],
                ['label' => 'Journey From', 'key' => 'journey_from', 'value' => $punch->journey_from ?? null],
                ['label' => 'Journey Upto', 'key' => 'journey_upto', 'value' => $punch->journey_upto ?? null],
                ['label' => 'Travel Class', 'key' => 'travel_class', 'value' => $punch->travel_class ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Passenger Details', 'key' => 'passenger_details', 'value' => $punch->passenger_details ?? null],
                ['label' => 'Base Fare', 'key' => 'base_fare', 'value' => $punch->base_fare ?? null],
                ['label' => 'GST (in Rs.)', 'key' => 'gst', 'value' => $punch->gst ?? null],
                ['label' => 'Fees & Surcharge', 'key' => 'fees_surcharge', 'value' => $punch->fees_surcharge ?? null],
                ['label' => 'CUTE Charge', 'key' => 'cute_charge', 'value' => $punch->cute_charge ?? null],
                ['label' => 'Extra Luggage', 'key' => 'extra_luggage', 'value' => $punch->extra_luggage ?? null],
                ['label' => 'Other', 'key' => 'other', 'value' => $punch->other ?? null],
                ['label' => 'Total Fare', 'key' => 'total_fare', 'value' => $punch->total_fare ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType50(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_50";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    mf_company.firm_name AS company_name,
                    mf_company.address AS company_address,
                    mf_vendor.firm_name AS vendor_name,
                    mf_vendor.address AS vendor_address,
                    p.vehicle_no,
                    p.vehicle_type,
                    lm.sName AS location,
                    p.invoice_date,
                    p.particular,
                    p.hour,
                    p.trips,
                    p.rate_per_trip,
                    p.total_amount,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf_company', 'mf_company.firm_id', '=', 'p.company_name')
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor_name')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location_id')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Company Name', 'key' => 'company_name', 'value' => $punch->company_name ?? null],
                ['label' => 'Company Address', 'key' => 'company_address', 'value' => $punch->company_address ?? null],
                ['label' => 'Vendor Name', 'key' => 'vendor_name', 'value' => $punch->vendor_name ?? null],
                ['label' => 'Vendor Address', 'key' => 'vendor_address', 'value' => $punch->vendor_address ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Vehicle Type', 'key' => 'vehicle_type', 'value' => $punch->vehicle_type ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Invoice Date', 'key' => 'invoice_date', 'value' => $punch->invoice_date ?? null],
                ['label' => 'Particular', 'key' => 'particular', 'value' => $punch->particular ?? null],
                ['label' => 'Hour', 'key' => 'hour', 'value' => $punch->hour ?? null],
                ['label' => 'Trips', 'key' => 'trips', 'value' => $punch->trips ?? null],
                ['label' => 'Rate per Trip', 'key' => 'rate_per_trip', 'value' => $punch->rate_per_trip ?? null],
                ['label' => 'Total Amount', 'key' => 'total_amount', 'value' => $punch->total_amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType48(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_48";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    mf.firm_name AS company_name,
                    p.voucher_no,
                    p.date,
                    lm.sName AS location,
                    p.receiver_name,
                    p.received_from,
                    p.particular,
                    p.amount,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf', 'mf.firm_id', '=', 'p.company_name')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Company Name', 'key' => 'company_name', 'value' => $punch->company_name ?? null],
                ['label' => 'Voucher No.', 'key' => 'voucher_no', 'value' => $punch->voucher_no ?? null],
                ['label' => 'Date', 'key' => 'date', 'value' => $punch->date ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Receiver Name', 'key' => 'receiver_name', 'value' => $punch->receiver_name ?? null],
                ['label' => 'Received From', 'key' => 'received_from', 'value' => $punch->received_from ?? null],
                ['label' => 'Particular', 'key' => 'particular', 'value' => $punch->particular ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType47(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_47";
        $detailTable = "y{$yearId}_punchdata_47_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.voucher_no,
                    p.payment_date,
                    p.payee,
                    lm.sName AS location,
                    p.particular,
                    p.total_amount,
                    p.from_date,
                    p.to_date,
                    p.sub_total,
                    p.remark_comment AS remark
                ')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw('d.head, d.amount')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Head', 'key' => 'head'],
            ['label' => 'Amount', 'key' => 'amount'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Voucher No.', 'key' => 'voucher_no', 'value' => $punch->voucher_no ?? null],
                ['label' => 'Payment Date', 'key' => 'payment_date', 'value' => $punch->payment_date ?? null],
                ['label' => 'Payee', 'key' => 'payee', 'value' => $punch->payee ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Particular', 'key' => 'particular', 'value' => $punch->particular ?? null],
                ['label' => 'Total Amount', 'key' => 'total_amount', 'value' => $punch->total_amount ?? null],
                ['label' => 'From Date', 'key' => 'from_date', 'value' => $punch->from_date ?? null],
                ['label' => 'To Date', 'key' => 'to_date', 'value' => $punch->to_date ?? null],
                ['label' => 'Sub Total', 'key' => 'sub_total', 'value' => $punch->sub_total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType46(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_46";
        $detailTable = "y{$yearId}_punchdata_46_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.cpin, p.deposit_date, p.cin, p.bank_name, p.brn,
                    p.gstin, p.email_id, p.mobile_no, p.company_name, p.address,
                    p.cgst_tax, p.cgst_interest, p.cgst_penalty, p.cgst_fees, p.cgst_other, p.cgst_total,
                    p.sgst_tax, p.sgst_interest, p.sgst_penalty, p.sgst_fees, p.sgst_other, p.sgst_total,
                    p.igst_tax, p.igst_interest, p.igst_penalty, p.igst_fees, p.igst_other, p.igst_total,
                    p.cess_tax, p.cess_interest, p.cess_penalty, p.cess_fees, p.cess_other, p.cess_total,
                    p.total_challan_amount, p.remark_comment AS remark
                ')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw('d.particular, d.tax, d.interest, d.penalty, d.fees, d.other, d.total')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Particular', 'key' => 'particular'],
            ['label' => 'Tax (₹)', 'key' => 'tax'],
            ['label' => 'Interest (₹)', 'key' => 'interest'],
            ['label' => 'Penalty (₹)', 'key' => 'penalty'],
            ['label' => 'Fees (₹)', 'key' => 'fees'],
            ['label' => 'Other (₹)', 'key' => 'other'],
            ['label' => 'Total (₹)', 'key' => 'total'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'CPIN', 'key' => 'cpin', 'value' => $punch->cpin ?? null],
                ['label' => 'Deposit Date', 'key' => 'deposit_date', 'value' => $punch->deposit_date ?? null],
                ['label' => 'CIN', 'key' => 'cin', 'value' => $punch->cin ?? null],
                ['label' => 'Bank Name', 'key' => 'bank_name', 'value' => $punch->bank_name ?? null],
                ['label' => 'BRN', 'key' => 'brn', 'value' => $punch->brn ?? null],
                ['label' => 'GSTIN', 'key' => 'gstin', 'value' => $punch->gstin ?? null],
                ['label' => 'Email ID', 'key' => 'email_id', 'value' => $punch->email_id ?? null],
                ['label' => 'Mobile No.', 'key' => 'mobile_no', 'value' => $punch->mobile_no ?? null],
                ['label' => 'Company Name', 'key' => 'company_name', 'value' => $punch->company_name ?? null],
                ['label' => 'Address', 'key' => 'address', 'value' => $punch->address ?? null],
                ['label' => 'CGST Tax', 'key' => 'cgst_tax', 'value' => $punch->cgst_tax ?? null],
                ['label' => 'CGST Interest', 'key' => 'cgst_interest', 'value' => $punch->cgst_interest ?? null],
                ['label' => 'CGST Penalty', 'key' => 'cgst_penalty', 'value' => $punch->cgst_penalty ?? null],
                ['label' => 'CGST Fees', 'key' => 'cgst_fees', 'value' => $punch->cgst_fees ?? null],
                ['label' => 'CGST Other', 'key' => 'cgst_other', 'value' => $punch->cgst_other ?? null],
                ['label' => 'CGST Total', 'key' => 'cgst_total', 'value' => $punch->cgst_total ?? null],
                ['label' => 'SGST Tax', 'key' => 'sgst_tax', 'value' => $punch->sgst_tax ?? null],
                ['label' => 'SGST Interest', 'key' => 'sgst_interest', 'value' => $punch->sgst_interest ?? null],
                ['label' => 'SGST Penalty', 'key' => 'sgst_penalty', 'value' => $punch->sgst_penalty ?? null],
                ['label' => 'SGST Fees', 'key' => 'sgst_fees', 'value' => $punch->sgst_fees ?? null],
                ['label' => 'SGST Other', 'key' => 'sgst_other', 'value' => $punch->sgst_other ?? null],
                ['label' => 'SGST Total', 'key' => 'sgst_total', 'value' => $punch->sgst_total ?? null],
                ['label' => 'IGST Tax', 'key' => 'igst_tax', 'value' => $punch->igst_tax ?? null],
                ['label' => 'IGST Interest', 'key' => 'igst_interest', 'value' => $punch->igst_interest ?? null],
                ['label' => 'IGST Penalty', 'key' => 'igst_penalty', 'value' => $punch->igst_penalty ?? null],
                ['label' => 'IGST Fees', 'key' => 'igst_fees', 'value' => $punch->igst_fees ?? null],
                ['label' => 'IGST Other', 'key' => 'igst_other', 'value' => $punch->igst_other ?? null],
                ['label' => 'IGST Total', 'key' => 'igst_total', 'value' => $punch->igst_total ?? null],
                ['label' => 'Cess Tax', 'key' => 'cess_tax', 'value' => $punch->cess_tax ?? null],
                ['label' => 'Cess Interest', 'key' => 'cess_interest', 'value' => $punch->cess_interest ?? null],
                ['label' => 'Cess Penalty', 'key' => 'cess_penalty', 'value' => $punch->cess_penalty ?? null],
                ['label' => 'Cess Fees', 'key' => 'cess_fees', 'value' => $punch->cess_fees ?? null],
                ['label' => 'Cess Other', 'key' => 'cess_other', 'value' => $punch->cess_other ?? null],
                ['label' => 'Cess Total', 'key' => 'cess_total', 'value' => $punch->cess_total ?? null],
                ['label' => 'Total Challan Amount', 'key' => 'total_challan_amount', 'value' => $punch->total_challan_amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType44(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_44";
        $detailTable = "y{$yearId}_punchdata_44_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    lm.sName AS location,
                    mf_vendor.firm_name AS vendor_name,
                    mf_billing.firm_name AS billing_to,
                    p.invoice_no,
                    p.invoice_date,
                    p.vehicle_no,
                    p.sub_total,
                    p.total,
                    p.total_discount,
                    p.round_off_value,
                    p.round_off_type,
                    p.grand_total,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor_name')
                ->leftJoin('master_firm as mf_billing', 'mf_billing.firm_id', '=', 'p.billing_to')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw('
                    d.particular,
                    d.hsn,
                    d.qty,
                    d.unit,
                    d.mrp,
                    d.discount_in_mrp,
                    d.price,
                    d.amount,
                    d.gst,
                    d.sgst,
                    d.igst,
                    d.cess,
                    d.total_amount
                ')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Particular', 'key' => 'particular'],
            ['label' => 'HSN', 'key' => 'hsn'],
            ['label' => 'Qty', 'key' => 'qty'],
            ['label' => 'Unit', 'key' => 'unit'],
            ['label' => 'MRP', 'key' => 'mrp'],
            ['label' => 'Discount in MRP', 'key' => 'discount_in_mrp'],
            ['label' => 'Price', 'key' => 'price'],
            ['label' => 'Amount', 'key' => 'amount'],
            ['label' => 'GST %', 'key' => 'gst'],
            ['label' => 'SGST %', 'key' => 'sgst'],
            ['label' => 'IGST %', 'key' => 'igst'],
            ['label' => 'Cess %', 'key' => 'cess'],
            ['label' => 'Total Amount', 'key' => 'total_amount'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Vendor Name', 'key' => 'vendor_name', 'value' => $punch->vendor_name ?? null],
                ['label' => 'Billing To', 'key' => 'billing_to', 'value' => $punch->billing_to ?? null],
                ['label' => 'Invoice No.', 'key' => 'invoice_no', 'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date', 'key' => 'invoice_date', 'value' => $punch->invoice_date ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Sub Total', 'key' => 'sub_total', 'value' => $punch->sub_total ?? null],
                ['label' => 'Total', 'key' => 'total', 'value' => $punch->total ?? null],
                ['label' => 'Total Discount', 'key' => 'total_discount', 'value' => $punch->total_discount ?? null],
                ['label' => 'Round Off', 'key' => 'round_off_value', 'value' => $punch->round_off_value ?? null],
                ['label' => 'Round Off Type', 'key' => 'round_off_type', 'value' => $punch->round_off_type ?? null],
                ['label' => 'Grand Total', 'key' => 'grand_total', 'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType43(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_43";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    mf_vendor.firm_name AS vendor_name,
                    mf_billing.firm_name AS billing_to,
                    p.dealer_code,
                    p.invoice_no,
                    p.invoice_date,
                    p.due_date,
                    lm.sName AS location,
                    p.vehicle_no,
                    p.description,
                    p.liters,
                    p.per_liter_rate,
                    p.amount,
                    p.round_off_value,
                    p.round_off_type,
                    p.grand_total,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor_name')
                ->leftJoin('master_firm as mf_billing', 'mf_billing.firm_id', '=', 'p.billing_to')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location_id')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Vendor Name', 'key' => 'vendor_name', 'value' => $punch->vendor_name ?? null],
                ['label' => 'Billing To', 'key' => 'billing_to', 'value' => $punch->billing_to ?? null],
                ['label' => 'Dealer Code', 'key' => 'dealer_code', 'value' => $punch->dealer_code ?? null],
                ['label' => 'Invoice No.', 'key' => 'invoice_no', 'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date', 'key' => 'invoice_date', 'value' => $punch->invoice_date ?? null],
                ['label' => 'Due Date', 'key' => 'due_date', 'value' => $punch->due_date ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Description', 'key' => 'description', 'value' => $punch->description ?? null],
                ['label' => 'Liters', 'key' => 'liters', 'value' => $punch->liters ?? null],
                ['label' => 'Per Liter Rate', 'key' => 'per_liter_rate', 'value' => $punch->per_liter_rate ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Round Off', 'key' => 'round_off_value', 'value' => $punch->round_off_value ?? null],
                ['label' => 'Round Off Type', 'key' => 'round_off_type', 'value' => $punch->round_off_type ?? null],
                ['label' => 'Grand Total', 'key' => 'grand_total', 'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType42(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_42";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.bill_invoice_date,
                    p.invoice_bill_no,
                    p.biller_name,
                    p.telephone_no,
                    p.invoice_period,
                    p.invoice_taxable_value,
                    p.cgst,
                    p.sgst,
                    p.igst,
                    p.total_amount_due,
                    p.total_amount_outstanding,
                    p.last_payment_date,
                    p.remark_comment AS remark
                ')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Bill / Invoice Date', 'key' => 'bill_invoice_date', 'value' => $punch->bill_invoice_date ?? null],
                ['label' => 'Invoice / Bill No.', 'key' => 'invoice_bill_no', 'value' => $punch->invoice_bill_no ?? null],
                ['label' => 'Biller Name', 'key' => 'biller_name', 'value' => $punch->biller_name ?? null],
                ['label' => 'Telephone No.', 'key' => 'telephone_no', 'value' => $punch->telephone_no ?? null],
                ['label' => 'Invoice Period', 'key' => 'invoice_period', 'value' => $punch->invoice_period ?? null],
                ['label' => 'Invoice Taxable Value', 'key' => 'invoice_taxable_value', 'value' => $punch->invoice_taxable_value ?? null],
                ['label' => 'CGST', 'key' => 'cgst', 'value' => $punch->cgst ?? null],
                ['label' => 'SGST', 'key' => 'sgst', 'value' => $punch->sgst ?? null],
                ['label' => 'IGST', 'key' => 'igst', 'value' => $punch->igst ?? null],
                ['label' => 'Total Amount Due', 'key' => 'total_amount_due', 'value' => $punch->total_amount_due ?? null],
                ['label' => 'Total Amount Outstanding', 'key' => 'total_amount_outstanding', 'value' => $punch->total_amount_outstanding ?? null],
                ['label' => 'Last Payment Date', 'key' => 'last_payment_date', 'value' => $punch->last_payment_date ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType31(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_31";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    mf_company.firm_name AS company_name,
                    p.voucher_no,
                    p.voucher_date,
                    lm.sName AS location,
                    mf_vendor.firm_name AS vendor_name,
                    p.amount,
                    p.particular,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf_company', 'mf_company.firm_id', '=', 'p.company')
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location_id')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Company', 'key' => 'company_name', 'value' => $punch->company_name ?? null],
                ['label' => 'Voucher No.', 'key' => 'voucher_no', 'value' => $punch->voucher_no ?? null],
                ['label' => 'Voucher Date', 'key' => 'voucher_date', 'value' => $punch->voucher_date ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Vendor', 'key' => 'vendor_name', 'value' => $punch->vendor_name ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Particular', 'key' => 'particular', 'value' => $punch->particular ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType29(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_29";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    mf.firm_name AS hotel_name,
                    p.bill_no,
                    p.bill_date,
                    p.hotel_address,
                    me.emp_name as employee_name,
                    me.emp_code,
                    p.amount,
                    lm.sName AS location,
                    p.detail,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf', 'mf.firm_id', '=', 'p.hotel_name')
                ->leftJoin('master_employee as me', 'me.id', '=', 'p.employee_name')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location_id')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Hotel Name', 'key' => 'hotel_name', 'value' => $punch->hotel_name ?? null],
                ['label' => 'Bill No.', 'key' => 'bill_no', 'value' => $punch->bill_no ?? null],
                ['label' => 'Bill Date', 'key' => 'bill_date', 'value' => $punch->bill_date ?? null],
                ['label' => 'Hotel Address', 'key' => 'hotel_address', 'value' => $punch->hotel_address ?? null],
                ['label' => 'Employee Name', 'key' => 'employee_name', 'value' => $punch->employee_name ?? null],
                ['label' => 'Emp Code', 'key' => 'emp_code', 'value' => $punch->emp_code ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Detail', 'key' => 'detail', 'value' => $punch->detail ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType28(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_28";
        $detailTable = "y{$yearId}_punchdata_28_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    lm.sName AS location,
                    p.bill_no,
                    p.bill_date,
                    mf_billing.firm_name AS billing_name,
                    mf_billing.address AS billing_address,
                    mf_hotel.firm_name AS hotel_name,
                    mf_hotel.address AS hotel_address,
                    p.billing_instruction,
                    p.booking_id,
                    p.check_in,
                    p.check_out,
                    p.duration_of_stay,
                    p.number_of_rooms,
                    p.room_type,
                    p.meal_plan,
                    p.rate,
                    p.amount,
                    p.other_charges,
                    p.discount,
                    p.gst,
                    p.grand_total,
                    p.remark_comment AS remark
                ')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->leftJoin('master_firm as mf_billing', 'mf_billing.firm_id', '=', 'p.billing_name')
                ->leftJoin('master_firm as mf_hotel', 'mf_hotel.firm_id', '=', 'p.hotel_name')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->leftJoin('master_employee as me', 'me.id', '=', 'd.emp_name')
                ->selectRaw('
                            me.emp_code,
                            me.emp_name as master_emp_name
                        ')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Employee', 'key' => 'master_emp_name'],
            ['label' => 'Emp Code', 'key' => 'emp_code'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Bill No.', 'key' => 'bill_no', 'value' => $punch->bill_no ?? null],
                ['label' => 'Bill Date', 'key' => 'bill_date', 'value' => $punch->bill_date ?? null],
                ['label' => 'Billing Name', 'key' => 'billing_name', 'value' => $punch->billing_name ?? null],
                ['label' => 'Billing Address', 'key' => 'billing_address', 'value' => $punch->billing_address ?? null],
                ['label' => 'Hotel Name', 'key' => 'hotel_name', 'value' => $punch->hotel_name ?? null],
                ['label' => 'Hotel Address', 'key' => 'hotel_address', 'value' => $punch->hotel_address ?? null],
                ['label' => 'Billing Instruction', 'key' => 'billing_instruction', 'value' => $punch->billing_instruction ?? null],
                ['label' => 'Booking ID', 'key' => 'booking_id', 'value' => $punch->booking_id ?? null],
                ['label' => 'Check In', 'key' => 'check_in', 'value' => $punch->check_in ?? null],
                ['label' => 'Check Out', 'key' => 'check_out', 'value' => $punch->check_out ?? null],
                ['label' => 'Duration of Stay', 'key' => 'duration_of_stay', 'value' => $punch->duration_of_stay ?? null],
                ['label' => 'Number of Rooms', 'key' => 'number_of_rooms', 'value' => $punch->number_of_rooms ?? null],
                ['label' => 'Room Type', 'key' => 'room_type', 'value' => $punch->room_type ?? null],
                ['label' => 'Meal Plan', 'key' => 'meal_plan', 'value' => $punch->meal_plan ?? null],
                ['label' => 'Rate', 'key' => 'rate', 'value' => $punch->rate ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Other Charges', 'key' => 'other_charges', 'value' => $punch->other_charges ?? null],
                ['label' => 'Discount', 'key' => 'discount', 'value' => $punch->discount ?? null],
                ['label' => 'GST (%)', 'key' => 'gst', 'value' => $punch->gst ?? null],
                ['label' => 'Grand Total', 'key' => 'grand_total', 'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType27(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_27";
        $detailTable = "y{$yearId}_punchdata_27_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.mode,
                    lm.sName AS location,
                    me.emp_name as employee_name,
                    me.emp_code as emp_code,
                    p.vehicle_no,
                    p.invoice_number AS voucher_no,
                    p.invoice_date AS voucher_date,
                    p.month,
                    p.calculation_base,
                    p.per_km_rate,
                    p.total_km,
                    p.total,
                    p.remark_comment AS remark
                ')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location_id')
                ->leftJoin('master_employee as me', 'me.id', '=', 'p.employee_name')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw('
                    d.travel_date,
                    d.opening_reading,
                    d.closing_reading,
                    d.total_km,
                    d.amount
                ')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => '#', 'key' => 'sr_no'],
            ['label' => 'Date', 'key' => 'travel_date'],
            ['label' => 'Opening Reading', 'key' => 'opening_reading'],
            ['label' => 'Closing Reading', 'key' => 'closing_reading'],
            ['label' => 'Total KM', 'key' => 'total_km'],
            ['label' => 'Amount', 'key' => 'amount'],
        ];

        return (object) [
            'fields' => [
                ['label' => 'Mode', 'key' => 'mode', 'value' => $punch->mode ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Employee Name', 'key' => 'employee_name', 'value' => $punch->employee_name ?? null],
                ['label' => 'Emp Code', 'key' => 'emp_code', 'value' => $punch->emp_code ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Voucher No.', 'key' => 'voucher_no', 'value' => $punch->voucher_no ?? null],
                ['label' => 'Voucher Date', 'key' => 'voucher_date', 'value' => $punch->voucher_date ?? null],
                ['label' => 'Month', 'key' => 'month', 'value' => $punch->month ?? null],
                ['label' => 'Calculation Base', 'key' => 'calculation_base', 'value' => $punch->calculation_base ?? null],
                ['label' => 'Per KM Rate', 'key' => 'per_km_rate', 'value' => $punch->per_km_rate ?? null],
                ['label' => 'Total KM', 'key' => 'total_km', 'value' => $punch->total_km ?? null],
                ['label' => 'Total', 'key' => 'total', 'value' => $punch->total ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items' => $rawDetails->values()->map(fn($row, $index) => array_merge(['sr_no' => $index + 1], (array) $row)),
        ];
    }

    private function getPunchDetailType22(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_22";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.insurance_type,
                    p.insurance_company,
                    p.policy_number,
                    p.policy_date,
                    p.from_date,
                    p.to_date,
                    p.vehicle_no,
                    lm.sName AS location,
                    p.premium_amount,
                    p.remark_comment AS remark
                ')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location_id')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;
        return (object) [
            'fields' => [
                ['label' => 'Insurance Type', 'key' => 'insurance_type', 'value' => $punch->insurance_type ?? null],
                ['label' => 'Insurance Company', 'key' => 'insurance_company', 'value' => $punch->insurance_company ?? null],
                ['label' => 'Policy Number', 'key' => 'policy_number', 'value' => $punch->policy_number ?? null],
                ['label' => 'Policy Date', 'key' => 'policy_date', 'value' => $punch->policy_date ?? null],
                ['label' => 'From Date', 'key' => 'from_date', 'value' => $punch->from_date ?? null],
                ['label' => 'To Date', 'key' => 'to_date', 'value' => $punch->to_date ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Premium Amount', 'key' => 'premium_amount', 'value' => $punch->premium_amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType20(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_20";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.section,
                    mf.firm_name AS company_name,
                    p.nature_of_payment,
                    fy.label as assessment_year,
                    p.bank_name,
                    p.bsr_code,
                    p.challan_no,
                    p.challan_date,
                    p.bank_reference_no,
                    p.amount,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf', 'mf.firm_id', '=', 'p.company')
                ->leftJoin('financial_years as fy', 'fy.id', '=', 'p.assessment_year')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Section', 'key' => 'section', 'value' => $punch->section ?? null],
                ['label' => 'Company', 'key' => 'company_name', 'value' => $punch->company_name ?? null],
                ['label' => 'Nature of Payment', 'key' => 'nature_of_payment', 'value' => $punch->nature_of_payment ?? null],
                ['label' => 'Assessment Year', 'key' => 'assessment_year', 'value' => $punch->assessment_year ?? null],
                ['label' => 'Bank Name', 'key' => 'bank_name', 'value' => $punch->bank_name ?? null],
                ['label' => 'BSR Code', 'key' => 'bsr_code', 'value' => $punch->bsr_code ?? null],
                ['label' => 'Challan No.', 'key' => 'challan_no', 'value' => $punch->challan_no ?? null],
                ['label' => 'Challan Date', 'key' => 'challan_date', 'value' => $punch->challan_date ?? null],
                ['label' => 'Bank Reference No.', 'key' => 'bank_reference_no', 'value' => $punch->bank_reference_no ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType17(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_17";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    mf_agency.firm_name AS agency_name,
                    mf_agency.address AS agency_address,
                    mf_billing.firm_name AS billing_name,
                    mf_billing.address AS billing_address,
                    me.emp_name as employee_name,
                    me.emp_code as emp_code,
                    p.vehicle_no,
                    lm.sName AS location,
                    p.invoice_no,
                    p.invoice_date,
                    p.per_km_rate,
                    p.booking_date,
                    p.end_date,
                    p.start_reading,
                    p.closing_reading,
                    p.total_km,
                    p.other_charges,
                    p.total_amount,
                    p.remark_comment AS remark
                ')
                ->leftJoin('master_firm as mf_agency', 'mf_agency.firm_id', '=', 'p.agency_name')
                ->leftJoin('master_firm as mf_billing', 'mf_billing.firm_id', '=', 'p.billing_name')
                ->leftJoin('master_employee as me', 'me.id', '=', 'p.employee_name')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;
        return (object) [
            'fields' => [
                ['label' => 'Agency Name', 'key' => 'agency_name', 'value' => $punch->agency_name ?? null],
                ['label' => 'Agency Address', 'key' => 'agency_address', 'value' => $punch->agency_address ?? null],
                ['label' => 'Billing Name', 'key' => 'billing_name', 'value' => $punch->billing_name ?? null],
                ['label' => 'Billing Address', 'key' => 'billing_address', 'value' => $punch->billing_address ?? null],
                ['label' => 'Employee Name', 'key' => 'employee_name', 'value' => $punch->employee_name ?? null],
                ['label' => 'Emp Code', 'key' => 'emp_code', 'value' => $punch->emp_code ?? null],
                ['label' => 'Vehicle No.', 'key' => 'vehicle_no', 'value' => $punch->vehicle_no ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Invoice No.', 'key' => 'invoice_no', 'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date', 'key' => 'invoice_date', 'value' => $punch->invoice_date ?? null],
                ['label' => 'Per KM Rate', 'key' => 'per_km_rate', 'value' => $punch->per_km_rate ?? null],
                ['label' => 'Booking Date', 'key' => 'booking_date', 'value' => $punch->booking_date ?? null],
                ['label' => 'End Date', 'key' => 'end_date', 'value' => $punch->end_date ?? null],
                ['label' => 'Start Reading', 'key' => 'start_reading', 'value' => $punch->start_reading ?? null],
                ['label' => 'Closing Reading', 'key' => 'closing_reading', 'value' => $punch->closing_reading ?? null],
                ['label' => 'Total KM', 'key' => 'total_km', 'value' => $punch->total_km ?? null],
                ['label' => 'Other Charges', 'key' => 'other_charges', 'value' => $punch->other_charges ?? null],
                ['label' => 'Total Amount', 'key' => 'total_amount', 'value' => $punch->total_amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType13(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_13";
        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->selectRaw('
                    lm.sName as location,
                    p.payment_date,
                    p.biller_name,
                    p.business_partner_no,
                    p.bill_period,
                    p.meter_number,
                    p.bill_date,
                    p.bill_no,
                    p.previous_meter_reading,
                    p.current_meter_reading,
                    p.unit_consumed,
                    p.last_date_of_payment,
                    p.payment_mode,
                    p.bill_amount,
                    p.payment_amount,
                    p.remark_comment AS remark
                ')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Payment Date', 'key' => 'payment_date', 'value' => $punch->payment_date ?? null],
                ['label' => 'Biller Name', 'key' => 'biller_name', 'value' => $punch->biller_name ?? null],
                ['label' => 'Business Partner No (BP)', 'key' => 'business_partner_no', 'value' => $punch->business_partner_no ?? null],
                ['label' => 'Bill Period', 'key' => 'bill_period', 'value' => $punch->bill_period ?? null],
                ['label' => 'Meter Number', 'key' => 'meter_number', 'value' => $punch->meter_number ?? null],
                ['label' => 'Bill Date', 'key' => 'bill_date', 'value' => $punch->bill_date ?? null],
                ['label' => 'Bill No.', 'key' => 'bill_no', 'value' => $punch->bill_no ?? null],
                ['label' => 'Previous Meter Reading', 'key' => 'previous_meter_reading', 'value' => $punch->previous_meter_reading ?? null],
                ['label' => 'Current Meter Reading', 'key' => 'current_meter_reading', 'value' => $punch->current_meter_reading ?? null],
                ['label' => 'Unit Consumed', 'key' => 'unit_consumed', 'value' => $punch->unit_consumed ?? null],
                ['label' => 'Last Date of Payment', 'key' => 'last_date_of_payment', 'value' => $punch->last_date_of_payment ?? null],
                ['label' => 'Payment Mode', 'key' => 'payment_mode', 'value' => $punch->payment_mode ?? null],
                ['label' => 'Bill Amount', 'key' => 'bill_amount', 'value' => $punch->bill_amount ?? null],
                ['label' => 'Payment Amount', 'key' => 'payment_amount', 'value' => $punch->payment_amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType7(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_7";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->leftJoin('master_firm as mf', 'mf.firm_id', '=', 'p.company_name')
                ->leftJoin('location_master as lm', 'lm.LocationId', '=', 'p.location')
                ->selectRaw('
                    mf.firm_name as company_name,
                    mf.firm_code,
                    p.voucher_no,
                    p.voucher_date,
                    lm.sName as location,
                    p.payee,
                    p.payer,
                    p.amount,
                    p.particular,
                    p.remark_comment AS remark
                ')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Company Name', 'key' => 'company_name', 'value' => $punch->company_name ?? null],
                ['label' => 'Voucher No.', 'key' => 'voucher_no', 'value' => $punch->voucher_no ?? null],
                ['label' => 'Voucher Date', 'key' => 'voucher_date', 'value' => $punch->voucher_date ?? null],
                ['label' => 'Location', 'key' => 'location', 'value' => $punch->location ?? null],
                ['label' => 'Payee', 'key' => 'payee', 'value' => $punch->payee ?? null],
                ['label' => 'Payer', 'key' => 'payer', 'value' => $punch->payer ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Particular', 'key' => 'particular', 'value' => $punch->particular ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getPunchDetailType6(int $scanId, int $yearId): object
    {
        $punchTable = "y{$yearId}_punchdata_6";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw('
                    p.type,
                    p.date,
                    p.invoice_number AS voucher_number,
                    p.bank_name,
                    p.branch,
                    p.account_no,
                    p.beneficiary_name,
                    p.amount,
                    p.remark_comment AS remark
                ')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        return (object) [
            'fields' => [
                ['label' => 'Type', 'key' => 'type', 'value' => $punch->type ?? null],
                ['label' => 'Date', 'key' => 'date', 'value' => $punch->date ?? null],
                ['label' => 'Voucher Number', 'key' => 'voucher_number', 'value' => $punch->voucher_number ?? null],
                ['label' => 'Bank Name', 'key' => 'bank_name', 'value' => $punch->bank_name ?? null],
                ['label' => 'Branch', 'key' => 'branch', 'value' => $punch->branch ?? null],
                ['label' => 'Account No.', 'key' => 'account_no', 'value' => $punch->account_no ?? null],
                ['label' => 'Beneficiary Name', 'key' => 'beneficiary_name', 'value' => $punch->beneficiary_name ?? null],
                ['label' => 'Amount', 'key' => 'amount', 'value' => $punch->amount ?? null],
                ['label' => 'Remark / Comment', 'key' => 'remark', 'value' => $punch->remark ?? null],
            ],
            'item_columns' => [],
            'items' => [],
        ];
    }

    private function getAdditionalDetails(int $scanId, int $yearId, int $docTypeId, int $departmentId): array
    {
        $scanTable = "y{$yearId}_scan_file";
        $aidTable = "y{$yearId}_tbl_additional_information_details";

        // Resolve tag control — specific first, fallback to default (doc_type_id = 0)
        $tagControl = DB::table('tbl_tag_control')
            ->where('document_type_id', $docTypeId)
            ->where('department_id', $departmentId)
            ->first();

        $fieldsToCheck = [
            'ledger',
            'subledger',
            'function',
            'vertical',
            'department',
            'sub_department',
            'business_unit',
            'sales_zone',
            'sales_region',
            'territory',
            'location',
            'production_zone',
            'activity',
            'state',
            'crop',
            'season',
            'acrage',
            'pmt_category',
            'reference',
            'remarks',
        ];

        $hasSpecific = false;
        if ($tagControl) {
            foreach ($fieldsToCheck as $field) {
                if (($tagControl->{$field} ?? '') === 'Y') {
                    $hasSpecific = true;
                    break;
                }
            }
        }

        if (!$hasSpecific) {
            $tagControl = DB::table('tbl_tag_control')->where('document_type_id', 0)->first();
        }

        $fields = [];
        foreach ($fieldsToCheck as $field) {
            $fields[$field] = ($tagControl->{$field} ?? '') === 'Y';
        }

        // Fetch rows
        $rows = DB::table("{$scanTable} as sf")
            ->selectRaw('
                aid.*,
                mad.account_name AS ledger_name,
                mcc.name AS subledger_name,
                cof.function_name,
                cv.vertical_name,
                cd.department_name,
                csd.sub_department_name,
                ca.activity_name,
                cc.crop_name,
                cbu.business_unit_name,
                cr.region_name AS sales_region_name,
                mwl.sName AS location_name,
                scz.zone_name AS sales_zone_name,
                pcz.zone_name AS production_zone_name,
                ct.territory_name,
                tpc.category_name AS pmt_category_name,
                ctt.state_name
            ')
            ->leftJoin("{$aidTable} as aid", 'aid.scan_id', '=', 'sf.scan_id')
            ->leftJoin('core_department as cd', 'cd.api_id', '=', 'sf.department_id')
            ->leftJoin('core_sub_department as csd', 'csd.api_id', '=', 'sf.sub_department_id')
            ->leftJoin('master_account_ledger as mad', DB::raw('CONVERT(mad.focus_code USING utf8mb4)'), '=', DB::raw('CONVERT(aid.ledger_id USING utf8mb4)'))
            ->leftJoin('tbl_subledger as mcc', DB::raw('CONVERT(mcc.focus_code USING utf8mb4)'), '=', DB::raw('CONVERT(aid.subledger_id USING utf8mb4)'))
            ->leftJoin('core_org_function as cof', 'cof.api_id', '=', 'aid.function_id')
            ->leftJoin('core_vertical as cv', 'cv.api_id', '=', 'aid.vertical_id')
            ->leftJoin('core_activity as ca', 'ca.api_id', '=', 'aid.activity_id')
            ->leftJoin('core_crop as cc', 'cc.api_id', '=', 'aid.crop_id')
            ->leftJoin('core_business_unit as cbu', 'cbu.api_id', '=', 'aid.business_unit_id')
            ->leftJoin('core_region as cr', 'cr.api_id', '=', 'aid.sales_region_id')
            ->leftJoin('core_zone as scz', 'scz.api_id', '=', 'aid.sales_zone_id')
            ->leftJoin('core_zone as pcz', 'pcz.api_id', '=', 'aid.production_zone_id')
            ->leftJoin('core_territory as ct', 'ct.api_id', '=', 'aid.territory_id')
            ->leftJoin('location_master as mwl', 'mwl.LocationId', '=', 'aid.location_id')
            ->leftJoin('tbl_pmt_category as tpc', 'tpc.id', '=', 'aid.pmt_category_id')
            ->leftJoin('core_state as ctt', 'ctt.api_id', '=', 'aid.state_id')
            ->where('sf.scan_id', $scanId)
            ->get();

        // Build visible columns
        $columnMap = [
            'ledger' => ['label' => 'Ledger', 'key' => 'ledger_name'],
            'subledger' => ['label' => 'Subledger', 'key' => 'subledger_name'],
            'function' => ['label' => 'Function', 'key' => 'function_name'],
            'vertical' => ['label' => 'Vertical', 'key' => 'vertical_name'],
            'department' => ['label' => 'Department', 'key' => 'department_name'],
            'sub_department' => ['label' => 'Sub Dept', 'key' => 'sub_department_name'],
            'business_unit' => ['label' => 'Business Unit', 'key' => 'business_unit_name'],
            'sales_zone' => ['label' => 'Sales Zone', 'key' => 'sales_zone_name'],
            'sales_region' => ['label' => 'Sales Region', 'key' => 'sales_region_name'],
            'territory' => ['label' => 'Territory', 'key' => 'territory_name'],
            'location' => ['label' => 'Location', 'key' => 'location_name'],
            'production_zone' => ['label' => 'Pro. Zone', 'key' => 'production_zone_name'],
            'activity' => ['label' => 'Activity', 'key' => 'activity_name'],
            'state' => ['label' => 'State', 'key' => 'state_name'],
            'crop' => ['label' => 'Crop', 'key' => 'crop_name'],
            'season' => ['label' => 'Season', 'key' => 'season_name'],
            'acrage' => ['label' => 'Acrage', 'key' => 'acrage'],
            'pmt_category' => ['label' => 'Payment Category', 'key' => 'pmt_category_name'],
            'reference' => ['label' => 'Reference', 'key' => 'reference'],
            'remarks' => ['label' => 'Remarks', 'key' => 'remarks'],
        ];

        $columns = [['label' => 'No', 'key' => 'sr_no']];
        foreach ($columnMap as $field => $col) {
            if ($fields[$field] ?? false) {
                $columns[] = $col;
            }
        }
        $columns[] = ['label' => 'Amount', 'key' => 'amount'];

        $items = $rows->values()->map(function ($row, $index) use ($fields, $columnMap) {
            $item = ['sr_no' => $index + 1];
            foreach ($columnMap as $field => $col) {
                if ($fields[$field] ?? false) {
                    $item[$col['key']] = $row->{$col['key']} ?? null;
                }
            }
            $item['amount'] = $row->amount ?? null;
            return $item;
        });

        return [
            'columns' => $columns,
            'rows' => $items,
        ];
    }
}
