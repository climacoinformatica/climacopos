{{--
    Aviso de facturas del plan.

    Se incluye en el layout del panel para que salga en cualquier
    pantalla: si solo apareciera en Suscripcion, nadie lo veria hasta que
    fuera tarde.
--}}

@php $avisoFacturas = \App\Support\LimitesPlan::avisoFacturas(); @endphp

@if ($avisoFacturas)
    <div @class([
        'banda-facturas',
        'banda-facturas--grave' => $avisoFacturas['grave'],
    ])>
        @if ($avisoFacturas['agotado'])
            <strong>Has llegado a las {{ $avisoFacturas['maximo'] }} facturas de tu plan.</strong>
            Puedes seguir cobrando con normalidad, pero el resto del programa
            está limitado hasta el mes que viene.
        @else
            Llevas <strong>{{ $avisoFacturas['usadas'] }} de {{ $avisoFacturas['maximo'] }}</strong>
            facturas este mes.
            @if ($avisoFacturas['grave'])
                Te quedan {{ $avisoFacturas['quedan'] }}.
            @endif
        @endif

        <a href="{{ route('panel.suscripcion') }}">Ver planes</a>
    </div>
@endif
