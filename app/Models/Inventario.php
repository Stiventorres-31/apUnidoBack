<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $primaryKey = ['referencia_material', 'consecutivo']; // No funciona en Eloquent directamente
    public $incrementing = false; // Laravel necesita saber que no es autoincrementable
    protected $keyType = 'string'; // Especifica que la clave primaria es string

    protected $fillable = [
        "referencia_material",
        "consecutivo",
        "numero_identificacion",
        "costo",
        "cantidad",
        "nit_proveedor",
        "nombre_proveedor",
        "descripcion_proveedor",
        "estado"
    ];


    protected $casts = [
        "estado" => "string"

    ];
    public function usuarios()
    {
        return $this->belongsTo(User::class, "numero_identificacion", "numero_identificacion");
    }

    public function material()
    {
        return $this->belongsTo(Materiale::class, "referencia_material", "referencia_material");
    }
}
