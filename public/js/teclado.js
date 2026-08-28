/* ============================================================
   TECLADO EN PANTALLA
   ============================================================

   Un único teclado para todo el sistema. Se engancha solo a los
   campos que lo necesitan, sin tocar ninguna vista.

   CÓMO SE ACTIVA UN CAMPO

     Automático, por el tipo de campo:
       <input type="number">              → teclado numérico
       <input inputmode="decimal">        → numérico con coma
       <input inputmode="numeric">        → numérico entero
       <input type="password">            → teclado de texto

     A mano, cuando el automático no acierta:
       data-teclado="numero"   decimales
       data-teclado="entero"   sin decimales
       data-teclado="pin"      solo dígitos, con puntos de progreso
       data-teclado="texto"    alfanumérico
       data-teclado="no"       desactivarlo en ese campo

   POR QUÉ NO SE USA EL TECLADO DEL SISTEMA

   En una tablet, el teclado de Android o iPad ocupa media pantalla,
   tapa el campo y a veces desplaza el diseño. Y en un PC con pantalla
   táctil no aparece ninguno. Este siempre está donde se espera.

   IMPORTANTE: nunca se bloquea el teclado físico. Quien tiene teclado
   sigue escribiendo con él; el de pantalla es un añadido, no un
   sustituto.
   ============================================================ */

const TecladoPantalla = (function () {

    let campoActivo = null;
    let panel = null;
    let modo = 'numero';
    let mayusculas = false;

    // --------------------------------------------------------
    // Detección
    // --------------------------------------------------------

    /**
     * ¿Debe aparecer el teclado en este equipo?
     *
     *   siempre  la tablet del mostrador
     *   nunca    el PC del despacho, con su teclado de siempre
     *   auto     según si la pantalla es táctil
     */
    function activoEnEsteEquipo() {
        const preferencia = document.body.dataset.tecladoTactil || 'auto';

        if (preferencia === 'siempre') return true;
        if (preferencia === 'nunca') return false;

        return navigator.maxTouchPoints > 0 || 'ontouchstart' in window;
    }

    /** Qué teclado le toca a un campo. Null si no lleva ninguno. */
    function modoDe(campo) {
        const explicito = campo.dataset.teclado;

        if (explicito === 'no') return null;
        if (explicito) return explicito;

        if (campo.tagName !== 'INPUT') return null;

        const tipo = (campo.type || '').toLowerCase();
        const inputmode = (campo.getAttribute('inputmode') || '').toLowerCase();

        if (tipo === 'number') return 'numero';
        if (inputmode === 'decimal') return 'numero';
        if (inputmode === 'numeric' || inputmode === 'tel') return 'entero';
        if (tipo === 'tel') return 'entero';
        if (tipo === 'password') return 'texto';

        return null;
    }

    // --------------------------------------------------------
    // Construcción
    // --------------------------------------------------------

    function crearPanel() {
        panel = document.createElement('div');
        panel.className = 'tp';
        panel.hidden = true;

        panel.innerHTML =
            '<div class="tp__cabecera">' +
                '<span class="tp__etiqueta" id="tecladoEtiqueta"></span>' +
                '<span class="tp__valor" id="tecladoValor"></span>' +
                '<button type="button" class="tp__cerrar" data-accion="cerrar">&times;</button>' +
            '</div>' +
            '<div class="tp__teclas" id="tecladoTeclas"></div>';

        document.body.appendChild(panel);

        /**
         * mousedown en lugar de click, y con preventDefault.
         *
         * Sin esto, al pulsar una tecla el campo pierde el foco antes de
         * que llegue el click: el cursor salta, el teclado se cierra y
         * parece que la tecla no funciona.
         */
        panel.addEventListener('mousedown', function (evento) {
            const tecla = evento.target.closest('[data-tecla], [data-accion]');

            if (!tecla) return;

            evento.preventDefault();
            pulsar(tecla);
        });

        panel.addEventListener('touchstart', function (evento) {
            const tecla = evento.target.closest('[data-tecla], [data-accion]');

            if (!tecla) return;

            evento.preventDefault();
            pulsar(tecla);
        }, { passive: false });
    }

    function teclasNumericas(conComa) {
        const filas = [
            ['7', '8', '9'],
            ['4', '5', '6'],
            ['1', '2', '3'],
            [conComa ? ',' : '', '0', 'borrar'],
        ];

        return '<div class="tp__rejilla tp__rejilla--numerica">' +
            filas.map(fila => fila.map(function (t) {
                if (t === '') return '<span></span>';

                if (t === 'borrar') {
                    return '<button type="button" class="tp-tecla tp-tecla--accion" data-accion="borrar">⌫</button>';
                }

                return '<button type="button" class="tp-tecla" data-tecla="' + t + '">' + t + '</button>';
            }).join('')).join('') +
        '</div>' +
        '<div class="tp__pie">' +
            '<button type="button" class="tp-tecla tp-tecla--secundaria" data-accion="limpiar">Borrar todo</button>' +
            '<button type="button" class="tp-tecla tp-tecla--aceptar" data-accion="aceptar">Aceptar</button>' +
        '</div>';
    }

    function teclasPin() {
        const conPuntos = campoActivo
                          && parseInt(campoActivo.maxLength) > 0
                          && parseInt(campoActivo.maxLength) <= 6;

        return (conPuntos ? '<div class="tp__puntos" id="tecladoPuntos"></div>' : '') +
            teclasNumericas(false);
    }

    /**
     * Teclado de texto.
     *
     * Distribución QWERTY española, con la Ñ donde se espera. Ordenarlo
     * alfabéticamente sería más "lógico" y mucho peor: nadie encuentra
     * las letras donde no están.
     */
    function teclasTexto() {
        const filas = [
            ['1','2','3','4','5','6','7','8','9','0'],
            ['q','w','e','r','t','y','u','i','o','p'],
            ['a','s','d','f','g','h','j','k','l','ñ'],
            ['z','x','c','v','b','n','m','@','.','-'],
        ];

        return '<div class="tp__rejilla tp__rejilla--texto">' +
            filas.map(fila =>
                '<div class="tp__fila">' +
                fila.map(t =>
                    '<button type="button" class="tp-tecla tp-tecla--letra" data-tecla="' + t + '">' +
                    (mayusculas && /[a-zñ]/.test(t) ? t.toUpperCase() : t) +
                    '</button>'
                ).join('') +
                '</div>'
            ).join('') +
            '<div class="tp__fila">' +
                '<button type="button" class="tp-tecla tp-tecla--secundaria" data-accion="mayusculas">' +
                    (mayusculas ? 'ABC' : 'abc') +
                '</button>' +
                '<button type="button" class="tp-tecla tp-tecla--espacio" data-tecla=" ">espacio</button>' +
                '<button type="button" class="tp-tecla tp-tecla--accion" data-accion="borrar">⌫</button>' +
            '</div>' +
        '</div>' +
        '<div class="tp__pie">' +
            '<button type="button" class="tp-tecla tp-tecla--secundaria" data-accion="limpiar">Borrar todo</button>' +
            '<button type="button" class="tp-tecla tp-tecla--aceptar" data-accion="aceptar">Aceptar</button>' +
        '</div>';
    }

    // --------------------------------------------------------
    // Comportamiento
    // --------------------------------------------------------

    function abrir(campo) {
        const nuevoModo = modoDe(campo);

        if (!nuevoModo) return;

        campoActivo = campo;
        modo = nuevoModo;
        mayusculas = false;

        pintarTeclas();
        pintarValor();

        // La etiqueta del campo, para saber qué se está escribiendo
        const etiqueta = document.querySelector('label[for="' + campo.id + '"]');

        document.getElementById('tecladoEtiqueta').textContent =
            etiqueta ? etiqueta.textContent.replace('*', '').trim()
                     : (campo.placeholder || '');

        panel.hidden = false;
        document.body.classList.add('con-teclado');
    }

    function cerrar() {
        panel.hidden = true;
        campoActivo = null;
        document.body.classList.remove('con-teclado');
    }

    function pintarTeclas() {
        const contenedor = document.getElementById('tecladoTeclas');

        contenedor.className = 'tp__teclas tp__teclas--' + modo;

        contenedor.innerHTML = modo === 'texto' ? teclasTexto()
                             : modo === 'pin'   ? teclasPin()
                             : teclasNumericas(modo === 'numero');
    }

    function pintarValor() {
        if (!campoActivo) return;

        const valor = campoActivo.value;
        const destino = document.getElementById('tecladoValor');

        /**
         * Los puntos de progreso solo con longitud fija y corta.
         *
         * Con un PIN de cuatro son utiles: se ve cuanto falta. Con una
         * contrasena de hasta doce saldrian doce huecos vacios, que
         * parece que hay que rellenarlos todos.
         */
        const conPuntos = modo === 'pin' && parseInt(campoActivo.maxLength) > 0
                          && parseInt(campoActivo.maxLength) <= 6;

        if (conPuntos) {
            destino.textContent = '';
            pintarPuntos(valor.length);

            return;
        }

        // Una contraseña no se enseña en el panel
        if (campoActivo.type === 'password') {
            destino.textContent = '•'.repeat(valor.length);
        } else {
            destino.textContent = valor;
        }
    }

    function pintarPuntos(cuantos) {
        const contenedor = document.getElementById('tecladoPuntos');

        if (!contenedor) return;

        const total = parseInt(campoActivo.maxLength) > 0 ? campoActivo.maxLength : 4;

        let html = '';

        for (let i = 0; i < total; i++) {
            html += '<span class="tp-punto' + (i < cuantos ? ' tp-punto--lleno' : '') + '"></span>';
        }

        contenedor.innerHTML = html;
    }

    function pulsar(boton) {
        if (!campoActivo) return;

        const accion = boton.dataset.accion;

        if (accion === 'cerrar')  return cerrar();
        if (accion === 'aceptar') return aceptar();

        if (accion === 'mayusculas') {
            mayusculas = !mayusculas;
            pintarTeclas();

            return;
        }

        if (accion === 'borrar') {
            escribir(campoActivo.value.slice(0, -1));

            return;
        }

        if (accion === 'limpiar') {
            escribir('');

            return;
        }

        let caracter = boton.dataset.tecla;

        if (caracter === undefined) return;

        if (mayusculas && /[a-zñ]/.test(caracter)) {
            caracter = caracter.toUpperCase();
        }

        // Una sola coma decimal
        if (caracter === ',' && campoActivo.value.includes(',')) return;

        if (campoActivo.maxLength > 0 && campoActivo.value.length >= campoActivo.maxLength) {
            return;
        }

        escribir(campoActivo.value + caracter);
    }

    /**
     * Escribe en el campo y avisa a quien esté escuchando.
     *
     * Los eventos son imprescindibles: el TPV recalcula el cambio con
     * `input`, y sin dispararlo el importe se quedaría congelado aunque
     * el campo cambiara.
     */
    function escribir(valor) {
        /**
         * Un input[type=number] rechaza la coma: el navegador guarda
         * cadena vacía y el usuario ve desaparecer lo que escribió. Se
         * convierte a punto antes de asignar.
         */
        if (campoActivo.type === 'number') {
            valor = valor.replace(',', '.');
        }

        campoActivo.value = valor;

        campoActivo.dispatchEvent(new Event('input', { bubbles: true }));
        campoActivo.dispatchEvent(new Event('change', { bubbles: true }));

        pintarValor();

        // El PIN suele enviarse solo al completarse
        if (modo === 'pin' && campoActivo.dataset.autoenviar
            && valor.length === parseInt(campoActivo.maxLength || 4)) {
            setTimeout(aceptar, 150);
        }
    }

    function aceptar() {
        const campo = campoActivo;

        cerrar();

        if (!campo) return;

        // Al aceptar se salta al siguiente campo, como con el tabulador
        const formulario = campo.form;

        if (campo.dataset.autoenviar && formulario) {
            formulario.requestSubmit
                ? formulario.requestSubmit()
                : formulario.submit();

            return;
        }

        if (formulario) {
            const campos = Array.from(formulario.elements)
                .filter(e => !e.disabled && e.type !== 'hidden' && e.offsetParent !== null);

            const siguiente = campos[campos.indexOf(campo) + 1];

            if (siguiente && modoDe(siguiente)) {
                siguiente.focus();
                abrir(siguiente);

                return;
            }

            siguiente?.focus();
        }
    }

    // --------------------------------------------------------
    // Arranque
    // --------------------------------------------------------

    function iniciar() {
        if (!activoEnEsteEquipo()) return;

        crearPanel();

        /**
         * Se escucha en el documento y no campo a campo: así funciona
         * también en los campos que se crean después, como las líneas
         * de fórmula o el modal de cobro.
         */
        document.addEventListener('focusin', function (evento) {
            const campo = evento.target;

            if (campo === campoActivo) return;

            if (modoDe(campo)) {
                abrir(campo);
            } else if (campoActivo) {
                cerrar();
            }
        });

        // Escape cierra, igual que en los modales
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && campoActivo) {
                cerrar();
            }
        });

        // Tocar fuera cierra
        document.addEventListener('mousedown', function (evento) {
            if (!campoActivo) return;

            if (!panel.contains(evento.target) && evento.target !== campoActivo) {
                cerrar();
            }
        });
    }

    return { iniciar, abrir, cerrar };
})();

document.addEventListener('DOMContentLoaded', TecladoPantalla.iniciar);
