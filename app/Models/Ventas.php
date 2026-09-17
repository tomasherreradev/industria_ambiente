<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ventas extends Model
{
    protected $table = 'coti';
    protected $primaryKey = 'coti_num';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'coti_num',
        'coti_version',
        'coti_edit_lock_usu',
        'coti_edit_lock_at',
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
        'coti_mostrar_descuento',
        'coti_aumentoglobal',
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
        'coti_prioridad_global',
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
        'coti_cuota_fecha_inicio',
        'coti_cuota_fecha_fin',
        'cancelada',
        'razon_cancelada'
    ];

    protected $casts = [
        'coti_edit_lock_at' => 'datetime',
        'coti_fechaalta' => 'date',
        'coti_fechaaprobado' => 'date',
        'coti_fechafin' => 'date',
        'coti_fechaencurso' => 'date',
        'coti_fechaaltatecnica' => 'date',
        'coti_descuentoglobal' => 'decimal:2',
        'coti_mostrar_descuento' => 'boolean',
        'coti_aumentoglobal' => 'decimal:2',
        'coti_sector_laboratorio_pct' => 'decimal:2',
        'coti_sector_higiene_pct' => 'decimal:2',
        'coti_sector_microbiologia_pct' => 'decimal:2',
        'coti_sector_cromatografia_pct' => 'decimal:2',
        'coti_cadena_custodia' => 'boolean',
        'coti_muestreo' => 'boolean',
        'coti_req_cadena_custodia_relacionada' => 'boolean',
        'coti_prioridad_global' => 'boolean',
        'coti_cuotas' => 'boolean',
        'coti_cuota_fact_fin_mes' => 'boolean',
        'coti_cuota_fact_inicio_mes' => 'boolean',
        'coti_cuota_fecha_inicio' => 'date',
        'coti_cuota_fecha_fin' => 'date',
        'cancelada' => 'boolean',
        'razon_cancelada' => 'string',
        'coti_para_empresa_rel' => 'boolean',
        'coti_cuota_monto_total' => 'decimal:4',
        'coti_cuota_monto_indiv' => 'decimal:4',
        'coti_cuota_interes' => 'decimal:4',
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

    // Relaciones
    public function cliente()
    {
        return $this->belongsTo(Clientes::class, 'coti_codigocli', 'cli_codigo');
    }

    public function matriz()
    {
        return $this->belongsTo(Matriz::class, 'coti_codigomatriz', 'matriz_codigo');
    }

    public function sector()
    {
        return $this->belongsTo(Divis::class, 'coti_sector', 'divis_codigo');
    }

    public function divisa()
    {
        return $this->belongsTo(Divisa::class, 'divisa_codigo', 'divisa_codigo');
    }

    public function condicionPago()
    {
        return $this->belongsTo(CondicionPago::class, 'coti_cond_pago', 'pag_codigo');
    }

    /**
     * Empresa relacionada seleccionada en "Para" (cuando el cliente es consultor).
     */
    public function empresaRelacionada()
    {
        return $this->belongsTo(ClienteEmpresaRelacionada::class, 'coti_cli_empresa', 'id');
    }

    /**
     * Sucursal del cliente seleccionada como destinatario (coti_codigosuc → cli).
     */
    public function sucursal()
    {
        return $this->belongsTo(Clientes::class, 'coti_codigosuc', 'cli_codigo');
    }

    public function listaPrecio()
    {
        return $this->belongsTo(ListaPrecio::class, 'coti_codigolp', 'lp_codigo');
    }

    public function cotios()
    {
        return $this->hasMany(Cotio::class, 'cotio_numcoti', 'coti_num');
    }
}