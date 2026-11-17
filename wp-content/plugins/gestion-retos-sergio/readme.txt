=== Gestión de Retos Sergio Aristizábal ===
Stable tag: 1.3.6
Requires at least: 6.4
Requires PHP: 8.0
License: GPL-2.0-or-later

== Instalación ==

1. Respalda la base de datos e instala el ZIP desde Plugins → Agregar plugin → Subir plugin. Actívalo en el sitio que organizará el reto. Requiere MySQL o MariaDB con InnoDB.
2. Abre Retos en wp-admin. El plugin crea y enlaza las páginas permanentes Retos y Evaluación de retos. Si esos nombres de URL ya están en uso, WordPress asigna nombres únicos a las páginas nuevas.
3. En Integración con la portada indica el ID interno del contenedor de Elementor que va justo después de la banda. En la portada local de Sergio Aristizábal es 94a6ba6, el contenedor de Próximas presentaciones. También puedes dejar el ajuste vacío y colocar [sa_retos_banda] en un bloque o widget de shortcode de la portada.
4. Usa HTTPS y excluye de la caché HTML y del CDN las páginas con el menú principal y las dos páginas del plugin. La banda y el enlace «Participar» dependen del periodo de inscripción. El formulario público usa un token de seguridad por navegador.
5. Administra las cuentas desde Retos → Evaluadores. Cada cuenta inicia sesión en WordPress con su contraseña y solo recibe permiso para evaluar. El enlace a la evaluación aparece en Retos → Evaluadores (por defecto, /evaluacion-retos/). Los administradores también pueden evaluar, consultar el histórico y exportar CSV. La evaluación no necesita aparecer en un menú.

== Crear el siguiente reto ==

Abre Retos → Nuevo reto y completa el contenido, las dos imágenes, las fechas, las reglas, la edad mínima y las URL de los documentos aprobados. Agrega el enlace de Spotify y el mensaje de confirmación si corresponden. Configura los criterios, un nombre y un peso por línea separados por una barra vertical. El total de los pesos debe ser 100. Los participantes abren el formulario directamente desde «Participar». La evaluación requiere una cuenta autorizada de WordPress.

El plugin añade «Participar» como último enlace en las ubicaciones de menú principal y móvil de Astra mientras las inscripciones estén abiertas. El enlace desaparece automáticamente al cerrar el reto.

Las fechas se interpretan en America/Bogota. El día final está incluido completo. La banda de inicio muestra el periodo configurado, el premio opcional y una cuenta regresiva que se actualiza cada segundo y desaparece al cierre. Desactiva el reto anterior y activa el nuevo cuando esté listo. Puedes cerrar el formulario manualmente desde su ficha. Retos → Participantes y resultados conserva el acceso a los datos y CSV de cada concurso.

Cada criterio se puede calificar y guardar por separado. El sistema identifica al usuario que guardó cada nota y conserva el historial de cambios. La puntuación final y el ranking aparecen al completar todos los criterios. Las calificaciones conservan los pesos y la versión usados al guardarse por primera vez. La inscripción permite un enlace de publicación por cada una de TikTok, Instagram y Facebook; se requiere al menos uno válido. El ranking excluye inscripciones descartadas o sin puntuación, y ordena de mayor a menor. Los empates se resuelven por la fecha de inscripción y, si coincide el segundo, por el identificador de inscripción.

== Datos ==

El ZIP instala el código. Para trasladar datos existentes utiliza un respaldo de la base de datos y de la biblioteca de medios. Desactivar el plugin conserva los retos, las inscripciones y sus calificaciones. En entornos de desarrollo, el administrador puede borrar exclusivamente las inscripciones de un reto creado como demostración desde su ficha.
