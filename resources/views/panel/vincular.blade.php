@extends('panel.base')

@section('titulo', 'Vincular equipo')

@section('contenido')
<div class="formulario">
    <h1 style="text-align:center;margin-bottom:.5rem;font-size:1.3rem">Vincular este equipo</h1>
    <p style="text-align:center;color:var(--suave);font-size:.85rem;margin-bottom:1.5rem">
        {{ $empresa->nombre_comercial }}<br>
        Solo hay que hacerlo una vez por dispositivo.
    </p>

    @if ($errors->any())
        <p class="aviso aviso--error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('panel.terminal.vincular.post') }}">
        @csrf

        <div class="campo">
            <label for="email">Email de un responsable</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   required autocomplete="username">
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   required autocomplete="current-password">
        </div>

        <div class="campo">
            <label for="terminal_id">Terminal</label>
            <select id="terminal_id" name="terminal_id">
                <option value="">— Crear uno nuevo —</option>
                @foreach ($terminales as $terminal)
                    <option value="{{ $terminal->id }}" @selected(old('terminal_id') == $terminal->id)>
                        {{ $terminal->nombre }} ({{ $terminal->codigo }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="campo">
            <label for="nombre_nuevo">Nombre del terminal nuevo</label>
            <input type="text" id="nombre_nuevo" name="nombre_nuevo"
                   value="{{ old('nombre_nuevo') }}" placeholder="Mostrador, Cabina 2...">
        </div>

        <button type="submit" class="boton">Vincular equipo</button>
    </form>

    <p style="color:var(--suave);font-size:.75rem;margin-top:1.5rem;text-align:center;line-height:1.5">
        Este equipo quedará autorizado durante un año.
        Después, los empleados entrarán solo con su PIN.
    </p>
</div>
@endsection
