<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirmaDigitalService
{
    protected string $endpoint = "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostFirmarDocumentoFirmaDigital";

    public function firmarDocumento($pdfBinary, $cuil, $cuitOrg)
    {
        Log::info("🔄 Iniciando proceso de firma digital", [
            'cuil_recibido' => $cuil,
            'cuit_org_recibido' => $cuitOrg,
            'pdf_size' => strlen($pdfBinary),
            'timestamp' => now()->toISOString()
        ]);
        
        try {
            // Convertir PDF en base64
            $documentoBase64 = base64_encode($pdfBinary);

            // Calcular hash SHA256
            $hash = hash('sha256', $pdfBinary);

            // Payload según documentación oficial
            $payload = 
            [
                "DocumentoBase64" => $documentoBase64,
                "HashSHA256Hexadecimal" => $hash,
                "IdentificadorGrupo" => "",
                "Personas" => [
                    [
                        "CodigoUnicoIdentificacion" => $cuil ?? '20000000019', // CUIL de prueba oficial
                        "CuitOrganizacion" => $cuitOrg ?? '',
                        "OrdenFirma" => 1,
                        "CuadroVisibleFirma_X"     => 595 - 200 - 20, // margen derecho de 20 = 375
                        "CuadroVisibleFirma_Y"     => 20, // margen superior de 20 = 20
                        "CuadroVisibleFirma_Ancho" => 200,
                        "CuadroVisibleFirma_Alto" => 80,
                        "CuadroVisibleFirma_ImagenBase64" => "",
                        "CuadroVisibleFirma_PlantillaID" => 1, 
                        "RazonFirma" => "",
                        "CuadroVisibleFirma_Pagina" => 1,
                        "CuadroVisibleFirma_TodasPaginas" => true,
                        "UrlRedireccionOK" => url("/firma/exitosa"),
                        "UrlRedireccionError" => url("/firma/error"),
                        "UrlRedireccionRechazar" => url("/firma/rechazada"),
                        "ForzarGeneracionErrorParaTest" => false,
                        "NroSerieCertificado" => "",
                        "PinEncriptado" => ""
                    ]
                ],
                "Origen" => "",
                "UserIdCreador" => 8
            ];
            
            $token = $this->generarTokenAuth('WebApi_industriayambiente', 'GrtLx92mQ');
            Log::info("Token generado: " . $token);

            // Log del payload para debug
            Log::info("Payload enviado a firma digital", [
                'endpoint' => $this->endpoint,
                'payload_size' => strlen(json_encode($payload)),
                'hash' => $hash,
                'cuil' => $payload['Personas'][0]['CodigoUnicoIdentificacion'],
                'plantilla_id' => $payload['Personas'][0]['CuadroVisibleFirma_PlantillaID']
            ]);

                        // Hacer la llamada POST
            Log::info("Iniciando llamada POST a API de firma digital");
            
            $response = Http::withOptions([
                'verify' => false
            ])->withHeaders([
                'Authorization' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->asJson()->post($this->endpoint, $payload);
            
            Log::info("Respuesta recibida de API firma digital", [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'failed' => $response->failed(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("✅ Respuesta exitosa de API firma digital", $responseData);
                
                // Verificar el código de resultado de la API
                if (isset($responseData['CodigoResultado']) && $responseData['CodigoResultado'] == 1) {
                    Log::info("🔗 Documento enviado correctamente para firma");
                    
                    // Obtener la URL de autorización de la respuesta
                    $urlAutorizacionRelativa = $responseData['Datos']['Autorizaciones'][0]['URLAutorizacion'] ?? null;
                    
                    // CRÍTICO: Construir la URL completa porque redirect()->away() necesita una URL absoluta
                    // Si la URL es relativa (empieza con /), Laravel la interpreta como ruta local
                    $urlAutorizacion = $urlAutorizacionRelativa;
                    if ($urlAutorizacion && !preg_match('/^https?:\/\//', $urlAutorizacion)) {
                        // Es una URL relativa, construir la URL completa
                        // IMPORTANTE: Según los logs antiguos, la URL de autorización apunta a un dominio diferente
                        // al de la API. La API está en test.firmador.alpha2000.com.ar pero la interfaz web
                        // de autorización está en Test.WebFirmador.digilogix.com.ar
                        // Probamos primero con el dominio de la interfaz web
                        $baseUrlWeb = 'https://Test.WebFirmador.digilogix.com.ar';
                        $baseUrlApi = 'https://test.firmador.alpha2000.com.ar';
                        
                        // Si la URL relativa comienza con /, es absoluta desde la raíz del dominio
                        if (strpos($urlAutorizacion, '/') === 0) {
                            // En los logs antiguos había un doble slash: //FirmaDigital/...
                            // Construir la URL con el dominio de la interfaz web
                            // Nota: Los logs antiguos mostraban: https://Test.WebFirmador.digilogix.com.ar//FirmaDigital/...
                            // IMPORTANTE: Si la ruta /FirmaDigital/AutorizacionFirmaDocumento no funciona,
                            // podría necesitar un path base como /WebUtilsWebApi o similar
                            $urlAutorizacion = rtrim($baseUrlWeb, '/') . $urlAutorizacion;
                            
                            Log::info("🔗 URL de autorización construida con dominio WebFirmador", [
                                'url_relativa' => $urlAutorizacionRelativa,
                                'url_completa' => $urlAutorizacion,
                                'dominio_usado' => $baseUrlWeb,
                                'nota' => 'Si esta URL da 404, podría necesitar path base adicional o la estructura cambió'
                            ]);
                        } else {
                            // URL relativa sin / inicial
                            $urlAutorizacion = rtrim($baseUrlWeb, '/') . '/' . ltrim($urlAutorizacion, '/');
                            
                            Log::info("🔗 URL de autorización construida (relativa)", [
                                'url_relativa' => $urlAutorizacionRelativa,
                                'url_completa' => $urlAutorizacion
                            ]);
                        }
                    } else if ($urlAutorizacion) {
                        Log::info("🔗 URL de autorización ya es completa", [
                            'url' => $urlAutorizacion
                        ]);
                    }
                    
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'url_autorizacion' => $urlAutorizacion,
                        'identificador_documento' => $responseData['Datos']['IdentificadorDocumento'] ?? null
                    ];
                } else {
                    Log::error("❌ API devolvió código de error", [
                        'codigo' => $responseData['CodigoResultado'] ?? 'N/A',
                        'mensaje' => $responseData['MensajeResultado'] ?? 'Sin mensaje'
                    ]);
                    return [
                        'success' => false,
                        'error' => $responseData['MensajeResultado'] ?? 'Error desconocido de la API'
                    ];
                }
            }

            // Log detallado para respuestas no exitosas
            Log::error("❌ Error en API firma digital", [
                'status_code' => $response->status(),
                'reason_phrase' => $response->getReasonPhrase() ?? 'N/A',
                'body' => $response->body(),
                'json_response' => $response->json(),
                'all_headers' => $response->headers(),
                'endpoint_used' => $this->endpoint
            ]);
 
            Log::warning("⚠️ Retornando error estructurado");
            return [
                'success' => false,
                'error' => 'Error HTTP ' . $response->status() . ': ' . ($response->getReasonPhrase() ?? 'Error desconocido'),
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error("🚨 Excepción crítica al firmar documento", [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            Log::warning("⚠️ Retornando error por excepción");
            return [
                'success' => false,
                'error' => 'Excepción: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }

    private function generarTokenAuth(string $usuario, string $password): string
    {
        $fecha = now()->format('d/m/Y');
        $cadena = "LOGIN|{$fecha}|{$usuario}|{$password}";
        Log::info("Cadena: " . $cadena);
    
        $clavePrivada = "X7M9C2LQ8T1JRAE";
        $salteo = "11258z12";
        $keyString = "af1238554vzidjH8";
    
        // Derivar key con PBKDF2 - exactamente como C# Rfc2898DeriveBytes
        // C# usa 256/8 = 32 bytes y 1000 iteraciones por defecto
        $key = hash_pbkdf2("sha1", $clavePrivada, $salteo, 1000, 32, true);
        
        // IV es el keyString directamente (16 bytes)
        $iv = substr($keyString, 0, 16);
        
        // Convertir texto a bytes UTF-8 (como C# Encoding.UTF8.GetBytes)
        $textoPlanoBytes = $cadena;
        
        // Padding con ceros (PaddingMode.Zeros en C#)
        $blockSize = 16;
        $remainder = strlen($textoPlanoBytes) % $blockSize;
        if ($remainder != 0) {
            $textoPlanoBytes .= str_repeat("\0", $blockSize - $remainder);
        }
        
        // Encriptar con AES-256-CBC (Rijndael equivalente)
        $encrypted = openssl_encrypt(
            $textoPlanoBytes,
            "AES-256-CBC",
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, // Zero padding como C#
            $iv
        );
    
        return base64_encode($encrypted);
    }

    /**
     * Obtiene el documento firmado usando el identificador
     * Nota: Según la documentación oficial, no hay un endpoint específico para descargar el documento firmado.
     * Este método intenta diferentes enfoques basados en patrones comunes de APIs.
     */
    public function obtenerDocumentoFirmado($identificadorDocumento)
    {
        Log::info("🔽 Solicitando documento firmado", [
            'identificador' => $identificadorDocumento
        ]);

        try {
            // Primero verificar el estado del documento
            $estadoResultado = $this->obtenerEstadoFirma($identificadorDocumento);
            
            if (!$estadoResultado['success']) {
                Log::warning("⚠️ No se pudo verificar el estado del documento");
                return [
                    'success' => false,
                    'error' => 'No se pudo verificar el estado del documento: ' . $estadoResultado['error']
                ];
            }

            // Verificar si ya tenemos el archivo firmado en la respuesta del estado
            if (!empty($estadoResultado['archivo_firmado_base64'])) {
                Log::info("✅ Archivo firmado encontrado directamente en la respuesta del estado");
                return [
                    'success' => true,
                    'documento_base64' => $estadoResultado['archivo_firmado_base64'],
                    'data' => $estadoResultado['data']
                ];
            }

            if (!$estadoResultado['firmado']) {
                Log::warning("⚠️ El documento no está firmado aún", [
                    'estado' => $estadoResultado['estado'],
                    'codigo_estado' => $estadoResultado['codigo_estado'] ?? null
                ]);
                return [
                    'success' => false,
                    'error' => 'El documento no está firmado. Estado actual: ' . $estadoResultado['estado']
                ];
            }

            Log::info("✅ Documento confirmado como firmado, intentando obtener...");

            $token = $this->generarTokenAuth('WebApi_industriayambiente', 'GrtLx92mQ');
            
            // Intentar con diferentes endpoints basados en patrones comunes de APIs de firma
            $endpoints = [
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostObtenerDocumentoFirmado",
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostDescargarDocumentoFirmado", 
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostObtenerDocumentoFirmadoDigital",
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostDescargarDocumentoFirmadoDigital",
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostObtenerDocumentoFirmadoCompleto",
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostDescargarDocumentoFirmadoCompleto",
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/GetDocumentoFirmado",
                "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/GetDocumentoFirmadoDigital"
            ];
            
            foreach ($endpoints as $endpoint) {
                Log::info("🔍 Probando endpoint: " . $endpoint);
                
                // Probar con POST (método más común en esta API)
                $response = Http::withOptions([
                    'verify' => false
                ])->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->asJson()->post($endpoint, [
                    'IdentificadorDocumento' => $identificadorDocumento
                ]);

                Log::info("📄 Respuesta POST obtener documento firmado", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'body_size' => strlen($response->body())
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    if (isset($responseData['CodigoResultado']) && $responseData['CodigoResultado'] == 1) {
                        Log::info("✅ Documento firmado obtenido exitosamente con POST");
                        return [
                            'success' => true,
                            'documento_base64' => $responseData['Datos']['DocumentoFirmadoBase64'] ?? 
                                                 $responseData['Datos']['DocumentoBase64'] ?? 
                                                 $responseData['Datos']['Documento'] ?? null,
                            'data' => $responseData
                        ];
                    }
                }

                // Si POST falla, probar con GET
                $response = Http::withOptions([
                    'verify' => false
                ])->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])->get($endpoint . '?IdentificadorDocumento=' . urlencode($identificadorDocumento));

                Log::info("📄 Respuesta GET obtener documento firmado", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'body_size' => strlen($response->body())
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    if (isset($responseData['CodigoResultado']) && $responseData['CodigoResultado'] == 1) {
                        Log::info("✅ Documento firmado obtenido exitosamente con GET");
                        return [
                            'success' => true,
                            'documento_base64' => $responseData['Datos']['DocumentoFirmadoBase64'] ?? 
                                                 $responseData['Datos']['DocumentoBase64'] ?? 
                                                 $responseData['Datos']['Documento'] ?? null,
                            'data' => $responseData
                        ];
                    }
                }

                // Log detallado de la respuesta para debug
                Log::info("Respuesta completa documento firmado", [
                    'endpoint' => $endpoint,
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
            }

            // Si ningún endpoint funciona, retornar información del estado
            Log::warning("⚠️ Ningún endpoint de descarga funcionó, pero el documento está firmado");
            return [
                'success' => false,
                'error' => 'El documento está firmado pero no se encontró un endpoint para descargarlo. Estado: ' . $estadoResultado['estado'],
                'estado_documento' => $estadoResultado['estado'],
                'firmado' => true,
                'sugerencia' => 'Verificar con el proveedor de la API si existe un endpoint específico para descargar documentos firmados'
            ];

        } catch (\Exception $e) {
            Log::error("🚨 Error al obtener documento firmado", [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Excepción: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }

    /**
     * Obtiene el estado de firma de un documento
     */
    public function obtenerEstadoFirma($identificadorDocumento)
    {
        Log::info("📋 Consultando estado de firma", [
            'identificador' => $identificadorDocumento
        ]);

        try {
            $token = $this->generarTokenAuth('WebApi_industriayambiente', 'GrtLx92mQ');
            
            $endpoint = "https://test.firmador.alpha2000.com.ar/api/FirmaDigital/PostObtenerEstadoFirmaDigitalDocumento";
            
            $response = Http::withOptions([
                'verify' => false
            ])->withHeaders([
                'Authorization' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->asJson()->post($endpoint, [
                'IdentificadorDocumento' => $identificadorDocumento
            ]);

            Log::info("📊 Respuesta estado de firma", [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_size' => strlen($response->body())
            ]);

            // Log completo de la respuesta para debug
            Log::info("📋 Respuesta completa estado de firma", [
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info("📋 Datos parseados del estado", $responseData);
                
                if (isset($responseData['CodigoResultado']) && $responseData['CodigoResultado'] == 1) {
                    $datos = $responseData['Datos'] ?? [];
                    $codigoEstado = $datos['CodigoEstado'] ?? null;
                    $descripcionEstado = $datos['DescripcionEstado'] ?? 'desconocido';
                    
                    // El documento está firmado cuando:
                    // - CodigoEstado es 3 (Firmado) o
                    // - DescripcionEstado contiene "Firmado" o
                    // - ArchivoFirmadoBase64 no es null
                    $firmado = (
                        $codigoEstado == 3 || 
                        stripos($descripcionEstado, 'firmado') !== false ||
                        !empty($datos['ArchivoFirmadoBase64'])
                    );
                    
                    Log::info("📋 Estado del documento", [
                        'codigo_estado' => $codigoEstado,
                        'descripcion_estado' => $descripcionEstado,
                        'firmado' => $firmado,
                        'archivo_firmado_disponible' => !empty($datos['ArchivoFirmadoBase64']),
                        'datos_completos' => $datos
                    ]);
                    
                    return [
                        'success' => true,
                        'estado' => $descripcionEstado,
                        'codigo_estado' => $codigoEstado,
                        'firmado' => $firmado,
                        'archivo_firmado_base64' => $datos['ArchivoFirmadoBase64'] ?? null,
                        'data' => $responseData
                    ];
                } else {
                    Log::warning("⚠️ API devolvió código de error en estado", [
                        'codigo' => $responseData['CodigoResultado'] ?? 'N/A',
                        'mensaje' => $responseData['MensajeResultado'] ?? 'Sin mensaje'
                    ]);
                }
            }

            return [
                'success' => false,
                'error' => 'No se pudo obtener el estado del documento',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error("🚨 Error al obtener estado de firma", [
                'mensaje' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Excepción: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }

    /**
     * Método para probar la autenticación por separado
     */
    public function probarAutenticacion($usuario = 'WebApi_industriayambiente', $password = 'GrtLx92mQ')
    {
        try {
            $token = $this->generarTokenAuth($usuario, $password);
            Log::info("Probando autenticación", [
                'usuario' => $usuario,
                'token' => $token
            ]);

            // Hacer una llamada simple para probar la autenticación
            $response = Http::withOptions([
                'verify' => false
            ])->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->get('https://test.firmador.alpha2000.com.ar/api/test'); // endpoint de prueba si existe

            Log::info("Respuesta prueba auth", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $response->status() !== 401;

        } catch (\Exception $e) {
            Log::error("Error probando autenticación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método para probar la obtención de documento firmado con un identificador específico
     */
    public function probarObtenerDocumentoFirmado($identificadorDocumento = '5o0SIGYht9l2uiDBtUmMMA==')
    {
        Log::info("🧪 Iniciando prueba de obtención de documento firmado", [
            'identificador' => $identificadorDocumento
        ]);

        $resultado = $this->obtenerDocumentoFirmado($identificadorDocumento);
        
        Log::info("🧪 Resultado de la prueba", $resultado);
        
        return $resultado;
    }

    /**
     * Método para probar solo el estado de firma
     */
    public function probarEstadoFirma($identificadorDocumento = '5o0SIGYht9l2uiDBtUmMMA==')
    {
        Log::info("🧪 Iniciando prueba de estado de firma", [
            'identificador' => $identificadorDocumento
        ]);

        $resultado = $this->obtenerEstadoFirma($identificadorDocumento);
        
        Log::info("🧪 Resultado del estado", $resultado);
        
        return $resultado;
    }

    /**
     * Flujo completo de firma digital: firmar y luego obtener el documento
     */
    public function firmarYDescargarDocumento($pdfBinary, $cuil, $cuitOrg, $maxIntentos = 10, $intervaloEspera = 5)
    {
        Log::info("🚀 Iniciando flujo completo de firma digital", [
            'cuil' => $cuil,
            'cuit_org' => $cuitOrg,
            'pdf_size' => strlen($pdfBinary),
            'max_intentos' => $maxIntentos,
            'intervalo_espera' => $intervaloEspera
        ]);

        try {
            // Paso 1: Firmar el documento
            Log::info("📝 Paso 1: Enviando documento para firma");
            $resultadoFirma = $this->firmarDocumento($pdfBinary, $cuil, $cuitOrg);
            
            if (!$resultadoFirma['success']) {
                Log::error("❌ Error al enviar documento para firma", $resultadoFirma);
                return $resultadoFirma;
            }

            $identificadorDocumento = $resultadoFirma['identificador_documento'];
            Log::info("✅ Documento enviado para firma", [
                'identificador' => $identificadorDocumento,
                'url_autorizacion' => $resultadoFirma['url_autorizacion']
            ]);

            // Paso 2: Esperar y verificar que se firme
            Log::info("⏳ Paso 2: Esperando a que se complete la firma...");
            
            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                Log::info("🔍 Intento {$intento}/{$maxIntentos}: Verificando estado de firma");
                
                $estadoResultado = $this->obtenerEstadoFirma($identificadorDocumento);
                
                if (!$estadoResultado['success']) {
                    Log::warning("⚠️ Error al verificar estado en intento {$intento}", $estadoResultado);
                    continue;
                }

                Log::info("📊 Estado actual del documento", [
                    'estado' => $estadoResultado['estado'],
                    'firmado' => $estadoResultado['firmado'],
                    'intento' => $intento
                ]);

                if ($estadoResultado['firmado']) {
                    Log::info("✅ Documento firmado exitosamente!");
                    break;
                }

                if ($intento < $maxIntentos) {
                    Log::info("⏳ Esperando {$intervaloEspera} segundos antes del siguiente intento...");
                    sleep($intervaloEspera);
                }
            }

            // Verificar si se firmó
            if (!$estadoResultado['firmado']) {
                Log::error("❌ El documento no se firmó después de {$maxIntentos} intentos");
                return [
                    'success' => false,
                    'error' => "El documento no se firmó después de {$maxIntentos} intentos. Estado final: " . $estadoResultado['estado'],
                    'identificador_documento' => $identificadorDocumento,
                    'estado_final' => $estadoResultado['estado']
                ];
            }

            // Paso 3: Obtener el documento firmado
            Log::info("📥 Paso 3: Obteniendo documento firmado");
            $resultadoDescarga = $this->obtenerDocumentoFirmado($identificadorDocumento);
            
            if ($resultadoDescarga['success']) {
                Log::info("🎉 Flujo completo exitoso: documento firmado y descargado");
                return [
                    'success' => true,
                    'identificador_documento' => $identificadorDocumento,
                    'documento_base64' => $resultadoDescarga['documento_base64'],
                    'estado' => $estadoResultado['estado'],
                    'data' => $resultadoDescarga['data']
                ];
            } else {
                Log::error("❌ Error al obtener documento firmado", $resultadoDescarga);
                return [
                    'success' => false,
                    'error' => 'Documento firmado pero no se pudo descargar: ' . $resultadoDescarga['error'],
                    'identificador_documento' => $identificadorDocumento,
                    'estado' => $estadoResultado['estado'],
                    'details' => $resultadoDescarga
                ];
            }

        } catch (\Exception $e) {
            Log::error("🚨 Error en flujo completo de firma digital", [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'error' => 'Excepción en flujo completo: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }

}
