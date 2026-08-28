@extends('panel.app')

@section('titulo', 'Familias')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Familias</h1>
        <p>Agrupan los servicios y productos del catálogo</p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="{{ route('panel.catalogo.articulos') }}" class="boton boton--secundario">Artículos</a>
        <a href="{{ route('panel.catalogo.familias.crear') }}" class="boton">Nueva familia</a>
    </div>
</div>

@if ($familias->isEmpty())
    <div class="vacio">
        <h3>Todavía no hay familias</h3>
        <p>Las familias organizan el catálogo y determinan el orden en que aparecen<br>
           los botones en el punto de venta.</p>
        <a href="{{ route('panel.catalogo.familias.crear') }}" class="boton">Crear la primera</a>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th class="num">Artículos</th>
                        <th class="num">Orden</th>
                        <th>Portal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($familias as $familia)
                    <tr>
                        <td>
                            <span class="punto-color" style="background:{{ $familia->color }}"></span>
                            @if ($familia->padre)
                                <span style="color:var(--suave)">{{ $familia->padre->nombre }} ›</span>
                            @endif
                            <strong>{{ $familia->nombre }}</strong>
                            @unless ($familia->activa)
                                <span class="etiqueta etiqueta--inactivo">Inactiva</span>
                            @endunless
                        </td>
                        <td>
                            <span class="etiqueta">
                                {{ ['SERVICIO' => 'Servicios', 'PRODUCTO' => 'Productos', 'AMBOS' => 'Ambos'][$familia->tipo] }}
                            </span>
                        </td>
                        <td class="num">{{ $familia->articulos_count }}</td>
                        <td class="num">{{ $familia->orden }}</td>
                        <td>{{ $familia->visible_online ? 'Visible' : 'Oculta' }}</td>
                        <td>
                            <a href="{{ route('panel.catalogo.familias.editar', $familia) }}"
                               class="boton boton--secundario boton--pequeno">Editar</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
