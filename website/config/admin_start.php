<?php

// Curated entry points for the Super Admin home page. Detailed links remain in the sidebar.
$module = static fn ($label, $route, $icon, $description) => compact('label', 'route', 'icon', 'description');

return [
    'Sales & customers' => [
        $module('Quick Sale', 'admin.pos.index', 'cash-register', 'Create a sale or place a new order.'),
        $module('Reports', 'admin.reports.index', 'chart-bar', 'Review sales, payments and business activity.'),
        $module('Customer Management', 'admin.customers.index', 'users', 'Open customer accounts, payments and refunds.'),
        $module('Order Management', 'admin.orders.index', 'shopping-cart', 'Track orders, packing and delivery.'),
        $module('Pricing Lists', 'admin.pricing-lists.index', 'tags', 'Prepare customer pricing and print a price list.'),
        $module('Quotations', 'admin.quotations.index', 'file-invoice', 'Create and manage customer quotations.'),
    ],
    'Products & operations' => [
        $module('Product Management', 'admin.products.index', 'box-open', 'Browse and update your product catalogue.'),
        $module('Inventory', 'admin.inventory.index', 'warehouse', 'Check stock levels and manage adjustments.'),
        $module('Purchasing', 'admin.supplier-orders.index', 'truck-loading', 'Manage supplier orders and receiving.'),
        $module('Warehouses & Locations', 'admin.warehouses.index', 'building', 'Manage warehouses and storage locations.'),
        $module('Stock Count Tasks', 'admin.stock-counts.index', 'clipboard-check', 'Assign and review physical stock counts.'),
        $module('Catalogue', 'admin.catalogue.index', 'book-open', 'Manage the pages in your customer catalogue.'),
    ],
    'Communication & administration' => [
        $module('Notifications', 'admin.notifications.index', 'bell', 'Prepare email campaigns and review their progress.'),
        $module('Customer Enquiries', 'admin.enquiries.index', 'envelope', 'Read and manage incoming customer enquiries.'),
        $module('Website Pages', 'admin.home-sections.edit', 'file-alt', 'Manage homepage content and featured sections.'),
        $module('User Management', 'admin.users.index', 'user-shield', 'Manage staff accounts and access.'),
        $module('Delivery Rates', 'admin.delivery.index', 'truck', 'Manage delivery zones, services and rates.'),
        $module('General Settings', 'admin.settings.general', 'cog', 'Update company details and business settings.'),
    ],
];
