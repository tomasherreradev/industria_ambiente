@php
    $notasCatalogoDisponibles = $notasCatalogoDisponibles ?? collect();
    $notasCatalogoSeleccionadas = $notasCatalogoSeleccionadas ?? collect();
    $notasCatalogoJson = $notasCatalogoDisponibles->map(function ($nota) {
        return [
            'id' => $nota->id,
            'titulo' => trim((string) ($nota->titulo ?? '')),
            'contenido' => trim((string) ($nota->contenido ?? '')),
        ];
    })->values();
@endphp

<div class="mb-4">
    <label class="form-label fw-bold">Notas guardadas</label>
    <p class="small text-muted mb-2">
        Agregá notas del catálogo. En el PDF aparecerán después de las notas automáticas de las determinaciones,
        en el orden en que las agregues aquí.
    </p>

    @if($notasCatalogoDisponibles->isEmpty())
        <div class="alert alert-info mb-0">
            No hay notas activas en el catálogo.
            <a href="{{ route('informes.notas.index') }}" target="_blank" rel="noopener">Crear notas reutilizables</a>
        </div>
    @else
        <script type="application/json" id="notasCatalogoData">@json($notasCatalogoJson)</script>
        <div class="mb-3" style="max-width: 640px;">
            <label class="form-label small text-muted mb-1" for="notaCatalogoBuscar">Buscar nota</label>
            <div class="input-group mb-2">
                <span class="input-group-text">
                    <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" />
                </span>
                <input type="search"
                       class="form-control"
                       id="notaCatalogoBuscar"
                       placeholder="Buscar por título o descripción..."
                       autocomplete="off">
            </div>
            <div class="list-group border rounded mb-2" id="notaCatalogoResultados" style="max-height: 220px; overflow-y: auto;"></div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-outline-primary" id="btnAgregarNotaCatalogo">
                    <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                    Agregar seleccionada
                </button>
                <a href="{{ route('informes.notas.index') }}" target="_blank" rel="noopener" class="btn btn-link btn-sm">
                    Gestionar catálogo
                </a>
            </div>
            <p class="small text-muted mb-0 mt-1" id="notaCatalogoBuscarMsg"></p>
        </div>
    @endif

    <ul class="list-group list-group-flush border rounded informe-notas-lista" id="notasCatalogoSeleccionadas">
        @foreach($notasCatalogoSeleccionadas as $notaSel)
            @include('informes.partials.protocolo-nota-catalogo-item', ['nota' => $notaSel])
        @endforeach
    </ul>
    <p class="small text-muted mt-2 mb-0 @if($notasCatalogoSeleccionadas->isNotEmpty()) d-none @endif" id="notasCatalogoVacioMsg">
        Todavía no agregaste notas del catálogo a este informe.
    </p>
</div>

<template id="tplNotaCatalogoItem">
    @include('informes.partials.protocolo-nota-catalogo-item', [
        'nota' => (object) ['id' => '__ID__', 'titulo' => '__TITULO__', 'contenido' => '__CONTENIDO__'],
    ])
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lista = document.getElementById('notasCatalogoSeleccionadas');
    const resultados = document.getElementById('notaCatalogoResultados');
    const dataEl = document.getElementById('notasCatalogoData');
    const buscar = document.getElementById('notaCatalogoBuscar');
    const buscarMsg = document.getElementById('notaCatalogoBuscarMsg');
    const btnAgregar = document.getElementById('btnAgregarNotaCatalogo');
    const msgVacio = document.getElementById('notasCatalogoVacioMsg');
    const tpl = document.getElementById('tplNotaCatalogoItem');

    if (!lista || !resultados || !dataEl || !btnAgregar || !tpl) {
        return;
    }

    const notasCatalogo = JSON.parse(dataEl.textContent || '[]');
    let notaSeleccionadaId = null;

    function normalizarTexto(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function notaCoincide(nota, q) {
        if (q === '') {
            return true;
        }
        const titulo = normalizarTexto(nota.titulo);
        const contenido = normalizarTexto(nota.contenido);
        return titulo.includes(q) || contenido.includes(q);
    }

    function etiquetaNota(nota) {
        if (nota.titulo) {
            return nota.titulo;
        }
        const texto = nota.contenido || '';
        return texto.length > 80 ? texto.slice(0, 80) + '…' : texto;
    }

    function resumenContenido(nota) {
        const texto = nota.contenido || '';
        if (!texto) {
            return '';
        }
        return texto.length > 120 ? texto.slice(0, 120) + '…' : texto;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function notasFiltradas() {
        const q = normalizarTexto(buscar ? buscar.value.trim() : '');
        return notasCatalogo.filter(function (nota) {
            return notaCoincide(nota, q);
        });
    }

    function renderResultados() {
        const filtradas = notasFiltradas();
        const q = normalizarTexto(buscar ? buscar.value.trim() : '');

        if (notaSeleccionadaId && !filtradas.some(function (n) { return String(n.id) === String(notaSeleccionadaId); })) {
            notaSeleccionadaId = null;
        }

        if (filtradas.length === 0) {
            resultados.innerHTML = '<div class="list-group-item text-muted small py-3">No hay notas que coincidan con la búsqueda.</div>';
        } else {
            resultados.innerHTML = filtradas.map(function (nota) {
                const activa = String(nota.id) === String(notaSeleccionadaId);
                const titulo = escapeHtml(etiquetaNota(nota));
                const descripcion = escapeHtml(resumenContenido(nota));
                return '<button type="button" class="list-group-item list-group-item-action text-start' + (activa ? ' active' : '') + '" data-pick-id="' + nota.id + '">' +
                    '<div class="fw-semibold">' + titulo + '</div>' +
                    (descripcion ? '<div class="small' + (activa ? '' : ' text-muted') + '">' + descripcion + '</div>' : '') +
                    '</button>';
            }).join('');
        }

        if (buscarMsg) {
            if (q === '') {
                buscarMsg.textContent = filtradas.length + (filtradas.length === 1 ? ' nota disponible.' : ' notas disponibles.');
            } else if (filtradas.length === 0) {
                buscarMsg.textContent = 'Sin coincidencias en título ni descripción.';
            } else if (filtradas.length === 1) {
                buscarMsg.textContent = '1 nota encontrada.';
            } else {
                buscarMsg.textContent = filtradas.length + ' notas encontradas.';
            }
        }
    }

    function idsEnLista() {
        return Array.from(lista.querySelectorAll('[data-nota-id]')).map(function (li) {
            return li.getAttribute('data-nota-id');
        });
    }

    function actualizarMsgVacio() {
        if (!msgVacio) {
            return;
        }
        msgVacio.classList.toggle('d-none', lista.querySelectorAll('[data-nota-id]').length > 0);
    }

    function crearItem(id, titulo, contenido) {
        let html = tpl.innerHTML
            .replace(/__ID__/g, String(id))
            .replace(/__TITULO__/g, escapeHtml(titulo))
            .replace(/__CONTENIDO__/g, escapeHtml(contenido));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        return wrap.firstElementChild;
    }

    function agregarNotaPorId(id) {
        const nota = notasCatalogo.find(function (n) {
            return String(n.id) === String(id);
        });
        if (!nota) {
            return;
        }
        if (idsEnLista().includes(String(nota.id))) {
            Swal.fire({
                icon: 'info',
                title: 'Nota ya agregada',
                text: 'Esta nota ya está en la lista de este informe.',
                confirmButtonColor: '#3085d6',
            });
            return;
        }
        lista.appendChild(crearItem(nota.id, nota.titulo, nota.contenido));
        notaSeleccionadaId = null;
        if (buscar) {
            buscar.value = '';
        }
        renderResultados();
        actualizarMsgVacio();
    }

    if (buscar) {
        buscar.addEventListener('input', renderResultados);
        buscar.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            const filtradas = notasFiltradas();
            if (filtradas.length === 0) {
                return;
            }
            const id = notaSeleccionadaId || filtradas[0].id;
            agregarNotaPorId(id);
        });
    }

    resultados.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-pick-id]');
        if (!btn) {
            return;
        }
        notaSeleccionadaId = btn.getAttribute('data-pick-id');
        renderResultados();
    });

    resultados.addEventListener('dblclick', function (e) {
        const btn = e.target.closest('[data-pick-id]');
        if (!btn) {
            return;
        }
        agregarNotaPorId(btn.getAttribute('data-pick-id'));
    });

    btnAgregar.addEventListener('click', function () {
        if (!notaSeleccionadaId) {
            Swal.fire({
                icon: 'info',
                title: 'Elegí una nota',
                text: 'Seleccioná una nota de la lista antes de agregar.',
                confirmButtonColor: '#3085d6',
            });
            return;
        }
        agregarNotaPorId(notaSeleccionadaId);
    });

    lista.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) {
            return;
        }
        const li = btn.closest('[data-nota-id]');
        if (!li) {
            return;
        }
        const action = btn.getAttribute('data-action');
        if (action === 'quitar') {
            li.remove();
            actualizarMsgVacio();
            return;
        }
        if (action === 'subir') {
            const prev = li.previousElementSibling;
            if (prev) {
                lista.insertBefore(li, prev);
            }
            return;
        }
        if (action === 'bajar') {
            const next = li.nextElementSibling;
            if (next) {
                lista.insertBefore(next, li);
            }
        }
    });

    renderResultados();
    actualizarMsgVacio();
});
</script>
@endpush
