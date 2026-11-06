<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryLocationBalance;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\StorefrontBanner;
use App\Models\StorefrontMenuItem;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Models\AdminRole;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferRule;

return [
    'model_resources' => [
        Category::class => 'categories',
        Brand::class => 'brands',
        Product::class => 'products',
        Inventory::class => 'inventories',
        InventoryMovement::class => 'inventory_movements',
        InventoryLocationBalance::class => 'inventory_location_balances',
        Order::class => 'orders',
        Payment::class => 'payments',
        PaymentMethod::class => 'payment_methods',
        ShippingZone::class => 'shipping_zones',
        NotificationTemplate::class => 'notification_templates',
        NotificationLog::class => 'notification_logs',
        StorefrontBanner::class => 'banners',
        StorefrontPage::class => 'pages',
        StorefrontMenuItem::class => 'menu_items',
        StorefrontSetting::class => 'store_settings',
        User::class => 'admin_users',
        AdminRole::class => 'admin_roles',
        Warehouse::class => 'warehouses',
        WarehouseLocation::class => 'warehouse_locations',
        WarehouseTransfer::class => 'warehouse_transfers',
        WarehouseTransferRule::class => 'warehouse_transfer_rules',
    ],

    'permissions' => [
        'panel.access' => 'Acceder al panel administrativo',
        'dashboard.view' => 'Consultar el dashboard',
        'admin_users.view' => 'Consultar usuarios administrativos',
        'admin_users.create' => 'Crear usuarios administrativos',
        'admin_users.update' => 'Modificar usuarios y sus accesos',
        'admin_users.delete' => 'Eliminar usuarios administrativos',
        'admin_roles.view' => 'Consultar roles',
        'admin_roles.create' => 'Crear roles',
        'admin_roles.update' => 'Modificar roles y permisos',
        'admin_roles.delete' => 'Eliminar roles',
        'categories.view' => 'Consultar categorías',
        'categories.create' => 'Crear categorías',
        'categories.update' => 'Modificar categorías',
        'categories.delete' => 'Eliminar categorías',
        'brands.view' => 'Consultar marcas',
        'brands.create' => 'Crear marcas',
        'brands.update' => 'Modificar marcas',
        'brands.delete' => 'Eliminar marcas',
        'products.view' => 'Consultar productos',
        'products.create' => 'Crear productos e importar catálogo',
        'products.update' => 'Modificar productos',
        'products.delete' => 'Eliminar productos',
        'inventories.view' => 'Consultar existencias',
        'inventories.update' => 'Ajustar existencias',
        'inventory_movements.view' => 'Consultar movimientos de inventario',
        'inventory_location_balances.view' => 'Consultar existencias por ubicación',
        'inventory_location_balances.update' => 'Ajustar y mover existencias entre ubicaciones',
        'warehouses.view' => 'Consultar almacenes',
        'warehouses.create' => 'Crear almacenes',
        'warehouses.update' => 'Modificar almacenes',
        'warehouses.delete' => 'Eliminar almacenes',
        'warehouse_locations.view' => 'Consultar ubicaciones de almacén',
        'warehouse_locations.create' => 'Crear ubicaciones',
        'warehouse_locations.update' => 'Modificar ubicaciones',
        'warehouse_locations.delete' => 'Eliminar ubicaciones',
        'warehouse_transfers.view' => 'Consultar transferencias entre almacenes',
        'warehouse_transfers.create' => 'Crear transferencias entre almacenes',
        'warehouse_transfers.update' => 'Aprobar, despachar y recibir transferencias',
        'warehouse_transfers.delete' => 'Cancelar transferencias no despachadas',
        'warehouse_transfer_rules.view' => 'Consultar reglas de aprobación de transferencias',
        'warehouse_transfer_rules.create' => 'Crear reglas de aprobación',
        'warehouse_transfer_rules.update' => 'Modificar reglas de aprobación',
        'warehouse_transfer_rules.delete' => 'Eliminar reglas de aprobación',
        'orders.view' => 'Consultar pedidos',
        'orders.update' => 'Gestionar pedidos y entregas',
        'payments.view' => 'Consultar pagos y comprobantes',
        'payments.update' => 'Aprobar, rechazar y confirmar pagos',
        'payment_methods.view' => 'Consultar métodos de pago',
        'payment_methods.create' => 'Crear métodos de pago',
        'payment_methods.update' => 'Modificar métodos de pago',
        'payment_methods.delete' => 'Eliminar métodos de pago',
        'shipping_zones.view' => 'Consultar zonas y tarifas',
        'shipping_zones.create' => 'Crear zonas y tarifas',
        'shipping_zones.update' => 'Modificar zonas y tarifas',
        'shipping_zones.delete' => 'Eliminar zonas y tarifas',
        'notification_templates.view' => 'Consultar plantillas de notificación',
        'notification_templates.create' => 'Crear plantillas',
        'notification_templates.update' => 'Modificar plantillas',
        'notification_templates.delete' => 'Eliminar plantillas',
        'notification_logs.view' => 'Consultar registros de notificaciones',
        'banners.view' => 'Consultar banners y campañas',
        'banners.create' => 'Crear banners y campañas',
        'banners.update' => 'Modificar banners y campañas',
        'banners.delete' => 'Eliminar banners y campañas',
        'pages.view' => 'Consultar páginas públicas',
        'pages.create' => 'Crear páginas públicas',
        'pages.update' => 'Modificar páginas públicas',
        'pages.delete' => 'Eliminar páginas públicas',
        'menu_items.view' => 'Consultar menús',
        'menu_items.create' => 'Crear elementos de menú',
        'menu_items.update' => 'Modificar elementos de menú',
        'menu_items.delete' => 'Eliminar elementos de menú',
        'store_settings.view' => 'Consultar contenido y textos de tienda',
        'store_settings.create' => 'Crear contenido de tienda',
        'store_settings.update' => 'Modificar contenido de tienda',
        'store_settings.delete' => 'Eliminar contenido de tienda',
    ],
];
