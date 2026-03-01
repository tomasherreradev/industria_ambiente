<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\CotioInstancia;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    protected $table = 'usu';
    protected $primaryKey = 'usu_codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = [
        'usu_clave',
    ];

    protected $fillable = [
        'usu_codigo', 'usu_descripcion', 'usu_clave', 'usu_nivel', 'usu_estado', 'sector_codigo', 'sector_trabajo', 'rol', 'dni', 'email', 'departamento', 'sector_trabajo'
    ];

    // public function getAuthPassword()
    // {
    //     return $this->usu_clave;
    // }

    public function tareas()
    {
        return $this->hasMany(Cotio::class, 'cotio_responsable_codigo', 'usu_codigo');
    }

    public function cotios()
    {
        return $this->belongsToMany(Cotio::class, 'cotio_user', 'usu_codigo', 'cotio_numcoti');
    }

    public function sector()
    {
        return $this->belongsTo(User::class, 'sector_codigo', 'usu_codigo');
    }

    public function miembros()
    {
        return $this->hasMany(User::class, 'sector_codigo', 'usu_codigo');
    }

    /**
     * Relación con instancias de análisis donde el usuario es responsable
     */
    public function instanciasAnalisis()
    {
        return $this->belongsToMany(
            CotioInstancia::class,
            'instancia_responsable_analisis',
            'usu_codigo',
            'cotio_instancia_id',
            'usu_codigo',
            'id'
        );
    }

    /**
     * Relación con instancias de muestreo donde el usuario es responsable
     */
    public function instanciasMuestreo()
    {
        return $this->belongsToMany(
            CotioInstancia::class,
            'instancia_responsable_muestreo',
            'usu_codigo',
            'cotio_instancia_id',
            'usu_codigo',
            'id'
        );
    }

    /**
     * Obtener roles adicionales directamente desde la tabla pivot
     */
    public function rolesAdicionales()
    {
        return DB::table('user_roles')
            ->where('usu_codigo', $this->usu_codigo)
            ->pluck('rol')
            ->toArray();
    }

    /**
     * Obtener todos los roles del usuario (rol principal + roles adicionales)
     */
    public function getAllRolesAttribute()
    {
        $roles = [];
        
        // Agregar rol principal si existe
        if (!empty($this->rol)) {
            $roles[] = $this->rol;
        }
        
        // Agregar roles adicionales desde la tabla pivot
        $rolesAdicionales = DB::table('user_roles')
            ->where('usu_codigo', $this->usu_codigo)
            ->pluck('rol')
            ->toArray();
        
        $roles = array_merge($roles, $rolesAdicionales);
        
        // Eliminar duplicados y retornar
        return array_unique($roles);
    }

    /**
     * Verificar si el usuario tiene un rol específico
     * 
     * @param string|array $role Rol o array de roles a verificar
     * @return bool
     */
    public function hasRole($role)
    {
        if (is_array($role)) {
            return $this->hasAnyRole($role);
        }
        
        // Verificar rol principal
        if ($this->rol === $role) {
            return true;
        }
        
        // Verificar roles adicionales
        return DB::table('user_roles')
            ->where('usu_codigo', $this->usu_codigo)
            ->where('rol', $role)
            ->exists();
    }

    /**
     * Verificar si el usuario tiene alguno de los roles especificados
     * 
     * @param array $roles Array de roles a verificar
     * @return bool
     */
    public function hasAnyRole(array $roles)
    {
        // Verificar rol principal
        if (in_array($this->rol, $roles)) {
            return true;
        }
        
        // Verificar roles adicionales
        return DB::table('user_roles')
            ->where('usu_codigo', $this->usu_codigo)
            ->whereIn('rol', $roles)
            ->exists();
    }

    /**
     * Obtener todos los roles como colección
     */
    public function getRoles()
    {
        return collect($this->all_roles);
    }

    /**
     * Sincronizar roles adicionales (elimina los que no están y agrega los nuevos)
     * 
     * @param array $roles Array de roles a sincronizar
     */
    public function syncRoles(array $roles)
    {
        // Filtrar roles vacíos y normalizar
        $roles = array_filter(array_map('trim', $roles));
        $roles = array_filter($roles, function($rol) {
            return !empty($rol);
        });
        
        // Obtener roles actuales
        $currentRoles = DB::table('user_roles')
            ->where('usu_codigo', $this->usu_codigo)
            ->pluck('rol')
            ->toArray();
        
        // Roles a eliminar (están en current pero no en roles)
        $toDelete = array_diff($currentRoles, $roles);
        
        // Roles a agregar (están en roles pero no en current)
        $toAdd = array_diff($roles, $currentRoles);
        
        // Eliminar roles
        if (!empty($toDelete)) {
            DB::table('user_roles')
                ->where('usu_codigo', $this->usu_codigo)
                ->whereIn('rol', $toDelete)
                ->delete();
        }
        
        // Agregar nuevos roles
        foreach ($toAdd as $rol) {
            DB::table('user_roles')->insert([
                'usu_codigo' => $this->usu_codigo,
                'rol' => $rol,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

}