@extends('panel.app')

@section('titulo', 'Catálogo')

@section('contenido')

<div class="titulo-pagina">
    <div>
        <h1>Catálogo</h1>
        <p>{{ $articulos->total() }} artículo(s)</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="{{ route('panel.catalogo.familias') }}" class="boton boton--secundario">Familias</a>
        <a href="{{ route('panel.catalogo.articulos.crear', ['tipo' => 'SERVICIO']) }}" class="boton">Nuevo servicio</a>
        <a href="{{ route('panel.catalogo.articulos.crear', ['tipo' => 'PRODUCTO']) }}" class="boton boton--secundario">Nuevo producto</a>
    </div>
</div>

<form method="GET" class="filtros">
    <div class="campo">
        <label for="buscar">Buscar</label>
        <input type="text" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}"
               placeholder="Nombre, código, barras...">
    </div>
    <div class="campo">
        <label for="familia">Familia</label>
        <select id="familia" name="familia">
            <option value="">Todas</option>
            @foreach ($familias as $familia)
                <option value="{{ $familia->id }}" @selected(($filtros['familia'] ?? null) == $familia->id)>
                    {{ $familia->nombre }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="campo">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            @foreach (['SERVICIO' => 'Servicios', 'PRODUCTO' => 'Productos', 'BONO' => 'Bonos', 'PACK' => 'Packs'] as $clave => $texto)
                <option value="{{ $clave }}" @selected(($filtros['tipo'] ?? null) === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    <div class="casilla" style="margin-bottom:.5rem">
        <input type="checkbox" id="inactivos" name="inactivos" value="1" @checked($filtros['inactivos'] ?? false)>
        <label for="inactivos">Ver inactivos</label>
    </div>
    <button type="submit" class="boton boton--secundario">Filtrar</button>
</form>

@if ($articulos->isEmpty())
    <div class="vacio">
        <h3>No hay artículos que mostrar</h3>
        <p>Ajusta los filtros o crea el primero.</p>
        <a href="{{ route('panel.catalogo.articulos.crear') }}" class="boton">Nuevo artículo</a>
    </div>
@else
    <div class="tarjeta" style="padding:.5rem">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th style="width:56px"></th>
                        <th>Nombre</th>
                        <th>Familia</th>
                        <th>Tipo</th>
                        <th class="num">Duración</th>
                        <th class="num">Precio</th>
                        <th class="num">Base</th>
                        <th class="num">Stock</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($articulos as $articulo)
                    <tr>
                        <td>
                            @if ($url = $articulo->urlFoto())
                                <img src="{{ $url }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:7px">
                            @else
                                <span style="display:block;width:40px;height:40px;border-radius:7px;
                                             background:{{ $articulo->color ?? $articulo->familia->color }}22"></span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('panel.catalogo.articulos.editar', $articulo) }}"
                               style="text-decoration:none;font-weight:500">{{ $articulo->nombre }}</a>
                            @unless ($articulo->activo)
                                <span class="etiqueta etiqueta--inactivo">Inactivo</span>
                            @endunless
                            @if ($articulo->codigo)
                                <div style="color:var(--suave);font-size:.72rem">{{ $articulo->codigo }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="punto-color" style="background:{{ $articulo->familia->color }}"></span>
                            {{ $articulo->familia->nombre }}
                        </td>
                        <td>
                            <span class="etiqueta etiqueta--{{ strtolower($articulo->tipo) }}">
                                {{ ucfirst(strtolower($articulo->tipo)) }}
                            </span>
                        </td>
                        <td class="num">
                            @if ($articulo->esServicio())
                                {{ $articulo->duracion_min }} min
                                @if ($articulo->tienePausa())
                                    <div style="color:var(--suave);font-size:.7rem">
                                        +{{ $articulo->tiempo_pausa_min }}' pausa
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">{{ number_format($articulo->precio, 2, ',', '.') }} €</td>
                        <td class="num" style="color:var(--suave);font-size:.8rem">
                            {{ number_format($articulo->baseImponible(), 2, ',', '.') }}
                            <div style="font-size:.7rem">{{ rtrim(rtrim(number_format($articulo->impuesto_pct, 2, ',', '.'), '0'), ',') }}%</div>
                        </td>
                        <td class="num">
                            @if ($articulo->control_stock)
                                <span @style(['color: var(--error)' => $articulo->stock <= $articulo->stock_min])>
                                    {{ rtrim(rtrim(number_format($articulo->stock, 3, ',', '.'), '0'), ',') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('panel.catalogo.articulos.editar', $articulo) }}"
                               class="boton boton--secundario boton--pequeno">Editar</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $articulos->links() }}
@endif

@endsection
