<?php
namespace Tests\Feature;
use App\Models\{GalleryEvent,ContactEnquiry,User,Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class GalleryContactTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);}
 private function image(){return UploadedFile::fake()->createWithContent('event.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));}
 public function test_event_upload_visibility_and_scoped_removal():void {
  Storage::fake('local');$this->actingAs($this->admin());
  $d=['title'=>'Bakery Opening','description'=>'Our celebration','is_active'=>1,'sort_order'=>0,'event_date'=>'2026-09-16'];
  $this->get('/admin/gallery/create')->assertOk();
  $this->post('/admin/gallery',$d+['photos'=>[$this->image(),$this->image()]])->assertSessionHasNoErrors();
  $event=GalleryEvent::firstOrFail();$this->assertCount(2,$event->photos);$photo=$event->photos->first();Storage::disk('local')->assertExists($photo->path);
  $this->get('/admin/gallery')->assertOk()->assertSee('Bakery Opening');$this->get('/admin/gallery/'.$event->id.'/edit')->assertOk();
  $this->get('/gallery')->assertOk()->assertSee('Bakery Opening');$this->get(route('gallery.event',$event))->assertOk()->assertSee('Our celebration');$this->get(route('gallery.image',$photo))->assertOk();
  $other=GalleryEvent::create(['title'=>'Other','is_active'=>1]);
  $this->delete(route('admin.gallery.photos.remove',[$other,$photo]))->assertNotFound();
  $this->put(route('admin.gallery.update',$event),array_replace($d,['is_active'=>0]))->assertSessionHasNoErrors();
  auth()->logout();$this->get('/gallery')->assertDontSee('Bakery Opening');$this->get(route('gallery.event',$event))->assertNotFound();$this->get(route('gallery.image',$photo))->assertNotFound();
  $this->actingAs($this->admin());
  $this->put(route('admin.gallery.update',$event),$d+['photos'=>[UploadedFile::fake()->create('bad.txt',1)]])->assertSessionHasErrors('photos.0');
  $this->delete(route('admin.gallery.photos.remove',[$event,$photo]))->assertSessionHasNoErrors();Storage::disk('local')->assertMissing($photo->path);
  $paths=$event->photos()->pluck('path');$this->delete(route('admin.gallery.destroy',$event))->assertSessionHasNoErrors();foreach($paths as $path)Storage::disk('local')->assertMissing($path);
 }
 public function test_contact_submission_validation_inbox_and_permissions():void {
  $this->post('/contact',[])->assertSessionHasErrors(['name','email','message']);
  $this->post('/contact',['name'=>'Customer','email'=>'customer@example.test','phone'=>'123','message'=>'Please contact me <script>alert(1)</script>'])->assertRedirect('/contact')->assertSessionHas('contact_success');
  $e=ContactEnquiry::firstOrFail();$this->assertSame('new',$e->status);
  $this->get('/contact')->assertOk()->assertSee('Send Message')->assertDontSee('Preview Message');
  $this->get('/admin/enquiries')->assertRedirect();
  $this->actingAs($this->admin());$this->get('/admin/enquiries')->assertOk()->assertSee('customer@example.test');
  $this->get(route('admin.enquiries.show',$e))->assertOk()->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;',false);
  $this->patch(route('admin.enquiries.update',$e),['status'=>'closed'])->assertSessionHasNoErrors();$this->assertSame('closed',$e->fresh()->status);
  $role=Role::create(['name'=>'Restricted','permissions'=>['dashboard.view']]);$user=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);$this->actingAs($user);
  $this->get('/admin/enquiries')->assertForbidden();$this->get('/admin/gallery')->assertForbidden();
 }
}
