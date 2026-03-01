# Guía de Implementación de Múltiples Roles

## Resumen

Se ha implementado un sistema de múltiples roles para usuarios. Cada usuario puede tener:
- **1 rol principal** (almacenado en `usu.rol`) - para compatibilidad hacia atrás
- **Múltiples roles adicionales** (almacenados en `user_roles`)

## Estructura de Base de Datos

### Tabla `user_roles`
```sql
- id (PK)
- usu_codigo (FK -> usu.usu_codigo)
- rol (string)
- created_at
- updated_at
- UNIQUE(usu_codigo, rol)
```

## Uso en el Código

### En Modelos/Controladores

```php
// Verificar si tiene un rol específico
if ($user->hasRole('coordinador_lab')) {
    // ...
}

// Verificar si tiene alguno de varios roles
if ($user->hasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'admin'])) {
    // ...
}

// Obtener todos los roles
$roles = $user->all_roles; // Array de todos los roles
$roles = $user->getRoles(); // Collection de roles

// Sincronizar roles adicionales
$user->syncRoles(['laboratorio', 'informes', 'muestreador']);
```

### En Vistas (Blade)

**ANTES (solo rol principal):**
```blade
@if(Auth::user()->rol == 'coordinador_lab')
    <!-- contenido -->
@endif
```

**DESPUÉS (múltiples roles):**
```blade
{{-- Opción 1: Usando helper global --}}
@if(userHasRole('coordinador_lab'))
    <!-- contenido -->
@endif

{{-- Opción 2: Usando método del modelo directamente --}}
@if(Auth::user()->hasRole('coordinador_lab'))
    <!-- contenido -->
@endif

{{-- Opción 3: Verificar múltiples roles --}}
@if(userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'admin']))
    <!-- contenido -->
@endif

{{-- Opción 4: Compatibilidad hacia atrás (solo rol principal) --}}
@if(Auth::user()->rol == 'coordinador_lab')
    <!-- contenido -->
@endif
```

## Importación desde Excel

El comando `usuarios:importar` ahora lee el archivo `usuarios_roles.xlsx` con la siguiente estructura:

| Código Usuario | DNI | Rol del Sistema | Rol adicional 1 | Rol adicional 2 | Rol adicional 3 |
|----------------|-----|-----------------|-----------------|------------------|------------------|
| aclausi | 27387059 | coordinador_lab | laboratorio | informes | |
| ahofmann | 22913526 | coordinador_lab | coordinador_muestreo | informes | |

### Ejecutar importación:
```bash
php artisan usuarios:importar
# O con archivo específico:
php artisan usuarios:importar ruta/al/archivo.xlsx
```

## Migración

Ejecutar la migración para crear la tabla:
```bash
php artisan migrate
```

## Helpers Globales Disponibles

- `userHasRole($role)` - Verifica si el usuario autenticado tiene un rol específico
- `userHasAnyRole(array $roles)` - Verifica si el usuario tiene alguno de los roles especificados

## Notas Importantes

1. **Compatibilidad hacia atrás**: El campo `rol` en la tabla `usu` se mantiene como rol principal. El código existente seguirá funcionando.

2. **Rol principal vs Roles adicionales**: 
   - El rol principal (`usu.rol`) se usa para compatibilidad y como rol "por defecto"
   - Los roles adicionales se almacenan en `user_roles`
   - El método `hasRole()` verifica ambos

3. **Normalización de roles**: Los roles se normalizan automáticamente (minúsculas, sin espacios, guiones bajos en lugar de guiones)

4. **Sincronización**: El método `syncRoles()` elimina roles que no están en el array y agrega los nuevos, manteniendo los que ya existen.
