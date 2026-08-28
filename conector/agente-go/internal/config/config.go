package config

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

// Config son los datos que el agente necesita para arrancar.
//
// La URL y el token vienen INCRUSTADOS en el ejecutable: el servidor los
// añade al final del fichero cuando el salón lo descarga desde su panel.
// Así el cliente no teclea nada, ni sabe que existe un token.
//
// La impresora sí se guarda aparte, porque la elige él al instalar.
type Config struct {
	URL       string `json:"url"`
	Token     string `json:"token"`
	Impresora string `json:"impresora"`
	Salon     string `json:"salon"`
}

// marca separa el ejecutable de los datos incrustados.
//
// Se busca desde el final del fichero, así que da igual lo que ocupe el
// programa: no hay que saber su tamaño ni recompilar por cada salón.
const marca = "\n---CLIMACO-CONFIG---\n"

// nombreFichero guarda la impresora elegida, junto al ejecutable.
const nombreFichero = "climaco-agente.json"

var (
	// ErrSinIncrustar significa que el .exe se copió a mano en lugar de
	// descargarse del panel. Es un caso real: alguien pasa el fichero por
	// WhatsApp a otro salón y no funciona, con razón.
	ErrSinIncrustar = errors.New(
		"este programa no se descargó desde el panel del salón")
)

// Cargar lee la configuración: primero lo incrustado, luego el fichero.
func Cargar() (*Config, error) {
	cfg, err := leerIncrustado()
	if err != nil {
		return nil, err
	}

	// La impresora elegida vive aparte, porque se puede cambiar
	if datos, err := os.ReadFile(rutaFichero()); err == nil {
		var guardado Config

		if json.Unmarshal(datos, &guardado) == nil {
			cfg.Impresora = guardado.Impresora
		}
	}

	return cfg, nil
}

// leerIncrustado saca la URL y el token del propio ejecutable.
func leerIncrustado() (*Config, error) {
	ruta, err := os.Executable()
	if err != nil {
		return nil, fmt.Errorf("no se encuentra el propio programa: %w", err)
	}

	datos, err := os.ReadFile(ruta)
	if err != nil {
		return nil, fmt.Errorf("no se puede leer el programa: %w", err)
	}

	pos := strings.LastIndex(string(datos), marca)

	if pos == -1 {
		return nil, ErrSinIncrustar
	}

	bruto := datos[pos+len(marca):]

	var cfg Config

	if err := json.Unmarshal(bruto, &cfg); err != nil {
		return nil, fmt.Errorf("los datos del salón están dañados: %w", err)
	}

	if cfg.URL == "" || cfg.Token == "" {
		return nil, ErrSinIncrustar
	}

	// Sin barra final, que si no todas las llamadas salen con doble barra
	cfg.URL = strings.TrimRight(cfg.URL, "/")

	return &cfg, nil
}

// GuardarImpresora recuerda la que eligió el cliente.
func (c *Config) GuardarImpresora(nombre string) error {
	c.Impresora = nombre

	datos, err := json.MarshalIndent(c, "", "  ")
	if err != nil {
		return err
	}

	return os.WriteFile(rutaFichero(), datos, 0o600)
}

// rutaFichero devuelve dónde se guarda la impresora elegida.
//
// Junto al ejecutable y no en AppData: el servicio de Windows corre como
// SYSTEM, que tiene otro AppData distinto del usuario que instaló. Es un
// fallo clásico y desconcertante, porque la impresora se guarda al
// instalar y luego el servicio no la encuentra.
func rutaFichero() string {
	ruta, err := os.Executable()
	if err != nil {
		return nombreFichero
	}

	return filepath.Join(filepath.Dir(ruta), nombreFichero)
}
