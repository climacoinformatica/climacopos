package servicio

import (
	"fmt"
	"log"
	"net"
	"os"
	"strings"
	"time"

	"climaco/agente/internal/api"
	"climaco/agente/internal/config"
	"climaco/agente/internal/impresora"
)

// Agente sondea el servidor y manda a la impresora lo que haya.
type Agente struct {
	cfg      *config.Config
	cliente  *api.Cliente
	parar    chan struct{}
	registro *log.Logger
}

func Nuevo(cfg *config.Config, registro *log.Logger) *Agente {
	return &Agente{
		cfg:      cfg,
		cliente:  api.Nuevo(cfg.URL, cfg.Token),
		parar:    make(chan struct{}),
		registro: registro,
	}
}

// Ejecutar arranca el bucle y no vuelve hasta que se para.
func (a *Agente) Ejecutar() {
	a.registro.Printf("Conector iniciado. Salón: %s", a.cfg.URL)

	if a.cfg.Impresora != "" {
		a.registro.Printf("Impresora: %s", a.cfg.Impresora)
	} else {
		a.registro.Print("AVISO: no hay impresora elegida")
	}

	/**
	 * Espera entre sondeos.
	 *
	 * Empieza en segundo y medio, que es lo que pide el servidor, pero
	 * sube sola cuando falla la conexión. Sin eso, un salón sin internet
	 * bombardearía al servidor con una petición por segundo durante
	 * horas, multiplicado por cada cliente.
	 */
	espera := 1500 * time.Millisecond
	esperaMax := 60 * time.Second

	fallos := 0

	for {
		select {
		case <-a.parar:
			a.registro.Print("Conector detenido.")
			return

		case <-time.After(espera):
		}

		trabajos, cfgServidor, err := a.cliente.Trabajos()

		if err != nil {
			fallos++

			// Solo se anota uno de cada diez: si no, un salón cerrado el
			// domingo llena el fichero de registro con lo mismo
			if fallos == 1 || fallos%10 == 0 {
				a.registro.Printf("Sin conexión con el salón (%d intentos): %v", fallos, err)
			}

			espera *= 2
			if espera > esperaMax {
				espera = esperaMax
			}

			continue
		}

		if fallos > 0 {
			a.registro.Printf("Conexión recuperada tras %d intentos.", fallos)
			fallos = 0
		}

		// El servidor manda el ritmo, dentro de un rango sensato
		if cfgServidor.IntervaloMs >= 500 && cfgServidor.IntervaloMs <= 10000 {
			espera = time.Duration(cfgServidor.IntervaloMs) * time.Millisecond
		} else {
			espera = 1500 * time.Millisecond
		}

		for _, trabajo := range trabajos {
			a.procesar(trabajo, cfgServidor)
		}
	}
}

func (a *Agente) Parar() {
	close(a.parar)
}

func (a *Agente) procesar(trabajo api.Trabajo, cfg api.Config) {
	datos, err := trabajo.Contenido()

	if err != nil {
		a.fallo(trabajo, "el documento llegó dañado")
		return
	}

	err = a.enviar(datos, trabajo, cfg)

	if err != nil {
		a.registro.Printf("Trabajo %d: %v", trabajo.ID, err)
		a.fallo(trabajo, err.Error())

		return
	}

	a.registro.Printf("Trabajo %d impreso (%s)", trabajo.ID, trabajo.Descripcion)

	if err := a.cliente.Confirmar(trabajo.ID, true, ""); err != nil {
		a.registro.Printf("No se pudo confirmar el trabajo %d: %v", trabajo.ID, err)
	}
}

// enviar elige el camino según cómo esté conectada la impresora.
func (a *Agente) enviar(datos []byte, trabajo api.Trabajo, cfg api.Config) error {
	modo := strings.ToUpper(cfg.ImpresoraModo)

	/**
	 * Si el salón eligió impresora en el instalador, esa manda.
	 *
	 * Va antes que el modo del panel porque es lo que el cliente tocó
	 * con sus manos: si eligió una impresora y no sale nada, buscará el
	 * fallo ahí, no en un ajuste del panel que quizá ni sabe que existe.
	 */
	if a.cfg.Impresora != "" && modo != "RED" {
		return impresora.Imprimir(a.cfg.Impresora, datos, trabajo.Descripcion)
	}

	if modo == "RED" {
		if cfg.ImpresoraIP == "" {
			return fmt.Errorf("no hay dirección de impresora configurada en el panel")
		}

		/**
		 * Si en el campo de IP hay un recurso de Windows, se trata como
		 * tal en lugar de intentar hablarle por TCP.
		 *
		 * Es un error de configuración fácil de cometer: el campo se
		 * llama «IP» y alguien pega ahí el \\PC\IMPRESORA. Antes de
		 * fallar con «la impresora no responde», que despista, se hace
		 * lo que el usuario evidentemente quería.
		 */
		if strings.HasPrefix(cfg.ImpresoraIP, "\\\\") {
			return escribirEnRecurso(cfg.ImpresoraIP, datos)
		}

		return a.porRed(datos, cfg.ImpresoraIP, cfg.ImpresoraPuerto)
	}

	/**
	 * Recurso compartido de Windows.
	 *
	 * Si el panel indica un recurso del tipo \\PC\IMPRESORA, se escribe
	 * directamente ahi en lugar de usar la API de impresion.
	 *
	 * POR QUE LAS DOS VIAS
	 *
	 * La API con modo RAW es la buena: manda los bytes sin que el driver
	 * los toque, que es lo que hace falta para que se corte el papel y se
	 * abra el cajon.
	 *
	 * Pero hay drivers genericos que aceptan el trabajo, no dan ningun
	 * error, y no imprimen nada. Escribir en el recurso compartido es mas
	 * tosco pero funciona siempre, porque es lo mismo que hace un
	 * `echo > \\PC\IMPRESORA` desde la consola.
	 */
	if cfg.ImpresoraLocal != "" {
		return escribirEnRecurso(cfg.ImpresoraLocal, datos)
	}

	if a.cfg.Impresora == "" {
		return fmt.Errorf("no hay ninguna impresora elegida en este equipo")
	}

	return impresora.Imprimir(a.cfg.Impresora, datos, trabajo.Descripcion)
}

// escribirEnRecurso manda los bytes a un recurso compartido de Windows.
func escribirEnRecurso(recurso string, datos []byte) error {
	fichero, err := os.OpenFile(recurso, os.O_WRONLY, 0)

	if err != nil {
		return fmt.Errorf(
			"no se puede escribir en %s. Comprueba que la impresora esta "+
				"compartida con ese nombre y encendida (%v)", recurso, err)
	}
	defer fichero.Close()

	if _, err := fichero.Write(datos); err != nil {
		return fmt.Errorf("se corto el envio a %s: %w", recurso, err)
	}

	return nil
}

// porRed manda los bytes a una impresora con IP propia.
func (a *Agente) porRed(datos []byte, ip string, puerto int) error {
	if puerto == 0 {
		puerto = 9100
	}

	direccion := fmt.Sprintf("%s:%d", ip, puerto)

	conexion, err := net.DialTimeout("tcp", direccion, 5*time.Second)

	if err != nil {
		return fmt.Errorf(
			"la impresora %s no responde. Comprueba que está encendida y "+
				"conectada a la red", direccion)
	}
	defer conexion.Close()

	conexion.SetWriteDeadline(time.Now().Add(10 * time.Second))

	if _, err := conexion.Write(datos); err != nil {
		return fmt.Errorf("se cortó el envío a la impresora: %w", err)
	}

	return nil
}

func (a *Agente) fallo(trabajo api.Trabajo, motivo string) {
	if err := a.cliente.Confirmar(trabajo.ID, false, motivo); err != nil {
		a.registro.Printf("No se pudo avisar del fallo del trabajo %d: %v", trabajo.ID, err)
	}
}

// Comprobar valida la configuración antes de instalar el servicio.
func Comprobar(cfg *config.Config) (string, error) {
	cliente := api.Nuevo(cfg.URL, cfg.Token)

	saludo, err := cliente.Saludo()
	if err != nil {
		return "", err
	}

	return saludo.Empresa, nil
}
