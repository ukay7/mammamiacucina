<?php
namespace Tests\Feature;
use App\Models\{Category,Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CategoryProtectionTest extends TestCase {
 use RefreshDatabase;
 public function test_category_managers_can_change_protection_and_viewers_cannot():void {
  $super=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
  $this->actingAs($super);
  $data=['name'=>'Home Collection','is_active'=>1,'sort_order'=>0,'show_to_customer'=>0];
  $this->post('/admin/categories',$data+['is_protected'=>1])->assertSessionHasNoErrors();
  $c=Category::where('name',$data['name'])->firstOrFail();$this->assertTrue($c->is_protected);
  $this->delete('/admin/categories/'.$c->id)->assertSessionHasErrors('category');
  $this->get('/admin/categories/'.$c->id.'/edit')->assertOk()->assertSee('Protect this category')->assertDontSee('Delete Category');
  $role=Role::create(['name'=>'Category Editor','permissions'=>['categories.view','categories.manage']]);
  $editor=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);$this->actingAs($editor);
  $this->get('/admin/categories/'.$c->id.'/edit')->assertOk()->assertSee('Protect this category');
  $this->put('/admin/categories/'.$c->id,$data+['is_protected'=>0])->assertSessionHasNoErrors();
  $this->assertFalse($c->fresh()->is_protected);
  $this->put('/admin/categories/'.$c->id,$data+['is_protected'=>1])->assertSessionHasNoErrors();
  $viewerRole=Role::create(['name'=>'Category Viewer','permissions'=>['categories.view']]);
  $viewer=User::factory()->create(['role_id'=>$viewerRole->id,'is_active'=>true]);
  $this->actingAs($viewer)->put('/admin/categories/'.$c->id,$data+['is_protected'=>0])->assertForbidden();
  $this->actingAs($editor);
  $this->put('/admin/categories/'.$c->id,$data+['description'=>'Updated collection'])->assertSessionHasNoErrors();
  $this->assertTrue($c->fresh()->is_protected);$this->assertSame('Updated collection',$c->fresh()->description);
  $this->delete('/admin/categories/'.$c->id)->assertSessionHasErrors('category');
  $this->actingAs($super)->put('/admin/categories/'.$c->id,$data+['is_protected'=>0])->assertSessionHasNoErrors();
  $this->delete('/admin/categories/'.$c->id)->assertSessionHasNoErrors();$this->assertModelMissing($c);
  $general=Category::where('slug','general')->firstOrFail();
  $this->put('/admin/categories/'.$general->id,['name'=>$general->name,'is_active'=>1,'sort_order'=>0,'is_protected'=>0])->assertSessionHasNoErrors();
  $this->assertTrue($general->fresh()->is_protected);
  $this->delete('/admin/categories/'.$general->id)->assertSessionHasErrors('category');
 }
}
