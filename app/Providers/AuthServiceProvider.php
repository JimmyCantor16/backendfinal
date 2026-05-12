<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\CashRegisterPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * Note: BusinessSettingsPolicy is intentionally NOT mapped here. The
     * Business model is owned by a separate module/agent which will register
     * its own policy. BusinessSettingsPolicy is invoked explicitly from the
     * BusinessSettingsController (see app/Policies/BusinessSettingsPolicy.php).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
        Invoice::class => InvoicePolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        CashRegister::class => CashRegisterPolicy::class,
        User::class => UserPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
