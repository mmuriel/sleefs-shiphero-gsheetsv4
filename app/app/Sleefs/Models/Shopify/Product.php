<?php

namespace Sleefs\Models\Shopify;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //
    protected $table = 'products';


    public function variants(){

    	return $this->hasMany('Sleefs\Models\Shopify\Variant','idproduct');

    }
}
