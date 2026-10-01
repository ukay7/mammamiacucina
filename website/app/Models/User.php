<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function customer(){return $this->hasOne(Customer::class);}
    public function customerRecord(): Customer {
        abort_unless($this->isCustomer(),403);
        return $this->customer()->firstOrCreate([],['name'=>$this->name,'email'=>$this->email,'phone'=>$this->phone,'account_type'=>$this->account_type]);
    }
    protected static function booted(): void {
        static::saved(function(User $user){
            if($user->isCustomer()) $user->customer()->updateOrCreate([],['name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,'account_type'=>$user->account_type]);
        });
    }
    public function businessApprovalPending(): bool {return $this->account_type==='business' && !$this->business_approved_at;}
    public function canSignIn(): bool {return $this->is_active && ($this->isCustomer() || $this->role !== null);}
    public function accountRoute(): string {
        if($this->isCustomer())return 'customer.orders';
        return $this->hasAdminPermission('warehouse.pack') && !$this->hasAdminPermission('orders.manage') ? 'admin.orders.index' : 'admin.dashboard';
    }
    public function isCustomer(): bool { return in_array($this->account_type, ['individual','business'], true); }
    public function isSuper(): bool
    {
        return !$this->isCustomer() && $this->is_active && (bool) $this->role?->is_super;
    }

    public function permissions(): array
    {
        return $this->isSuper() ? array_keys(config('admin.permissions')) : ($this->role?->permissions ?? []);
    }

    public function hasAdminPermission(string $permission): bool
    {
        return !$this->isCustomer() && $this->is_active && in_array($permission, $this->permissions(), true);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'business_approved_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
