# Administración de inventario y acceso — Fase 5

## Inventario
En Filament > Inventario:
- Existencias: registrar stock para productos o variantes, ajustar cantidades y filtrar existencias bajas.
- Movimientos: consultar el historial de ajustes, reservas, liberaciones y salidas por venta.
- Cada ajuste utiliza InventoryService y genera un movimiento auditable.
- No se permite reducir la existencia por debajo de las unidades reservadas.

## Panel de ventas
El dashboard muestra pedidos del día, comprobantes pendientes, ventas cobradas del mes agrupadas por moneda y productos con stock bajo.

## Acceso administrativo
El panel Filament requiere que el usuario tenga `is_admin = true`.
- Los usuarios nuevos no son administradores por defecto.
- En entornos local/testing, el seeder crea `admin@example.test` para pruebas.
- Para crear o promover un administrador en un entorno desplegado, define `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` en el entorno y ejecuta `php artisan db:seed`.
- No guardes contraseñas reales en el repositorio ni en archivos versionados.
- Los permisos por rol y granularidad por módulo quedan para una fase posterior; esta entrega establece el control de acceso administrativo básico.
