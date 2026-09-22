# Capítulo 2. Planificación

## 2.1. Temporización
El desarrollo del proyecto se ha estructurado en las siguientes fases lógicas:

1. **Análisis de requisitos**: Selección de hardware (servidor con 32GB RAM y RAID 1) y presupuesto de dispositivos de red Cisco y SAI APC.
2. **Diseño de arquitectura**: Esquematización de la topología lógica segmentada en 4 VLANs (Gestión, Servidores, Clientes e Invitados).
3. **Implementación del servidor**: Instalación de Ubuntu Server 22.04 LTS aplicando el particionado de seguridad y el entorno LAMP.
4. **Desarrollo de base de datos**: Creación del esquema `db_cocina` con motor InnoDB para asegurar la integridad referencial y normalización.
5. **Integración web y pruebas**: Despliegue de la plataforma en el Virtual Host seguro de Apache y optimización de rendimiento (WPO).
6. **Seguridad y mantenimiento**: Configuración de reglas en UFW, Fail2Ban y puesta en marcha del script de backup automatizado mediante cron.

## 2.2. Cronograma estimado
La duración total del proyecto se estima en un periodo de **8 semanas**, distribuidas de la siguiente forma:

* **Semanas 1-2**: Análisis de requisitos y diseño de la red (VLANs).
* **Semanas 3-4**: Montaje del servidor físico, particionado de discos e instalación del SGBD MySQL/MariaDB.
* **Semanas 5-7**: Desarrollo de la base de datos, programación de la web y pruebas de conectividad entre capas.
* **Semana 8**: Auditoría final de seguridad, configuración del SAI y entrega de la documentación.

## 2.3. Recursos necesarios
Para llevar a cabo la implementación se han requerido los siguientes recursos técnicos y materiales:

* **Hardware**: Servidor con soporte de virtualización, 2x SSD NVMe, 2x HDD y SAI APC 1500VA.
* **Software**: Ubuntu Server, Apache2, MariaDB/MySQL y PHP (Stack LAMP).
* **Infraestructura de Red**: Switch gestionable L3 y Router Cisco.
* **Herramientas de gestión**: Cliente SSH (PuTTY/Termius), DBeaver para la gestión de la BD y un editor de código.

*Documentación de la fase de planificacion finalizada.*
