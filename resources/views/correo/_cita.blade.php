{{-- Bloque con los datos de la cita, reutilizado en varios correos --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#f8fafc;border-radius:8px;margin:20px 0;">
    <tr>
        <td style="padding:18px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:6px 0;color:#64748b;font-size:13px;width:34%;">Cuándo</td>
                    <td style="padding:6px 0;color:#0f172a;font-size:14px;font-weight:600;">
                        {{ $reserva->fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}<br>
                        a las {{ substr($reserva->hora_ini, 0, 5) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#64748b;font-size:13px;">Servicio</td>
                    <td style="padding:6px 0;color:#0f172a;font-size:14px;">
                        {{ $reserva->resumenServicios() }}
                    </td>
                </tr>
                @if ($reserva->importe_total > 0)
                    <tr>
                        <td style="padding:6px 0;color:#64748b;font-size:13px;">Precio</td>
                        <td style="padding:6px 0;color:#0f172a;font-size:14px;">
                            {{ number_format($reserva->importe_total, 2, ',', '.') }} €
                            @if ($reserva->importe_pagado > 0)
                                <br><span style="color:#059669;font-size:13px;">
                                    Ya has pagado {{ number_format($reserva->importe_pagado, 2, ',', '.') }} €
                                </span>
                            @endif
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:6px 0;color:#64748b;font-size:13px;">Código</td>
                    <td style="padding:6px 0;color:#0f172a;font-size:14px;font-family:monospace;">
                        {{ $reserva->codigo }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
