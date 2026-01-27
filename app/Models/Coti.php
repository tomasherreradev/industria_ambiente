<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Matriz;
use App\Models\User;
use App\Models\Cotio;
use App\Models\Clientes;

class Coti extends Model
{
    protected $table = 'coti';
    protected $primaryKey = 'coti_num';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'coti_num',
        'coti_para',
        'coti_cli_empresa',
        'coti_descripcion',
        'coti_codigocli',
        'coti_fechaalta',
        'coti_fechaaprobado',
        'coti_aprobo',
        'coti_estado',
        'coti_codigomatriz',
        'coti_responsable',
        'coti_fechafin',
        'coti_notas',
        'coti_fechaencurso',
        'coti_fechaaltatecnica',
        'coti_empresa',
        'coti_establecimiento',
        'coti_contacto',
        'coti_direccioncli',
        'coti_localidad',
        'coti_partido',
        'coti_cuit',
        'coti_codigopostal',
        'coti_telefono',
        'coti_codigosuc',
        'coti_mail1',
        'coti_sector',
        'coti_referencia_tipo',
        'coti_referencia_valor',
        'coti_oc_referencia',
        'coti_hes_has_tipo',
        'coti_hes_has_valor',
        'coti_gr_contrato_tipo',
        'coti_gr_contrato',
        'coti_otro_referencia',
        'coti_descuentoglobal',
        'coti_sector_laboratorio_pct',
        'coti_sector_higiene_pct',
        'coti_sector_microbiologia_pct',
        'coti_sector_cromatografia_pct',
        'coti_sector_laboratorio_contacto',
        'coti_sector_higiene_contacto',
        'coti_sector_microbiologia_contacto',
        'coti_sector_cromatografia_contacto',
        'coti_sector_laboratorio_observaciones',
        'coti_sector_higiene_observaciones',
        'coti_sector_microbiologia_observaciones',
        'coti_sector_cromatografia_observaciones',
        'coti_cadena_custodia',
        'coti_muestreo'
    ];

    protected $casts = [
        'coti_fechaalta' => 'date',
        'coti_fechaaprobado' => 'date',
        'coti_fechafin' => 'date',
        'coti_fechaencurso' => 'date',
        'coti_fechaaltatecnica' => 'date',
        'coti_descuentoglobal' => 'decimal:2',
        'coti_sector_laboratorio_pct' => 'decimal:2',
        'coti_sector_higiene_pct' => 'decimal:2',
        'coti_sector_microbiologia_pct' => 'decimal:2',
        'coti_sector_cromatografia_pct' => 'decimal:2',
        'coti_cadena_custodia' => 'boolean',
        'coti_muestreo' => 'boolean'
    ]; 

    /**
     * Relación con cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Clientes::class, 'coti_codigocli', 'cli_codigo');
    }

    /**
     * Relación con tareas/items de la cotización
     */
    public function tareas()
    {
        return $this->hasMany(Cotio::class, 'cotio_numcoti', 'coti_num');
    }

    /**
     * Relación con muestras (ensayos) - cotio_subitem = 0
     */
    public function muestras()
    {
        return $this->hasMany(Cotio::class, 'cotio_numcoti', 'coti_num')
                    ->where('cotio_subitem', 0);
    }

    /**
     * Relación con componentes (análisis) - cotio_subitem > 0
     */
    public function componentes()
    {
        return $this->hasMany(Cotio::class, 'cotio_numcoti', 'coti_num')
                    ->where('cotio_subitem', '>', 0);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'coti_responsable', 'usu_codigo');
    }

    public function matriz()
    {
        return $this->belongsTo(Matriz::class, 'coti_codigomatriz', 'matriz_codigo');
    }

    public function instancias()
    {
        return $this->hasMany(CotioInstancia::class, 'cotio_numcoti', 'coti_num');
    }

        public function cotioInstancias()
    {
        return $this->hasMany(CotioInstancia::class, 'cotio_numcoti', 'coti_num');
    }

    public function categoriasHabilitadas()
    {
        return $this->hasMany(Cotio::class, 'cotio_numcoti', 'coti_num')
            ->where('cotio_subitem', 0)
            ->where('enable_ot', true);
    }

    public function tareasDeCategoriasHabilitadas()
    {
        return $this->hasMany(Cotio::class, 'cotio_numcoti', 'coti_num')
            ->where('cotio_subitem', '!=', 0)
            ->whereIn('cotio_item', function($query) {
                $query->select('cotio_item')
                    ->from('cotio')
                    ->whereColumn('cotio_numcoti', 'coti.coti_num')
                    ->where('cotio_subitem', 0)
                    ->where('enable_ot', true);
            });
    }
}
