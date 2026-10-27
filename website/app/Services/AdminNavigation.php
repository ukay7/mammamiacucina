<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminNavigation
{
    public const MARKER = 'access.configured';

    public static function key(array $node): string
    {
        return 'access.'.$node['route'].(empty($node['params'])?'':'.'.implode('.', $node['params']));
    }

    public static function nodes(): array
    {
        $nodes=[];
        foreach(config('admin_navigation') as $section=>$entries) {
            foreach($entries as $entry) {
                foreach($entry['children']??[$entry] as $node) {
                    $node['group']=$section.(isset($entry['children'])?' / '.$entry['label']:'');
                    $nodes[self::key($node)]=$node;
                }
            }
        }
        return $nodes;
    }

    public static function allowed(User $user, array $node): bool
    {
        if($user->isSuper()) return true;
        if($node['super_only']??false) return false;
        if(in_array(self::MARKER,$user->permissions(),true)) return $user->hasAdminPermission(self::key($node));
        return $node['permission']==='' || collect(explode('|',$node['permission']))->contains(fn($p)=>$user->hasAdminPermission($p));
    }

    public static function upgrade(array $permissions): array
    {
        if(in_array(self::MARKER,$permissions,true)) return $permissions;
        $result=$permissions;
        foreach(self::nodes() as $key=>$node) {
            if($node['route']==='admin.reports.overdue-cheques') $node['permission']='orders.manage';
            if($node['permission']==='' || array_intersect(explode('|',$node['permission']),$permissions)) $result[]=$key;
        }
        // Preserve the existing order-record boundary independently of menu visibility.
        if(!in_array('warehouse.pack',$permissions,true) || in_array('orders.manage',$permissions,true)) $result[]='orders.scope.all';
        $result[]=self::MARKER;
        return array_values(array_unique($result));
    }

    public static function normalize(array $permissions): array
    {
        $result=[self::MARKER,...$permissions];
        foreach(self::nodes() as $key=>$node) {
            if(!in_array($key,$permissions,true) || $node['permission']==='') continue;
            // Existing route/action checks remain in force beneath the menu gate.
            $result[]=explode('|',$node['permission'])[0];
        }
        foreach(['users','roles','categories','products','imports','inventory','warehouses','stock-counts','banners','quotations','orders','suppliers','purchases'] as $module) {
            if(in_array($module.'.manage',$result,true)) $result[]=$module.'.view';
        }
        return array_values(array_unique($result));
    }

    public static function landing(User $user): string
    {
        foreach(self::nodes() as $node) if(self::allowed($user,$node)) return empty($node['params'])?$node['route']:'admin.module-home';
        return 'admin.no-access';
    }

    public static function authorize(Request $request): void
    {
        $user=$request->user();
        if($user->isSuper() || !in_array(self::MARKER,$user->permissions(),true)) return;
        $route=$request->route()->getName();
        if(in_array($route,['admin.home','admin.logout','admin.no-access','admin.module-home'],true)) return;
        $target=null;
        if(Str::startsWith($route,'admin.orders.')) {
            $order=$request->route('order');
            $view=$route==='admin.orders.export'?$request->input('view'):null;
            $target=Str::startsWith($route,'admin.orders.deleted.')?'admin.orders.deleted.index':(($route==='admin.orders.completed' || $view==='completed' || ($order instanceof \App\Models\Order && $order->status==='completed'))?'admin.orders.completed':'admin.orders.index');
        } elseif(Str::startsWith($route,'admin.invoices.')) {
            $target='admin.customers.index';
        } elseif(Str::startsWith($route,'admin.payments.')) {
            $target='admin.reports.index';
        } elseif($route==='admin.customers.business-requests' || $route==='admin.customers.approve-business') {
            $target='admin.customers.business-requests';
        } elseif($route==='admin.reports.overdue-cheques') {
            $target='admin.reports.overdue-cheques';
        } elseif(Str::startsWith($route,'admin.reports.inventory-valuation')) {
            $target='admin.reports.inventory-valuation';
        } elseif(Str::startsWith($route,'admin.reports.inventory')) {
            $target='admin.reports.inventory';
        } elseif(Str::startsWith($route,'admin.inventory.')) {
            $target='admin.inventory.index';
        } elseif(Str::startsWith($route,'admin.warehouses.')) {
            $target='admin.warehouses.index';
        } elseif(Str::startsWith($route,'admin.stock-counts.')) {
            $target=$user->hasAdminPermission('stock-counts.view')?'admin.stock-counts.index':'admin.stock-counts.mine';
        } elseif(Str::startsWith($route,'admin.dashboard')) {
            $target='admin.dashboard';
        } elseif(Str::startsWith($route,'admin.email.') && !Str::startsWith($route,['admin.email.templates.','admin.email.history'])) {
            $target='admin.email.edit';
        }
        $matches=[];
        foreach(self::nodes() as $node) {
            if($target!==null) {if($node['route']===$target) $matches[]=$node;continue;}
            if(Str::startsWith($node['match'],'path:')) {
                if($request->is(substr($node['match'],5))) $matches[]=$node;
            } elseif(Str::is($node['match'],$route)) $matches[]=$node;
        }
        // Prefer the specific child module over broad patterns such as settings.*.
        if($matches) {
            usort($matches,fn($a,$b)=>strlen($b['match'])<=>strlen($a['match']));
            abort_unless(self::allowed($user,$matches[0]),403);
        } else abort(403);
    }
}
