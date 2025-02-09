<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoInmueble extends Model
{
    use HasFactory;

    protected $table = 'tipo_inmuebles';
    protected $fillable = [
        'nombre_tipo_inmueble',
        'numero_identificacion',
        'estado',
    ];
    protected $casts = [
        'estado' => 'string',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    public function usuario()
    {
        return $this->belongsTo(User::class, 'numero_identificacion', 'numero_identificacion');
    }
    public function inmuebles(){
        return $this->hasOne(Inmueble::class,"tipo_inmueble","id");
    }
    // public function toArray()
    // {
    //     return [
    //         'id' => $this->id,
    //         'nombre_tipo_inmueble' => $this->nombre_tipo_inmueble,
    //         'numero_identificacion' => $this->numero_identificacion,
    //         'estado' => $this->estado,
    //         'usuario' => $this->usuario ? $this->usuario->toArray() : null,
    //     ];

        
    // }
}
