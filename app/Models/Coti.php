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
        'coti_para_empresa_rel',
        'coti_empresa_rel',
        'coti_descripcion',
        'coti_codigocli',
        'coti_fechaalta',
        'coti_fechaaprobado',
        'coti_aprobo',
        'coti_estado',
        'coti_codigomatriz',
        'coti_responsable',
        'coti_creador',
        'coti_fechafin',
        'coti_notas',
        'coti_fechaencurso',
        'coti_fechaaltatecnica',
        'coti_empresa',
        'coti_establecimiento',
        'coti_contacto',
        'coti_contacto_tipo1',
        'coti_contacto2',
        'coti_mail2',
        'coti_telefono2',
        'coti_contacto_tipo2',
        'coti_contacto3',
        'coti_mail3',
        'coti_telefono3',
        'coti_contacto_tipo3',
        'coti_contacto4',
        'coti_mail4',
        'coti_telefono4',
        'coti_contacto_tipo4',
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
        'coti_oc_requerido_factura',
        'coti_refs_facturacion_json',
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
        'coti_muestreo',
        'coti_req_cadena_custodia_relacionada',
        'divisa_codigo',
        'coti_cond_pago',
        'coti_cuotas',
        'coti_cuota_desc',
        'coti_cuota_cant',
        'coti_cuota_monto_total',
        'coti_cuota_monto_indiv',
        'coti_cuota_interes',
        'coti_cuota_fact_fin_mes',
        'coti_cuota_fact_inicio_mes',
        'cancelada',
        'razon_cancelada',
        'coti_notas_facturacion'
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
        'coti_muestreo' => 'boolean',
        'coti_req_cadena_custodia_relacionada' => 'boolean',
        'coti_cuotas' => 'boolean',
        'coti_cuota_fact_fin_mes' => 'boolean',
        'coti_cuota_fact_inicio_mes' => 'boolean',
        'cancelada' => 'boolean',
        'razon_cancelada' => 'string',
        'coti_cuota_monto_total' => 'decimal:4',
        'coti_cuota_monto_indiv' => 'decimal:4',
        'coti_cuota_interes'     => 'decimal:4',
        'coti_oc_requerido_factura' => 'boolean',
        'coti_refs_facturacion_json' => 'array',
    ]; 

    /**
     * CHAR en SQL Server suele venir con espacios; sin trim la relación cliente no matchea.
     */
    public function getCotiCodigocliAttribute($value): ?string
    {
        return $value === null ? null : trim((string) $value);
    }

    /**
     * Relación con cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Clientes::class, 'coti_codigocli', 'cli_codigo');
    }

    public function sucursal()
    {
        return $this->belongsTo(Clientes::class, 'coti_codigosuc', 'cli_codigo');
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

    /**
     * Obtiene el canal especial inferido desde sus items (ensayos).
     * Retorna 'consultoria', 'asp', 'clarke_fire' o null.
     */
    public function getCanalEspecialAttribute(): ?string
    {
        // Usar la relación 'muestras' (cotio_subitem = 0) para buscar el canal
        foreach ($this->muestras as $muestra) {
            $canal = strtolower(trim((string)($muestra->cotio_canal_especial ?? '')));
            if (in_array($canal, ['consultoria', 'asp', 'clarke_fire', 'mediciones'], true)) {
                return $canal;
            }
        }
        return null;
    }

    /**
     * Retorna la descripción de la matriz real o el nombre del canal especial si la matriz es null.
     */
    public function getMatrizDescripcionCalculadaAttribute(): string
    {
        if ($this->matriz) {
            return trim($this->matriz->matriz_descripcion);
        }

        $canal = $this->canal_especial;
        if ($canal) {
            switch($canal) {
                case 'consultoria': return 'Consultoría';
                case 'asp':         return 'ASP';
                case 'clarke_fire': return 'Clarke Fire';
                case 'mediciones':  return 'Mediciones';
                default:            return ucwords(str_replace('_', ' ', $canal));
            }
        }

        return 'N/A';
    }
}
