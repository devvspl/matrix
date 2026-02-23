<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportFile extends Model
{
    use HasFactory;

    protected $table = 'support_file';
    protected $primaryKey = 'support_id';
    public $timestamps = false;

    protected $fillable = [
        'scan_id',
        'supp_doc_type_id',
        'file_name',
        'file_extension',
        'file_path',
        'secondary_file_path',
        'file_name_old',
        'file_extension_old',
        'file_path_old',
        'secondary_file_path_old',
        'is_main_file',
        'uploaded_date',
        'is_deleted',
    ];

    protected $casts = [
        'uploaded_date' => 'datetime',
    ];

    // Constants
    const STATUS_YES = 'Y';
    const STATUS_NO = 'N';

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', self::STATUS_NO);
    }

    public function scopeByScanId($query, $scanId)
    {
        return $query->where('scan_id', $scanId);
    }

    // Relationships
    public function documentType()
    {
        return $this->belongsTo(SupportDocumentType::class, 'supp_doc_type_id', 'DocTypeId');
    }
}
