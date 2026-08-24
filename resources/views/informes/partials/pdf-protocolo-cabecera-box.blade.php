{{--
    Recuadro de cabecera del protocolo (PDF).
    $cab desde ProtocoloInformePdfCabecera::forPdf()

    Layout: dos columnas independientes separadas por línea vertical.
      Izquierda (60%): identificación del cliente / OT
      Derecha   (40%): datos de la muestra (NEGRITA en primer renglón) + sitio + precinto + custodia + protocolo de informe N + legislación
--}}
<div class="box">
    <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr>

            {{-- COLUMNA IZQUIERDA --}}
            <td style="width:60%; vertical-align:top; padding-right:4mm; border-right:1px solid #bbb;">
                <table class="kv" style="width:100%;">
                    <tr>
                        <td class="k" style="width:45%; white-space:normal;">Fecha de emisión:</td>
                        <td>{{ $cab['fecha_emision'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">O.T. N°:</td>
                        <td>{{ $cab['otn'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Razón social:</td>
                        <td>{{ $cab['razon_social'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Dirección:</td>
                        <td>{{ $cab['direccion'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Fecha de extracción:</td>
                        <td>{{ $cab['fecha_extraccion'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Fecha de recepción:</td>
                        <td>{{ $cab['fecha_recepcion'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Identificación de muestra:</td>
                        <td>{{ $cab['identificacion_muestra'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Muestra extraída por:</td>
                        <td>{{ $cab['muestra_extraida_por'] }}</td>
                    </tr>
                </table>
            </td>

            {{-- COLUMNA DERECHA --}}
            <td style="width:40%; vertical-align:top; padding-left:4mm;">
                <table class="kv" style="width:100%;">
                    {{-- Primer renglón de la derecha: MATRIZ / datos de la muestra — en NEGRITA --}}
                    <tr>
                        <td class="k" style="width:52%; white-space:normal;">Datos de la muestra:</td>
                        <td><strong>{{ $cab['datos_muestra'] }}</strong></td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Sitio de extracción:</td>
                        <td>{{ $cab['sitio_extraccion'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Precinto N°:</td>
                        <td>{{ $cab['precinto'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Cadena de Custodia N°:</td>
                        <td>{{ $cab['cadena_custodia'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Protocolo de informe N:</td>
                        <td>{{ $cab['protocolo_opds'] }}</td>
                    </tr>
                    <tr>
                        <td class="k" style="white-space:normal;">Legislación / normativa:</td>
                        <td>{{ $cab['legislacion_normativa'] }}</td>
                    </tr>
                </table>
            </td>

        </tr>
    </table>
</div>
