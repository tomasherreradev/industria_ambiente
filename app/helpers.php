<?php
use Illuminate\Support\Facades\Auth;
/**
 * Helper global para verificar si el usuario autenticado tiene un rol específico
 * 
 * @param string|array $role Rol o array de roles a verificar
 * @return bool
 */
if (!function_exists('userHasRole')) {
    function userHasRole($role)
    {
        if (!Auth::check()) {
            return false;
        }
        
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        
        return $user->hasRole($role);
    }
}

/**
 * Helper global para verificar si el usuario autenticado tiene alguno de los roles especificados
 * 
 * @param array $roles Array de roles a verificar
 * @return bool
 */
if (!function_exists('userHasAnyRole')) {
    function userHasAnyRole(array $roles)
    {
        if (!Auth::check()) {
            return false;
        }
        
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        
        return $user->hasAnyRole($roles);
    }
}
