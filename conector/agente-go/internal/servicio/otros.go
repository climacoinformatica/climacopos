//go:build !windows

package servicio

import "fmt"

const NombreServicio = "ClimacoPOSConector"

func InstalarServicio() error    { return fmt.Errorf("solo en Windows") }
func DesinstalarServicio() error { return fmt.Errorf("solo en Windows") }
func ServicioInstalado() bool    { return false }
func EsAdministrador() bool      { return false }
