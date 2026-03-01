# Estructura de Múltiples Roles - Explicación Visual

## Cómo Funciona la Tabla `user_roles`

La tabla `user_roles` es una **tabla pivot** que permite almacenar **múltiples roles por usuario**. Cada fila representa **un rol adicional** para un usuario.

### Ejemplo Visual

Para el usuario `aclausi` según el Excel:

**Tabla `usu` (rol principal):**
```
usu_codigo: aclausi
rol: coordinador_lab  ← Rol principal
```

**Tabla `user_roles` (roles adicionales):**
```
id | usu_codigo | rol           | created_at | updated_at
---|------------|---------------|------------|------------
1  | aclausi    | laboratorio   | 2026-01-28 | 2026-01-28  ← Fila 1: Rol adicional 1
2  | aclausi    | informes      | 2026-01-28 | 2026-01-28  ← Fila 2: Rol adicional 2
```

**Resultado:** El usuario `aclausi` tiene:
- ✅ 1 rol principal: `coordinador_lab`
- ✅ 2 roles adicionales: `laboratorio`, `informes`
- ✅ **Total: 3 roles**

### Otro Ejemplo: `ahofmann`

**Tabla `usu`:**
```
usu_codigo: ahofmann
rol: coordinador_lab
```

**Tabla `user_roles`:**
```
id | usu_codigo | rol                  | created_at | updated_at
---|------------|----------------------|------------|------------
3  | ahofmann   | coordinador_muestreo | 2026-01-28 | 2026-01-28  ← Rol adicional 1
4  | ahofmann   | informes             | 2026-01-28 | 2026-01-28  ← Rol adicional 2
```

**Resultado:** El usuario `ahofmann` tiene:
- ✅ 1 rol principal: `coordinador_lab`
- ✅ 2 roles adicionales: `coordinador_muestreo`, `informes`
- ✅ **Total: 3 roles**

## Estructura de la Migración

```php
Schema::create('user_roles', function (Blueprint $table) {
    $table->id();
    $table->string('usu_codigo');  // FK hacia usu.usu_codigo
    $table->string('rol');         // El nombre del rol
    $table->timestamps();
    
    // UNIQUE(usu_codigo, rol) → Evita duplicados
    // PERO permite múltiples filas con diferentes roles
    $table->unique(['usu_codigo', 'rol']);
});
```

**Importante:** 
- El constraint `UNIQUE(usu_codigo, rol)` evita que un usuario tenga el mismo rol dos veces
- **PERO** permite que un usuario tenga múltiples filas con **diferentes** roles
- Por ejemplo: `(aclausi, laboratorio)` y `(aclausi, informes)` son válidos ✅

## Cómo se Guardan los Roles

Cuando ejecutas `$user->syncRoles(['laboratorio', 'informes'])`:

1. **Elimina** roles que ya no están en el array
2. **Mantiene** roles que ya existen
3. **Inserta** nuevos roles (uno por fila)

```php
// Para cada rol en el array, se crea UNA FILA en user_roles
foreach ($toAdd as $rol) {
    DB::table('user_roles')->insert([
        'usu_codigo' => 'aclausi',
        'rol' => 'laboratorio',  // ← Fila 1
        ...
    ]);
    
    DB::table('user_roles')->insert([
        'usu_codigo' => 'aclausi',
        'rol' => 'informes',     // ← Fila 2
        ...
    ]);
}
```

## Verificación de Roles

El método `hasRole()` busca en **ambos lugares**:

```php
public function hasRole($role)
{
    // 1. Verifica rol principal
    if ($this->rol === $role) {
        return true;
    }
    
    // 2. Verifica roles adicionales (busca en user_roles)
    return DB::table('user_roles')
        ->where('usu_codigo', $this->usu_codigo)
        ->where('rol', $role)
        ->exists();
}
```

## Resumen

✅ **Un usuario puede tener:**
- 1 rol principal (en `usu.rol`)
- Múltiples roles adicionales (en `user_roles`, una fila por rol)

✅ **La tabla `user_roles` permite:**
- Múltiples filas por usuario
- Cada fila = un rol diferente
- Sin duplicados (gracias al UNIQUE constraint)

✅ **Ejemplo real:**
- Usuario `aclausi`: 1 rol principal + 2 roles adicionales = **3 roles totales**
