# 🔄 Equipment Recovery - Sistema de Gestión

Sistema completo para gestión de recuperación de equipos móviles (CNT, Claro, Movistar) con app móvil Flutter, panel admin Vue 3 y API Laravel.

## 📁 Estructura del Proyecto

```
recovery-app/
├── backend/          # API Laravel 13 (PHP 8.3+)
├── admin-panel/      # Panel Admin Vue 3 + Vite + Tailwind
└── mobile-app/       # App Móvil Flutter
```

---

## 🚀 Fases del Proyecto

### ✅ Fase 1: MVP (Completada)
- [x] Inicio de sesión (email/contraseña)
- [x] Importación de Excel con mapeo de columnas
- [x] Reparto automático de tareas entre agentes
- [x] Ver tareas asignadas (app móvil)
- [x] Panel de administración básico

### ✅ Fase 2: Google Maps
- [x] Botón de navegación directa al cliente
- [x] Mapa con ubicaciones de tareas del día
- [x] Optimización de ruta (algoritmo nearest-neighbor)

### ✅ Fase 3: Evidencias
- [x] Captura de fotos con GPS
- [x] Firma digital del cliente
- [x] Comentarios en tareas
- [x] Subida de archivos (fotos, documentos)

### ⚠️ Fase 4: Panel de Control
- [x] Dashboard con estadísticas en tiempo real
- [x] Mapa de ubicaciones realizadas
- [x] Rendimiento por agente
- [~] Filtros por empresa, fecha, estado (parcial: filtros por empresa/estado)

### ⚠️ Fase 5: Reportes y Notificaciones
- [x] Reportes semanales/mensuales bajo demanda
- [x] Exportación a Excel/CSV
- [~] Envío masivo de WhatsApp (requiere WHATSAPP_TOKEN y WHATSAPP_PHONE_NUMBER_ID)
- [ ] Recordatorios automáticos (pendiente: programador y comandos artisan)

---

## 🔧 Backend (Laravel 13)

### Requisitos
- PHP 8.3+
- SQLite (por defecto) o MySQL 8.0+
- Composer 2+
- Extensiones PHP: gd, fileinfo, mbstring

### Instalación

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

La API queda en `http://localhost:8000`.

### Configuración .env
```env
DB_CONNECTION=sqlite
# o MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=recovery_db
# DB_USERNAME=root
# DB_PASSWORD=

# WhatsApp Cloud API
WHATSAPP_TOKEN=tu_access_token
WHATSAPP_PHONE_NUMBER_ID=tu_phone_number_id
WHATSAPP_VERSION=v21.0
WHATSAPP_BASE_URL=https://graph.facebook.com
WHATSAPP_WEBHOOK_SECRET=tu_webhook_secret
```

### Migraciones y Seeders
```bash
php artisan migrate --seed
```

### Credenciales por defecto (seed)
- **Admin**: admin@recovery.local / password123
- **Supervisor**: supervisor@recovery.local / password123
- **Agentes**: juan.perez@recovery.local / password123

### API Endpoints

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | /api/v1/login | Iniciar sesión |
| POST | /api/v1/register | Registrar agente |
| GET | /api/v1/user | Obtener usuario actual |
| GET/POST | /api/v1/companies | Listar / crear empresas |
| GET/PUT/DELETE | /api/v1/companies/{id} | CRUD de empresa |
| GET/POST | /api/v1/users | Listar / crear usuarios |
| GET/PUT/DELETE | /api/v1/users/{id} | CRUD de usuario |
| PUT | /api/v1/users/{id}/toggle-status | Activar/desactivar |
| PUT | /api/v1/users/{id}/reset-password | Resetear contraseña |
| GET/POST | /api/v1/clients | Listar / crear clientes |
| POST | /api/v1/clients/bulk-assign | Asignar clientes masivamente |
| GET/POST/PUT/DELETE | /api/v1/tasks | CRUD de tareas |
| POST | /api/v1/tasks/auto-assign | Auto-asignar tareas pendientes |
| GET | /api/v1/my-tasks | Tareas del agente actual |
| PUT | /api/v1/tasks/{id}/start | Iniciar tarea |
| PUT | /api/v1/tasks/{id}/complete | Completar tarea |
| PUT | /api/v1/tasks/{id}/fail | Marcar como fallida |
| GET | /api/v1/my-route | Ruta del día del agente |
| POST | /api/v1/my-route/optimize | Optimizar ruta (nearest-neighbor) |
| GET/POST | /api/v1/excel-import | Listar / subir importación |
| POST | /api/v1/excel-import/{id}/process | Procesar importación |
| GET | /api/v1/excel-import/template/download | Descargar plantilla |
| POST | /api/v1/whatsapp/send-bulk | Envío masivo WhatsApp |
| POST | /api/v1/whatsapp/send-to-client | Enviar a un cliente |
| GET | /api/v1/whatsapp/messages | Historial de mensajes |
| GET | /api/v1/reports | Listar reportes |
| POST | /api/v1/reports/generate | Generar reporte (Excel/CSV) |
| GET/POST/PUT/DELETE | /api/v1/reports/schedules | Programación de reportes |
| GET | /api/v1/dashboard/stats | Estadísticas del dashboard |
| GET | /api/v1/dashboard/map-data | Datos para el mapa |
| GET | /api/v1/dashboard/agent-performance | Rendimiento por agente |

---

## 💻 Panel Admin (Vue 3)

### Requisitos
- Node.js 18+
- npm 9+

### Instalación

```bash
cd admin-panel
npm install
npm run dev
```

El panel estará disponible en `http://localhost:5173` (proxy `/api` → `http://localhost:8000`).

### Build para producción
```bash
npm run build
```

### Vistas implementadas
- **Login** - Autenticación con Sanctum
- **Dashboard** - Estadísticas, gráficos, rendimiento de agentes
- **Empresas** - CRUD de empresas (CNT, Claro, Movistar)
- **Usuarios** - Gestión de agentes y supervisores
- **Clientes** - Lista, filtros, asignación masiva
- **Tareas** - Gestión completa, auto-asignación
- **Importar Excel** - Upload con mapeo de columnas
- **WhatsApp** - Envío masivo, historial de mensajes
- **Reportes** - Generación y programación
- **Mapa** - Vista de ubicaciones con Leaflet

---

## 📱 App Móvil (Flutter)

### Requisitos
- Flutter 3.44+ (Dart 3.12+)
- Android Studio (compileSdk 36, minSdk 21)
- Xcode (iOS)

### Instalación

```bash
cd mobile-app
flutter pub get
flutter run
```

### Build
```bash
# Android
flutter build apk --debug   # build verificada (resultó en APK funcional)
flutter build apk --release
flutter build appbundle --release

# iOS
flutter build ios --release
```

### Funcionalidades implementadas
- **Login** - Autenticación con token JWT
- **Mis Tareas** - Lista de tareas del día con filtros
- **Detalle de Tarea** - Info del cliente, navegación, WhatsApp, llamadas
- **Navegación** - Botón directo a Google Maps/Apple Maps
- **Mapa** - Vista de todas las ubicaciones del día
- **Perfil** - Info del usuario, cerrar sesión
- **Evidencias** - Cámara, galería, firma digital (estructura base)
- **Comentarios** - Agregar notas a tareas

### Arquitectura Flutter
- **State Management**: Riverpod
- **Routing**: GoRouter
- **HTTP Client**: Dio con interceptores
- **Storage**: FlutterSecureStorage
- **Maps**: Google Maps + url_launcher para navegación
- **Forms**: Reactive Forms

---

## 📊 Base de Datos

### Tablas principales
- `users` - Usuarios del sistema (admin, supervisor, agente)
- `companies` - Empresas (CNT, Claro, Movistar)
- `clients` - Clientes importados desde Excel
- `tasks` - Tareas de recuperación
- `task_assignments` - Historial de asignaciones
- `task_evidence` - Evidencias (fotos, firmas, documentos)
- `task_comments` - Comentarios en tareas
- `excel_imports` - Registro de importaciones
- `whatsapp_messages` - Historial de mensajes enviados
- `routes` - Rutas optimizadas por agente
- `reports` - Reportes generados
- `report_schedules` - Programación de reportes automáticos

---

## 📝 Formato de Excel para Importación

| Columna | Obligatorio | Descripción |
|---------|-------------|-------------|
| Número de Pedido | ✅ | ID único del pedido |
| Nombre Completo | ✅ | Nombre del cliente |
| Teléfono | ✅ | Celular (10 dígitos) |
| Teléfono Alterno | ❌ | Número alternativo |
| Dirección | ✅ | Dirección de recuperación |
| Referencia | ❌ | Punto de referencia |
| Detalles del Equipo | ❌ | Equipos a recuperar |
| Latitud | ❌ | Coordenada GPS |
| Longitud | ❌ | Coordenada GPS |

Descargar plantilla: `GET /api/v1/excel-import/template/download`

---

## 📱 Mensaje WhatsApp (Template)

```
Estimado/a {nombre_cliente}, le informamos que el Departamento de
Recuperación de Equipos de {empresa} se comunicó con usted respecto
al pedido #{numero_pedido}. Un agente se acercará a la dirección
registrada ({direccion}) para retirar los equipos. Por favor
manténgase atento/a a su teléfono {telefono}. Gracias.
```

---

## 🔐 Seguridad

- Autenticación con Laravel Sanctum (tokens Bearer)
- Roles: admin, supervisor, agente
- Tokens revocables al cerrar sesión
- Almacenamiento seguro de credenciales (FlutterSecureStorage en la app)
- Prefijo telefónico unificado Ecuador (+593)

---

## 📅 Próximos Pasos

1. **Notificaciones Push** - Firebase Cloud Messaging
2. **Sincronización offline** - SQLite local en Flutter
3. **Firma digital** - Pad de firma completo
4. **Fotos con marca de agua** - Overlay de fecha/ubicación
5. **Reportes PDF** - Generación con DomPDF
6. **Chat en tiempo real** - WebSockets para comunicación agente-supervisor
7. **Modo oscuro** - Tema oscuro en app móvil

---

## 📄 Licencia

MIT License