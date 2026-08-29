//go:build windows

package servicio

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"
)

// Arranque automático con el usuario.
//
// POR QUÉ NO ES UN SERVICIO DE WINDOWS
//
// Lo intentamos y Windows lo mataba con el error 1053: espera que un
// servicio responda a su protocolo de control en unos segundos, y eso
// obliga a una librería y bastante código añadido.
//
// Arrancar con el usuario resuelve lo mismo con menos piezas:
//
//   - No hace falta ejecutar como administrador para instalarlo
//   - Si algo falla, el cliente ve un icono en lugar de un servicio
//     invisible que nadie sabe mirar
//   - Un antivirus se pone menos nervioso con un programa de usuario
//     que con uno que se instala como servicio del sistema
//
// La contrapartida: necesita que alguien inicie sesión en Windows. En un
// salón el ordenador está encendido y con la sesión abierta todo el día,
// así que en la práctica da igual.

const NombreEntrada = "ClimacoPOSConector"

// rutaArranque devuelve la carpeta de Inicio del usuario.
//
// Se usa un acceso directo en esa carpeta en vez de una entrada en el
// registro: es visible, el cliente puede quitarlo él mismo desde el
// Administrador de tareas, y no levanta las sospechas que sí levanta
// escribir en Run del registro, que es lo que hace el malware.
func rutaArranque() (string, error) {
	appdata := os.Getenv("APPDATA")

	if appdata == "" {
		return "", fmt.Errorf("no se encuentra la carpeta del usuario")
	}

	return filepath.Join(appdata,
		"Microsoft", "Windows", "Start Menu", "Programs", "Startup"), nil
}

func rutaAcceso() (string, error) {
	carpeta, err := rutaArranque()
	if err != nil {
		return "", err
	}

	return filepath.Join(carpeta, "CLIMACO POS Conector.lnk"), nil
}

// InstalarServicio deja el conector arrancando con Windows y lo lanza.
func InstalarServicio() error {
	ejecutable, err := os.Executable()
	if err != nil {
		return err
	}

	ejecutable, _ = filepath.Abs(ejecutable)

	acceso, err := rutaAcceso()
	if err != nil {
		return err
	}

	if err := crearAccesoDirecto(acceso, ejecutable); err != nil {
		return fmt.Errorf("no se pudo crear el arranque automático: %w", err)
	}

	// Arrancarlo ya, sin esperar al próximo reinicio
	return Arrancar()
}

// crearAccesoDirecto genera el .lnk con PowerShell.
//
// Crear un acceso directo en Go requiere COM y bastante código. Con
// PowerShell son cuatro líneas y está en todos los Windows desde el 7.
func crearAccesoDirecto(destino, ejecutable string) error {
	guion := fmt.Sprintf(
		`$s = (New-Object -ComObject WScript.Shell).CreateShortcut('%s'); `+
			`$s.TargetPath = '%s'; `+
			`$s.Arguments = '--servicio'; `+
			`$s.WorkingDirectory = '%s'; `+
			`$s.Description = 'Conector de impresion de CLIMACO POS'; `+
			`$s.Save()`,
		destino, ejecutable, filepath.Dir(ejecutable))

	cmd := exec.Command("powershell", "-NoProfile", "-NonInteractive", "-Command", guion)

	// Sin ventana negra parpadeando
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}

	if salida, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("%s", strings.TrimSpace(string(salida)))
	}

	return nil
}

// Arrancar lanza el conector en segundo plano.
func Arrancar() error {
	ejecutable, err := os.Executable()
	if err != nil {
		return err
	}

	// Si ya estaba corriendo, se para antes para no duplicar
	_ = Parar()

	cmd := exec.Command(ejecutable, "--servicio")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("no se pudo arrancar: %w", err)
	}

	/**
	 * Se suelta el proceso hijo.
	 *
	 * Sin esto, al cerrar la ventana de instalación se llevaría por
	 * delante el conector que acaba de arrancar.
	 */
	_ = cmd.Process.Release()

	return nil
}

// Parar cierra el conector si está corriendo.
func Parar() error {
	ejecutable, err := os.Executable()
	if err != nil {
		return err
	}

	nombre := filepath.Base(ejecutable)

	cmd := exec.Command("taskkill", "/F", "/IM", nombre, "/FI", "PID ne "+
		fmt.Sprint(os.Getpid()))

	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}

	return cmd.Run()
}

// DesinstalarServicio quita el arranque automático y para el conector.
func DesinstalarServicio() error {
	acceso, err := rutaAcceso()
	if err != nil {
		return err
	}

	_ = os.Remove(acceso)

	return Parar()
}

// ServicioInstalado dice si el arranque automático está puesto.
func ServicioInstalado() bool {
	acceso, err := rutaAcceso()
	if err != nil {
		return false
	}

	_, err = os.Stat(acceso)

	return err == nil
}

// EsAdministrador ya no hace falta, pero se conserva porque el resto del
// código la consulta. Arrancando con el usuario no se necesitan permisos.
func EsAdministrador() bool {
	return true
}
