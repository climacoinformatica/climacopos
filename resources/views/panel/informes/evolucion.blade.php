<div class="tarjeta">
    <div class="tarjeta__cabecera">
        <h2>Por día</h2>
        <a href="{{ route('panel.informes.exportar', request()->query() + ['que' => 'por_dia']) }}"
           class="boton boton--secundario boton--pequeno">Exportar CSV</a>
    </div>
    @include('panel.informes.partes.barras', ['datos' => $datos['por_dia'], 'campo' => 'total', 'etiqueta' => 'etiqueta'])
</div>

<div class="informes-columnas">
    <div class="tarjeta">
        <h2>Por hora del día</h2>
        <p class="tarjeta__ayuda">Dónde se concentra el trabajo. Útil para decidir turnos.</p>
        @include('panel.informes.partes.barras', ['datos' => $datos['por_hora'], 'campo' => 'total', 'etiqueta' => 'etiqueta'])
    </div>

    <div class="tarjeta">
        <h2>Por día de la semana</h2>
        @include('panel.informes.partes.barras', ['datos' => $datos['por_dia_semana'], 'campo' => 'total', 'etiqueta' => 'etiqueta'])
    </div>
</div>
