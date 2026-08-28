//go:build !windows

package main

import (
	"fmt"
	"os"

	"climaco/agente/internal/config"
)

// El conector solo tiene sentido en Windows: es donde estan las
// impresoras de los salones. Esto existe para que el codigo compile en
// Linux y se puedan hacer comprobaciones desde el servidor.

func abrirVentana(cfg *config.Config) {
	fmt.Println("El conector de impresion solo funciona en Windows.")
	os.Exit(1)
}

func avisar(texto string) {
	fmt.Println(texto)
}

func avisarYSalir(texto string) {
	fmt.Fprintln(os.Stderr, texto)
	os.Exit(1)
}
