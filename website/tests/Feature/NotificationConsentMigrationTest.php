<?php
namespace Tests\Feature;

use App\Models\{NotificationCampaign, NotificationEvent, NotificationSubscription, NotificationSuppression};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationConsentMigrationTest extends TestCase
{
    use RefreshDatabase;
    public function test_existing_unsubscribe_history_is_preserved_without_inventing_consent(): void
    {
        if (DB::getDriverName()!=='sqlite') $this->markTestSkipped('Legacy-schema fixture uses transactional SQLite DDL; MySQL migration is covered separately.');
        $migration=require database_path('migrations/2026_11_21_010000_add_notification_consent_history.php');
        $migration->down();
        $c=NotificationCampaign::create(['title'=>'Old campaign','subject'=>'Old subject','body'=>'Old message','audience'=>[]]);
        $d=$c->deliveries()->create(['address'=>'old@example.test','source'=>'additional','campaign_revision'=>0,'token'=>Str::random(64),'idempotency_key'=>(string)Str::uuid(),'attempts'=>1]);
        NotificationSuppression::create(['channel'=>'email','address'=>$d->address,'reason'=>'unsubscribed']);
        NotificationEvent::create(['delivery_id'=>$d->id,'type'=>'unsubscribed','source'=>'recipient','occurred_at'=>now()->subMonths(3),'processed_at'=>now()->subMonths(3)]);
        NotificationSuppression::create(['channel'=>'email','address'=>'bounced@example.test','reason'=>'bounced']);
        $migration->up();
        $s=NotificationSubscription::where('address',$d->address)->sole();
        $this->assertSame('unsubscribed',$s->status); $this->assertSame(1,$s->version); $this->assertNull($s->consented_at);
        $this->assertSame($d->id,$s->events()->sole()->delivery_id); $this->assertSame('legacy',$s->events()->sole()->source);
        $this->assertSame('unknown',NotificationSubscription::where('address','bounced@example.test')->sole()->status);
        $this->assertDatabaseCount('notification_suppressions',2);
    }
}
