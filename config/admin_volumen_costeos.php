<?php

/**
 * Presets para volumetría / análisis de costo (solo referencia; ajustar patrones según catálogo real).
 * Cada preset cuenta filas de análisis (cotio_instancias, subítem > 0, OT activa) que cumplan
 * al menos una de las condiciones LIKE indicadas (muestra padre, línea de análisis o matriz de la cotización).
 */
return [
    'presets' => [
        'efluente_liquido' => [
            'label' => 'Efluentes líquidos (por texto de muestra / análisis)',
            'help' => 'Coincidencias en descripción de la muestra (ítem padre) o del análisis.',
            'muestra_descripcion_like' => [
                '%EFLUENTE%',
                '%Efluente%',
                '%efluente%',
            ],
            'analisis_descripcion_like' => [
                '%EFLUENTE%',
                '%efluente%',
            ],
        ],
        'cromatografia' => [
            'label' => 'Cromatografía (texto o matriz)',
            'help' => 'Incluye descripciones con “cromato” y matrices de cotización relacionadas.',
            'muestra_descripcion_like' => ['%CROMATO%', '%cromato%', '%Cromatografía%', '%cromatografia%'],
            'analisis_descripcion_like' => ['%CROMATO%', '%cromato%', '%cromatografía%', '%cromatografia%'],
            'matriz_descripcion_like' => ['%CROMATO%', '%cromato%', '%CROMATOGRAF%', '%cromatograf%'],
        ],
        'vibraciones' => [
            'label' => 'Vibraciones (higiene / mediciones)',
            'help' => 'Patrones típicos en higiene; ampliar en este archivo si usan otra nomenclatura.',
            'muestra_descripcion_like' => [
                '%VIBRAC%',
                '%vibrac%',
                '%ISO 2631%',
                '%2631%',
            ],
            'analisis_descripcion_like' => [
                '%VIBRAC%',
                '%vibrac%',
                '%ISO 2631%',
            ],
        ],
    ],
];
