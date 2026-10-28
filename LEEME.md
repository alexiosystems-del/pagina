# Sitio nuevo de Quique Marduk

Sitio en PHP simple (sin WordPress y sin base de datos), en español e inglés, con panel para administrar el catálogo.

- **Requisitos:** PHP 7.4 a 8.4 (recomendado 8.2 u 8.3) y Apache con `mod_rewrite` (el hosting actual, con DirectAdmin, lo tiene).
- **Datos:** el catálogo está en `data/productos.json`. Cada vez que se guarda algo, se hace una copia automática en `data/backups/` (se guardan las últimas 30).

## Cómo subirlo

1. **Hacer una copia de seguridad** del `public_html` actual (por ejemplo, renombrarlo a `public_html_viejo`).
2. Subir **el contenido** de la carpeta `sitio/` a `public_html/`, incluidos los archivos ocultos `.htaccess`.
3. En DirectAdmin, poner la versión de PHP en **8.2 u 8.3**.
4. Darle permiso de escritura al servidor sobre `data/` y `img/productos/` (normalmente ya lo tiene; si no, permisos 755).
5. **Enseguida** entrar a `https://www.marduk.com.ar/admin/` y crear la contraseña del panel. La primera persona que entra la crea, por eso hay que hacerlo apenas se sube.
6. Revisar que funcionen el inicio, el catálogo, una ficha de truco, el botón ES/EN y el panel.

## Probarlo en Render

1. Crear un repositorio en GitHub y subir **el contenido** de la carpeta `sitio/`. El `Dockerfile` tiene que quedar en la raíz del repositorio.
2. En Render: *New → Web Service*, conectar el repositorio, elegir **Language: Docker** y el plan *Free*.
3. Cuando termine el despliegue, abrir `https://<nombre>.onrender.com/` y después `/admin/` para crear la contraseña.

Ojo: en el plan gratis el disco se borra cada vez que el servicio se reinicia o se duerme (después de 15 minutos sin visitas). Los cambios hechos en el panel, las fotos subidas y la contraseña se pierden. Sirve para probar, no para dejarlo publicado. El ZIP para el hosting no incluye los archivos de Docker.

## El panel (`/admin/`)

- **Productos:** lista con buscador. El precio se cambia ahí mismo, con «Guardar precio». También se puede publicar u ocultar cada producto.
- **Nuevo / Editar:** nombre, descripción y aviso en español e inglés; precio; foto principal y fotos extra; videos de YouTube (un link por línea); producto destacado en el inicio.
  - Si un producto no tiene nombre en inglés, solo aparece en la versión en español.
  - En la descripción, una línea en blanco separa párrafos, `- ` al principio de una línea arma una lista y `**texto**` pone el texto en negrita.
- **Videos y ajustes:** videos de la página de inicio (por idioma) y cambio de contraseña.
- Si alguien se equivoca 5 veces seguidas con la contraseña, el ingreso desde esa conexión queda bloqueado 15 minutos.

## Lo que se cambió respecto del sitio viejo

- Se mantienen las mismas direcciones (`/portfolio/...`, `/acerca-de/`, `/en/trick-catalog/`, etc.), así no se pierden los enlaces que tiene Google.
- No hay formulario de email: el contacto es por WhatsApp (+54 9 11 5312-1510). El formulario de la página de contacto abre WhatsApp con el mensaje ya escrito.
- Los precios en dólares y en pesos se pasaron tal como estaban en el sitio viejo, con un formato unificado (`u$s 33` / `$ Arg. 47.000`). 50 de los 54 productos tienen precio en pesos; los otros 4 solo lo tenían en dólares. **Los precios en pesos probablemente están desactualizados**: conviene revisarlos desde el panel. El precio es el mismo en las dos versiones, así que en la versión en inglés también se ven los pesos.
- Los 101 trucos del WordPress (que estaban duplicados por idioma) quedaron como **54 productos bilingües**; 7 están solo en español.

## Para revisar

- «La carta en la frente» usa la foto de la Ruleta Rusa del Huevo (así estaba en el sitio viejo); hay que cambiarle la foto desde el panel.
- «Magical Mystery Trick» tenía u$s 20 en español y u$s 18 en inglés; quedó en u$s 20.
- «Pizarras Espiritistas MAGNETICAS miniatura (Blancas)» no tenía versión en inglés.
- La versión en inglés de «Triángulo de las Bermudas» tiene el título mal escrito («Bermiuda Triangle»); se corrige desde el panel.

## Regenerar desde el WordPress (solo para desarrollo)

`tools/extract.py` lee el `.sql` y `tools/migrate.py` arma `sitio/data/*.json` y copia las imágenes. **Si se vuelven a correr, pisan los cambios hechos desde el panel.**
