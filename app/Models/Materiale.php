<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materiale extends Model
{

    protected $fillable =[
        "referencia_material",
        "nombre_material",
        "numero_identificacion",
        "estado"
    ];
    protected $hidden = [
        'updated_at',
        'created_at'
    ];
    protected function casts(){
        return [
            "estado"=>"string"
        ];
    }
    
    public function usuarios():BelongsTo{
        return $this->belongsTo(User::class,"numero_identificacion","numero_identificacion");
    }
    public function inventario(){
        return $this->hasMany(Inventario::class,"referencia_material","referencia_material");
    }
}
