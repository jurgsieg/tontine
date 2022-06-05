<?php

namespace Siak\Tontine\Model;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'paid_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'paid_at',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
