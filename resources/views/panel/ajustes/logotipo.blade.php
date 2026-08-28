{{-- Sección de logotipo, para incluir en la pantalla de Ajustes --}}

<div class="tarjeta" style="max-width:600px">
    <h2>Logotipo</h2>
    <p class="tarjeta__ayuda">
        Aparece en la cabecera del panel y en la página de reservas que ven
        tus clientas. Si no subes ninguno, se usa el de CLIMACO POS.
    </p>

    <div class="logo-actual">
        <img src="{{ logo_salon() }}" alt="Logotipo" class="logo-actual__imagen">

        <div class="logo-actual__pie">
            @if (logo_es_propio())
                <span class="etiqueta">El tuyo</span>
            @else
                <span class="etiqueta etiqueta--inactivo">CLIMACO POS</span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('panel.logotipo.subir') }}"
          enctype="multipart/form-data">
        @csrf

        <div class="campo">
            <label for="logo">Cambiar logotipo</label>
            <input type="file" id="logo" name="logo" required
                   accept="image/png,image/jpeg,image/webp,image/gif">
            <p class="campo__pista">
                JPG, PNG, WEBP o GIF, hasta 4 MB. Se ajusta solo al tamaño
                que hace falta.
                <strong>Un PNG con fondo transparente queda mejor</strong>,
                porque se ve sobre fondo claro y oscuro.
            </p>
        </div>

        <button type="submit" class="boton boton--pequeno">Subir</button>
    </form>

    @if (logo_es_propio())
        <form method="POST" action="{{ route('panel.logotipo.borrar') }}"
              onsubmit="return confirm('¿Quitar tu logotipo? Volverá a verse el de CLIMACO POS.')"
              style="margin-top:1rem">
            @csrf
            @method('DELETE')
            <button type="submit" class="boton boton--secundario boton--pequeno">
                Quitar mi logotipo
            </button>
        </form>
    @endif
</div>
