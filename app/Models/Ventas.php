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
        'coti_muestreo'
    ];

    protected $casts = [
        'coti_fechaalta' => 'date',
        'coti_fechaaprobado' => 'date',
        'coti_fechafin' => 'date',
        'coti_fechaencurso' => 'date',
        'coti_fechaaltatecnica' => 'date',
        'coti_descuentoglobal' => 'decimal:2',
        'coti_aumentoglobal' => 'decimal:2',
        'coti_sector_laboratorio_pct' => 'decimal:2',
        'coti_sector_higiene_pct' => 'decimal:2',
        'coti_sector_microbiologia_pct' => 'decimal:2',
        'coti_sector_cromatografia_pct' => 'decimal:2',
        'coti_cadena_custodia' => 'boolean',
        'coti_muestreo' => 'boolean'
    ];

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

    public function condicionPago()
    {
        return $this->belongsTo(CondicionPago::class, 'coti_codigopag', 'pag_codigo');
    }

    public function listaPrecio()
    {
        return $this->belongsTo(ListaPrecio::class, 'coti_codigolp', 'lp_codigo');
    }
}