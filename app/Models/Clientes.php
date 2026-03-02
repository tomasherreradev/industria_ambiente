<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clientes extends Model
{
    protected $table = 'cli';
    protected $primaryKey = 'cli_codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'cli_codigo',
        'cli_razonsocial',
        'cli_fantasia',
        'cli_direccion',
        'cli_partido',
        'cli_localidad',
        'cli_codigopostal',
        'cli_preftel',
        'cli_telefono',
        'cli_telefono1',
        'cli_telefono2',
        'cli_telefono3',
        'cli_telefono4',
        'cli_telefono5',
        'cli_telefono6',
        'cli_telefono7',
        'cli_telefono8',
        'cli_telefono9',
        'cli_telefono10',
        'cli_telefono11',
        'cli_telpago1',
        'cli_telpago2',
        'cli_telpago3',
        'cli_telpago4',
        'cli_horario1',
        'cli_horario2',
        'cli_fax',
        'cli_codigopais',
        'cli_codigoprv',
        'cli_debito',
        'cli_email',
        'cli_email2',
        'cli_email3',
        'cli_webpage',
        'cli_cuit',
        'cli_codigociva',
        'cli_codigopag',
        'cli_estado',
        'cli_codigolp',
        'cli_nroprecio',
        'cli_fechaalta',
        'cli_generico',
        'cli_codigotcli',
        'cli_formcuit',
        'cli_importecredito',
        'cli_descuentoglobal',
        'cli_sector_laboratorio_pct',
        'cli_sector_higiene_pct',
        'cli_sector_microbiologia_pct',
        'cli_sector_cromatografia_pct',
        'cli_contacto',
        'cli_contacto1',
        'cli_contacto2',
        'cli_contacto3',
        'cli_promotor',
        'cli_codigozon',
        'cli_contactopago',
        'cli_vctomax',
        'cli_vctomax1',
        'cli_factura',
        'cli_usuario',
        'cli_ultfot',
        'cli_ultot',
        'cli_mailing',
        'cli_diapgo',
        'cli_diarec',
        'cli_lapsomax',
        'cli_montomax',
        'cli_factmax',
        'cli_autoriza',
        'cli_causa',
        'cli_codalt',
        'cli_interno',
        'cli_externo',
        'cli_calidad',
        'cli_muestras',
        'cli_informe',
        'cli_facturar',
        'cli_gestion',
        'cli_periodicidad',
        'cli_obs',
        'cli_obs1',
        'cli_obscoti',
        'cli_zonacom',
        'cli_carpeta',
        'cli_codigotrans',
        'cli_codigoven',
        'cli_fechaultcompra',
        'cli_codigorepar',
        'cli_observaciones',
        'cli_codigocomi',
        'cli_codigocrub',
        'cli_codigocanal',
        'cli_obsgeneral',
        'cli_idcontacto',
        'cli_fleteabona',
        'cli_tipoiibb',
        'cli_numeroiibb',
        'cli_rel_empresa_razon_social',
        'cli_rel_empresa_cuit',
        'cli_rel_empresa_direcciones',
        'cli_rel_empresa_localidad',
        'cli_rel_empresa_partido',
        'cli_rel_empresa_contacto',
        'es_consultor',
    ];

    protected $casts = [
        'cli_estado' => 'boolean',
        'cli_fechaalta' => 'date',
        'cli_fechaultcompra' => 'date',
        'cli_nroprecio' => 'integer',
        'cli_factmax' => 'integer',
        'cli_lapsomax' => 'integer',
        'cli_importecredito' => 'decimal:2',
        'cli_descuentoglobal' => 'decimal:2',
        'cli_sector_laboratorio_pct' => 'decimal:2',
        'cli_sector_higiene_pct' => 'decimal:2',
        'cli_sector_microbiologia_pct' => 'decimal:2',
        'cli_sector_cromatografia_pct' => 'decimal:2',
        'cli_montomax' => 'decimal:2',
        'es_consultor' => 'boolean',
    ];

    // Relaciones
    public function condicionIva()
    {
        return $this->belongsTo(CondicionIva::class, 'cli_codigociva', 'civa_codigo');
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'cli_codigozon', 'zon_codigo');
    }

    public function condicionPago()
    {
        return $this->belongsTo(CondicionPago::class, 'cli_codigopag', 'pag_codigo');
    }

    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class, 'cli_codigotcli', 'tcli_codigo');
    }

    public function empresasRelacionadas()
    {
        return $this->hasMany(ClienteEmpresaRelacionada::class, 'cli_codigo', 'cli_codigo');
    }

    public function razonesSocialesFacturacion()
    {
        return $this->hasMany(ClienteRazonSocialFacturacion::class, 'cli_codigo', 'cli_codigo');
    }

    public function contactos()
    {
        return $this->hasMany(ClienteContacto::class, 'cli_codigo', 'cli_codigo');
    }

    // Alcances (scopes)
    public function scopeSoloPrincipales($query)
    {
        return $query
            ->whereNotNull('cli_cuit')
            ->whereRaw("TRIM(cli_cuit) <> ''")
            ->whereRaw("TRIM(cli_cuit) <> '__-________-_'");
    }

    // Sucursales: mismos datos de razón social pero sin CUIT real
    public function sucursales()
    {
        return $this->hasMany(self::class, 'cli_razonsocial', 'cli_razonsocial')
            ->where('cli_codigo', '!=', $this->cli_codigo)
            ->where(function ($q) {
                $q->whereNull('cli_cuit')
                  ->orWhereRaw("TRIM(cli_cuit) = ''")
                  ->orWhereRaw("TRIM(cli_cuit) = '__-________-_'");
            });
    }

    // Métodos auxiliares
    public function getProximoCodigoCliente()
    {
        $ultimoCliente = self::orderBy('cli_codigo', 'desc')->first();
        if ($ultimoCliente) {
            $ultimoCodigo = intval(trim($ultimoCliente->cli_codigo));
            return str_pad($ultimoCodigo + 1, 10, ' ', STR_PAD_RIGHT);
        }
        return str_pad('1', 10, ' ', STR_PAD_RIGHT);
    }
}