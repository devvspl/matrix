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

        $scanId    = $request->input('scan_id');
        $yearId    = $request->input('year_id');
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
                'scan'         => $scan,
                'punch_detail' => $punchDetail,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch scan detail: ' . $e->getMessage(), 500);
        }
    }

    private function getPunchDetail(int $scanId, int $yearId, int $docTypeId): ?object
    {
        if ($docTypeId === 23) {
            return $this->getPunchDetailType23($scanId, $yearId);
        }

    }

    private function getPunchDetailType23(int $scanId, int $yearId): object
    {
        $punchTable  = "y{$yearId}_punchdata_23";
        $detailTable = "y{$yearId}_punchdata_23_details";

        $punch = Schema::hasTable($punchTable)
            ? DB::table("{$punchTable} as p")
                ->selectRaw("
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
                ")
                ->leftJoin('master_firm as mf_buyer', 'mf_buyer.firm_id', '=', 'p.buyer')
                ->leftJoin('master_firm as mf_vendor', 'mf_vendor.firm_id', '=', 'p.vendor')
                ->where('p.scan_id', $scanId)
                ->first()
            : null;

        $rawDetails = Schema::hasTable($detailTable)
            ? DB::table("{$detailTable} as d")
                ->selectRaw("
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
                ")
                ->leftJoin('master_unit as u', 'u.unit_id', '=', 'd.unit')
                ->where('d.scan_id', $scanId)
                ->get()
            : collect();

        $itemColumns = [
            ['label' => 'Particular', 'key' => 'particular'],
            ['label' => 'HSN',        'key' => 'hsn'],
            ['label' => 'Qty',        'key' => 'qty'],
            ['label' => 'Unit',       'key' => 'unit'],
            ['label' => 'MRP',        'key' => 'mrp'],
            ['label' => 'Dis. MRP',   'key' => 'dis_mrp'],
            ['label' => 'Amt',        'key' => 'amt'],
            ['label' => 'CGST %',     'key' => 'cgst'],
            ['label' => 'SGST %',     'key' => 'sgst'],
            ['label' => 'IGST %',     'key' => 'igst'],
            ['label' => 'Cess %',     'key' => 'cess'],
            ['label' => 'Total Amt',  'key' => 'total_amt'],
        ];

        return (object) [
            'fields'       => [
                ['label' => 'Invoice No.',          'key' => 'invoice_no',          'value' => $punch->invoice_no ?? null],
                ['label' => 'Invoice Date',         'key' => 'invoice_date',        'value' => $punch->invoice_date ?? null],
                ['label' => 'Purchase Order No.',   'key' => 'purchase_order_no',   'value' => $punch->purchase_order_no ?? null],
                ['label' => 'Purchase Order Date',  'key' => 'purchase_order_date', 'value' => $punch->purchase_order_date ?? null],
                ['label' => 'Buyer',                'key' => 'buyer_name',          'value' => $punch->buyer_name ?? null],
                ['label' => 'Buyer Address',        'key' => 'buyer_address',       'value' => $punch->buyer_address ?? null],
                ['label' => 'Vendor',               'key' => 'vendor_name',         'value' => $punch->vendor_name ?? null],
                ['label' => 'Vendor Address',       'key' => 'vendor_address',      'value' => $punch->vendor_address ?? null],
                ['label' => 'Dispatch Through',     'key' => 'dispatch_through',    'value' => $punch->dispatch_through ?? null],
                ['label' => 'Delivery Note Date',   'key' => 'delivery_note_date',  'value' => $punch->delivery_note_date ?? null],
                ['label' => 'LR Number',            'key' => 'lr_number',           'value' => $punch->lr_number ?? null],
                ['label' => 'LR Date',              'key' => 'lr_date',             'value' => $punch->lr_date ?? null],
                ['label' => 'Total',                'key' => 'total',               'value' => $punch->total ?? null],
                ['label' => 'Sub Total',            'key' => 'sub_total',           'value' => $punch->sub_total ?? null],
                ['label' => 'Round Off',            'key' => 'round_off',           'value' => $punch->round_off ?? null],
                ['label' => 'Additional Discount',  'key' => 'additional_discount', 'value' => $punch->additional_discount ?? null],
                ['label' => 'Grand Total',          'key' => 'grand_total',         'value' => $punch->grand_total ?? null],
                ['label' => 'Remark / Comment',     'key' => 'remark',              'value' => $punch->remark ?? null],
            ],
            'item_columns' => $itemColumns,
            'items'        => $rawDetails,
        ];
    }
}
