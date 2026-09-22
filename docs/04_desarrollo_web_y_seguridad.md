# Capítulo 4. Desarrollo (Web y Seguridad)

## 4.1. Gestión de Bases de Datos
### 4.1.1. Diseño del Modelo Entidad-Relación
La base de datos vendria siendo la parte mas importante de nuestra web teniendo que tener en cuenta los proveedores de los productos, los cocinero, los clientes,etc. Para procurar que no haya redundancia de datos y que las consultas sean rápidas, además de aplicar un proceso de normalización.

**Diccionario de Datos**
A continuación, se detallan las tablas principales que componen el esquema db_cocina (el código SQL completo se encuentra en la carpeta `database/db_cocina.sql`):

| Tabla | Campo Principal | Tipo de Dato | Restricción |
| :--- | :--- | :--- | :--- |
| usuarios | id_user | INT (PK) | AUTO_INCREMENT |
| cocineros | id_cocinero | INT (PK) | AUTO_INCREMENT |
| proveedores | id_proveedor | INT (PK) | AUTO_INCREMENT |
| pedidos | id_pedido | INT (PK) | FK (proveedores) |
| cursos | id_curso | INT (PK) | NOT NULL |
| inscripciones | id_ins | INT (PK) | FK (user, curso, cocinero) |
| recetas | id_receta | INT (PK) | Relación 1:N con curso |

*Tabla 3.2: Diccionario de datos simplificado del sistema.*

### 4.1.2. Optimización y Consultas Avanzadas
Para mejorar el rendimiento de la plataforma web, decidí que se implementarán Indices en los campos que se consultaran con mayor frecuencia, como el email del usuario y el título del curso. Además, se definen vistas generales para que el departamento de administración pueda ver el total de ingresos por curso sin acceder a los datos sensibles de los alumnos.

## 4.2. Administración de Sistemas Operativos
### 4.2.1. Automatización de Tareas con Bash Scripting
Para facilitar el buscar información y reducir errores optaremos por usar scripts. En este proyecto se automatizan las tareas críticas de mantenimiento mediante scripts ejecutados por el tecnico que los implementara de manera automatica ne segundo plano o CRON.
- Script de Copias de Seguridad (Backup): Queremos implementar un script que realiza un volcado de la base de datos y comprime los archivos de la web.

### 4.2.2. Gestión de Usuarios y Cuotas de Disco
En un entorno multi-usuario (varios chefs subiendo contenido que se almacenara), es vital limitar el espacio en disco para evitar que un usuario llene sin querer la capacidad por algun error.
- Grupos: Se crean los grupos chefs y alumnos con permisos diferenciados.
- Cuotas: Se activa el módulo quota de Linux para limitar a los chefs a 5GB de almacenamiento de vídeos/recetas que se le daran a los alumnos una vez acabado el curso.

## 4.3. Servicios de Red e Internet
### 4.3.1. Servidor Web Apache2
Para servir la plataforma, opté por un servidor web de alto rendimiento. Se configurará un Host Virtual que gestione las peticiones al dominio www.cursoscocina.es.
- Configuración de Virtual Host Seguro: La configuración incluirá la redirección automática del tráfico HTTP (puerto 80) al puerto seguro HTTPS (puerto 443).

## 4.4. Implantación de Aplicaciones Web
### 4.4.1. Arquitectura de la Aplicación
Para la plataforma de cursos de cocina, he seleccionado el stack llamdo LAMP que incluye (Linux, Apache, MySQL, PHP/Python). Esta arquitectura es el típica de la industria por su robusteza y flexibilidad pudiendo hacer de todo en ella.
- Servidor de Aplicaciones: Uso de PHP con módulos específicos como php-mysql para la comunicación con la base de datos y php-gd para el procesamiento de imágenes de las recetas.
- Gestión de Dependencias: Uso de Composer para gestionar las librerías necesarias, garantizando que el entorno de desarrollo y producción sean iguales.
- Motor de Plantillas: Implementación de un sistema que separa la lógica de negocio (PHP) de la presentación (HTML/CSS), facilitando el mantenimiento.

### 4.4.2. Optimización del Rendimiento
Para asegurar que la web no tarde en cargar, se utilizaran técnicas de optimización de la web:
- Compresión Gzip: Configuración del servidor Apache para comprimir los archivos antes de enviarlos al navegador.
- Caché de Navegador: Definición de políticas de expiración para archivos estáticos (imágenes, fuentes, CSS).
- Minificación: Reducción del tamaño de los archivos JavaScript y CSS eliminando espacios en blanco y comentarios.

## 4.5. Administración de Sistemas Gestores de Bases de Datos
### 4.5.1. Seguridad Avanzada en el SGBD
A diferencia de la creación básica de tablas, la administración avanzada se centra en proteger la información ante accesos no autorizados.
### 4.5.2. Política de Privilegios Mínimos
He creado usuarios específicos para cada tarea, evitando el uso de la cuenta 'root' por parte de la aplicación web (por seguridad).
### 4.5.3. Monitorización y Mantenimiento
Como queremos garantizar el correcto funcionamiento de la base de datos, el administrador (DBA) tendra que supervisar:
- Logs de Errores: Revisión periódica de /var/log/mysql/error.log para detectar fallos de hardware o corrupción de tablas.
- Optimización de Índices: Uso del comando ANALYZE TABLE para reconstruir las estadísticas de búsqueda y acelerar el acceso a los cursos más populares.

## 4.6. Seguridad y Alta Disponibilidad
### 4.6.1. Seguridad Perimetral y Firewalls
La proteccion del servidor la realizare en distintas capas. La primera capa, siendo la mas intrinseca e importante, es el firewall de host llamada UFW (Uncomplicated Firewall).
### 4.6.2. Detección de Intrusos y Prevención
Se implementa Fail2Ban, una herramienta que monitoriza los logs del sistema y banea automáticamente las direcciones IP que muestran comportamientos sospechosos (como múltiples intentos fallidos de login por SSH).
### 4.6.3. Continuidad de Negocio y Alta Disponibilidad
Teóricamente, para evitar que la plataforma caiga si el servidor físico falla, se propone:
- Balanceo de Carga: Uso de un balanceador (como HAProxy por ejemplo) que distribuya el tráfico entre dos servidores web gemelos.
- Réplica de Base de Datos: Configuración Maestro-Esclavo donde los datos se copian en tiempo real.
