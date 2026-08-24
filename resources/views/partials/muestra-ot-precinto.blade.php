@php
    $inst = $instancia ?? null;
    $texto = '';

    if ($inst) {
        $ot = trim((string) ($inst->otn ?? ''));
        $otMostrar = $ot !== '' ? $ot : ($vacioOt ?? '—');
        $precinto = trim((string) ($inst->nro_precinto ?? ''));
        $prefijo = $prefijo ?? 'OT';
        $separador = !empty($conDosPuntos) ? ': ' : ' ';
        $nucleo = $prefijo . $separador . $otMostrar;

        if ($precinto !== '') {
            $nucleo .= ' · Precinto ' . $precinto;
        }

        $texto = ($envoltura ?? null) === 'parens' ? '(' . $nucleo . ')' : $nucleo;
    }
@endphp
{{ $texto }}
