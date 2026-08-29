//go:build windows

package main

import (
	"fmt"
	"syscall"
	"unsafe"

	"climaco/agente/internal/config"
	"climaco/agente/internal/impresora"
	"climaco/agente/internal/servicio"
)

// Ventana de instalación.
//
// POR QUÉ NO SE USA UNA LIBRERÍA DE INTERFAZ
//
// Las librerías de ventanas para Go añaden dependencias y bastante peso
// al ejecutable, y aquí solo hacen falta tres diálogos: elegir impresora,
// confirmar e informar. Con las funciones que ya trae Windows se resuelve
// sin dependencias, y el ejecutable se queda en pocos megas.
//
// Menos peso también significa menos sospecha del antivirus, que sin
// firma de código es una preocupación real.

var (
	user32 = syscall.NewLazyDLL("user32.dll")

	procMessageBox = user32.NewProc("MessageBoxW")
)

const (
	mbOK              = 0x00000000
	mbOKCancel        = 0x00000001
	mbYesNo           = 0x00000004
	mbIconError       = 0x00000010
	mbIconPregunta    = 0x00000020
	mbIconAviso       = 0x00000030
	mbIconInformacion = 0x00000040

	respuestaOK  = 1
	respuestaSi  = 6
)

const titulo = "CLIMACO POS · Conector de impresión"

// abrirVentana es lo que ve el cliente al hacer doble clic.
func abrirVentana(cfg *config.Config) {

	// ---- ¿Ya está puesto?
	if servicio.ServicioInstalado() {
		if !preguntar(
			"El conector ya está instalado en este equipo.\n\n" +
				"¿Quieres volver a configurarlo?\n\n" +
				"Elige «No» si solo querías comprobar que estaba.") {
			return
		}
	}

	// ---- Comprobar que el salón responde
	nombreSalon, err := servicio.Comprobar(cfg)

	if err != nil {
		avisarYSalir(
			"No se ha podido contactar con tu salón.\n\n" +
				err.Error() + "\n\n" +
				"Comprueba que este equipo tiene internet e inténtalo de nuevo.")
	}

	// ---- Elegir impresora
	impresoras, err := impresora.Listar()

	if err != nil || len(impresoras) == 0 {
		avisarYSalir(
			"No se ha encontrado ninguna impresora en este equipo.\n\n" +
				"Instálala primero en Windows, comprueba que aparece en\n" +
				"Configuración → Impresoras, y vuelve a abrir este programa.")
	}

	elegida := elegirImpresora(impresoras, cfg.Impresora)

	if elegida == "" {
		return // canceló
	}

	if err := cfg.GuardarImpresora(elegida); err != nil {
		avisarYSalir("No se pudo guardar la impresora elegida:\n\n" + err.Error())
	}

	// ---- Instalar
	if err := servicio.InstalarServicio(); err != nil {
		avisarYSalir(
			"No se pudo dejar el conector arrancando solo.\n\n" +
				err.Error() + "\n\n" +
				"Prueba a desactivar temporalmente el antivirus e inténtalo otra vez.")
	}

	avisar(fmt.Sprintf(
		"Listo.\n\n"+
			"Salón: %s\n"+
			"Impresora: %s\n\n"+
			"Los tickets saldrán solos al cobrar.\n\n"+
			"El conector se abre solo cada vez que enciendas el ordenador,\n"+
			"así que no hay que hacer nada más. Puedes cerrar esta ventana.",
		nombreSalon, elegida))
}

// elegirImpresora enseña la lista y devuelve la elegida.
//
// Se usan diálogos numerados en vez de un desplegable: es menos vistoso,
// pero funciona en cualquier Windows sin librerías, y para elegir entre
// tres o cuatro impresoras basta de sobra.
func elegirImpresora(lista []string, actual string) string {
	texto := "¿Por cuál se imprimen los tickets?\n\n"

	for i, nombre := range lista {
		marca := "  "

		if nombre == actual {
			marca = "→ " // la que ya estaba elegida
		}

		texto += fmt.Sprintf("%s%d. %s\n", marca, i+1, nombre)
	}

	texto += "\nSe irán mostrando una a una. Pulsa «Sí» en la tuya."

	avisar(texto)

	for _, nombre := range lista {
		if preguntar(fmt.Sprintf(
			"¿Es esta tu impresora de tickets?\n\n%s\n\n"+
				"Sí = elegir esta\n"+
				"No = ver la siguiente", nombre)) {
			return nombre
		}
	}

	avisar(
		"No has elegido ninguna impresora, así que no se ha instalado nada.\n\n" +
			"Vuelve a abrir el programa cuando sepas cuál es la tuya.")

	return ""
}

// ---------------------------------------------------------------- Diálogos

func mensaje(texto string, banderas uint32) int {
	textoPtr, _ := syscall.UTF16PtrFromString(texto)
	tituloPtr, _ := syscall.UTF16PtrFromString(titulo)

	ret, _, _ := procMessageBox.Call(
		0,
		uintptr(unsafe.Pointer(textoPtr)),
		uintptr(unsafe.Pointer(tituloPtr)),
		uintptr(banderas),
	)

	return int(ret)
}

func avisar(texto string) {
	mensaje(texto, mbOK|mbIconInformacion)
}

func avisarYSalir(texto string) {
	mensaje(texto, mbOK|mbIconAviso)
	exitAhora()
}

func preguntar(texto string) bool {
	return mensaje(texto, mbYesNo|mbIconPregunta) == respuestaSi
}
