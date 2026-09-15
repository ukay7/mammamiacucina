<?php
namespace Tests\Feature;
use App\Models\{GeneralSetting,Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SocialSettingsTest extends TestCase {
 use RefreshDatabase;
 public function test_social_links_save_render_validate_and_clear():void {
  $this->actingAs(User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]));
  $data=['revision'=>0,'delivery'=>'0','tax'=>'0','facebook_url'=>'https://www.facebook.com/mmc','instagram_url'=>'https://www.instagram.com/mmc','twitter_url'=>'https://x.com/mmc'];
  $this->put(route('admin.settings.update'),$data)->assertSessionHasNoErrors();
  $this->get('/')->assertOk()->assertSee('https://www.facebook.com/mmc')->assertSee('https://www.instagram.com/mmc')->assertSee('https://x.com/mmc');
  $this->put(route('admin.settings.update'),array_replace($data,['revision'=>1,'facebook_url'=>'javascript:alert(1)']))->assertSessionHasErrors('facebook_url');
  $this->put(route('admin.settings.update'),array_replace($data,['revision'=>1,'facebook_url'=>'','instagram_url'=>'','twitter_url'=>'']))->assertSessionHasNoErrors();
  $this->assertNull(GeneralSetting::find(1)->facebook_url);
  $this->get('/')->assertOk()->assertDontSee('class="ps-widget__social"',false);
 }
}
