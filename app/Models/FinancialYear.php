<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    use HasFactory;

    protected $table = 'financial_years';
    public $timestamps = false;

    protected $fillable = [
        'start_date',
        'end_date',
        'label',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', 1);
    }

    public function scopeActive($query)
    {
        return $query->where('end_date', '>=', now());
    }

    // Helper method to get current financial year
    public static function getCurrentYear()
    {
        return static::current()->first();
    }

    // Relationship with ScanFile
    public function scanFiles()
    {
        return ScanFile::forYear($this->id);
    }
}
