//go:build windows

package impresora

import (
	"fmt"
	"syscall"
	"unsafe"
)

// Impresión en Windows por la API del sistema.
//
// POR QUÉ NO SE ESCRIBE EL FICHERO Y YA
//
// Lo fácil sería copiar los bytes a \\PC\IMPRESORA. Funciona, pero
// Windows los pasa por el driver, que puede reinterpretarlos: los
// códigos ESC/POS de corte de papel y apertura de cajón acaban impresos
// como caracteres raros en lugar de ejecutarse.
//
// Con RAW se le dice al sistema que no toque nada y lo mande tal cual.
// Es lo que hace falta para que el papel se corte solo.

var (
	winspool = syscall.NewLazyDLL("winspool.drv")

	procOpenPrinter    = winspool.NewProc("OpenPrinterW")
	procClosePrinter   = winspool.NewProc("ClosePrinter")
	procStartDocPrinter = winspool.NewProc("StartDocPrinterW")
	procEndDocPrinter  = winspool.NewProc("EndDocPrinter")
	procStartPagePrinter = winspool.NewProc("StartPagePrinter")
	procEndPagePrinter = winspool.NewProc("EndPagePrinter")
	procWritePrinter   = winspool.NewProc("WritePrinter")
	procEnumPrinters   = winspool.NewProc("EnumPrintersW")
)

type docInfo struct {
	NombreDoc  *uint16
	NombreSal  *uint16
	TipoDatos  *uint16
}

type printerInfo2 struct {
	ServerName   *uint16
	PrinterName  *uint16
	ShareName    *uint16
	PortName     *uint16
	DriverName   *uint16
	Comment      *uint16
	Location     *uint16
	DevMode      uintptr
	SepFile      *uint16
	PrintProc    *uint16
	Datatype     *uint16
	Parameters   *uint16
	SecurityDesc uintptr
	Attributes   uint32
	Priority     uint32
	DefaultPrio  uint32
	StartTime    uint32
	UntilTime    uint32
	Status       uint32
	Jobs         uint32
	AveragePPM   uint32
}

// Listar devuelve las impresoras instaladas en este equipo.
//
// Incluye las locales y las conectadas por red, que es justo lo que el
// cliente ve en su Panel de control: así reconoce la suya sin dudar.
func Listar() ([]string, error) {
	const (
		nivel      = 2
		locales    = 0x00000002 // PRINTER_ENUM_LOCAL
		conexiones = 0x00000004 // PRINTER_ENUM_CONNECTIONS
	)

	var necesarios, devueltos uint32

	// Primera llamada: solo para saber cuánta memoria hace falta
	procEnumPrinters.Call(
		uintptr(locales|conexiones),
		0, uintptr(nivel), 0, 0,
		uintptr(unsafe.Pointer(&necesarios)),
		uintptr(unsafe.Pointer(&devueltos)),
	)

	if necesarios == 0 {
		return nil, fmt.Errorf("no se ha encontrado ninguna impresora instalada")
	}

	buffer := make([]byte, necesarios)

	ret, _, err := procEnumPrinters.Call(
		uintptr(locales|conexiones),
		0, uintptr(nivel),
		uintptr(unsafe.Pointer(&buffer[0])),
		uintptr(necesarios),
		uintptr(unsafe.Pointer(&necesarios)),
		uintptr(unsafe.Pointer(&devueltos)),
	)

	if ret == 0 {
		return nil, fmt.Errorf("no se pudieron listar las impresoras: %v", err)
	}

	nombres := make([]string, 0, devueltos)
	info := (*[1 << 16]printerInfo2)(unsafe.Pointer(&buffer[0]))

	for i := 0; i < int(devueltos); i++ {
		if info[i].PrinterName != nil {
			nombres = append(nombres, utf16APalabra(info[i].PrinterName))
		}
	}

	return nombres, nil
}

// Imprimir manda los bytes tal cual a la impresora.
func Imprimir(nombre string, datos []byte, descripcion string) error {
	if nombre == "" {
		return fmt.Errorf("no hay ninguna impresora elegida")
	}

	nombrePtr, err := syscall.UTF16PtrFromString(nombre)
	if err != nil {
		return err
	}

	var manejador syscall.Handle

	ret, _, errSis := procOpenPrinter.Call(
		uintptr(unsafe.Pointer(nombrePtr)),
		uintptr(unsafe.Pointer(&manejador)),
		0,
	)

	if ret == 0 {
		return fmt.Errorf(
			"no se puede abrir la impresora «%s». Comprueba que está encendida "+
				"y que sigue instalada en este equipo (%v)", nombre, errSis)
	}
	defer procClosePrinter.Call(uintptr(manejador))

	if descripcion == "" {
		descripcion = "CLIMACO POS"
	}

	docPtr, _ := syscall.UTF16PtrFromString(descripcion)

	// RAW: sin pasar por el driver, o los códigos de control se pierden
	tipoPtr, _ := syscall.UTF16PtrFromString("RAW")

	doc := docInfo{
		NombreDoc: docPtr,
		TipoDatos: tipoPtr,
	}

	ret, _, errSis = procStartDocPrinter.Call(
		uintptr(manejador), 1, uintptr(unsafe.Pointer(&doc)),
	)

	if ret == 0 {
		return fmt.Errorf("la impresora no acepta el trabajo: %v", errSis)
	}
	defer procEndDocPrinter.Call(uintptr(manejador))

	procStartPagePrinter.Call(uintptr(manejador))
	defer procEndPagePrinter.Call(uintptr(manejador))

	var escritos uint32

	ret, _, errSis = procWritePrinter.Call(
		uintptr(manejador),
		uintptr(unsafe.Pointer(&datos[0])),
		uintptr(len(datos)),
		uintptr(unsafe.Pointer(&escritos)),
	)

	if ret == 0 {
		return fmt.Errorf("no se pudo enviar el documento: %v", errSis)
	}

	if int(escritos) != len(datos) {
		return fmt.Errorf(
			"el documento se envió a medias (%d de %d bytes)", escritos, len(datos))
	}

	return nil
}

func utf16APalabra(p *uint16) string {
	if p == nil {
		return ""
	}

	var salida []uint16

	for i := 0; ; i++ {
		c := *(*uint16)(unsafe.Pointer(uintptr(unsafe.Pointer(p)) + uintptr(i)*2))

		if c == 0 {
			break
		}

		salida = append(salida, c)
	}

	return syscall.UTF16ToString(salida)
}
