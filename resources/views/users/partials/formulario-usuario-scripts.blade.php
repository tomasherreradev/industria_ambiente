@php
    $formId = $formId ?? 'form-usuario';
@endphp
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById(@json($formId));
        const rolSelect = document.getElementById('rol');
        const sectorSelect = document.getElementById('sector_codigo');
        const rolesMultiSelect = document.getElementById('roles_adicionales');
        const sectoresMultiSelect = document.getElementById('sectores_codigos');
        const wrapperSectorUnico = document.getElementById('wrapper_sector_unico');
        const wrapperSectoresMultiples = document.getElementById('wrapper_sectores_multiples');
        const wrapperAdminLab = document.getElementById('wrapper_admin_lab');
        const adminLabCheckbox = document.getElementById('admin_lab');
        const puedeGestionarOrdenesCheckbox = document.getElementById('puede_gestionar_ordenes');
        const wrapperBandejaSoloInformes = document.getElementById('wrapper_bandeja_solo_informes');
        const bandejaSoloInformesCheckbox = document.getElementById('bandeja_solo_informes');

        if (!form || !rolSelect) return;

        function tieneCoordinadorLabOMediciones() {
            if (['coordinador_lab', 'coordinador_mediciones'].includes(rolSelect.value)) {
                return true;
            }
            if (rolesMultiSelect) {
                return Array.from(rolesMultiSelect.selectedOptions).some(option =>
                    ['coordinador_lab', 'coordinador_mediciones'].includes(option.value)
                );
            }
            return wrapperBandejaSoloInformes?.dataset.coordinadorLabOMedicionesAdicional === '1';
        }

        function toggleBandejaSoloInformes() {
            if (!wrapperBandejaSoloInformes) return;
            if (tieneCoordinadorLabOMediciones()) {
                wrapperBandejaSoloInformes.style.display = 'block';
            } else {
                wrapperBandejaSoloInformes.style.display = 'none';
                if (bandejaSoloInformesCheckbox) bandejaSoloInformesCheckbox.checked = false;
            }
        }

        function tieneCoordinadorLab() {
            if (rolSelect.value === 'coordinador_lab') {
                return true;
            }
            if (rolesMultiSelect) {
                return Array.from(rolesMultiSelect.selectedOptions).some(option => option.value === 'coordinador_lab');
            }
            return wrapperAdminLab?.dataset.coordinadorLabAdicional === '1';
        }

        function toggleAdminLab() {
            if (!wrapperAdminLab) return;
            if (tieneCoordinadorLab()) {
                wrapperAdminLab.style.display = 'block';
            } else {
                wrapperAdminLab.style.display = 'none';
                if (adminLabCheckbox) adminLabCheckbox.checked = false;
                if (puedeGestionarOrdenesCheckbox) puedeGestionarOrdenesCheckbox.checked = false;
            }
        }

        function rolPermiteSector() {
            const rol = rolSelect.value;
            return rol === 'laboratorio' || rol === 'coordinador_lab';
        }

        function toggleSectores() {
            const permite = rolPermiteSector();
            if (permite) {
                if (wrapperSectorUnico) wrapperSectorUnico.style.display = 'none';
                if (wrapperSectoresMultiples) wrapperSectoresMultiples.style.display = 'block';
                if (sectorSelect) sectorSelect.disabled = true;
                if (sectoresMultiSelect) sectoresMultiSelect.disabled = false;
            } else {
                if (wrapperSectorUnico) wrapperSectorUnico.style.display = 'block';
                if (wrapperSectoresMultiples) wrapperSectoresMultiples.style.display = 'none';
                if (sectorSelect) sectorSelect.disabled = true;
                if (sectoresMultiSelect) sectoresMultiSelect.disabled = true;
            }
        }

        function updateMultiSelectStyles(selectElement) {
            if (!selectElement) return;
            const hasOriginalMarkers = Array.from(selectElement.options).some(o => o.hasAttribute('data-original'));
            if (!hasOriginalMarkers) return;

            Array.from(selectElement.options).forEach(option => {
                const isOriginal = option.getAttribute('data-original') === 'true';
                const isSelected = option.selected;
                const cleanText = option.getAttribute('data-clean-text') || option.textContent.replace(/^[✓✗✚\s]+/, '').trim();

                if (!option.getAttribute('data-clean-text')) {
                    option.setAttribute('data-clean-text', cleanText);
                }

                if (isOriginal) {
                    if (isSelected) {
                        option.style.fontWeight = 'bold';
                        option.style.color = '#2e7d32';
                        option.style.backgroundColor = '#e8f5e9';
                        option.style.textDecoration = 'none';
                        option.textContent = '✓ ' + cleanText;
                    } else {
                        option.style.fontWeight = 'bold';
                        option.style.color = '#c62828';
                        option.style.backgroundColor = '#ffebee';
                        option.style.textDecoration = 'line-through';
                        option.textContent = '✗ ' + cleanText + ' (se quitará)';
                    }
                } else {
                    option.style.textDecoration = 'none';
                    if (isSelected) {
                        option.style.fontWeight = 'bold';
                        option.style.color = '#0d47a1';
                        option.style.backgroundColor = '#e3f2fd';
                        option.textContent = '✚ ' + cleanText + ' (nuevo)';
                    } else {
                        option.style.fontWeight = 'normal';
                        option.style.color = '';
                        option.style.backgroundColor = '';
                        option.textContent = cleanText;
                    }
                }
            });
        }

        if (rolesMultiSelect) {
            rolesMultiSelect.addEventListener('change', () => {
                updateMultiSelectStyles(rolesMultiSelect);
                toggleAdminLab();
                toggleBandejaSoloInformes();
            });
            updateMultiSelectStyles(rolesMultiSelect);
        }
        if (sectoresMultiSelect) {
            sectoresMultiSelect.addEventListener('change', () => updateMultiSelectStyles(sectoresMultiSelect));
            updateMultiSelectStyles(sectoresMultiSelect);
        }

        rolSelect.addEventListener('change', () => {
            toggleSectores();
            toggleAdminLab();
            toggleBandejaSoloInformes();
        });
        toggleSectores();
        toggleAdminLab();
        toggleBandejaSoloInformes();

        form.addEventListener('submit', function () {
            if (sectorSelect) sectorSelect.disabled = false;
            if (sectoresMultiSelect) sectoresMultiSelect.disabled = false;
        });
    });
</script>
