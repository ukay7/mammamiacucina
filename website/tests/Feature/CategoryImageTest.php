<?php
namespace Tests\Feature;
use App\Models\{Category,Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class CategoryImageTest extends TestCase {
 use RefreshDatabase;
 private function image(){return UploadedFile::fake()->createWithContent('category.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));}
 public function test_upload_preserve_replace_and_delete_image():void {
  Storage::fake('local');
  $this->actingAs(User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]));
  $data=['name'=>'Photo Category','is_active'=>1,'sort_order'=>0];
  $this->get('/admin/categories/create')->assertOk()->assertSee('multipart/form-data',false)->assertSee('800');
  $this->post('/admin/categories',$data+['image'=>$this->image()])->assertSessionHasNoErrors();
  $c=Category::where('name','Photo Category')->firstOrFail();$first=$c->image_path;Storage::disk('local')->assertExists($first);
  $this->get(route('category.image',$c))->assertOk();
  $this->get('/admin/categories/'.$c->id.'/edit')->assertOk()->assertSee(route('category.image',$c));
  $this->put('/admin/categories/'.$c->id,$data)->assertSessionHasNoErrors();$this->assertSame($first,$c->fresh()->image_path);
  $this->put('/admin/categories/'.$c->id,$data+['image'=>UploadedFile::fake()->create('bad.txt',1,'text/plain')])->assertSessionHasErrors('image');
  $this->put('/admin/categories/'.$c->id,$data+['image'=>$this->image()])->assertSessionHasNoErrors();
  $second=$c->fresh()->image_path;$this->assertNotSame($first,$second);Storage::disk('local')->assertMissing($first);Storage::disk('local')->assertExists($second);
  $this->delete('/admin/categories/'.$c->id)->assertSessionHasNoErrors();Storage::disk('local')->assertMissing($second);
 }
}
