<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
	
    protected $guarded = [];
    static function get($field)
    {
        $data = Setting::where('id',1)->get();
        return $data[0][$field];
    }
}
