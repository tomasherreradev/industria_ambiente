@extends('layouts.app')

@section('title', 'Centro de ayuda')

@php
    $codigoUsuario = trim((string) $user->usu_codigo);
    $mailtoSoporte = 'mailto:'.$soporteEmail
        .'?subject='.rawurlencode('Soporte — '.$codigoUsuario)
        .'&body='.rawurlencode("Hola,\n\nUsuario: {$codigoUsuario}\nNombre: {$user->usu_descripcion}\n\nConsulta:\n");
@endphp

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud ucrud-perfil" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">Centro de ayuda</h1>
            <p class="ucrud-subtitle">
                {{ $user->usu_descripcion }}
                <span class="text-muted">·</span>
                <span class="ucrud-role-chip ucrud-role-chip--extra">{{ $codigoUsuario }}</span>
            </p>
        </div>
        <div class="ucrud-header__actions">
            <a href="{{ route('auth.show', $codigoUsuario) }}" class="ucrud-btn ucrud-btn--ghost">
                <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" />
                Volver al perfil
            </a>
        </div>
    </header>

    <div class="ucrud-alert ucrud-alert--success mb-3">
        <x-heroicon-o-information-circle style="width: 18px; height: 18px;" />
        <span>Respuestas rápidas sobre tu cuenta y cómo contactar al equipo de soporte.</span>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="ucrud-panel h-100">
                <div class="ucrud-detail-block mb-3">
                    <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                        <x-heroicon-o-question-mark-circle style="width: 18px; height: 18px;" />
                        Preguntas frecuentes
                    </h2>
                </div>

                <div class="ucrud-accordion" id="ayudaFaq">
                    <div class="ucrud-accordion__item">
                        <div class="ucrud-accordion__header" id="faqHeadingPassword">
                            <button class="ucrud-accordion__toggle w-100" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#faqPassword"
                                    aria-expanded="true"
                                    aria-controls="faqPassword">
                                <x-heroicon-o-lock-closed style="width: 18px; height: 18px; flex-shrink: 0;" />
                                ¿Cómo cambio mi contraseña?
                            </button>
                        </div>
                        <div id="faqPassword" class="collapse show ucrud-accordion__body"
                             aria-labelledby="faqHeadingPassword" data-bs-parent="#ayudaFaq">
                            <div class="p-3 text-muted" style="font-size: 0.9375rem; line-height: 1.55;">
                                <ol class="mb-3 ps-3">
                                    <li>Abrí <strong>Seguridad y contraseña</strong> desde el menú de tu usuario o el perfil.</li>
                                    <li>Ingresá la contraseña actual.</li>
                                    <li>Escribí y confirmá la nueva contraseña (mínimo 4 caracteres).</li>
                                    <li>Pulsá <strong>Actualizar contraseña</strong>.</li>
                                </ol>
                                <a href="{{ route('auth.security', ['id' => $codigoUsuario]) }}" class="ucrud-btn ucrud-btn--primary">
                                    <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" />
                                    Ir a seguridad
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="ucrud-accordion__item">
                        <div class="ucrud-accordion__header" id="faqHeadingSession">
                            <button class="ucrud-accordion__toggle w-100" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#faqSession"
                                    aria-expanded="false"
                                    aria-controls="faqSession">
                                <x-heroicon-o-arrow-right-on-rectangle style="width: 18px; height: 18px; flex-shrink: 0;" />
                                ¿Por qué se cerró mi sesión sola?
                            </button>
                        </div>
                        <div id="faqSession" class="collapse ucrud-accordion__body"
                             aria-labelledby="faqHeadingSession" data-bs-parent="#ayudaFaq">
                            <div class="p-3 text-muted" style="font-size: 0.9375rem; line-height: 1.55;">
                                <p class="mb-0">
                                    El sistema permite <strong>una sola sesión activa</strong> por usuario. Si iniciás sesión en otro navegador o dispositivo,
                                    la sesión anterior se cierra. Podés revisar el estado en
                                    <a href="{{ route('auth.security', ['id' => $codigoUsuario]) }}">Seguridad y contraseña</a>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="ucrud-accordion__item">
                        <div class="ucrud-accordion__header" id="faqHeadingProfile">
                            <button class="ucrud-accordion__toggle w-100" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#faqProfile"
                                    aria-expanded="false"
                                    aria-controls="faqProfile">
                                <x-heroicon-o-user-circle style="width: 18px; height: 18px; flex-shrink: 0;" />
                                ¿Cómo actualizo mis datos de perfil?
                            </button>
                        </div>
                        <div id="faqProfile" class="collapse ucrud-accordion__body"
                             aria-labelledby="faqHeadingProfile" data-bs-parent="#ayudaFaq">
                            <div class="p-3 text-muted" style="font-size: 0.9375rem; line-height: 1.55;">
                                <p class="mb-3">
                                    Desde tu perfil podés ver código, roles y estado. Los cambios de nombre (y otros datos administrativos, si aplican)
                                    se hacen en <strong>Editar perfil</strong>.
                                </p>
                                <a href="{{ route('auth.edit', $codigoUsuario) }}" class="ucrud-btn ucrud-btn--ghost">
                                    <x-heroicon-o-pencil-square style="width: 16px; height: 16px;" />
                                    Editar perfil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ucrud-panel h-100">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                        <x-heroicon-o-paper-airplane style="width: 18px; height: 18px;" />
                        Contacto con soporte
                    </h2>
                    <p class="text-muted mt-2 mb-3" style="font-size: 0.9375rem; line-height: 1.55;">
                        Para problemas técnicos, consultas sobre el uso del sistema o reportes de error, escribinos por correo.
                        Incluí tu usuario, qué pantalla estabas usando y el mayor detalle posible.
                    </p>

                    <div class="ucrud-detail-grid mb-3">
                        <div>
                            <div class="ucrud-detail-item__label">Correo de soporte</div>
                            <div class="ucrud-detail-item__value">
                                <a href="{{ $mailtoSoporte }}">{{ $soporteEmail }}</a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ $mailtoSoporte }}" class="ucrud-btn ucrud-btn--primary">
                            <x-heroicon-o-paper-airplane style="width: 16px; height: 16px;" />
                            Enviar correo
                        </a>
                        <button type="button" class="ucrud-btn ucrud-btn--ghost" id="copySupportEmail" data-email="{{ $soporteEmail }}">
                            <x-heroicon-o-clipboard-document style="width: 16px; height: 16px;" />
                            Copiar correo
                        </button>
                    </div>
                    <p class="small text-muted mb-0 mt-3" id="copySupportEmailFeedback" role="status" aria-live="polite"></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('copySupportEmail');
        const feedback = document.getElementById('copySupportEmailFeedback');
        if (!btn || !feedback) return;

        btn.addEventListener('click', async function () {
            const email = btn.getAttribute('data-email') || '';
            try {
                await navigator.clipboard.writeText(email);
                feedback.textContent = 'Correo copiado al portapapeles.';
            } catch (e) {
                feedback.textContent = 'No se pudo copiar. Usá el enlace «Enviar correo».';
            }
        });
    });
</script>
@endpush
