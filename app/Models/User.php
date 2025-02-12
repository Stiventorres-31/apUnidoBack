<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $table = "usuarios";
    protected $primaryKey = "numero_identificacion";
    protected $fillable = [
        'numero_identificacion',
        'nombre_completo',
        'password',
        'rol_usuario',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'updated_at',
        'created_at'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getJWTCustomClaims():array
    {
        return [
            'id' => $this->id,
            "numero_identificacion"=>$this->numero_identificacion,
            "nombre_completo"=>$this->nombre_completo,
            "rol_usuario"=>$this->rol_usuario,
            
        ];
    }
    public function getJWTIdentifier()
    {
        //identificacion real
        return $this->getKey();
    }

    public function inventario(){
        return $this->hasMany(Inventario::class,"numero_identificacion","numero_identificacion");
    }

    public function materiale(): HasOne
    {
        return $this->hasOne(Materiale::class, "referencia_material", "numero_identificacion");
    }
    public function proyecto(): HasOne
    {
        return $this->hasOne(Materiale::class, "codigo_proyecto", "numero_identificacion");
    }
    public function inmuebles(){
        return $this->hasOne(Inmueble::class);
    }
    public function presupuestos(){
        return $this->hasMany(Presupuesto::class,"numero_identificacion","numero_identificacion");
    }
    public function asignaciones(){
        return $this->hasMany(Asignacione::class,"numero_identificacion","numero_identificacion");
    }
}
