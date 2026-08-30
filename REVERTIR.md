# Revertir las acciones de caja del TPV

Deja el proyecto como estaba antes de los cambios de hoy, y añade el enlace
de `tpv-caja.css` que faltaba en el layout.

## 1. Descomprimir

```bash
cd /var/www/climacopos
unzip -o climacopos_revertir.zip
```

Sobrescribe seis ficheros, devolviéndolos al estado del commit `5ba140f`:

- `resources/views/panel/tpv/index.blade.php` (vuelve a 310 líneas, sin botones)
- `resources/views/panel/app.blade.php` (original **+ el enlace de tpv-caja.css**)
- `routes/tenant.php`
- `app/Support/Permisos.php`
- `app/Services/ConstructorTicket.php`
- `app/Services/GestorImpresion.php`

## 2. Borrar los ficheros que añadí

```bash
cd /var/www/climacopos
rm -f app/Http/Controllers/Panel/TpvAccionesController.php
rm -f database/migrations/tenant/2026_08_29_100000_permiso_informes_caja_tpv.php
rm -f app/Console/Commands/SincronizarPermisos.php
rm -rf public/img/tpv
```

## 3. Limpiar cachés

```bash
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

## 4. Quitar el permiso de la base de datos

La migración añadió `tpv.informes_caja` a los perfiles. Al revertir `Permisos.php`
ese permiso deja de existir en el catálogo, así que conviene sacarlo:

```bash
php artisan tinker --execute="
\App\Models\Empresa::find(5)->run(function () {
    foreach (\App\Models\Perfil::all() as \$p) {
        \$permisos = array_values(array_diff(\$p->permisos ?? [], ['tpv.informes_caja']));
        if (\$permisos !== (\$p->permisos ?? [])) { \$p->permisos = \$permisos; \$p->save(); }
    }
});
"
```

## Aviso

`public/css/tpv-caja.css` define clases `.tpv-caja` y `.tpv-caja__boton` que no
usa ningún fichero del proyecto. Enlazarlo no hace que aparezca ningún botón:
solo carga una hoja de estilos sin destinatario. Para que sirva de algo hace
falta escribir el marcado y las rutas, que nunca llegaron a existir.

Quedan también sin usar, por si quieres limpiarlos:

- `app/Http/Controllers/Panel/metodos_caja.txt`
- `app/Services/metodos_impresion.txt`
- `resources/views/panel/caja/informe-x.blade.php` (sin ruta)
- `CajaController::informeX()` e `informeXImprimir()` (sin ruta)
- `GestorCierre::informeX()` (sin quien lo llame)
