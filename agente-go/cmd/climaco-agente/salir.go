package main

import "os"

// exitAhora existe para que las funciones de aviso puedan cortar la
// ejecución sin que cada llamada tenga que acordarse de hacerlo.
func exitAhora() {
	os.Exit(1)
}
