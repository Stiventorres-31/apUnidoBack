<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignacione extends Model
{
    
    use HasFactory;

    protected $table = 'asignaciones';

    protected $primaryKey = ['numero_identificacion', 'codigo_inmueble',"referencia_material","consecutivo"];
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'numero_identificacion',
        'referencia_material',
        'codigo_inmueble',
        "consecutivo",
        'codigo_proyecto',
        'cantidad_material',
        'costo_material',
        'estado'
    ];

    protected $casts = [
        'cantidad_material' => 'float',
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
