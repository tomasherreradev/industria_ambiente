(function () {
    'use strict';

    var SEL_ROOT = '[data-ucrud-root]';
    var SEL_SEG = '[data-ucrud-segmented]';
    var SEL_ITEM = '.ucrud-segmented__item';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var cache = new Map();
    var enCurso = false;

    function raiz() {
        return document.querySelector(SEL_ROOT);
    }

    function segmented() {
        var root = raiz();
        return root ? root.querySelector(SEL_SEG) : null;
    }

    function items() {
        var seg = segmented();
        return seg ? Array.prototype.slice.call(seg.querySelectorAll(SEL_ITEM)) : [];
    }

    function colocarPill(animar) {
        var seg = segmented();
        if (!seg) {
            return;
        }

        var pill = seg.querySelector('.ucrud-segmented__pill');
        var activo = seg.querySelector(SEL_ITEM + '.is-active') || seg.querySelector(SEL_ITEM);
        if (!pill || !activo) {
            return;
        }

        var base = seg.getBoundingClientRect();
        var destino = activo.getBoundingClientRect();

        if (!animar) {
            pill.classList.add('is-static');
        }

        pill.style.width = destino.width + 'px';
        pill.style.height = destino.height + 'px';
        pill.style.transform = 'translate(' + (destino.left - base.left) + 'px, ' + (destino.top - base.top) + 'px)';

        if (!animar) {
            // Reflow para que la posición inicial no dispare la transición.
            void pill.offsetWidth;
            pill.classList.remove('is-static');
        }
    }

    /**
     * Marca el destino como activo y desliza la píldora antes de que llegue la
     * respuesta, para que el toggle responda al instante.
     */
    function anticiparSeleccion(url) {
        var destino = items().filter(function (item) {
            return item.href === url;
        })[0];

        if (!destino || destino.classList.contains('is-active')) {
            return;
        }

        items().forEach(function (item) {
            item.classList.toggle('is-active', item === destino);
            if (item === destino) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        colocarPill(!reduceMotion.matches);
    }

    /**
     * La precarga guarda la promesa y el click la consume, así nunca se sirve
     * un listado viejo tras crear, editar o eliminar un registro.
     */
    function pedirHtml(url, consumir) {
        var pendiente = cache.get(url);

        if (!pendiente) {
            pendiente = fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
                .then(function (respuesta) {
                    if (!respuesta.ok) {
                        throw new Error('HTTP ' + respuesta.status);
                    }
                    return respuesta.text();
                });

            cache.set(url, pendiente);
            pendiente.catch(function () {
                cache.delete(url);
            });
        }

        if (consumir) {
            cache.delete(url);
        }

        return pendiente;
    }

    function pintar(html, url) {
        var documento = new DOMParser().parseFromString(html, 'text/html');
        var nuevo = documento.querySelector(SEL_ROOT);
        var actual = raiz();

        if (!nuevo || !actual) {
            window.location.href = url;
            return;
        }

        actual.innerHTML = nuevo.innerHTML;

        if (documento.title) {
            document.title = documento.title;
        }

        colocarPill(false);
    }

    function navegar(url, guardarHistorial) {
        if (enCurso) {
            return;
        }

        enCurso = true;
        var root = raiz();
        if (root) {
            root.classList.add('is-cargando');
        }

        anticiparSeleccion(url);

        pedirHtml(url, true)
            .then(function (html) {
                if (window.scrollY > 0) {
                    window.scrollTo(0, 0);
                }

                var aplicar = function () {
                    pintar(html, url);
                    if (guardarHistorial) {
                        window.history.pushState({ ucrud: true }, '', url);
                    }
                };

                if (typeof document.startViewTransition === 'function' && !reduceMotion.matches) {
                    return document.startViewTransition(aplicar).finished.catch(function () {});
                }

                aplicar();
            })
            .catch(function () {
                window.location.href = url;
            })
            .then(function () {
                enCurso = false;
                var actualizada = raiz();
                if (actualizada) {
                    actualizada.classList.remove('is-cargando');
                }
            });
    }

    document.addEventListener('click', function (evento) {
        if (evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
            return;
        }

        var destino = evento.target.closest ? evento.target.closest(SEL_ITEM) : null;
        if (!destino || !destino.closest(SEL_ROOT)) {
            return;
        }

        evento.preventDefault();

        if (destino.classList.contains('is-active')) {
            return;
        }

        navegar(destino.href, true);
    });

    // Precarga al pasar el mouse: la respuesta suele estar lista antes del click.
    document.addEventListener('mouseover', function (evento) {
        var destino = evento.target.closest ? evento.target.closest(SEL_ITEM) : null;
        if (!destino || destino.classList.contains('is-active') || cache.has(destino.href)) {
            return;
        }

        pedirHtml(destino.href, false).catch(function () {});
    });

    // Marca la entrada actual para poder reconstruirla al volver atrás.
    window.history.replaceState({ ucrud: true }, '', window.location.href);

    window.addEventListener('popstate', function (evento) {
        if (!evento.state || !evento.state.ucrud) {
            return;
        }

        navegar(window.location.href, false);
    });

    window.addEventListener('resize', function () {
        colocarPill(false);
    });

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            colocarPill(false);
        });
    }

    colocarPill(false);
})();
