# Borra todas las retenciones de hueco de todas las empresas.
# Util tras las pruebas del portal, si han quedado huecos bloqueados.
#
#   powershell -ExecutionPolicy Bypass -File herramientas\limpiar_retenciones.ps1

php artisan tinker --execute="App\Models\Empresa::all()->each(function(`$e){ tenancy()->initialize(`$e); `$n = App\Models\ReservaTemporal::query()->delete(); echo `$e->nombre_comercial . ': ' . `$n . ' retencion(es) borrada(s)' . PHP_EOL; tenancy()->end(); });"
