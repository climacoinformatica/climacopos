package main

import (
	"errors"
	"flag"
	"log"
	"os"
	"path/filepath"

	"climaco/agente/internal/config"
	"climaco/agente/internal/servicio"
)

// CLIMACO POS · Conector de impresión
//
// Un solo ejecutable con dos vidas:
//
//   Sin argumentos     abre la ventana de instalación, que es lo que ve
//                      el cliente al hacer doble clic
//
//   Con --servicio     corre en segundo plano imprimiendo. Es como lo
//                      arranca Windows, y nunca lo lanza una persona
//
// La URL y el token vienen INCRUSTADOS en el propio fichero: el servidor
// los añade al final cuando el salón lo descarga desde su panel. El
// cliente no teclea nada ni sabe que existen.

func main() {
	modoServicio := flag.Bool("servicio", false, "correr en segundo plano")
	desinstalar := flag.Bool("desinstalar", false, "quitar el conector")

	flag.Parse()

	cfg, err := config.Cargar()

	if err != nil {
		if errors.Is(err, config.ErrSinIncrustar) {
			avisarYSalir(
				"Este programa no sirve tal cual.\n\n" +
					"Hay que descargarlo desde el panel de tu salón, en\n" +
					"Ajustes → Impresora, para que sepa a qué salón pertenece.\n\n" +
					"Si te lo ha pasado alguien por correo o WhatsApp,\n" +
					"no funcionará: cada salón tiene el suyo.")
		}

		avisarYSalir("No se pudo arrancar el conector:\n\n" + err.Error())
	}

	if *desinstalar {
		desinstalarTodo()
		return
	}

	if *modoServicio {
		correrComoServicio(cfg)
		return
	}

	// Doble clic: el cliente quiere instalarlo o cambiar la impresora
	abrirVentana(cfg)
}

// correrComoServicio es lo que ejecuta Windows en segundo plano.
func correrComoServicio(cfg *config.Config) {
	registro := abrirRegistro()

	agente := servicio.Nuevo(cfg, registro)

	/**
	 * Se ejecuta directamente, sin envoltorio de servicio de Windows.
	 *
	 * Windows espera que un servicio responda a su protocolo de control
	 * en unos segundos o lo mata. Aquí se evita ese requisito
	 * arrancándolo con sc.exe y dejando que Windows lo reinicie si cae,
	 * que es lo que ya está configurado en la instalación.
	 *
	 * Es menos ortodoxo, pero elimina toda una clase de fallos difíciles
	 * de diagnosticar en el ordenador de un cliente al que no puedes
	 * acceder.
	 */
	agente.Ejecutar()
}

// abrirRegistro deja el fichero de registro junto al ejecutable.
func abrirRegistro() *log.Logger {
	ruta, err := os.Executable()

	if err != nil {
		return log.New(os.Stdout, "", log.LstdFlags)
	}

	fichero := filepath.Join(filepath.Dir(ruta), "climaco-agente.log")

	/**
	 * Se rota al llegar a 5 MB.
	 *
	 * Un salón que lleve meses sin internet acumularía cientos de megas
	 * de «sin conexión», y ese fichero acaba siendo el problema en lugar
	 * de la ayuda.
	 */
	if info, err := os.Stat(fichero); err == nil && info.Size() > 5*1024*1024 {
		_ = os.Rename(fichero, fichero+".old")
	}

	f, err := os.OpenFile(fichero, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)

	if err != nil {
		return log.New(os.Stdout, "", log.LstdFlags)
	}

	return log.New(f, "", log.LstdFlags)
}

func desinstalarTodo() {
	if err := servicio.DesinstalarServicio(); err != nil {
		avisarYSalir("No se pudo quitar el conector.\n\n" + err.Error())
	}

	avisar("El conector se ha quitado de este equipo.\n\n" +
		"Los tickets dejarán de imprimirse hasta que lo instales de nuevo.")
}
