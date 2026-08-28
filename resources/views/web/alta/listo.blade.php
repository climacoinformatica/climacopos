@extends('web.base')

@section('titulo', 'Tu salón está listo')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--formulario">
        <div class="texto-centrado">
            <div class="icono-grande">✓</div>
            <h1>{{ $alta['nombre'] }} ya está en marcha</h1>
        </div>

        <div class="tarjeta-credenciales">
            <h2>Tu dirección</h2>

            <p class="direccion-salon">
                <a href="https://{{ $alta['slug'] }}.climacopos.com" target="_blank" rel="noopener">
                    {{ $alta['slug'] }}.climacopos.com
                </a>
            </p>

            <hr>

            <h2>Tus claves</h2>

            <p class="aviso-claves">
                <strong>Apúntalas ahora.</strong> Se guardan cifradas, así que
                no podemos volver a mostrártelas: si las pierdes, habrá que
                generar otras.
            </p>

            <div class="credencial">
                <span>PIN para entrar al programa</span>
                <strong class="credencial__valor">{{ $alta['pin'] }}</strong>
            </div>

            <div class="credencial">
                <span>Contraseña, para acciones delicadas</span>
                <strong class="credencial__valor">{{ $alta['password'] }}</strong>
            </div>
        </div>

        <div class="aviso-saas">
            <h3>Lo que viene ahora</h3>
            <p>
                Al entrar te llevaremos por cuatro pasos: tus datos fiscales,
                el horario, los servicios que ofreces y tu equipo. Cinco
                minutos y el salón queda listo para dar citas.
            </p>
        </div>

        <a href="https://{{ $alta['slug'] }}.climacopos.com/panel"
           class="boton boton--grande boton--marca boton--ancho"
           target="_blank" rel="noopener">
            Entrar en mi salón
        </a>

        <p class="pie-formulario" style="margin-top:1.5rem">
            <a href="{{ route('web.area') }}">Volver a mi cuenta</a>
        </p>
    </div>
</section>

@endsection
