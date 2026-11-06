# Control de acceso administrativo

## Objetivo

El panel Filament usa control de acceso basado en roles (RBAC). Una cuenta de cliente no obtiene acceso administrativo automáticamente: el acceso al panel debe habilitarse y el usuario debe tener al menos un rol que conceda `panel.access`.

## Administración desde Filament

En `/admin`, el Superadministrador dispone del grupo **Seguridad**:

- **Acceso de usuarios:** habilitar o revocar el acceso al panel, asignar uno o varios roles y definir permisos individuales para cada cuenta.
- **Roles y permisos:** crear roles, describirlos y seleccionar los permisos que conceden.

Los permisos se combinan entre los roles asignados. Además, el Superadministrador puede agregar excepciones individuales para conceder o denegar permisos concretos; una excepción individual prevalece sobre los roles. La cuenta Superadministrador conserva siempre sus privilegios de emergencia.

La gestión de usuarios y roles está reservada al Superadministrador. Las cuentas Superadministrador y el rol de emergencia no se pueden editar ni eliminar desde la interfaz. La cuenta Superadministrador tampoco puede deshabilitarse desde el formulario de gestión.

## Roles iniciales

| Rol | Acceso inicial |
|---|---|
| Superadministrador | Todos los recursos y acciones; administra usuarios y roles |
| Administrador | Gestión general de la tienda, sin administrar accesos |
| Gestor de catálogo | Categorías, marcas y productos |
| Gestor de pedidos | Pedidos y pagos |
| Gestor de inventario | Existencias y movimientos |
| Gestor de contenido | Banners, páginas, menús y contenido de tienda |
| Atención al cliente | Consulta de pedidos y pagos |

Son perfiles iniciales editables. El Superadministrador puede crear otros roles y modificar la matriz de permisos.

## Permisos por recurso

Cada recurso admite las acciones aplicables:

- **Consultar:** acceder al listado y ver registros.
- **Crear:** crear registros.
- **Modificar:** editar registros y ejecutar operaciones de gestión permitidas.
- **Eliminar:** eliminar registros, incluidas operaciones masivas autorizadas.

Los permisos específicos incluyen, entre otros, categorías, marcas, productos, inventario, movimientos, pedidos, pagos, métodos de pago, zonas y tarifas, plantillas y registros de notificaciones, banners, páginas, menús y configuración de tienda. El dashboard y sus widgets también se filtran por permisos.

Las operaciones personalizadas —como importar catálogo, ajustar inventario, aprobar/rechazar pagos y gestionar pedidos— tienen comprobaciones propias además de la autorización CRUD.

## Migración y compatibilidad

La migración crea los roles y relaciones RBAC, añade `admin_access_enabled` y conserva el acceso de los administradores existentes:

1. Los usuarios que ya tenían `is_admin = true` se habilitan para el panel.
2. Esos usuarios reciben el rol **Superadministrador** para mantener el acceso completo que tenían antes.
3. Los demás usuarios quedan sin acceso administrativo por defecto.
4. Las cuentas nuevas no reciben acceso al panel salvo que un Superadministrador lo habilite y asigne un rol.

El campo histórico `is_admin` se conserva como puente de migración y para el seeder de desarrollo; la autorización efectiva del panel se basa en `admin_access_enabled` y los roles.

## Alta de un nuevo usuario administrativo

Un Superadministrador puede crear una cuenta desde **Seguridad → Acceso de usuarios**. Debe definir una contraseña temporal de al menos 12 caracteres y entregarla al usuario por un canal seguro. Después puede habilitar el acceso y asignar los roles adecuados. También puede promover una cuenta que ya se haya registrado en la tienda.

## Despliegue

En un despliegue existente, publicar el código y ejecutar la migración habitual:

```bash
php artisan migrate --force
```

Antes de aplicar en producción, realizar un backup de la base de datos. La migración conserva automáticamente a los administradores existentes; no es necesario cambiar manualmente sus registros.

En instalaciones nuevas, el seeder de desarrollo/instalación asigna el rol Superadministrador a la cuenta configurada como administradora mediante `ADMIN_EMAIL` y `ADMIN_PASSWORD`.

## Seguridad técnica

- `canAccessPanel()` bloquea a las cuentas sin acceso habilitado y sin rol con `panel.access`.
- Las políticas Laravel aplican autorización en servidor por recurso y acción.
- Las acciones personalizadas comprueban sus permisos antes de ejecutar cambios.
- Las vistas de navegación y widgets se filtran por autorización; ocultar una opción no sustituye la comprobación en servidor.
- Los roles y cuentas de emergencia están protegidos mediante políticas específicas.
- Las pruebas cubren acceso al panel, aislamiento de recursos, separación entre lectura y creación y protección del Superadministrador.
