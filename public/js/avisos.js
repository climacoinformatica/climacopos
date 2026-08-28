/* ============================================================
   AVISO DESTELLANTE DE RESERVAS
   ============================================================

   Sondeo ligero cada 10 s. Solo pide el detalle cuando la huella
   del servidor cambia, para no castigar la base de datos.

   Se usa sondeo en lugar de WebSocket a propósito: la red de un
   salón se corta a menudo, y una conexión persistente que cae
   deja de avisar sin que nadie se entere. Un sondeo que falla
   vuelve a intentarlo diez segundos después.
*/

(function () {
    const barra    = document.getElementById('barraAvisos');
    if (!barra) return;

    const panel     = document.getElementById('panelAvisos');
    const contador  = document.getElementById('contadorAvisos');
    const lista     = document.getElementById('listaAvisos');
    const cerrar    = document.getElementById('cerrarAvisos');

    const urlContador = barra.dataset.urlContador;
    const urlLista    = barra.dataset.urlLista;
    const urlResolver = barra.dataset.urlResolver;
    const token       = document.querySelector('meta[name=csrf-token]').content;

    const INTERVALO = 10000;
    let huella = null;
    let sonoAlguna = false;
    let audioListo = false;

    // --- Sonido. En iOS no suena hasta que el usuario toca algo,
    //     así que se desbloquea con la primera interacción.
    let pitido = null;

    function prepararAudio() {
        if (audioListo) return;
        audioListo = true;

        try {
            const Contexto = window.AudioContext || window.webkitAudioContext;
            const contexto = new Contexto();

            pitido = function () {
                const osc = contexto.createOscillator();
                const vol = contexto.createGain();
                osc.connect(vol);
                vol.connect(contexto.destination);
                osc.frequency.value = 880;
                osc.type = 'sine';
                vol.gain.setValueAtTime(0.0001, contexto.currentTime);
                vol.gain.exponentialRampToValueAtTime(0.25, contexto.currentTime + 0.02);
                vol.gain.exponentialRampToValueAtTime(0.0001, contexto.currentTime + 0.45);
                osc.start();
                osc.stop(contexto.currentTime + 0.5);
            };
        } catch (e) {
            pitido = null;
        }
    }

    document.addEventListener('click', prepararAudio, { once: true });
    document.addEventListener('touchstart', prepararAudio, { once: true });

    // --- Sondeo
    async function sondear() {
        try {
            const respuesta = await fetch(urlContador, { headers: { 'Accept': 'application/json' } });
            if (!respuesta.ok) return;

            const datos = await respuesta.json();

            pintarContador(datos);

            if (datos.huella !== huella) {
                huella = datos.huella;
                if (!panel.hidden) cargarLista();
            }
        } catch (e) {
            // Sin conexión: se reintenta en el siguiente ciclo
        }
    }

    function pintarContador(datos) {
        const total = datos.avisos || 0;

        contador.textContent = total;
        barra.hidden = total === 0;
        barra.classList.toggle('barra-avisos--activa', datos.pendientes > 0);

        document.body.classList.toggle('con-barra-avisos', total > 0);

        if (datos.pendientes > 0 && !sonoAlguna) {
            sonoAlguna = true;
            if (pitido) pitido();
        }

        if (datos.pendientes === 0) {
            sonoAlguna = false;
        }

        // Título de la pestaña: se ve aunque el TPV esté en otra ventana
        const base = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = datos.pendientes > 0 ? '(' + datos.pendientes + ') ' + base : base;
    }

    async function cargarLista() {
        lista.innerHTML = '<li class="aviso-item aviso-item--cargando">Cargando…</li>';

        try {
            const respuesta = await fetch(urlLista, { headers: { 'Accept': 'application/json' } });
            const avisos = await respuesta.json();

            if (avisos.length === 0) {
                lista.innerHTML = '<li class="aviso-item aviso-item--vacio">No hay nada pendiente.</li>';
                return;
            }

            lista.innerHTML = '';

            avisos.forEach(function (aviso) {
                lista.appendChild(construirAviso(aviso));
            });
        } catch (e) {
            lista.innerHTML = '<li class="aviso-item aviso-item--vacio">No se pudo cargar.</li>';
        }
    }

    function construirAviso(aviso) {
        const item = document.createElement('li');
        item.className = 'aviso-item' + (aviso.accion ? ' aviso-item--accion' : '');

        let html =
            '<div class="aviso-item__cabecera">' +
                '<span class="aviso-item__icono">' + aviso.icono + '</span>' +
                '<div class="aviso-item__texto">' +
                    '<strong>' + escapar(aviso.titulo) + '</strong>' +
                    (aviso.mensaje ? '<span>' + escapar(aviso.mensaje) + '</span>' : '') +
                '</div>' +
                '<span class="aviso-item__hace">' + aviso.hace + '</span>' +
            '</div>';

        if (aviso.reserva && aviso.reserva.pendiente) {
            html +=
                '<div class="aviso-item__acciones">' +
                    '<button type="button" class="boton boton--pequeno" data-confirmar="' + aviso.reserva.id + '">Confirmar</button>' +
                    '<button type="button" class="boton boton--secundario boton--pequeno" data-rechazar="' + aviso.reserva.id + '">Rechazar</button>' +
                    '<a href="' + aviso.reserva.url + '" class="boton boton--secundario boton--pequeno">Ver</a>' +
                '</div>';
        } else if (!aviso.accion) {
            html += '<div class="aviso-item__acciones">' +
                '<button type="button" class="boton boton--secundario boton--pequeno" data-leido="' + aviso.id + '">Entendido</button>' +
                '</div>';
        }

        item.innerHTML = html;

        const confirmar = item.querySelector('[data-confirmar]');
        const rechazar  = item.querySelector('[data-rechazar]');
        const leido     = item.querySelector('[data-leido]');

        if (confirmar) {
            confirmar.addEventListener('click', function () {
                resolver(confirmar.dataset.confirmar, 'confirmar', null, item);
            });
        }

        if (rechazar) {
            rechazar.addEventListener('click', function () {
                const motivo = prompt('Motivo del rechazo (se lo enviaremos al cliente):',
                                      'No tenemos disponibilidad para ese horario');
                if (motivo === null) return;
                resolver(rechazar.dataset.rechazar, 'rechazar', motivo, item);
            });
        }

        if (leido) {
            leido.addEventListener('click', function () {
                marcarLeido(leido.dataset.leido, item);
            });
        }

        return item;
    }

    async function resolver(reservaId, accion, motivo, item) {
        item.classList.add('aviso-item--procesando');

        try {
            const respuesta = await fetch(urlResolver.replace('__ID__', reservaId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ accion: accion, motivo: motivo })
            });

            const datos = await respuesta.json();

            if (!respuesta.ok) {
                alert(datos.error || 'No se pudo completar la acción.');
                item.classList.remove('aviso-item--procesando');
                return;
            }

            item.classList.add('aviso-item--resuelto');
            setTimeout(function () {
                item.remove();
                if (lista.children.length === 0) {
                    lista.innerHTML = '<li class="aviso-item aviso-item--vacio">No hay nada pendiente.</li>';
                }
            }, 400);

            huella = datos.huella;
            sondear();

            // Si estamos en la agenda, se refresca para ver la cita ya confirmada
            if (window.location.pathname.indexOf('/panel/agenda') === 0) {
                setTimeout(function () { window.location.reload(); }, 600);
            }
        } catch (e) {
            item.classList.remove('aviso-item--procesando');
            alert('Error de conexión.');
        }
    }

    async function marcarLeido(avisoId, item) {
        await fetch(barra.dataset.urlLeido.replace('__ID__', avisoId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
        });

        item.remove();
        sondear();
    }

    function escapar(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    // --- Abrir y cerrar el panel
    barra.addEventListener('click', function () {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) cargarLista();
    });

    cerrar.addEventListener('click', function (evento) {
        evento.stopPropagation();
        panel.hidden = true;
    });

    document.addEventListener('click', function (evento) {
        if (!panel.hidden && !panel.contains(evento.target) && !barra.contains(evento.target)) {
            panel.hidden = true;
        }
    });

    // --- Arranque
    sondear();
    setInterval(sondear, INTERVALO);

    // Al volver a la pestaña, comprobar de inmediato
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) sondear();
    });
})();
