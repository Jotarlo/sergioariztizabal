# Agenda de Conciertos — Sergio Aristizábal

## Instalación

1. Comprima la carpeta `agenda-conciertos-sergio` en un archivo ZIP.
2. En WordPress vaya a **Plugins > Añadir nuevo > Subir plugin**, seleccione el ZIP y actívelo.
3. En el administrador aparecerá **Agenda de conciertos**.

## Uso

1. Entre a **Agenda de conciertos > Agregar concierto**. El título identifica el registro internamente.
2. Defina fecha, municipio, departamento (opcional), tipo público/privado y visibilidad.
3. En **Agenda de conciertos > Diseño de agenda** cambie el fondo, el título, el mensaje, el botón y cuántos conciertos se muestran.
4. Añada el shortcode `[agenda_conciertos_sergio]` en Elementor o en el editor de WordPress, justo debajo del banner principal y antes del módulo de Spotify.

Opcionalmente, `[agenda_conciertos_sergio limit="5"]` sustituye el número de conciertos definido en Diseño de agenda solo para ese lugar.

Los conciertos se muestran automáticamente desde la fecha actual hacia adelante y por orden de fecha. Cada fila es informativa: no incluye flecha ni enlace a una página de detalle.
