# Muestreo App — Industria y Ambiente

Aplicación web para la gestión integral del laboratorio: cotizaciones y ventas, órdenes de trabajo, muestreo en campo, análisis, informes y facturación.

**Stack:** Laravel, Blade, Bootstrap 5, assets en `public/css` y `public/js`, FullCalendar y Chart.js en módulos puntuales.

---

## Puesta en marcha (resumen)

1. Copiar `.env.example` → `.env` y configurar base de datos y correo.
2. `composer install` · `php artisan key:generate` · `php artisan migrate`
3. `npm install` · `npm run dev` (o `npm run build` en producción)
4. `php artisan serve`

Documentación de uso para usuarios finales: `docs/MANUAL_USUARIO.md`.

---

## Registro de cambios (por commit)

Resumen generalizado de lo incorporado en cada commit reciente. **Convención:** al hacer un push relevante, agregar una entrada nueva arriba de esta lista (fecha, hash corto, qué cambió para el negocio o la UX).

### Pendiente de publicar (working tree)

- **Facturación:** flujo de revisión previa a facturar, permiso `puede_autorizar_facturacion`, referencias editables en pantalla y migraciones (`facturacion_aprobada`, `listo_para_firmar`, condiciones de pago normalizadas).
- **Dashboards:** vistas admin / análisis / muestreo con estilos y gráficos de segmentos (`dashboard-home`, `dashboard-sub`, Chart.js).
- **Ventas y cotización:** CSS modular, mejoras en modals/scripts/PDF e índice de ventas.
- **Operación en campo y lab:** mediciones, formularios compactos de resultados en OT, modal de edición de análisis, variables de medición en instancias.
- **Mis muestras / Mis órdenes (móvil):** filtros en acordeón; calendario con selector Mes/Semana/Día; toques en mes que abren el día o la tarea (sin tooltips que bloqueen en touch).
- **Login:** footer anclado al borde inferior de la pantalla.
- **Usuarios y perfil:** formularios parciales reutilizables, ayuda/seguridad/perfil, resumen de perfil en backend.
- **Layout:** topbar/sidebar y rutas asociadas a facturación-revisión y mediciones.

---

### `f2f4aab` — login nuevo (2026-09-16)

- Nueva pantalla de inicio de sesión a pantalla completa con imagen topográfica, columna de marca y tarjeta de acceso.
- Estilos dedicados en `public/css/login.css` y layout `layouts/login.blade.php`.

### `70824e1` — rediseño de vistas (2026-09-15)

- Unificación visual tipo **uCRUD** (listados, filtros, paneles) en módulos operativos.
- CSS compartido (`usuarios-crud`, `operativo`, sidebar/topbar) y partials de estilos reutilizables.

### `fd06477` — cambios actualizaciones varias (2026-08-24)

- Ajustes transversales en controladores, vistas y soporte de dominio acumulados entre releases.

### `be1a4cf` — 08/05 (2026-05-08)

- Mejoras y correcciones puntuales de mayo (detalle en diff del commit).

### `56c356f` — hoy (2026-03-02)

- Iteración rápida de fixes y pequeñas funcionalidades.

### `340ae49` — last (2026-03-01)

- Cierre de tareas pendientes de la iteración anterior.

### `9de3904` — actualización (2026-01-27)

- Actualización general de dependencias y comportamiento.

### `486bb22` — update 02/12 (2025-12-02)

- Cambios de fin de año en flujos de laboratorio y vistas.

### `74e14fa` — bug solved (2025-08-29)

- Corrección de defectos reportados en operación diaria.

### `06dbedc` — rol ventas, cotizaciones y clientes (2025-08-28)

- Rol de ventas, alta de cotizaciones y ABM de clientes integrado al flujo comercial.

### `7de63f5` — dashboard y responsables (2025-08-27)

- Dashboard operativo y reasignación de responsables en muestras/OT.

### `96efc7a` — mejoras post-testing (2025-08-07)

- Bugs y mejoras derivadas del informe de pruebas de usuario.

---

## Commits anteriores

Para el historial completo:

```bash
git log --oneline
```

---

## Licencia / propiedad

Uso interno — InitSoluciones S.R.L. / Industria y Ambiente S.A. Consultar con el equipo antes de redistribuir.
