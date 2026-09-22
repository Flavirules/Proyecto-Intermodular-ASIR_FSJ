# Capítulo 3
Desarrollo

## 3.1. Fundamentos de Hardware

### 3.1.1. Especificaciones del Servidor Principal
Para este proyecto teórico, he optado por una configuración de hardware orientada a la virtualización, lo que permite ejecutar varios servidores lógicos (Web, DB, Backup) sobre una única máquina física reduciendo el coste total de la parte fisica y el espacio. Esto es debido a que al no necesitar un local fisico para la parte de IT se puede externalizar o realizar de manera mas independiente, teniendo en cuenta que seria una plantilla de un par de personas en la parte informatica.

* Unidad Central de Procesamiento (CPU): Procesador con 8 núcleos y 16 hilos (frecuencia base de 3.5 GHz). El soporte para tecnología de virtualización (Intel VT-x o AMD-V) es indispensable y mas que suficiente para lo que la vamos a utilizar, ofreciendo vistas 3d de nuestras cocinas.
* Memoria RAM: 32 GB DDR4 con tecnología ECC (Error Correcting Code). El uso de ECC es crítico en servidores de bases de datos para prevenir la corrupción de datos en memoria.
* Sistema de Almacenamiento:
  * 2 x SSD NVMe de 500GB en configuración RAID 1 (Espejo) que son los mas seguros, para el Sistema Operativo y la plataforma web. Esto garantiza que si un disco falla, el sistema siga funcionando.
  * 2 x HDD de 2TB en configuración RAID 1 para el almacenamiento de vídeos de cursos y copias de seguridad.
* Interfaz de Red (NIC): Doble puerto Gigabit Ethernet para permitir la agregación de enlaces o la separación física del tráfico de gestión y producción.

### 3.1.2. Dispositivos de Red
Para la interconexión de la infraestructura se proponen los siguientes elementos:

* Router de Borde o Edge: Encargado de la gestión de la conexión a Internet, NAT y reglas de firewall iniciales permitiendo la conexion entre los dispositivos que tengamos y la nube. Cisco tiene algunos muy buenos como los series ISR 4000 ASR 1000 (de cisco que rondan los 1000€)
* Switch Gestionable L2/L3: Capaz de gestionar VLANS (IEEE 802.1Q). Esto permitirá separar la red de los alumnos, la red de administración y la red de los servidores de cocina. Como los de cisco Catalyst 1200-24T/P-4X Siendo los T mas baratos de 400-600 y los X mas caros en torno a los 1000, lo que los diferencia son que los primeros son mas basicos permitiendo los segundos mejor gestion de los L3
* SAI (Sistema de Alimentación Ininterrumpida): De tecnología Online de doble conversión, con una capacidad de 1500VA para proteger al servidor de picos de tensión y permitir un apagado controlado ante cortes eléctricos prolongados. Como los APC Smart-UPS 1500VA que tienen bastante buena calidad y son para empresas grandes rondando los 1000 (lo que sería mucho para nosotros pero es en caso de emergencias asique no viene mal

## 3.2. Planificación y Administración de Redes

### 3.2.1. Diseño de la Topología Lógica
He optado por un diseño de red jerárquico, ya que me permite separar la parte de servicios destinado a los usuarios, de los datos críticos de una forma más gestionable. De esta manera, en caso de problema, el impacto queda más limitado.

| VLAN | Nombre | Subred | Descripción |
| :--- | :--- | :--- | :--- |
| VLAN 10 | Gestión | 192.168.10.0/24 | Acceso SSH y mantenimiento de equipos. |
| VLAN 20 | Servidores | 192.168.20.0/24 | Ubicación del servidor Web y BD. |
| VLAN 30 | Clientes | 192.168.30.0/24 | Acceso para personal administrativo. |
| VLAN 40 | Invitados | 172.16.0.0/22 | Red Wi-Fi para alumnos presenciales. |

*Tabla 3.1: Ejemplo de plan de segmentación de red mediante VLANs basico.*

### 3.2.2. Protocolos de Red e Interconexión
* Direccionamiento: Se utilizará direccionamiento estático para todos los servidores y equipos de red, mientras que para la red de clientes e invitados nos basaremos en un servidor DHCP con rangos dinámicos controlados.
* DNS (Domain Name System): Se configurará un servidor DNS interno para resolver los nombres de los servicios (ej. cocina.local, db.cocina.local).
* Capa de Enlace: Implementación de protocolos como STP (Spanning Tree Protocol) para evitar bucles de red en el switch gestionable.

## 3.3. Implantación de Sistemas Operativos

### 3.3.1. Selección del Sistema Operativo
Elegí Ubuntu Server 22.04 LTS como sistema operativo base por las siguientes razones:
1. Estabilidad: Ciclo de soporte de 5 años ampliable.
2. Seguridad: Comunidad activa y actualizaciones de parches de seguridad frecuentes.
3. Eficiencia: Al no disponer de interfaz gráfica por defecto, consume menos de 512MB de RAM en reposo.

### 3.3.2. Proceso de Instalación y Particionado
El particionado de disco ha sido dispuesto de tal manera para proteger la integridad de los datos si una partición se llena:
* /: 40GB (Sistema raíz).
* /home: 50GB (Datos de usuarios).
* /var/log: 10GB (Aislamiento de logs para evitar bloqueos del sistema).
* /var/lib/mysql: 100GB (Espacio dedicado a la base de datos de los cursos).
* swap: 4GB (Memoria de intercambio).

## 3.4. Gestión de Bases de Datos

### 3.4.1. Diseño del Modelo Entidad-Relación
La base de datos vendria siendo la parte mas importante de nuestra web teniendo que tener en cuenta los proveedores de los productos, los cocinero, los clientes,etc. Para procurar que no haya redundancia de datos y que las consultas sean rápidas, además de aplicar un proceso de normalización.

Diccionario de Datos
A continuación, se detallan las tablas principales que componen el esquema db_cocina:

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

### 3.4.2. Implementación del Lenguaje de Definición de Datos
Para la creación del esquema en el servidor MariaDB/MySQL, se utilizará el código SQL que se puede ver en la sección 4.1.

### 3.4.3. Optimización y Consultas Avanzadas
Para mejorar el rendimiento de la plataforma web, decidí que se implementarán Indices en los campos que se consultaran con mayor frecuencia, como el email del usuario y el título del curso. Además, se definen vistas generales para que el departamento de administración pueda ver el total de ingresos por curso sin acceder a los datos sensibles de los alumnos.

## 3.5. Administración de Sistemas Operativos

### 3.5.1. Automatización de Tareas con Bash Scripting
Para facilitar el buscar información y reducir errores optaremos por usar scripts. En este proyecto se automatizan las tareas críticas de mantenimiento mediante scripts ejecutados por el tecnico que los implementara de manera automatica ne segundo plano o CRON.

Script de Copias de Seguridad (Backup)
Queremos implementar un script que realiza un volcado de la base de datos y comprime los archivos de la web.

### 3.5.2. Gestión de Usuarios y Cuotas de Disco
En un entorno multi-usuario (varios chefs subiendo contenido que se almacenara), es vital limitar el espacio en disco para evitar que un usuario llene sin querer la capacidad por algun error.

* Grupos: Se crean los grupos chefs y alumnos con permisos diferenciados.
* Cuotas: Se activa el módulo quota de Linux para limitar a los chefs a 5GB de almacenamiento de vídeos/recetas que se le daran a los alumnos una vez acabado el curso.

## 3.6. Servicios de Red e Internet

### 3.6.1. Servidor Web Apache2
Para servir la plataforma, opté por un servidor web de alto rendimiento. Se configurará un Host Virtual que gestione las peticiones al dominio www.cursoscocina.es.

Configuración de Virtual Host Seguro
La configuración incluirá la redirección automática del tráfico HTTP (puerto 80) al puerto seguro HTTPS (puerto 443).

## 3.7. Implantación de Aplicaciones Web

### 3.7.1. Arquitectura de la Aplicación
Para la plataforma de cursos de cocina, he seleccionado el stack llamdo LAMP que incluye (Linux, Apache, MySQL, PHP/Python). Esta arquitectura es el típica de la industria por su robusteza y flexibilidad pudiendo hacer de todo en ella.

Componentes del Entorno de Ejecución
* Servidor de Aplicaciones: Uso de PHP con módulos específicos como php-mysql para la comunicación con la base de datos y php-gd para el procesamiento de imágenes de las recetas.
* Gestión de Dependencias: Uso de Composer para gestionar las librerías necesarias, garantizando que el entorno de desarrollo y producción sean iguales.
* Motor de Plantillas: Implementación de un sistema que separa la lógica de negocio (PHP) de la presentación (HTML/CSS), facilitando el mantenimiento.

### 3.7.2. Optimización del Rendimiento
Para asegurar que la web no tarde en cargar, se utilizaran técnicas de optimización de la web:
* Compresión Gzip: Configuración del servidor Apache para comprimir los archivos antes de enviarlos al navegador.
* Caché de Navegador: Definición de políticas de expiración para archivos estáticos (imágenes, fuentes, CSS).
* Minificación: Reducción del tamaño de los archivos JavaScript y CSS eliminando espacios en blanco y comentarios.

## 3.8. Administración de Sistemas Gestores de Bases de Datos

### 3.8.1. Seguridad Avanzada en el SGBD
A diferencia de la creación básica de tablas, la administración avanzada se centra en proteger la información ante accesos no autorizados.

### 3.8.2. Política de Privilegios Mínimos
He creado usuarios específicos para cada tarea, evitando el uso de la cuenta 'root' por parte de la aplicación web (por seguridad)

### 3.8.3. Monitorización y Mantenimiento
Como queremos garantizar el correcto funcionamiento de la base de datos, el administrador (DBA) tendra que supervisar:
* Logs de Errores: Revisión periódica de /var/log/mysql/error.log para detectar fallos de hardware o corrupción de tablas.
* Optimización de Índices: Uso del comando ANALYZE TABLE para reconstruir las estadísticas de búsqueda y acelerar el acceso a los cursos más populares.

## 3.9. Seguridad y Alta Disponibilidad

### 3.9.1. Seguridad Perimetral y Firewalls
La proteccion del servidor la realizare en distintas capas. La primera capa, siendo la mas intrinseca e importante, es el firewall de host llamada UFW (Uncomplicated Firewall).

### 3.9.2. Detección de Intrusos y Prevención
Se implementa Fail2Ban, una herramienta que monitoriza los logs del sistema y banea automáticamente las direcciones IP que muestran comportamientos sospechosos (como múltiples intentos fallidos de login por SSH).

### 3.9.3. Continuidad de Negocio y Alta Disponibilidad
Teóricamente, para evitar que la plataforma caiga si el servidor físico falla, se propone:
* Balanceo de Carga: Uso de un balanceador (como HAProxy por ejemplo) que distribuya el tráfico entre dos servidores web gemelos.
* Réplica de Base de Datos: Configuración Maestro-Esclavo donde los datos se copian en tiempo real

## 3.10. Lenguaje de Marcas

### 3.10.1. Diseño de la Interfaz Web
Para que nuestra plataforma de cocina sea fácil de usar, he aplicado lo aprendido en el primer año sobre HTML y CSS con ligero javascript ya que no hace falta. No he querido hacer algo extremadamente complejo, sino algo que cargue rápido y que un usuario de la pagina pueda entender nada más entrar.
La página web desarrollada se puede ver en la figura 4.1.

### 3.10.2. Estructura con HTML5
Para la forma de la web, he usado etiquetas semánticas de HTML5. Esto es importante porque ayuda a que la página esté ordenada y que los buscadores como Google la entiendan mejor.

### 3.10.3. Estilos con CSS y Responsive Design
Hoy en día casi todo el mundo mira las recetas desde el móvil mientras cocina. Por eso, he aplicado Responsive Design. He usado una hoja de estilos CSS para que, si entras desde un móvil, los botones sean más grandes y el texto se lea bien sin tener que hacer zoom.

## 3.11. Itinerario Personal para la Empleabilidad
En este apartado vamos a ver la parte "humana 2 legal del proyecto. No todo es configurar servidores; también hay que cuidar la salud del técnico y cumplir con la ley.

### 3.11.1. Prevención de Riesgos Laborales
Como administradores de sistemas, pasamos muchas horas delante del ordenador y manipulando equipos eléctricos. He identificado los principales riesgos y además he incluido los riesgos que puedan ocurrir a nuestros alumnos en los cursos:
* Riesgos Ergonómicos: Estar mal sentados o con la pantalla a mala altura puede darnos dolores de espalda y cuello. Para el proyecto, he seguido la norma de tener la pantalla a la altura de los ojos y usar una silla que nos permita tener los pies apoyados.
* Fatiga Visual: Para no acabar con la vista cansada, aplicamos la regla de descansar la vista cada 20 minutos.
* Riesgo Eléctrico: Al montar el servidor y el switch (que mencionamos en el capítulo de Hardware), siempre hay que trabajar con los equipos apagados y usar pulseras antiestáticas para no dar calambrazos a las piezas ni a nosotros mismos.
* Cortes y Pinchazos: Es el riesgo más común por el uso constante de cuchillos, peladores y mandolinas.
* Quemaduras: Por contacto directo con hornos, placas de inducción o salpicaduras de aceite hirviendo. En la plataforma, siempre recomendamos el uso de guantes térmicos en las fichas de seguridad de las recetas.
* Resbalones y Caídas: En una cocina es fácil que caiga agua o aceite al suelo. Es obligatorio el uso de calzado antideslizante para evitar accidentes graves.
* Riesgos Biológicos (Higiene Alimentaria): Un mal cocinado o una mala conservación de los alimentos puede causar intoxicaciones. Por eso, en la base de datos he incluido un campo de Alérgenos para que cada receta avise claramente de los riesgos (celíacos, intolerantes a la lactosa, etc.).

### 3.11.2. Derechos de los Trabajadores y Emprendimiento
Si este proyecto de cursos de cocina se convirtiera en una empresa real, tendríamos que tener en cuenta:
* Contratos: El informático que mantenga la web debería estar bajo el convenio de consultoría o empresas de informática.
* Protección de Datos (RGPD): Como pedimos el correo y el nombre a los alumnos, estamos obligados por ley a guardar esos datos de forma segura (por eso ciframos las contraseñas en el capítulo de Base de Datos) y a no venderlos a terceros.
  
*Documentación de la fase de desarrollo finalizada.*
