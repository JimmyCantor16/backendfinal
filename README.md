# Jamz Backend

> API REST del POS multi-tenant Jamz. Provee autenticación, catálogo, inventario, POS, caja, facturación y reportes para múltiples negocios.

## Stack

| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| PHP | 8.1+ | Lenguaje |
| Laravel | 10.x | Framework HTTP |
| PostgreSQL | 14+ | Base de datos principal |
| Laravel Sanctum | 3.2 | Auth por token (Bearer) |
| darkaonline/l5-swagger | 8.6 | OpenAPI / Swagger UI |
| laravel/cashier (Stripe) | en progreso | Suscripciones / facturación SaaS |
| Pint | 1.x | Formato de código |
| PHPUnit | 10.x | Testing |

## Requisitos previos

- PHP 8.1+ con extensiones: `pdo_pgsql`, `mbstring`, `openssl`, `xml`, `ctype`, `json`, `bcmath` (y `pdo_sqlite` para tests).
- Composer 2+.
- PostgreSQL 14+ (o SQLite local solo para pruebas rápidas).
- Node 18+ (opcional; solo si se compilan assets de Laravel).

## Setup local

```bash
# 1. Dependencias
composer install

# 2. Variables de entorno
cp .env.example .env

# 3. Clave de aplicación
php artisan key:generate

# 4. Migraciones + seeds (crea negocios demo, roles, usuario admin)
php artisan migrate --seed

# 5. Servir API en http://localhost:8000
php artisan serve
```

> Nota: el `.env.example` actual aún es el boilerplate de Laravel (MySQL). Para Jamz se usa PostgreSQL: ajustar `DB_CONNECTION=pgsql`, `DB_PORT=5432`, etc. al copiarlo.

## Variables de entorno

| Variable | Descripción | Ejemplo | Requerido |
|----------|-------------|---------|-----------|
| `APP_NAME` | Nombre de la app | `Jamz` | si |
| `APP_ENV` | Entorno de ejecución | `local` / `production` | si |
| `APP_KEY` | Clave generada por `key:generate` | `base64:...` | si |
| `APP_DEBUG` | Modo debug | `true` | si |
| `APP_URL` | URL base del backend | `http://localhost:8000` | si |
| `LOG_CHANNEL` | Canal de logs | `stack` | si |
| `DB_CONNECTION` | Driver de BD | `pgsql` | si |
| `DB_HOST` | Host de la BD | `127.0.0.1` | si |
| `DB_PORT` | Puerto de la BD | `5432` | si |
| `DB_DATABASE` | Nombre de la BD | `jamz` | si |
| `DB_USERNAME` | Usuario de la BD | `postgres` | si |
| `DB_PASSWORD` | Clave de la BD | `postgres` | si |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios de frontends que reciben cookie de sesión | `localhost:8080,127.0.0.1:8080` | si (SPA) |
| `SESSION_DOMAIN` | Dominio de la cookie de sesión | `localhost` | no |
| `RECAPTCHA_SECRET` | Llave secreta de reCAPTCHA v3 (login) | `6Lc...` | si |
| `STRIPE_KEY` | Public key de Stripe (Cashier) | `pk_test_...` | si (billing) |
| `STRIPE_SECRET` | Secret key de Stripe | `sk_test_...` | si (billing) |
| `STRIPE_WEBHOOK_SECRET` | Secret del endpoint de webhooks | `whsec_...` | si (billing) |
| `CASHIER_CURRENCY` | Moneda por defecto | `cop` | si (billing) |
| `MAIL_*` | SMTP para notificaciones | ver `.env.example` | no |
| `L5_SWAGGER_GENERATE_ALWAYS` | Regenerar OpenAPI en cada request | `true` (solo local) | no |

## Comandos comunes

| Comando | Qué hace |
|---------|----------|
| `php artisan serve` | Levanta el servidor de desarrollo en `:8000` |
| `php artisan migrate` | Ejecuta migraciones |
| `php artisan migrate:fresh --seed` | Reinicia BD y vuelve a sembrar |
| `php artisan db:seed --class=NombreSeeder` | Ejecuta un seeder concreto |
| `php artisan l5-swagger:generate` | Regenera la documentación OpenAPI |
| `php artisan route:list` | Lista todas las rutas registradas |
| `php artisan tinker` | REPL para probar modelos |
| `php artisan cache:clear && php artisan config:clear` | Limpia caches al cambiar `.env` |
| `php vendor/bin/pint` | Formatea el código PHP |
| `php vendor/bin/phpunit` | Corre la suite de tests |
| `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit` | Tests con SQLite en memoria (sin Postgres) |

## Arquitectura

```
app/
  Http/
    Controllers/
      Api/                 -> Controladores REST por dominio (Auth, Product,
                              Order, Invoice, CashRegister, Report, etc.)
      ProfileController.php
      Controller.php       -> Base controller
    Middleware/
      EnsureBusinessContext.php  -> Resuelve el negocio activo desde el header
                                    X-Business-Id (o current_business_id del user)
      RoleMiddleware.php         -> Gate por rol (`role:admin`, `role:cajero`...)
      CheckPlanLimits.php        -> Enforcement de limites por plan SaaS
      JwtGuard.php / IsAuthenticated.php  -> Helpers de autenticacion
  Models/                  -> Eloquent (Business, User, Product, Order,
                              OrderItem, Invoice, CashRegister, AuditLog, ...)
  Policies/                -> Authorization por recurso (Order, Invoice,
                              Product, User, BusinessSettings, AuditLog)
  Services/                -> Logica de negocio reusable:
                              OrderService, InvoiceService, InventoryService,
                              CashRegisterService, AuditService, RecaptchaService
config/                    -> sanctum, cors, l5-swagger, cashier, etc.
database/
  migrations/              -> Schema versionado
  seeders/                 -> Datos iniciales (roles, business demo, admin)
  factories/               -> Factories para tests
routes/
  api.php                  -> Endpoints publicos / autenticados con Sanctum
  business.php             -> Endpoints multi-tenant (CRUD de businesses)
  web.php                  -> Rutas web (Swagger UI, perfil)
storage/api-docs/          -> JSON OpenAPI generado por l5-swagger
tests/
  Feature/                 -> Tests de endpoints HTTP
  Unit/                    -> Tests de services / models
```

### Convenciones

- **Controllers**: solo orquestan request -> service -> response. Sin logica de negocio.
- **Services**: toda transaccion compleja (cerrar orden, recibir compra, abrir caja) vive aqui. Inyectables via constructor.
- **Policies**: cada modelo expuesto al cliente tiene su Policy. Los controllers llaman `$this->authorize(...)`.
- **Multi-tenant**: el middleware `EnsureBusinessContext` setea el `business_id` activo. Todos los modelos por tenant deben filtrar por `business_id` (idealmente via global scope o query scope `forBusiness()`).
- **AuditLog**: cualquier mutacion sensible (crear factura, abrir/cerrar caja, ajustar inventario) debe pasar por `AuditService::log()`.
- **Validacion**: usar FormRequests cuando el payload sea no trivial.
- **OpenAPI**: anotar los controllers con atributos `@OA\...` (l5-swagger los recoge en build).

### Flujo multi-tenant

1. Login devuelve `access_token` (Sanctum) y el `current_business_id` del usuario.
2. El frontend manda en cada request:
   - `Authorization: Bearer {token}`
   - `X-Business-Id: {id}`
3. `EnsureBusinessContext` valida que el usuario pertenezca a ese business y lo fija como tenant activo.
4. Las queries de Eloquent filtran por `business_id` para aislar datos.

### Flujo POS

1. `POST /api/cash-registers/open` -> abre caja del turno (requerido para cobrar).
2. `POST /api/orders` -> crea orden vacia (estado `open`).
3. `POST /api/orders/{id}/add-item` -> agrega lineas; reserva stock.
4. `POST /api/orders/{id}/close` -> registra metodo de pago, descuenta stock, genera `Invoice` via `InvoiceService`.
5. `POST /api/orders/{id}/cancel` -> devuelve stock.
6. `POST /api/cash-registers/{id}/close` -> totaliza turno y genera reporte.

## API

- **Auth**: Sanctum (token Bearer obtenido en `POST /api/login`).
- **Multi-tenant**: header `X-Business-Id` obligatorio en rutas autenticadas.
- **Docs OpenAPI / Swagger UI**: `GET /api/documentation`.
- **JSON OpenAPI crudo**: `GET /docs?api-docs.json` (segun config de l5-swagger).
- **Throttling**: el login usa el rate limiter `throttle:login`.
- **CORS**: configurado en `config/cors.php` (permite el origen del frontend Vue).

### Endpoints principales (resumen)

| Metodo | Ruta | Descripcion |
|--------|------|-------------|
| POST | `/api/login` | Login con credenciales + reCAPTCHA, retorna token |
| POST | `/api/logout` | Revoca el token actual |
| GET  | `/api/me` | Perfil del usuario autenticado |
| GET  | `/api/dashboard` | KPIs del negocio activo |
| RES  | `/api/categories`, `/api/suppliers`, `/api/clients` | Catalogos |
| RES  | `/api/products` | Productos + stock |
| RES  | `/api/purchase-orders` (+`/receive`,`/cancel`) | Compras a proveedores |
| RES  | `/api/invoices` (+`/cancel`) | Facturas de venta |
| RES  | `/api/inventory-movements` | Ajustes de inventario |
| POST | `/api/orders` (+`add-item`,`remove-item`,`close`,`cancel`) | Carrito POS |
| -    | `/api/cash-registers/...` | Apertura, cierre y reportes de caja |
| GET  | `/api/business/settings`, POST update | Datos del negocio |
| GET  | `/api/reports/daily` | Reporte diario de ventas |
| GET  | `/api/audit-logs` | Bitacora (solo admin) |
| -    | `/api/users` (admin) | Gestion de usuarios del negocio |

## Testing

```bash
# Suite completa
php vendor/bin/phpunit

# Con SQLite en memoria (sin Postgres)
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit

# Un archivo o un test
php vendor/bin/phpunit tests/Feature/OrderTest.php
php vendor/bin/phpunit --filter=test_can_close_order

# Solo unit o solo feature
php vendor/bin/phpunit --testsuite=Unit
php vendor/bin/phpunit --testsuite=Feature
```

`phpunit.xml` ya trae variables para test (descomentar `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` para correr sin BD real).

## Proximos pasos

- Completar integracion de `laravel/cashier` (planes, webhooks, periodo de prueba).
- Mover el seeder de roles/plan demo a un seeder dedicado por entorno.
- Cobertura de tests del modulo POS y caja.
- Anotaciones `@OA\` faltantes para 100% de los endpoints en Swagger.
