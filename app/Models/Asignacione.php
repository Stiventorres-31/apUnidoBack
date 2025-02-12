<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignacione extends Model
{

    use HasFactory;

    protected $table = 'asignaciones';

    protected $fillable = [
        'id',
        'inmueble_id',
        'codigo_proyecto',
        'referencia_material',
        "consecutivo",
        'numero_identificacion',
        'cantidad_material',
        'costo_material',
        'subtotal',
        'estado'
    ];

    protected $casts = [
        'cantidad_material' => 'decimal',
        'subtotal'=>'decimal',
        'costo_material' => 'decimal:2',
    ];
    
    
    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(User::class, 'numero_identificacion', 'numero_identificacion');
    }

    public function material()
    {
        return $this->belongsTo(Materiale::class, 'referencia_material', 'referencia_material');
    }

    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class, 'codigo_inmueble', 'codigo_inmueble');
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'codigo_proyecto', 'codigo_proyecto');
    }
}
