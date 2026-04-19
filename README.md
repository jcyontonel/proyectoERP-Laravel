# 🧾 ERP Web System - Laravel

Sistema web tipo ERP desarrollado con Laravel para la gestión de operaciones de negocio, incluyendo ventas, productos y usuarios.

---

## 🎯 Objetivo del proyecto

Desarrollar un sistema transaccional que permita registrar y gestionar operaciones de negocio, sirviendo como fuente de datos para procesos analíticos posteriores.

---

## 🔗 Proyecto relacionado

Este sistema forma parte de una arquitectura completa:

👉 https://github.com/jcyontonel/erp-fullstack-system

---

## 🚀 Funcionalidades

- Gestión de productos
- Registro de ventas
- Administración de usuarios
- Persistencia en base de datos relacional
- Arquitectura MVC

---

## 🧠 Arquitectura

El sistema está basado en el patrón MVC:

- Modelos: gestión de datos
- Controladores: lógica de negocio
- Vistas: interfaz de usuario

---

## 🔄 Flujo del sistema

1. Usuario inicia sesión
2. Registra productos
3. Realiza ventas
4. Sistema almacena la información en base de datos
5. Datos quedan disponibles para procesos analíticos

---

## ⚙️ Tecnologías

- PHP (Laravel)
- MySQL
- JavaScript
- HTML / CSS

---

## 🧪 Entorno de ejecución

Este sistema fue desplegado en:

- Linux ejecutado en Termux (Android)
- Servidor web local
- Base de datos MySQL

---

## 💡 Características adicionales

- Integración con procesos de análisis de datos
- Preparado para automatización de tareas
- Base para generación de reportes

---

## 📸 Evidencia

(Aquí agregar screenshots del sistema funcionando)

---

## 🛠️ Instalación

```bash
git clone https://github.com/jcyontonel/proyectoERP-Laravel.git
cd proyectoERP-Laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
