<style>
    /* Estilos personalizados para las solapas */
    .nav-tabs-custom {
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
        padding: 0;
        margin: 0;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        border-radius: 0;
        padding: 12px 20px;
        color: #495057;
        background-color: transparent;
        font-weight: 500;
        position: relative;
    }

    .nav-tabs-custom .nav-link:hover {
        background-color: #e9ecef;
        border: none;
    }

    .nav-tabs-custom .nav-link.active {
        background-color: #fff;
        color: #0d6efd;
        border: none;
        border-bottom: 2px solid #0d6efd;
    }

    .nav-tabs-custom .nav-link.disabled {
        color: #6c757d;
        background-color: transparent;
        cursor: not-allowed;
    }

    /* Estilo para los campos de formulario */
    .form-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.25rem;
    }

    .form-control,
    .form-select {
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Header del formulario */
    .bg-light {
        background-color: #f8f9fa !important;
    }

    /* Radio buttons y checkboxes */
    .form-check-inline .form-check-input {
        margin-right: 0.25rem;
    }

    .form-check-inline .form-check-label {
        margin-right: 1rem;
    }

    /* Espaciado de contenido */
    .tab-content {
        min-height: 400px;
    }

    /* Tabla de items */
    .table th {
        background-color: #f8f9fa;
        border-color: #dee2e6;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.5rem;
    }

    .table td {
        padding: 0.5rem;
        vertical-align: middle;
        font-size: 0.875rem;
    }

    /* Estilos para resaltar ensayos */
    .table tbody tr[data-tipo="ensayo"] {
        background-color: #e7f3ff;
        font-weight: 600;
    }


    /* Estilos para componentes (indentación) */
    .table tbody tr[data-tipo="componente"] {
        background-color: #ffffff;
    }

    .table tbody tr[data-tipo="componente"] td:nth-child(3) {
        position: relative;
    }
/* 
    .table tbody tr[data-tipo="componente"] td:nth-child(3)::before {
        content: "└─ ";
        position: absolute;
        left: 0.5rem;
        color: #6c757d;
        font-weight: bold;
    } */

    /* Inputs en tabla */
    .table tbody input[type="number"] {
        min-width: 70px;
    }

    /* Botones de búsqueda */
    .btn-outline-secondary {
        border-color: #ced4da;
        color: #6c757d;
    }

    .btn-outline-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    /* Input groups */
    .input-group .form-control {
        border-right: 0;
    }

    .input-group .form-control:not(:last-child) {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    .input-group .form-control:not(:first-child) {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        border-left: 0;
    }

    .input-group .btn {
        border-left: 0;
    }

    /* Modales */
    .modal-header.bg-primary {
        background-color: #0d6efd !important;
    }

    .modal-header.bg-info {
        background-color: #0dcaf0 !important;
    }

    /* Campos pequeños en modales */
    .modal-body .form-control,
    .modal-body .form-select {
        font-size: 0.875rem;
    }

    /* Botones pequeños */
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .cotizacion-loading-overlay {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 255, 255, 0.85);
        z-index: 2000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out;
    }

    .cotizacion-loading-overlay.is-visible {
        opacity: 1;
        visibility: visible;
    }

    .tabla-items-bloqueada {
        opacity: 0.6;
        pointer-events: none;
    }

    /* Resumen de componentes seleccionados */
    .componentes-resumen-card {
        border: 1px dashed #ced4da;
        border-radius: 0.5rem;
        padding: 0.75rem;
        background-color: #f8f9fa;
        max-height: 230px;
        overflow-y: auto;
    }

    .componentes-resumen-item + .componentes-resumen-item {
        border-top: 1px solid #e9ecef;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
    }

    .componentes-resumen-item .componentes-resumen-meta {
        font-size: 0.78rem;
    }

    .campo-multi-disabled {
        background-color: #f1f3f5;
        cursor: not-allowed;
    }

    .select2-results__option .componente-option-title {
        font-weight: 600;
        font-size: 0.9rem;
    }

    .select2-results__option .componente-option-meta {
        font-size: 0.78rem;
    }

    /* Estilos para toggle de componentes */
    .toggle-componentes {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .toggle-componentes:hover {
        color: #0a58ca;
    }

    .toggle-icon {
        transition: transform 0.3s ease;
        transform: rotate(0deg);
    }

    .toggle-icon.rotated {
        transform: rotate(-90deg);
    }

    .componente-row {
        transition: opacity 0.2s ease;
    }

    .componente-row.componente-oculto {
        display: none !important;
    }

    .table tbody tr[data-tipo="ensayo"] td:first-child .toggle-componentes {
        flex-shrink: 0;
        margin-right: 0.25rem;
    }

    /* Estilos para el select de empresas relacionadas */
    .empresa-option-item {
        padding: 0.5rem 0;
    }
    
    .empresa-option-item .fw-semibold {
        color: #495057;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .empresa-option-item .small {
        font-size: 0.8rem;
        line-height: 1.6;
    }
    
    .empresa-option-item .text-muted {
        color: #6c757d;
    }
    
    .select2-results__option--highlighted .empresa-option-item .fw-semibold {
        color: #ffffff;
    }
    
    .select2-results__option--highlighted .empresa-option-item .text-muted {
        color: rgba(255, 255, 255, 0.8);
    }

    /* Contenedor de componentes preseleccionados */
    .preseleccionados-container {
        background: linear-gradient(135deg, #e7f5ff 0%, #f0f9ff 100%);
        border: 2px solid #0dcaf0;
        border-radius: 0.5rem;
        padding: 1rem;
        box-shadow: 0 2px 8px rgba(13, 202, 240, 0.15);
    }
    
    .preseleccionados-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #0dcaf0;
    }
    
    .preseleccionados-title {
        color: #0c5460;
        font-size: 0.9rem;
        margin: 0;
    }
    
    .preseleccionados-lista {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-height: 200px;
        overflow-y: auto;
        padding-right: 0.5rem;
    }
    
    .preseleccionados-lista::-webkit-scrollbar {
        width: 6px;
    }
    
    .preseleccionados-lista::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .preseleccionados-lista::-webkit-scrollbar-thumb {
        background: #0dcaf0;
        border-radius: 3px;
    }
    
    .preseleccionados-lista::-webkit-scrollbar-thumb:hover {
        background: #0aa2c0;
    }
    
    .componente-preseleccionado-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: white;
        border: 1px solid #bee5eb;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        transition: all 0.2s ease;
    }
    
    .componente-preseleccionado-item:hover {
        background: #f8f9fa;
        border-color: #0dcaf0;
        box-shadow: 0 2px 4px rgba(13, 202, 240, 0.1);
    }
    
    .componente-preseleccionado-info {
        flex: 1;
        min-width: 0;
    }
    
    .componente-preseleccionado-nombre {
        font-weight: 500;
        color: #212529;
        font-size: 0.875rem;
        margin: 0;
        word-break: break-word;
    }
    
    .componente-preseleccionado-detalles {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    .componente-preseleccionado-remove {
        margin-left: 0.75rem;
        flex-shrink: 0;
        padding: 0.25rem;
        border: none;
        background: transparent;
        color: #dc3545;
        cursor: pointer;
        border-radius: 0.25rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .componente-preseleccionado-remove:hover {
        background: #fee;
        color: #c82333;
        transform: scale(1.1);
    }
    
    #btn_limpiar_preseleccion {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    
    #btn_limpiar_preseleccion:hover {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    
    /* Ocultar componentes preseleccionados del select - se muestran como chips arriba */
    #modalAgregarComponente .select2-selection__choice.componente-preseleccionado-hidden {
        display: none !important;
    }
    
    /* Estilos mejorados para tags del select - más altura y mejor visibilidad */
    #modalAgregarComponente .select2-selection__choice {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
        margin: 0.25rem;
        max-width: 100%;
        height: auto;
        min-height: 2.25rem;
        line-height: 1.4;
        display: inline-flex;
        align-items: center;
        word-break: break-word;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        border-radius: 0.375rem;
        color: #0c5460;
        font-weight: 500;
        border: 1px solid #0aa2c0;
    }
    
    #modalAgregarComponente .select2-selection__choice__remove {
        margin-right: 0.5rem;
        margin-left: 0.25rem;
        font-size: 1.1rem;
        font-weight: bold;
        color: #0c5460;
        opacity: 0.8;
        line-height: 1;
    }
    
    #modalAgregarComponente .select2-selection__choice__remove:hover {
        opacity: 1;
        color: #dc3545;
    }
    
    /* Contenedor del select con más altura para mostrar mejor los tags */
    #modalAgregarComponente .select2-selection {
        min-height: 2.5rem;
        max-height: 200px;
        overflow-y: auto;
        padding: 0.5rem;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        align-content: flex-start;
    }
    
    /* Scrollbar personalizado para el contenedor de selección */
    #modalAgregarComponente .select2-selection::-webkit-scrollbar {
        width: 8px;
    }
    
    #modalAgregarComponente .select2-selection::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    #modalAgregarComponente .select2-selection::-webkit-scrollbar-thumb {
        background: #0dcaf0;
        border-radius: 4px;
    }
    
    #modalAgregarComponente .select2-selection::-webkit-scrollbar-thumb:hover {
        background: #0aa2c0;
    }
    
    /* Estilos para los chips de componentes preseleccionados */
    .componente-chip-preseleccionado {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        margin: 0.125rem;
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        cursor: default;
    }
    
    .componente-chip-preseleccionado .btn-close {
        font-size: 0.7rem;
        margin-left: 0.25rem;
        opacity: 0.7;
        padding: 0.125rem;
    }
    
    .componente-chip-preseleccionado .btn-close:hover {
        opacity: 1;
    }
    
    #componentes_preseleccionados_container {
        max-height: 80px;
        overflow-y: auto;
        padding: 0.25rem;
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 0.25rem;
    }
    
    /* Limitar altura del dropdown de Select2 en el modal */
    #modalAgregarComponente .select2-results {
        max-height: 300px !important;
        overflow-y: auto !important;
    }
    
    /* Asegurar que el placeholder se vea bien con el nuevo tamaño */
    #modalAgregarComponente .select2-selection__rendered {
        padding: 0;
        min-height: 2.5rem;
    }
    
    #modalAgregarComponente .select2-selection__placeholder {
        line-height: 2.5rem;
        color: #6c757d;
    }

    /* Estilos sutiles para componentes que provienen de agrupadores */
    .componente-de-agrupador {
        background-color: #f8f9ff !important;
    }
    
    .componente-de-agrupador:hover {
        background-color: #f0f2ff !important;
    }
    
    .badge-agrupador {
        display: inline-block;
        font-size: 0.7rem;
        padding: 0.15rem 0.35rem;
        margin-right: 0.4rem;
        background-color: #e7f3ff;
        color: #0c5460;
        border: 1px solid #bee5eb;
        border-radius: 0.25rem;
        font-weight: 500;
        vertical-align: middle;
        line-height: 1.2;
    }

    /* Estilos para drag and drop */
    .sortable-ensayo,
    .sortable-componente {
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .sortable-ensayo.sortable-ghost,
    .sortable-componente.sortable-ghost {
        opacity: 0.4;
        background-color: #e7f3ff !important;
    }

    .sortable-ensayo.sortable-drag,
    .sortable-componente.sortable-drag {
        opacity: 0.8;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: rotate(2deg);
    }

    .sortable-ensayo.sortable-chosen,
    .sortable-componente.sortable-chosen {
        background-color: #fff3cd !important;
        cursor: grabbing !important;
    }

    .drag-handle,
    .drag-handle-componente {
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    .drag-handle:hover,
    .drag-handle-componente:hover {
        color: #0d6efd !important;
        transform: scale(1.1);
    }

    .sortable-ensayo:hover,
    .sortable-componente:hover {
        background-color: #f8f9fa;
    }

    /* Indicador visual cuando se puede soltar */
    .sortable-ensayo.sortable-drag-over,
    .sortable-componente.sortable-drag-over {
        border-top: 2px solid #0d6efd;
    }

</style>

