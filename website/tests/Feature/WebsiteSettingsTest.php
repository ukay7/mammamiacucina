<?php
namespace Tests\Feature;
use App\Models\{GeneralSetting,Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class WebsiteSettingsTest extends TestCase {
 use RefreshDatabase;
 public function test_settings_upload_and_public_content_and_stale_cleanup():void {
  Storage::fake('local');$this->actingAs(User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]));
  $hours=GeneralSetting::find(1)->opening_hours;$hours['Sunday']['closed']=true;
  $data=['revision'=>0,'delivery'=>'5','tax'=>'5','email'=>'hello@example.test','phone'=>'+1 555 123 4567','youtube'=>'https://youtu.be/abcdefghijk','opening_hours'=>$hours];
  $image=fn()=>UploadedFile::fake()->createWithContent('logo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));
  $this->put(route('admin.settings.update'),$data+['logo'=>$image()])->assertSessionHasNoErrors();
  $s=GeneralSetting::find(1);$this->assertSame('abcdefghijk',$s->youtube_video_id);Storage::disk('local')->assertExists($s->logo_path);
  $this->get('/site-logo')->assertOk();
  $this->get('/')->assertOk()->assertSee('hello@example.test')->assertSee('+1 555 123 4567')->assertSee('Closed')->assertSee('/site-logo');
  $this->get('/contact')->assertOk()->assertSee('hello@example.test')->assertSee('Closed');
  $this->get(route('admin.settings.general'))->assertOk()->assertSee('Opening Hours')->assertSee('YouTube video URL');
  $this->put(route('admin.settings.update'),$data+['logo'=>$image()])->assertSessionHasErrors('settings');
  $this->assertCount(1,Storage::disk('local')->allFiles('branding'));
  $this->put(route('admin.settings.update'),array_replace($data,['revision'=>1,'youtube'=>'https://evil.test/watch?v=abcdefghijk']))->assertSessionHasErrors('youtube');
  $this->put(route('admin.settings.update'),array_replace($data,['revision'=>1,'youtube'=>'','email'=>'','phone'=>'']))->assertSessionHasNoErrors();
  $this->assertNull(GeneralSetting::find(1)->youtube_video_id);
  $this->assertSame($s->logo_path,GeneralSetting::find(1)->logo_path);
 }
}
