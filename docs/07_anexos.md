# Anexos

## I. Encuesta individualizada de recomendación de cursos
Con el objetivo de mejorar la experiencia de nuestros usuarios y facilitar la decisión de apuntarse, he planteado la implementación de una herramienta interactiva de diagnóstico. Inspirada en los cuestionarios de recomendación de estilo "BuzzFeed", esta encuesta permitirá a los usuarios indecisos descubrir qué itinerario formativo se adapta mejor a sus habilidades actuales, gustos culinarios y disponibilidad de tiempo.
Esta encuesta también actúa como un filtro que evita la frustración del alumno al matricularse en un curso que no corresponde a su nivel, aumentando así la tasa de éxito y satisfacción.

### I.1. Estructura y Lógica de la Encuesta
La encuesta se compone de una serie de preguntas cerradas (tipo test) con respuestas ponderadas. No se trata de un examen, sino de un perfilado de preferencias. La lógica interna asigna puntos a diferentes categorías de cursos en función de la respuesta elegida.

### I.2. Categorías de preguntas
Para que la recomendación sea precisa, el cuestionario abordará cuatro ejes principales:
- Nivel de destreza: Preguntas sobre el manejo de cuchillos o conocimientos de técnicas básicas (sofritos, fondos, puntos de cocción).
- Preferencias dietéticas: Identificación de gustos (repostería, cocina internacional, cocina vegana o carnes).
- Objetivo personal: Si el usuario busca cocinar para su día a día, profesionalizarse o simplemente como un hobby de fin de semana.
- Disponibilidad: Tiempo que puede dedicar al curso (intensivo o semanal).

### I.3. Funcionamiento del Algoritmo de Recomendación
El sistema no requiere de una inteligencia artificial compleja, sino de un motor de reglas sencillo programado en el lado del servidor (PHP).
Al finalizar el test, el script suma los valores de cada respuesta. Por ejemplo, si un usuario marca que tiene poco tiempo pero le apasiona el dulce, el sistema cruzará estos datos y le redirigirá automáticamente a la ficha del curso "Repostería Express: Postres en 30 minutos".

### I.4. Integración con la Infraestructura Actual
Para que este módulo sea funcional dentro de nuestro entorno ASIR, se requieren tres integraciones clave:
1. Base de Datos (GBD): He preparado una tabla opcional para almacenar de forma anónima las respuestas más frecuentes. Esto nos permitirá extraer estadísticas sobre qué tipo de cocina es la más demandada y así planificar nuevos cursos en el futuro.
2. Interfaz Web (IAW): El cuestionario se desarrollará utilizando componentes dinámicos que no recarguen la página por cada pregunta, ofreciendo una navegación fluida y visualmente atractiva.
3. Seguridad (SAD): Las respuestas se procesan de forma temporal en la sesión del usuario. No se solicitan datos personales hasta que el sistema ofrece el resultado, garantizando la privacidad del navegante según la normativa vigente.

### I.5. Ejemplo de Flujo de Usuario
Un flujo típico de la encuesta sería el siguiente:
1. El usuario accede al banner "¿No sabes por dónde empezar? Haz nuestro test".
2. Responde a 5 preguntas rápidas con imágenes ilustrativas.
3. El sistema muestra una barra de carga animada (generando expectativa).
4. Se presenta el resultado: "Tu curso ideal es: Iniciación a la Cocina Japonesa".
5. Se ofrece un botón directo a la matrícula con un código de descuento por haber completado la encuesta.

---

## II. Formulario de Candidatura y Gestión de CVs
Dada la ambición de crecimiento de la plataforma, es fundamental contar con un canal oficial para la incorporación de nuevos profesionales. Se ha diseñado un apartado específico denominado "Únete a nuestro equipo", donde cocineros y expertos gastronómicos pueden postularse como instructores.
Este modelo constará de una encuesta de perfilado profesional que recoge los siguientes datos:
- Especialidad principal: Selección entre cocina de vanguardia, tradicional, repostería, nutrición, etc.
- Experiencia previa: Años de trayectoria en el sector servicios o formación.
- Carga de documentos: Un sistema de subida de archivos para el Currículum Vitae y, opcionalmente, un portafolio fotográfico de sus creaciones.

### II.1. Tratamiento Técnico y Seguridad de las Candidaturas
Desde la perspectiva de la administración de sistemas (ASIR), este apartado introduce retos que he abordado de la siguiente manera:
1. Almacenamiento Seguro: Los archivos (PDF/JPG) no se almacenan directamente en la base de datos para no comprometer el rendimiento de las consultas SQL. En su lugar, se guardan en un directorio aislado del servidor de archivos, almacenando en la base de datos únicamente la ruta lógica del archivo.
2. Validación de Archivos: Para evitar la subida de scripts maliciosos que pudieran ejecutar un ataque de Remote Code Execution, el sistema web validará estrictamente que el tipo de archivo sea un documento legítimo antes de permitir su escritura en el disco.
3. Privacidad de Datos Sensibles: Siguiendo la normativa de protección de datos, los currículums recibidos se cifrarán al no ser usados y tendrán un periodo de caducidad automática de 12 meses, tras el cual el sistema eliminará el archivo y el registro cumpliendo con el "derecho al olvido" como he aprendido en los módulos de Digitalización e IPE.

### II.2. Integración con el Flujo de Administración
Una vez que un cocinero completa su encuesta de postulación, el administrador del sistema recibe una notificación automática. Si el perfil es validado, los datos de la encuesta se pueden "promocionar" directamente a la tabla cocineros de la base de datos de producción mediante un procedimiento almacenado, evitando tener que introducir los datos manualmente y agilizando la expansión de la oferta docente de la web.
