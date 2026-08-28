/* ============================================================
   PUNTO DE VENTA
   ============================================================
   Vanilla, sin dependencias. El TPV tiene que arrancar rápido
   en una tablet modesta y seguir funcionando si algo falla.
*/

function iniciarTpv() {
    const raiz   = document.getElementById('tpv');
    const datos  = window.TPV_DATOS;
    let   ticket = window.TPV_TICKET;

    const urlBase = raiz.dataset.urlBase;

    /**
     * Que hacer despues de cobrar: NADA, SELECTOR o INICIO.
     *
     * En un salon con un solo ordenador, cada profesional teclea y cobra
     * lo suyo. Volver al selector hace que el siguiente meta su PIN y
     * todo lo que teclee se le asigne a el, sin preguntas ni pasos
     * de mas.
     */
    const trasCobrar  = raiz.dataset.trasCobrar || 'NADA';
    const urlSelector = raiz.dataset.urlSelector || '/panel/selector';
    const urlInicio   = raiz.dataset.urlInicio || '/panel';
    const csrf    = datos.csrf;

    // --------------------------------------------------------
    // Utilidades
    // --------------------------------------------------------
    const euros = (n) => (Number(n) || 0).toFixed(2).replace('.', ',') + ' €';

    async function llamar(ruta, cuerpo) {
        const respuesta = await fetch(urlBase + ruta, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(cuerpo || {})
        });

        const resultado = await respuesta.json();

        if (resultado.ticket) {
            ticket = resultado.ticket;
            pintar();
        }

        if (!resultado.ok && resultado.error) {
            avisar(resultado.error);
        }

        return resultado;
    }

    function avisar(mensaje) {
        const nota = document.createElement('div');
        nota.className = 'tpv-nota';
        nota.textContent = mensaje;
        document.body.appendChild(nota);
        setTimeout(() => nota.remove(), 3500);
    }

    // --------------------------------------------------------
    // Pintar el ticket
    // --------------------------------------------------------
    const lineasUl = document.getElementById('ticketLineas');
    const botonCobrar = document.getElementById('abrirCobro');

    function pintar() {
        lineasUl.innerHTML = '';

        if (ticket.lineas.length === 0) {
            lineasUl.innerHTML = '<li class="ticket-vacio">Toca un artículo para empezar</li>';
        }

        ticket.lineas.forEach(function (linea) {
            const li = document.createElement('li');
            li.className = 'ticket-linea' + (linea.invitacion ? ' ticket-linea--invitacion' : '');

            if (linea.bono) {
                li.classList.add('ticket-linea--bono');
            }

            li.innerHTML =
                '<div class="ticket-linea__texto">' +
                    '<strong>' + escapar(linea.descripcion) + '</strong>' +
                    '<small>' + linea.cantidad + ' × ' + euros(linea.precio) +
                    (linea.dto > 0 && !linea.bono ? ' · -' + linea.dto + '%' : '') +
                    (linea.invitacion ? ' · INVITACIÓN' : '') +
                    (linea.bono ? ' · BONO ' + escapar(linea.bono) : '') +
                    '</small>' +
                '</div>' +
                '<div class="ticket-linea__importe">' + euros(linea.importe) + '</div>';

            li.addEventListener('click', () => abrirAcciones(linea));
            lineasUl.appendChild(li);
        });

        document.getElementById('ticketBase').textContent     = euros(ticket.base);
        document.getElementById('ticketImpuesto').textContent = euros(ticket.impuesto);
        document.getElementById('ticketTotal').textContent    = euros(ticket.total);
        document.getElementById('ticketRef').textContent      = ticket.referencia;

        const hayCobros = ticket.cobros && ticket.cobros.length > 0;
        document.getElementById('filaPendiente').hidden = !hayCobros;
        document.getElementById('ticketPendiente').textContent = euros(ticket.pendiente);

        botonCobrar.disabled = ticket.lineas.length === 0 || ticket.pendiente <= 0;
        botonCobrar.textContent = hayCobros
            ? 'Cobrar ' + euros(ticket.pendiente)
            : 'Cobrar ' + euros(ticket.total);
    }

    function escapar(texto) {
        const d = document.createElement('div');
        d.textContent = texto;
        return d.innerHTML;
    }

    // --------------------------------------------------------
    // Añadir artículos
    // --------------------------------------------------------
    document.querySelectorAll('.articulo').forEach(function (boton) {
        boton.addEventListener('click', async function () {
            this.classList.add('articulo--pulsado');
            setTimeout(() => this.classList.remove('articulo--pulsado'), 150);

            const resultado = await llamar('/lineas', { articulo_id: this.dataset.id, cantidad: 1 });

            // Si la clienta tiene un bono que cubre esto, se ofrece
            if (resultado.ok && ticket.cliente_id && ticket.tiene_bonos) {
                const ultima = ticket.lineas[ticket.lineas.length - 1];

                if (ultima) {
                    comprobarBonos(ultima.id);
                }
            }
        });
    });

    // --------------------------------------------------------
    // Bonos
    // --------------------------------------------------------

    async function comprobarBonos(lineaId) {
        try {
            const respuesta = await fetch(urlBase + '/lineas/' + lineaId + '/bonos', {
                headers: { 'Accept': 'application/json' }
            });

            const datos = await respuesta.json();

            if (datos.bonos && datos.bonos.length > 0) {
                ofrecerBono(lineaId, datos.bonos);
            }
        } catch (e) {
            // Si falla la consulta, simplemente no se ofrece
        }
    }

    function ofrecerBono(lineaId, bonos) {
        const lista = bonos.map(function (b, i) {
            return (i + 1) + '. ' + b.nombre + ' (' + b.resumen + ')';
        }).join('\n');

        const mensaje = bonos.length === 1
            ? 'La clienta tiene un bono que cubre esto:\n\n'
              + bonos[0].nombre + '\n' + bonos[0].resumen
              + '\n\n¿Usarlo?'
            : 'La clienta tiene varios bonos que cubren esto:\n\n' + lista
              + '\n\nEscribe el número, o cancela para cobrarlo normal:';

        if (bonos.length === 1) {
            if (confirm(mensaje)) {
                llamar('/lineas/' + lineaId + '/bono', { bono_id: bonos[0].id });
            }

            return;
        }

        const elegido = prompt(mensaje);

        if (!elegido) return;

        const bono = bonos[parseInt(elegido) - 1];

        if (bono) {
            llamar('/lineas/' + lineaId + '/bono', { bono_id: bono.id });
        }
    }

    // --------------------------------------------------------
    // Filtros de familia y pestañas
    // --------------------------------------------------------
    let familiaActiva = '';
    let tipoActivo = 'SERVICIO';

    function filtrar() {
        document.querySelectorAll('.articulo').forEach(function (art) {
            const coincideFamilia = !familiaActiva || art.dataset.familia === familiaActiva;
            const coincideTipo = tipoActivo === 'PRODUCTO'
                ? art.dataset.tipo === 'PRODUCTO'
                : art.dataset.tipo !== 'PRODUCTO';

            art.hidden = !(coincideFamilia && coincideTipo);
        });
    }

    document.querySelectorAll('.tpv__familia').forEach(function (boton) {
        boton.addEventListener('click', function () {
            document.querySelectorAll('.tpv__familia').forEach(b => b.classList.remove('tpv__familia--activa'));
            this.classList.add('tpv__familia--activa');
            familiaActiva = this.dataset.familia;
            filtrar();
        });
    });

    document.querySelectorAll('.tpv__pestana').forEach(function (boton) {
        boton.addEventListener('click', function () {
            document.querySelectorAll('.tpv__pestana').forEach(b => b.classList.remove('tpv__pestana--activa'));
            this.classList.add('tpv__pestana--activa');

            const esCitas = this.dataset.tipo === 'CITAS';
            document.getElementById('citas').hidden = !esCitas;
            document.getElementById('rejilla').hidden = esCitas;
            document.getElementById('familias').hidden = esCitas;

            if (!esCitas) {
                tipoActivo = this.dataset.tipo;
                filtrar();
            }
        });
    });

    filtrar();

    // --------------------------------------------------------
    // Acciones sobre una línea
    // --------------------------------------------------------
    function abrirAcciones(linea) {
        const opciones = ['Cambiar cantidad'];

        if (datos.puedeDto) opciones.push('Descuento');
        if (datos.puedeInvitar) opciones.push('Invitar');
        if (datos.puedeQuitar) opciones.push('Quitar');

        const elegida = prompt(
            linea.descripcion + '\n\n' +
            opciones.map((o, i) => (i + 1) + '. ' + o).join('\n') +
            '\n\nEscribe el número:'
        );

        if (!elegida) return;

        const accion = opciones[parseInt(elegida) - 1];

        if (accion === 'Cambiar cantidad') {
            const cantidad = prompt('Cantidad:', linea.cantidad);
            if (cantidad !== null) llamar('/lineas/' + linea.id + '/cantidad', { cantidad: cantidad });
        } else if (accion === 'Descuento') {
            const pct = prompt('Descuento (%):', linea.dto || 0);
            if (pct !== null) llamar('/lineas/' + linea.id + '/descuento', { porcentaje: pct });
        } else if (accion === 'Invitar') {
            const motivo = prompt('Motivo de la invitación:');
            if (motivo) llamar('/lineas/' + linea.id + '/invitar', { motivo: motivo });
        } else if (accion === 'Quitar') {
            llamar('/lineas/' + linea.id + '/quitar');
        }
    }

    // --------------------------------------------------------
    // Buscador de cliente
    // --------------------------------------------------------

    const modalCliente = document.getElementById('modalCliente');
    const campoBuscar  = document.getElementById('buscarCliente');
    const resultados   = document.getElementById('resultadosCliente');
    const bloqueAlta   = document.getElementById('bloqueAlta');

    let temporizador = null;

    document.getElementById('botonCliente')?.addEventListener('click', function () {
        campoBuscar.value = '';
        resultados.innerHTML = '';
        bloqueAlta.hidden = true;
        modalCliente.hidden = false;
        campoBuscar.focus();
    });

    document.getElementById('cerrarCliente')?.addEventListener('click', function () {
        modalCliente.hidden = true;
    });

    /**
     * Busqueda con retardo.
     *
     * Sin esperar, cada tecla dispara una consulta y el servidor recibe
     * diez peticiones para escribir «mercedes». Trescientos milisegundos
     * es el punto donde deja de notarse el retardo y deja de haber
     * peticiones de sobra.
     */
    campoBuscar?.addEventListener('input', function () {
        clearTimeout(temporizador);

        const texto = this.value.trim();

        if (texto.length < 2) {
            resultados.innerHTML = '';
            bloqueAlta.hidden = true;
            return;
        }

        temporizador = setTimeout(() => buscarClientes(texto), 300);
    });

    async function buscarClientes(texto) {
        resultados.innerHTML = '<li class="cliente-cargando">Buscando…</li>';

        try {
            const respuesta = await fetch(
                urlBase.replace(/\/tpv\/\d+$/, '/tpv/clientes') + '?q=' + encodeURIComponent(texto),
                { headers: { 'Accept': 'application/json' } }
            );

            const datos = await respuesta.json();

            pintarResultados(datos.clientes || [], texto);
        } catch (e) {
            resultados.innerHTML = '<li class="cliente-cargando">No se pudo buscar.</li>';
        }
    }

    function pintarResultados(clientes, texto) {
        resultados.innerHTML = '';

        if (clientes.length === 0) {
            resultados.innerHTML = '<li class="cliente-cargando">Ninguna ficha coincide.</li>';
            prepararAlta(texto);
            return;
        }

        bloqueAlta.hidden = true;

        clientes.forEach(function (cliente) {
            const li = document.createElement('li');
            li.className = 'cliente-resultado';

            let extras = '';

            if (cliente.saldo > 0) {
                extras += '<span class="pastilla pastilla--saldo">' + euros(cliente.saldo) + '</span>';
            }

            if (cliente.bonos.length > 0) {
                extras += '<span class="pastilla pastilla--bono">' +
                          cliente.bonos.length + ' bono' + (cliente.bonos.length > 1 ? 's' : '') +
                          '</span>';
            }

            li.innerHTML =
                '<div class="cliente-resultado__datos">' +
                    '<strong>' + escapar(cliente.nombre) + '</strong>' +
                    '<small>' +
                        (cliente.telefono ? escapar(cliente.telefono) : 'sin teléfono') +
                        (cliente.ultima ? ' · última visita ' + cliente.ultima : ' · sin visitas') +
                    '</small>' +
                '</div>' +
                '<div class="cliente-resultado__extras">' + extras + '</div>';

            li.addEventListener('click', () => asignarCliente(cliente.id));
            resultados.appendChild(li);
        });

        // Siempre se puede dar de alta, aunque haya coincidencias
        prepararAlta(texto);
    }

    /**
     * Precarga el formulario de alta con lo que se ha escrito.
     * Si son solo digitos se toma como teléfono; si no, como nombre.
     */
    function prepararAlta(texto) {
        bloqueAlta.hidden = false;

        const esTelefono = /^[0-9+\s]{6,}$/.test(texto);

        document.getElementById('altaNombre').value = esTelefono ? '' : texto;
        document.getElementById('altaTelefono').value = esTelefono ? texto : '';
    }

    async function asignarCliente(clienteId) {
        const resultado = await llamar('/cliente', { cliente_id: clienteId });

        modalCliente.hidden = true;

        if (resultado.cliente) {
            pintarCliente(resultado.cliente);
        }

        // Si tiene bonos que cubren lo ya tecleado, se ofrecen
        if (resultado.con_bono && resultado.con_bono.length > 0) {
            resultado.con_bono.forEach(function (item) {
                ofrecerBono(item.linea_id, item.bonos);
            });
        }
    }

    document.getElementById('quitarCliente')?.addEventListener('click', async function () {
        await llamar('/cliente', { cliente_id: null });
        pintarCliente(null);
        modalCliente.hidden = true;
    });

    document.getElementById('confirmarAlta')?.addEventListener('click', async function () {
        const nombre = document.getElementById('altaNombre').value.trim();

        if (!nombre) {
            avisar('Hace falta al menos el nombre.');
            return;
        }

        this.disabled = true;

        const respuesta = await fetch(urlBase + '/cliente/nuevo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                nombre: nombre,
                telefono: document.getElementById('altaTelefono').value.trim() || null
            })
        });

        const datos = await respuesta.json();

        this.disabled = false;

        if (!datos.ok) {
            avisar(datos.error || 'No se pudo crear la ficha.');
            return;
        }

        if (datos.aviso) avisar(datos.aviso);

        ticket = datos.ticket;
        pintar();
        pintarCliente(datos.cliente);
        modalCliente.hidden = true;
    });

    function pintarCliente(cliente) {
        const etiqueta = document.getElementById('ticketCliente');
        const panel = document.getElementById('panelCliente');

        if (!cliente) {
            etiqueta.textContent = 'Sin cliente';
            if (panel) panel.hidden = true;
            ticket.cliente_id = null;
            ticket.saldo = 0;
            ticket.tiene_bonos = false;
            return;
        }

        etiqueta.textContent = cliente.nombre;

        ticket.cliente_id = cliente.id;
        ticket.saldo = cliente.saldo;
        ticket.tiene_bonos = cliente.bonos.length > 0;

        if (!panel) return;

        let html = '';

        if (cliente.saldo > 0) {
            html += '<div class="panel-cliente__dato">Monedero: <strong>' +
                    euros(cliente.saldo) + '</strong></div>';
        }

        cliente.bonos.forEach(function (bono) {
            html += '<div class="panel-cliente__dato">' + escapar(bono.nombre) +
                    ': <strong>' + escapar(bono.resumen) + '</strong></div>';
        });

        if (cliente.avisos) {
            html += '<div class="panel-cliente__aviso">' + escapar(cliente.avisos) + '</div>';
        }

        panel.innerHTML = html;
        panel.hidden = html === '';
    }

    // --------------------------------------------------------
    // Cobro
    // --------------------------------------------------------
    const modal    = document.getElementById('modalCobro');
    const modalOk  = document.getElementById('modalHecho');
    const campoEnt = document.getElementById('entregado');
    const bloqueEf = document.getElementById('bloqueEfectivo');
    const cambioP  = document.getElementById('cambio');
    const errorP   = document.getElementById('cobroError');
    const confirmar = document.getElementById('confirmarCobro');

    let medioElegido = null;

    botonCobrar.addEventListener('click', function () {
        medioElegido = null;
        campoEnt.value = '';
        cambioP.hidden = true;
        errorP.hidden = true;
        bloqueEf.hidden = true;
        confirmar.disabled = true;

        document.querySelectorAll('.medio').forEach(b => b.classList.remove('medio--activo'));
        document.getElementById('cobroImporte').textContent = euros(ticket.pendiente);

        pintarRapidos(ticket.pendiente);
        pintarSaldo();
        modal.hidden = false;
    });

    document.getElementById('cerrarCobro').addEventListener('click', () => modal.hidden = true);

    const bloqueVale = document.getElementById('bloqueVale');
    const campoVale  = document.getElementById('codigoVale');
    const saldoP     = document.getElementById('avisoSaldo');

    document.querySelectorAll('.medio').forEach(function (boton) {
        boton.addEventListener('click', function () {
            document.querySelectorAll('.medio').forEach(b => b.classList.remove('medio--activo'));
            this.classList.add('medio--activo');

            medioElegido = this.dataset.medio;

            bloqueEf.hidden = medioElegido !== 'EFECTIVO';
            if (bloqueVale) bloqueVale.hidden = medioElegido !== 'VALE';

            errorP.hidden = true;
            confirmar.disabled = false;

            // El monedero necesita cliente y saldo suficiente
            if (medioElegido === 'MONEDERO') {
                if (!ticket.cliente_id) {
                    errorP.textContent = 'Para usar el monedero hay que asignar el cliente al ticket.';
                    errorP.hidden = false;
                    confirmar.disabled = true;
                } else if (ticket.saldo < ticket.pendiente) {
                    errorP.textContent = 'El monedero solo tiene ' + euros(ticket.saldo) + '.';
                    errorP.hidden = false;
                    confirmar.disabled = true;
                }
            }

            if (medioElegido === 'EFECTIVO') campoEnt.focus();
            if (medioElegido === 'VALE' && campoVale) campoVale.focus();
        });
    });

    // Aviso de saldo disponible al abrir el cobro
    function pintarSaldo() {
        if (!saldoP) return;

        if (ticket.cliente_id && ticket.saldo > 0) {
            saldoP.textContent = 'Esta clienta tiene ' + euros(ticket.saldo) + ' en el monedero.';
            saldoP.hidden = false;
        } else {
            saldoP.hidden = true;
        }
    }

    // Importes redondos habituales
    function pintarRapidos(importe) {
        const contenedor = document.getElementById('rapidos');
        contenedor.innerHTML = '';

        const candidatos = [importe, 5, 10, 20, 50, 100]
            .map(n => n === importe ? importe : n)
            .filter((n, i, a) => n >= importe && a.indexOf(n) === i)
            .sort((a, b) => a - b)
            .slice(0, 5);

        candidatos.forEach(function (valor) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rapido';
            b.textContent = euros(valor);
            b.addEventListener('click', function () {
                campoEnt.value = valor.toFixed(2).replace('.', ',');
                calcularCambio();
            });
            contenedor.appendChild(b);
        });
    }

    function calcularCambio() {
        const entregado = parseFloat(campoEnt.value.replace(',', '.')) || 0;
        const diferencia = entregado - ticket.pendiente;

        if (diferencia > 0) {
            cambioP.hidden = false;
            document.getElementById('cambioImporte').textContent = euros(diferencia);
        } else {
            cambioP.hidden = true;
        }
    }

    campoEnt.addEventListener('input', calcularCambio);

    confirmar.addEventListener('click', async function () {
        if (!medioElegido) return;

        confirmar.disabled = true;

        const cuerpo = { medio: medioElegido, importe: ticket.pendiente };

        if (medioElegido === 'EFECTIVO' && campoEnt.value) {
            cuerpo.entregado = parseFloat(campoEnt.value.replace(',', '.'));
        }

        if (medioElegido === 'VALE') {
            if (!campoVale || !campoVale.value.trim()) {
                errorP.textContent = 'Escribe el código del vale.';
                errorP.hidden = false;
                confirmar.disabled = false;
                return;
            }

            cuerpo.referencia = campoVale.value.trim().toUpperCase();
        }

        const resultado = await llamar('/cobrar', cuerpo);

        confirmar.disabled = false;

        if (!resultado.ok) {
            errorP.textContent = resultado.error;
            errorP.hidden = false;
            return;
        }

        modal.hidden = true;

        if (resultado.cerrado) {
            document.getElementById('hechoTitulo').textContent =
                'Cobrado · ' + resultado.referencia;

            if (resultado.cambio > 0) {
                document.getElementById('hechoCambio').textContent = 'Cambio: ' + euros(resultado.cambio);
                document.getElementById('hechoCambio').hidden = false;
            }

            /**
             * Boton de imprimir.
             *
             * Solo aparece en modo PREGUNTAR. Con SIEMPRE ya se imprimio
             * solo, y con NUNCA se reimprime desde el listado de tickets
             * si la clienta lo pide.
             */
            const botonImprimir = document.getElementById('hechoImprimir');

            if (botonImprimir) {
                botonImprimir.hidden = resultado.impresion !== 'PREGUNTAR';
                botonImprimir.dataset.ticket = ticket.id;
            }

            modalOk.hidden = false;

            /**
             * Solo se sale cuando el ticket queda COBRADO del todo.
             * En un cobro parcial la venta sigue abierta, y salir
             * dejaria el ticket a medias con la clienta delante.
             */
            if (trasCobrar !== 'NADA' && resultado.impresion !== 'PREGUNTAR') {
                setTimeout(function () {
                    window.location = trasCobrar === 'SELECTOR' ? urlSelector : urlInicio;
                }, 1600);   // margen para leer el cambio a devolver
            }

            /**
             * Con PREGUNTAR no se sale solo.
             *
             * Marcharse mientras alguien decide si imprime seria quitarle
             * la pantalla de las manos. Se sale al pulsar «Nuevo ticket».
             */
        } else {
            avisar('Quedan ' + euros(resultado.pendiente) + ' por cobrar.');
        }
    });

    document.getElementById('hechoImprimir')?.addEventListener('click', async function () {
        this.disabled = true;
        this.textContent = 'Enviando...';

        const respuesta = await fetch(urlBase + '/imprimir', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({}),
        });

        const datos = await respuesta.json();

        this.textContent = datos.ok ? 'Enviado' : 'No se pudo';
        this.disabled = datos.ok;

        if (!datos.ok) {
            avisar(datos.error || 'No se pudo imprimir.');
            this.disabled = false;
            this.textContent = 'Imprimir ticket';
        }
    });

    document.getElementById('hechoNuevo').addEventListener('click', function () {
        window.location = urlBase.replace(/\/tpv\/\d+$/, '/tpv/nuevo');
    });

    // Escape cierra los modales
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            modal.hidden = true;
            if (modalCliente) modalCliente.hidden = true;
        }
    });

    pintar();
}
