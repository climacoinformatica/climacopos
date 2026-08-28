<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('titulo', 'Software de gestión para tu negocio') · CLIMACO</title>

    <meta name="description" content="@yield('descripcion', 'Software de gestión para hostelería, gimnasios y peluquerías. Hecho en Canarias, con soporte en español.')">

    <link rel="stylesheet" href="{{ asset('css/web.css') }}?v=21">
</head>
<body>

<header class="cabecera">
    <div class="contenedor cabecera__interior">
        <a href="{{ route('web.inicio') }}" class="logotipo">
            CLIMACO
            <small>Informática</small>
        </a>

        <button type="button" class="menu-boton" id="menuBoton" aria-label="Menú">
            <span></span><span></span><span></span>
        </button>

        <nav class="navegacion" id="navegacion">
            <a href="{{ route('web.producto', 'restaurant') }}">Restaurantes</a>
            <a href="{{ route('web.producto', 'gym') }}">Gimnasios</a>
            <a href="{{ route('web.producto', 'beauty') }}">Peluquerías</a>
            <a href="{{ route('web.contacto') }}">Contacto</a>

            @auth('cuenta')
                <a href="{{ route('web.area') }}" class="boton boton--marca">Mi cuenta</a>
            @else
                <a href="{{ route('web.acceso') }}" class="enlace-acceso">Entrar</a>
                <a href="{{ route('web.registro') }}" class="boton boton--marca">Crear cuenta</a>
            @endauth
        </nav>
    </div>
</header>

@if (session('exito') || session('error'))
    <div class="contenedor">
        <p class="mensaje mensaje--{{ session('error') ? 'error' : 'ok' }}">
            {{ session('error') ?? session('exito') }}
        </p>
    </div>
@endif

<main>
    @yield('contenido')
</main>

<footer class="pie">
    <div class="contenedor pie__interior">
        <div class="pie__marca">
            <strong>CLIMACO Informática</strong>
            <p>
                Software de gestión hecho en La Palma, Canarias.
                Soporte en español, de quien lo ha programado.
            </p>
        </div>

        <div class="pie__columna">
            <h4>Soluciones</h4>
            <a href="{{ route('web.producto', 'restaurant') }}">CLIMACO POS Restaurant</a>
            <a href="{{ route('web.producto', 'gym') }}">CLIMACO Gym</a>
            <a href="{{ route('web.producto', 'beauty') }}">CLIMACO POS Beauty</a>
        </div>

        <div class="pie__columna">
            <h4>Cuenta</h4>
            <a href="{{ route('web.registro') }}">Crear cuenta</a>
            <a href="{{ route('web.acceso') }}">Entrar</a>
            <a href="{{ route('web.contacto') }}">Contacto y soporte</a>
        </div>

        <div class="pie__columna">
            <h4>Legal</h4>
            <a href="{{ route('web.legal', 'aviso-legal') }}">Aviso legal</a>
            <a href="{{ route('web.legal', 'privacidad') }}">Privacidad</a>
            <a href="{{ route('web.legal', 'condiciones') }}">Condiciones</a>
        </div>
    </div>

    <div class="contenedor pie__cierre">
        <span>© {{ date('Y') }} Climaco Informática · Jectán Fco. Acosta Sánchez</span>
        <span>La Palma, Islas Canarias</span>
    </div>
</footer>

<script>
// Menú desplegable en móvil
document.getElementById('menuBoton')?.addEventListener('click', function () {
    document.getElementById('navegacion').classList.toggle('navegacion--abierta');
    this.classList.toggle('menu-boton--abierto');
});
</script>

@stack('scripts')

</body>
</html>
