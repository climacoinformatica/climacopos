@extends('admin.base')

@section('titulo', 'Acceso')

@section('contenido')
<div class="acceso-admin">
    <h1>CLIMACO POS</h1>
    <p class="acceso-admin__sub">Panel de administración de la plataforma</p>

    <form method="POST" action="{{ route('admin.acceso.entrar') }}">
        @csrf

        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus
                   autocomplete="username" value="{{ old('email') }}">
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password">
        </div>

        <button type="submit" class="boton boton--ancho">Entrar</button>
    </form>

    <p class="campo__pista" style="text-align:center;margin-top:1.5rem">
        Si es la primera vez, crea el administrador con:<br>
        <code>php artisan climacopos:crear-superadmin</code>
    </p>
</div>
@endsection
