<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanFile extends Model
{
    use HasFactory;

    protected $primaryKey = 'scan_id';
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Default table if not set
        if (!isset($this->table)) {
            $this->table = 'y1_scan_file';
        }
    }

    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    public static function forYear($yearId)
    {
        $instance = new static;
        $instance->setTable("y{$yearId}_scan_file");
        return $instance;
    }

    protected $fillable = [
        'group_id',
        'location_id',
        'department_id',
        'sub_department_id',
        'doc_type',
        'doc_type_id',
        'bill_number',
        'bill_date',
        'bill_amount',
        'is_temp_scan',
        'temp_scan_date',
        'temp_scan_date_datetime',
        'temp_scan_by',
        'is_scan_complete',
        'scanned_by',
        'is_document_verified',
        'verified_by',
        'verified_date',
        'verified_date_datetime',
        'document_received_date',
        'document_received_date_datetime',
        'is_temp_scan_rejected',
        'temp_scan_reject_remark',
        'temp_scan_rejected_by',
        'temp_scan_reject_date',
        'temp_scan_reject_date_datetime',
        'is_classified',
        'classified_by',
        'classified_date',
        'classified_date_datetime',
        'is_classifion_reject',
        'classifion_reject_date',
        'classifion_reject_date_datetime',
        'classifion_reject_by',
        'classifion_reject_remark',
        'document_name',
        'file_name',
        'file_extension',
        'file_path',
        'secondary_file_path',
        'is_main_file',
        'scan_date',
        'scan_date_datetime',
        'year',
        'is_final_submitted',
        'is_file_punched',
        'is_deleted',
        'deleted_date',
        'deleted_date_datetime',
        'deleted_by',
        'punched_by',
        'punched_date',
        'punched_date_datetime',
        'is_auto_approve',
        'auto_approve_reason_id',
        'having_multiple_dep',
        'l1_approved_by',
        'l1_approved_by_id',
        'l1_approved_date',
        'l1_approved_date_datetime',
        'l1_remark',
        'l1_approved_status',
        'l2_approved_by',
        'l2_approved_by_id',
        'l2_approved_date',
        'l2_approved_date_datetime',
        'l2_remark',
        'l2_approved_status',
        'l3_approved_by',
        'l3_approved_by_id',
        'l3_approved_date',
        'l3_approved_date_datetime',
        'l3_remark',
        'l3_approved_status',
        'is_file_approved',
        'approved_by',
        'approved_date',
        'approved_date_datetime',
        'is_admin_approved',
        'is_scan_resend',
        'scan_resend_remark',
        'scan_resend_by',
        'scan_resend_date',
        'scan_resend_date_datetime',
        'is_rejected',
        'reject_remark',
        'reject_date',
        'reject_date_datetime',
        'has_edit_permission',
        'index_field1',
        'index_field2',
        'index_field3',
        'is_entry_confirmed',
        'confirmed_date',
        'confirmed_date_datetime',
        'bill_approval_status',
        'bill_approver_id',
        'bill_approved_date',
        'bill_approved_date_datetime',
        'bill_approver_remark',
        'finance_punch_status',
        'finance_punched_by',
        'finance_punched_date',
        'finance_punched_date_datetime',
        'finance_punch_remark',
        'finance_punch_action_status',
        'finance_punch_action_by',
        'finance_punch_action_date',
        'finance_punch_action_date_datetime',
        'finance_punch_action_remark',
        'extract_status',
        'focus_export',
        'focus_export_date',
        'focus_export_datetime',
        'focus_export_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'bill_amount' => 'decimal:2',
        'temp_scan_date' => 'date',
        'temp_scan_date_datetime' => 'datetime',
        'verified_date' => 'date',
        'verified_date_datetime' => 'datetime',
        'document_received_date' => 'date',
        'document_received_date_datetime' => 'datetime',
        'temp_scan_reject_date' => 'date',
        'temp_scan_reject_date_datetime' => 'datetime',
        'classified_date' => 'date',
        'classified_date_datetime' => 'datetime',
        'classifion_reject_date' => 'date',
        'classifion_reject_date_datetime' => 'datetime',
        'scan_date' => 'datetime',
        'scan_date_datetime' => 'datetime',
        'deleted_date' => 'datetime',
        'deleted_date_datetime' => 'datetime',
        'punched_date' => 'date',
        'punched_date_datetime' => 'datetime',
        'l1_approved_date' => 'datetime',
        'l1_approved_date_datetime' => 'datetime',
        'l2_approved_date' => 'datetime',
        'l2_approved_date_datetime' => 'datetime',
        'l3_approved_date' => 'datetime',
        'l3_approved_date_datetime' => 'datetime',
        'approved_date' => 'datetime',
        'approved_date_datetime' => 'datetime',
        'scan_resend_date' => 'date',
        'scan_resend_date_datetime' => 'datetime',
        'reject_date' => 'date',
        'reject_date_datetime' => 'datetime',
        'confirmed_date' => 'datetime',
        'confirmed_date_datetime' => 'datetime',
        'bill_approved_date' => 'date',
        'bill_approved_date_datetime' => 'datetime',
        'finance_punched_date' => 'date',
        'finance_punched_date_datetime' => 'datetime',
        'finance_punch_action_date' => 'date',
        'finance_punch_action_date_datetime' => 'datetime',
        'focus_export_date' => 'date',
        'focus_export_datetime' => 'datetime',
    ];

    // Status constants
    const STATUS_YES = 'Y';
    const STATUS_NO = 'N';
    const STATUS_REJECTED = 'R';
    const STATUS_PENDING = 'P';
    const STATUS_COMPLETED = 'C';

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', self::STATUS_NO);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_document_verified', self::STATUS_YES);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_file_approved', self::STATUS_YES);
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }
}
