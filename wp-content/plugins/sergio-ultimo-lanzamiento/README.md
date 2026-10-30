# Sergio Aristizábal – Último lanzamiento

Plugin para WordPress que añade una sección configurable de último lanzamiento para el Inicio.

## Qué administra

- Título de la canción.
- Descripción opcional.
- Foto seleccionada desde la Biblioteca de medios.
- Enlace de YouTube.
- Enlace de Spotify.

El video se inserta automáticamente desde el ID detectado en el enlace de YouTube. Se aceptan enlaces `watch`, `youtu.be`, `embed`, `shorts` y `live`.

## Instalación

1. En WordPress entra a `Plugins > Añadir nuevo > Subir plugin`.
2. Selecciona el archivo ZIP del plugin.
3. Instálalo y actívalo.
4. Ve a `Ajustes > Último lanzamiento`.
5. Completa los campos y pulsa `Guardar lanzamiento`.

## Cómo agregarlo con Elementor

1. Antes de editar, duplica o respalda la página Inicio.
2. Ve a `Páginas > Inicio > Editar con Elementor`.
3. Localiza la sección o contenedor que contiene “Biografía”.
4. Elimina solamente ese contenedor o sección, no la página completa.
5. Busca el widget `Sergio – Último lanzamiento` en la categoría `General`.
6. Arrástralo al mismo lugar donde estaba “Biografía”.
7. Pulsa `Actualizar` y revisa la versión desktop y móvil.

También puede insertarse con el widget `Shortcode` de Elementor usando:

```text
[sergio_ultimo_lanzamiento]
```

## Nota sobre el bloque de video existente

El plugin no borra automáticamente contenido de Elementor. Si el Inicio ya tiene otro video en la sección “Presentación”, conviene retirarlo o reutilizarlo para no mostrar dos videos principales.

## Compatibilidad

- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- Elementor es opcional: el shortcode funciona sin Elementor.
