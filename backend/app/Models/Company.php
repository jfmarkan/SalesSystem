<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'parent_company_id',
    ];

    // Parent company (head company / Stammgesellschaft)
    public function parent()
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    // Child companies (subsidiaries / Tochtergesellschaften)
    public function children()
    {
        return $this->hasMany(Company::class, 'parent_company_id');
    }

    // Teams directly attached to this company (via company_id)
    public function teams()
    {
        return $this->hasMany(Team::class, 'company_id');
    }
}
