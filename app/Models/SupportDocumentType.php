<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportDocumentType extends Model
{
    use HasFactory;

    protected $table = 'supp_document_type_master';
    protected $primaryKey = 'DocTypeId';
    public $timestamps = false;

    protected $fillable = [
        'DocTypeName',
        'DocTypeCode',
        'Description',
        'IsActive',
        'CreatedBy',
        'CreatedOn',
        'UpdatedBy',
        'UpdatedOn',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
        'CreatedOn' => 'datetime',
        'UpdatedOn' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('IsActive', 1);
    }

    // Relationships
    public function supportFiles()
    {
        return $this->hasMany(SupportFile::class, 'supp_doc_type_id', 'DocTypeId');
    }
}
