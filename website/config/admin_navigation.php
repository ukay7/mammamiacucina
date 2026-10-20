<?php
$link = static fn($permission, $route, $match, $label, $icon, $params = []) => compact('permission', 'route', 'match', 'label', 'icon', 'params');
$group = static fn($label, $icon, $children) => compact('label', 'icon', 'children');
return [
    'Workspace' => [
        $link('', 'admin.notes.index', 'admin.notes.*', 'My Notes', 'sticky-note'),
        $link('dashboard.view', 'admin.dashboard', 'admin.dashboard', 'Dashboard', 'chart-bar'),
    ],
    'Sales & Orders' => [
        $link('pos.manage', 'admin.pos.index', 'admin.pos.*', 'Quick Sale / POS', 'cash-register'),
        $group('Order Management', 'shopping-cart', [
            $link('orders.view|warehouse.pack', 'admin.orders.index', 'admin.orders.*', 'Pending Orders', 'shopping-cart'),
            $link('orders.view|warehouse.pack', 'admin.orders.completed', 'admin.orders.completed', 'Completed Orders', 'check-circle'),
            $link('orders.view', 'admin.orders.archived', 'admin.orders.archived', 'Archived Orders', 'archive'),
        ]),
        $group('Customer Management', 'address-book', [
            $link('orders.view', 'admin.customers.index', 'admin.customers.*', 'View Customer', 'users'),
            $link('orders.view', 'admin.customers.business-requests', 'admin.customers.business-requests', 'Business Approval Requests', 'user-check'),
        ]),
        $link('quotations.view|quotations.manage', 'admin.quotations.index', 'admin.quotations.*', 'Quotations', 'file-invoice'),
        $link('enquiries.manage', 'admin.enquiries.index', 'admin.enquiries.*', 'Customer Enquiries', 'envelope'),
    ],
    'Reports' => [
        $link('orders.view', 'admin.reports.overdue-cheques', 'admin.reports.overdue-cheques', 'Overdue Cheques', 'money-check'),
        $link('inventory.view', 'admin.reports.inventory', 'admin.reports.inventory*', 'Inventory Dashboard', 'boxes'),
        $link('orders.view', 'admin.reports.index', 'admin.reports.*', 'Reporting Centre', 'chart-bar'),
    ],
    'Products & Stock' => [
        $group('Product Management', 'box-open', [
            $link('products.view', 'admin.products.index', 'admin.products.*', 'View Products', 'box-open'),
            $link('categories.view', 'admin.categories.index', 'admin.categories.*', 'Categories', 'tags'),
            $link('imports.view', 'admin.imports.index', 'admin.imports.*', 'Bulk Product Uploader', 'file-upload'),
            $link('allergies.manage', 'admin.allergies.index', 'admin.allergies.*', 'Allergies', 'leaf'),
        ]),
        $link('inventory.view', 'admin.inventory.index', 'admin.inventory.*', 'Inventory', 'warehouse'),
        $link('warehouses.view', 'admin.warehouses.index', 'admin.warehouses.*', 'Warehouses & Locations', 'building'),
        $link('stock-counts.view', 'admin.stock-counts.index', 'admin.stock-counts.*', 'Stock Count Tasks', 'clipboard-check'),
        $link('stock-counts.perform', 'admin.stock-counts.mine', 'admin.stock-counts.*', 'My Stock Counts', 'tasks'),
    ],
    'Website' => [
        $group('Website Pages', 'file-alt', [
            $link('banners.view', 'admin.banners.index', 'admin.banners.*', 'Home Banners', 'images'),
            $link('settings.manage', 'admin.home-sections.edit', 'admin.home-sections.*', 'Homepage Sections', 'home'),
            $link('about.manage', 'admin.about.edit', 'admin.about.*', 'About Us', 'info-circle'),
            $link('catalogue.manage', 'admin.catalogue.index', 'admin.catalogue.*', 'Catalogue', 'book-open'),
            $link('gallery.manage', 'admin.gallery.index', 'admin.gallery.*', 'Gallery Events', 'images'),
            $link('contact.manage', 'admin.contact.edit', 'admin.contact.*', 'Contact & Social Links', 'address-book'),
            $link('settings.manage', 'admin.content.index', 'path:admin/content/policies*', 'Policies', 'file-alt', ['policies']),
        ]),
        $group('Careers', 'briefcase', [
            $link('settings.manage', 'admin.content.index', 'path:admin/content/departments*', 'Departments', 'building', ['departments']),
            $link('settings.manage', 'admin.content.index', 'path:admin/content/jobs*', 'Job Postings', 'briefcase', ['jobs']),
        ]),
        $link('settings.manage', 'admin.theme.edit', 'admin.theme.*', 'Theme Settings', 'palette'),
    ],
    'Administration' => [
        $link('settings.manage', 'admin.settings.general', 'admin.settings.*', 'General Settings', 'cog'),
        $link('settings.manage', 'admin.delivery.index', 'admin.delivery.*', 'Delivery Rates', 'truck'),
        $link('gateways.manage', 'admin.gateways.edit', 'admin.gateways.*', 'Gateway Settings', 'credit-card'),
        $group('Manage Email', 'envelope', [
            $link('settings.manage', 'admin.email.edit', 'admin.email.edit', 'Email Configuration', 'cog'),
            $link('settings.manage', 'admin.email.templates.index', 'admin.email.templates.*', 'Email Templates', 'file-alt'),
            $link('settings.manage', 'admin.email.history', 'admin.email.history*', 'Email History', 'history'),
        ]),
        $group('User Management', 'users', [
            $link('users.view', 'admin.users.index', 'admin.users.*', 'Users', 'user'),
            $link('roles.view', 'admin.roles.index', 'admin.roles.*', 'Roles & Permissions', 'user-shield'),
        ]),
    ],
];
