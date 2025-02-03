<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inmueble extends Model
{
    protected $fillable = [
        "id",
        'nombre_inmueble',
        'estado',
        'codigo_proyecto',
        'numero_identificacion',
        'tipo_inmueble',
    ];

    protected $casts = [
        'estado' => 'string',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tipo_inmueble(){
        return $this->belongsTo(TipoInmueble::class,"tipo_inmueble","id");

    }
    public function proyecto(){
        return $this->belongsTo(Proyecto::class,"codigo_proyecto","codigo_proyecto");
    }
    public function usuario(){
        return $this->belongsTo(User::class,"numero_identificacion","numero_identificacion");
    }
    public function presupuestos(){
        return $this->hasMany(Presupuesto::class,"nombre_inmueble","nombre_inmueble");
    }
    public function asignaciones(){
        return $this->hasMany(Asignacione::class,"nombre_inmueble","nombre_inmueble");
    }
    public function toArray()
    {
        return [
            'id'=>$this->id,
          
            'nombre_inmueble' => $this->nombre_inmueble,
            'numero_identificacion' => $this->numero_identificacion,
            'codigo_proyecto' => $this->codigo_proyecto,
            'estado' => $this->estado,
            "tipo_inmueble"=>$this->tipo_inmueble,
            'usuario' => $this->usuario ? $this->usuario->toArray() : [],
            "presupuestos"=> $this->presupuestos ? $this->presupuestos->toArray() : [],
            "asignaciones"=>$this->asignaciones ? $this->asignaciones->toArray():[]
        ];
    }
}
