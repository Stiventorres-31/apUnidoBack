<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proyecto extends Model
{
    protected $primaryKey = 'codigo_proyecto';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable =[

        "codigo_proyecto",
        "departamento_proyecto",
        "ciudad_municipio_proyecto",
        "direccion_proyecto",
        "numero_identificacion",
        "fecha_inicio_proyecto",
        "fecha_final_proyecto",
        "estado",
    ];

    protected $casts = [
        "fecha_inicio_proyecto" => "date:d/m/Y",
        "fecha_final_proyecto" => "date:d/m/Y",
        "estado" => "string"
    ];
    

    protected $hidden = [
        'updated_at',
        'created_at'
    ];
    public function inmuebles(){
        return $this->hasMany(Inmueble::class,"codigo_proyecto","codigo_proyecto");
    }
    public function usuarios():BelongsTo{
        return $this->belongsTo(User::class,"numero_identificacion","numero_identificacion");
    }
}
