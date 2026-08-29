//go:build !windows

package impresora

import "fmt"

// Solo para que compile fuera de Windows.

func Listar() ([]string, error) {
	return nil, fmt.Errorf("solo disponible en Windows")
}

func Imprimir(nombre string, datos []byte, descripcion string) error {
	return fmt.Errorf("solo disponible en Windows")
}
