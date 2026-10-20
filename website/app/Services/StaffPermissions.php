<?php

namespace App\Services;

class StaffPermissions
{
    public const VERSION = 'access.grouped';

    /** Actions live beside their sidebar module; there is no second permissions list. */
    public static function actions(): array
    {
        return [
            'admin.products.index' => ['products.manage' => 'Manage products'],
            'admin.categories.index' => ['categories.manage' => 'Manage categories'],
            'admin.imports.index' => ['imports.manage' => 'Upload and commit imports'],
            'admin.inventory.index' => ['inventory.manage' => 'Adjust stock'],
            'admin.warehouses.index' => ['warehouses.manage' => 'Create and edit warehouses'],
            'admin.stock-counts.index' => ['stock-counts.manage' => 'Create and cancel count tasks'],
            'admin.banners.index' => ['banners.manage' => 'Manage banners'],
            'admin.quotations.index' => ['quotations.manage' => 'Create, edit and delete'],
            'admin.customers.index' => ['customers.manage' => 'Manage customers, terms, discounts and notes', 'customers.invoice-email' => 'Email invoices'],
            'admin.customers.business-requests' => ['customers.approve' => 'Approve businesses'],
            'admin.reports.index' => ['payments.manage' => 'Verify and resolve payments', 'payments.refund' => 'Refund through gateway'],
            'admin.users.index' => ['users.manage' => 'Create, edit and deactivate staff'],
            'admin.roles.index' => ['roles.manage' => 'Manage user types'],
        ];
    }

    public static function orderActions(): array
    {
        return ['warehouse.pack' => 'Pack orders and report shortages', 'orders.manage' => 'Manage orders, status and settlements', 'orders.scope.all' => 'Allow packing users to see all orders'];
    }

    public static function extraKeys(): array
    {
        return [self::VERSION, 'orders.scope.restricted', 'customers.manage', 'customers.approve', 'customers.invoice-email', 'payments.manage', 'stock-counts.perform'];
    }

    public static function labels(array $permissions): array
    {
        $labels=[];
        foreach(AdminNavigation::nodes() as $key=>$node) $labels[$key]=$node['label'];
        foreach(self::actions() as $actions) $labels += $actions;
        $labels += self::orderActions();
        return array_values(array_intersect_key($labels,array_flip(self::upgrade($permissions))));
    }

    /** Compatibility before and during deployment; never assign a new role to a user. */
    public static function compatible(array $permissions): array
    {
        if (in_array(self::VERSION, $permissions, true)) return $permissions;
        if (in_array('users.manage', $permissions, true)) $permissions = [...$permissions, 'customers.manage', 'customers.approve'];
        if (in_array('orders.manage', $permissions, true)) $permissions = [...$permissions, 'customers.invoice-email', 'payments.manage'];
        return array_values(array_unique($permissions));
    }

    public static function normalize(array $submitted): array
    {
        $nodes = AdminNavigation::nodes();
        $result = array_values(array_intersect($submitted, array_keys($nodes)));
        if (in_array('orders.scope.restricted', $submitted, true)) $result[] = 'orders.scope.restricted';
        foreach (self::actions() as $route => $actions) {
            $chosen = array_values(array_intersect($submitted, array_keys($actions)));
            if ($chosen) $result = [...$result, 'access.'.$route, ...$chosen];
        }
        $orderPages = ['access.admin.orders.index', 'access.admin.orders.completed', 'access.admin.orders.archived'];
        $orderActions = array_values(array_intersect($submitted, array_keys(self::orderActions())));
        if ($orderActions && !array_intersect($result, $orderPages)) $result[] = $orderPages[0];
        $result = [...$result, ...$orderActions];
        if (in_array('payments.refund', $result, true)) $result[] = 'payments.manage';
        return AdminNavigation::normalize([...$result, self::VERSION]);
    }

    /** Preserve effective page/action access; discard actions on pages that were blocked. */
    public static function upgrade(array $permissions): array
    {
        if (in_array(self::VERSION, $permissions, true)) return $permissions;
        $old = self::compatible(AdminNavigation::upgrade($permissions));
        if (!in_array('payments.manage', $old, true)) $old = array_values(array_diff($old, ['payments.refund']));
        $selected = array_values(array_intersect($old, array_keys(AdminNavigation::nodes())));
        if (in_array('warehouse.pack', $old, true) && !array_intersect($old, ['orders.manage','orders.scope.all'])) $selected[] = 'orders.scope.restricted';
        foreach (self::actions() as $route => $actions) {
            if (in_array('access.'.$route, $selected, true)) $selected = [...$selected, ...array_intersect($old, array_keys($actions))];
        }
        if (array_intersect($selected, ['access.admin.orders.index', 'access.admin.orders.completed', 'access.admin.orders.archived'])) {
            $selected = [...$selected, ...array_intersect($old, array_keys(self::orderActions()))];
        }
        return self::normalize($selected);
    }
}
