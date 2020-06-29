<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadContact extends BaseModel
{
    protected $table = 'lead_contacts';
    protected $dates = ['contact_date', 'created_at'];
    public function lead(){
        return $this->belongsTo(Lead::class);
    }
}
