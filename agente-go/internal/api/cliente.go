package api

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

// Cliente habla con el servidor del salón.
type Cliente struct {
	url   string
	token string
	http  *http.Client
}

// Trabajo es un documento pendiente de imprimir.
type Trabajo struct {
	ID          int    `json:"id"`
	Tipo        string `json:"tipo"`
	Destino     string `json:"destino"`
	Descripcion string `json:"descripcion"`
	Payload     string `json:"payload"` // base64
}

// Config es el hardware de este terminal, tal y como está en el panel.
//
// Viene del servidor en cada sondeo: así el salón cambia la impresora
// desde el panel y el agente se entera sin reinstalar nada.
type Config struct {
	ImpresoraModo   string `json:"impresora_tickets_modo"`
	ImpresoraIP     string `json:"impresora_tickets_ip"`
	ImpresoraPuerto int    `json:"impresora_tickets_puerto"`
	ImpresoraLocal  string `json:"impresora_tickets_local"`

	CajonModo   string `json:"cajon_modo"`
	CajonPuerto string `json:"cajon_puerto"`

	IntervaloMs int `json:"intervalo_ms"`
}

type respuestaTrabajos struct {
	Trabajos []Trabajo `json:"trabajos"`
	Config   Config    `json:"config"`
}

type respuestaSaludo struct {
	OK       bool   `json:"ok"`
	Empresa  string `json:"empresa"`
	Config   Config `json:"config"`
	Terminal struct {
		Nombre string `json:"nombre"`
		Codigo string `json:"codigo"`
	} `json:"terminal"`
}

func Nuevo(url, token string) *Cliente {
	return &Cliente{
		url:   url,
		token: token,
		http: &http.Client{
			// Corto a propósito: si el salón se queda sin internet, es
			// mejor fallar rápido y reintentar que dejar el sondeo
			// colgado medio minuto.
			Timeout: 15 * time.Second,
		},
	}
}

// Saludo comprueba que el token vale y devuelve la configuración.
func (c *Cliente) Saludo() (*respuestaSaludo, error) {
	cuerpo, err := c.pedir("GET", "/agente/saludo", nil)
	if err != nil {
		return nil, err
	}

	var r respuestaSaludo

	if err := json.Unmarshal(cuerpo, &r); err != nil {
		return nil, fmt.Errorf("respuesta que no se entiende: %w", err)
	}

	return &r, nil
}

// Trabajos recoge lo que haya pendiente.
func (c *Cliente) Trabajos() ([]Trabajo, Config, error) {
	cuerpo, err := c.pedir("GET", "/agente/trabajos", nil)
	if err != nil {
		return nil, Config{}, err
	}

	var r respuestaTrabajos

	if err := json.Unmarshal(cuerpo, &r); err != nil {
		return nil, Config{}, fmt.Errorf("respuesta que no se entiende: %w", err)
	}

	return r.Trabajos, r.Config, nil
}

// Confirmar avisa al servidor de cómo fue.
//
// Importa hacerlo también cuando falla: así el error sale en el panel del
// salón en lugar de quedarse en un registro que nadie mira.
func (c *Cliente) Confirmar(id int, ok bool, motivo string) error {
	datos, _ := json.Marshal(map[string]any{
		"ok":    ok,
		"error": motivo,
	})

	_, err := c.pedir("POST", fmt.Sprintf("/agente/trabajos/%d/confirmar", id), datos)

	return err
}

// Contenido descodifica el documento listo para la impresora.
func (t Trabajo) Contenido() ([]byte, error) {
	return base64.StdEncoding.DecodeString(t.Payload)
}

func (c *Cliente) pedir(metodo, ruta string, cuerpo []byte) ([]byte, error) {
	var lector io.Reader

	if cuerpo != nil {
		lector = bytes.NewReader(cuerpo)
	}

	peticion, err := http.NewRequest(metodo, c.url+ruta, lector)
	if err != nil {
		return nil, err
	}

	peticion.Header.Set("X-Agente-Token", c.token)
	peticion.Header.Set("Accept", "application/json")

	if cuerpo != nil {
		peticion.Header.Set("Content-Type", "application/json")
	}

	respuesta, err := c.http.Do(peticion)
	if err != nil {
		return nil, fmt.Errorf("no se pudo contactar con el salón: %w", err)
	}
	defer respuesta.Body.Close()

	datos, err := io.ReadAll(respuesta.Body)
	if err != nil {
		return nil, err
	}

	// Mensajes en cristiano para los errores que se van a ver de verdad
	switch respuesta.StatusCode {
	case http.StatusOK:
		return datos, nil

	case http.StatusUnauthorized, http.StatusForbidden:
		return nil, fmt.Errorf(
			"el salón no reconoce este conector: vuelve a descargarlo desde tu panel")

	case http.StatusNotFound:
		return nil, fmt.Errorf("la dirección del salón no responde")

	default:
		return nil, fmt.Errorf("el salón respondió con un error %d", respuesta.StatusCode)
	}
}
