<?php
namespace Tests\Feature;
use App\Models\{User,Role,SitePolicy,CareerDepartment,CareerJob};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class ContentPagesTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);}
 public function test_policy_crud_publication_safe_text_and_footer():void {
  $admin=$this->admin();$this->actingAs($admin);
  $this->get(route('admin.content.create','policies'))->assertOk();
  $this->put(route('admin.content.page','policies'),['title'=>'Our Policies','heading'=>'Helpful information','eyebrow'=>'CUSTOMER CARE','introduction'=>'Our latest published policies.'])->assertSessionHasNoErrors();
  $this->get('/policies')->assertOk()->assertSee('Helpful information');
  $data=['title'=>'Returns policy','summary'=>'Please read before ordering','body'=>'<script>alert(1)</script> Policy paragraph','effective_date'=>'2026-10-10','is_active'=>0,'sort_order'=>1];
  $this->post(route('admin.content.store','policies'),$data)->assertSessionHasNoErrors();$p=SitePolicy::firstOrFail();
  $this->get('/policies')->assertOk()->assertDontSee('Returns policy');
  $this->put(route('admin.content.update',['policies',$p]),array_replace($data,['is_active'=>1]))->assertSessionHasNoErrors();
  $this->get('/policies')->assertOk()->assertSee('Returns policy')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false)->assertSee('href="'.route('theme.careers').'"',false);
  $this->get(route('admin.content.index','policies'))->assertOk();$this->get(route('admin.content.edit',['policies',$p]))->assertOk();
  $this->delete(route('admin.content.destroy',['policies',$p]))->assertSessionHasNoErrors();$this->assertDatabaseCount('site_policies',0);
 }
 public function test_departments_jobs_images_expiry_and_permissions():void {
  Storage::fake('local');$admin=$this->admin();$this->actingAs($admin);
  $image=fn()=>UploadedFile::fake()->createWithContent('kitchen.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
  $this->get(route('admin.content.create','departments'))->assertOk();
  $this->post(route('admin.content.store','departments'),['title'=>'Kitchen','description'=>'Meet the kitchen team','sort_order'=>0,'is_active'=>1,'image'=>$image(),'image_alt'=>'Our kitchen'])->assertSessionHasNoErrors();
  $dept=CareerDepartment::firstOrFail();$old=$dept->image_path;Storage::disk('local')->assertExists($old);
  $job=['title'=>'Pastry Chef','department_id'=>$dept->id,'location'=>'Vaughan','employment_type'=>'Full-time','salary'=>'CAD 25–30/hour','description'=>'Prepare Italian desserts.','skills'=>"Baking\nTeamwork",'application_email'=>'jobs@example.test','application_instructions'=>'Send your CV','closing_date'=>now()->addDay()->toDateString(),'sort_order'=>1,'is_active'=>1];
  $this->get(route('admin.content.create','jobs'))->assertOk();$this->post(route('admin.content.store','jobs'),$job)->assertSessionHasNoErrors();$j=CareerJob::firstOrFail();
  $this->get(route('admin.content.edit',['jobs',$j]))->assertOk();$this->get(route('admin.content.index','jobs'))->assertOk();
  $this->get('/careers')->assertOk()->assertSee('Pastry Chef')->assertSee('Kitchen');$this->get(route('careers.job',$j))->assertOk()->assertSee('Teamwork')->assertSee('Apply by email');
  $this->delete(route('admin.content.destroy',['departments',$dept]))->assertSessionHasErrors('department');
  $this->put(route('admin.content.update',['jobs',$j]),array_replace($job,['closing_date'=>now()->subDay()->toDateString()]))->assertSessionHasNoErrors();
  $this->get('/careers')->assertDontSee('Pastry Chef');$this->get(route('careers.job',$j))->assertNotFound();
  $this->put(route('admin.content.update',['jobs',$j]),$job)->assertSessionHasNoErrors();
  $this->put(route('admin.content.update',['departments',$dept]),['title'=>'Kitchen','sort_order'=>0,'is_active'=>0,'image'=>$image()])->assertSessionHasNoErrors();Storage::disk('local')->assertMissing($old);
  $this->get(route('careers.image',$dept))->assertOk();
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'No content permission','permissions'=>[]])->id]);
  $this->actingAs($staff)->get(route('admin.content.index','jobs'))->assertForbidden();$this->post(route('admin.content.store','jobs'),$job)->assertForbidden();
  $this->get(route('careers.image',$dept))->assertNotFound();$this->get(route('careers.job',$j))->assertNotFound();$this->get('/careers')->assertDontSee('Kitchen');
  $this->actingAs($admin)->delete(route('admin.content.destroy',['jobs',$j]))->assertSessionHasNoErrors();
  $path=$dept->fresh()->image_path;$this->delete(route('admin.content.destroy',['departments',$dept]))->assertSessionHasNoErrors();Storage::disk('local')->assertMissing($path);
 }

 public function test_demo_seeder_can_repeat_without_overwriting_edited_content_or_images():void {
  Storage::fake('local');
  $this->seed(\Database\Seeders\PoliciesCareersDemoSeeder::class);
  $this->assertDatabaseCount('site_policies',4);$this->assertDatabaseCount('career_departments',3);$this->assertDatabaseCount('career_jobs',4);
  foreach(CareerDepartment::all() as $department)Storage::disk('local')->assertExists($department->image_path);
  $policy=SitePolicy::first();$policy->update(['title'=>'My edited policy','body'=>'Approved custom text','is_active'=>false]);
  $department=CareerDepartment::first();$department->update(['title'=>'Custom team']);
  $job=CareerJob::first();$job->update(['title'=>'Custom vacancy','application_email'=>'jobs@example.org','is_active'=>false]);
  $files=Storage::disk('local')->allFiles('career-departments');
  $this->seed(\Database\Seeders\PoliciesCareersDemoSeeder::class);
  $this->assertDatabaseCount('site_policies',4);$this->assertDatabaseCount('career_departments',3);$this->assertDatabaseCount('career_jobs',4);
  $this->assertSame('Approved custom text',$policy->fresh()->body);$this->assertFalse($policy->fresh()->is_active);
  $this->assertSame('Custom team',$department->fresh()->title);$this->assertSame('jobs@example.org',$job->fresh()->application_email);
  $this->assertFalse($job->fresh()->is_active);$this->assertSame($files,Storage::disk('local')->allFiles('career-departments'));
 }
}
