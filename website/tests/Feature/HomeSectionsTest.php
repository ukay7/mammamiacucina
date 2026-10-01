<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\{User,Role,HomeSection};
class HomeSectionsTest extends TestCase {
 use RefreshDatabase;
 public function test_editing_upload_permissions_and_stale_protection():void {
  Storage::fake('local');$url=route('admin.home-sections.update','tradition');
  $data=['revision'=>0,'eyebrow'=>'Our story','heading'=>'New heading','description'=>'Fresh description','image_alt'=>'Cake'];
  $this->put($url,$data)->assertRedirect();
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'No editing','permissions'=>[]])->id]);
  $this->actingAs($staff)->put($url,$data)->assertForbidden();
  $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
  $this->actingAs($admin)->get(route('admin.home-sections.edit'))->assertOk()->assertSee('Premium Ingredients');
  $this->put($url,$data+['image'=>UploadedFile::fake()->createWithContent('cake.png',file_get_contents(public_path('pwa/icon-192.png')))])->assertSessionHasNoErrors();
  $row=HomeSection::findOrFail('tradition');Storage::disk('local')->assertExists($row->image_path);
  $this->get($row->imageUrl())->assertOk();
  $this->get('/')->assertOk()->assertSee('New heading')->assertSee('Fresh description');
  $this->put($url,$data)->assertSessionHasErrors('revision');
  $this->put($url,array_replace($data,['revision'=>1,'heading'=>'<script>alert(1)</script>']))->assertSessionHasNoErrors();
  $this->get('/')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
  $this->assertSame($row->image_path,HomeSection::find('tradition')->image_path);
 }
 public function test_recipe_features_update_and_images_are_validated():void {
  $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);$this->actingAs($admin);
  $data=config('home_sections.baking');unset($data['image']);$data['revision']=0;$data['features'][0]['heading']='Fresh ingredients';
  $this->put(route('admin.home-sections.update','baking'),$data)->assertSessionHasNoErrors();
  $this->get('/')->assertOk()->assertSee('Fresh ingredients');
  $data['revision']=1;$data['image']=UploadedFile::fake()->create('bad.svg',1,'image/svg+xml');
  $this->put(route('admin.home-sections.update','baking'),$data)->assertSessionHasErrors('image');
 }
}
