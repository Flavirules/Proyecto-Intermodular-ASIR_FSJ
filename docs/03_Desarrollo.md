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

