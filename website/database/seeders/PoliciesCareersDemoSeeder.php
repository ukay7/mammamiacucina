<?php
namespace Database\Seeders;
use App\Models\{SitePolicy,CareerDepartment,CareerJob,ContentPageSetting};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
class PoliciesCareersDemoSeeder extends Seeder {
 public function run():void {
  $createdFiles=[];
  try { DB::transaction(function()use(&$createdFiles){
   foreach(['policies','careers'] as $page){$settings=ContentPageSetting::forPage($page);if(!$settings->exists)$settings->save();}
   $policies=[
    ['ordering','Ordering & Payment','A guide to placing an order and selecting a payment method.',"Placing an order\nBrowse our products, choose your quantities and review your order before confirming checkout. Check your contact details so the team can reach you about your order.\n\nPayment options\nAvailable payment methods are displayed at checkout. For e-transfer orders, upload your payment receipt through the order confirmation page or your account. A receipt submission remains subject to administrator verification.\n\nOrder changes\nContact the team with your order number if you need to request a change. Availability and any price differences must be confirmed before the change is completed."],
    ['fulfillment','Pickup & Delivery','Helpful information for receiving your order.',"Pickup orders\nSelect pickup at checkout and review the displayed collection address. Keep your order number handy and contact the team to confirm collection arrangements.\n\nDelivery orders\nEnter a complete delivery address and an accurate postal code. Available delivery services and charges are shown during checkout where applicable.\n\nReceiving your products\nCheck your order when it arrives. Follow the storage and handling instructions supplied with each product, and contact us if anything needs attention."],
    ['order-support','Order Changes & Refund Requests','How to contact our team when an order needs attention.',"Something needs attention?\nContact the team with your order number and a clear description of the issue. If an item is damaged or incorrect, photographs can help the team understand what happened.\n\nChanges and adjustments\nThe team will review the request and confirm any changes to quantities, charges or amounts due.\n\nPayment adjustments\nWhere a refund is agreed, the payment record will show the amount returned. A refund due balance does not mean money has already been returned; the actual refund must be confirmed separately."],
    ['product-care','Product Care & Allergen Information','Get the information you need before choosing and serving a product.',"Before ordering\nReview the product information and contact the team for ingredient, allergen and serving details. Do not assume a product is suitable for a dietary requirement unless this has been confirmed.\n\nStorage and preparation\nFollow the instructions on the product packaging or supplied by the team. Storage and preparation requirements can vary between products.\n\nQuestions\nPlease contact us before ordering if you need clarification about ingredients, handling or preparation."]
   ];
   foreach($policies as $i=>[$key,$title,$summary,$body])SitePolicy::firstOrCreate(['demo_key'=>'demo-policy-'.$key],['title'=>$title,'summary'=>$summary,'body'=>"SAMPLE POLICY — For demonstration only. Review and replace this text with your approved business policy before public use.\n\n".$body,'effective_date'=>null,'is_active'=>true,'sort_order'=>($i+1)*10]);
   $departments=[];
   foreach([
    ['kitchen','Kitchen & Pastry','Sample department: bring care, creativity and consistency to the preparation and presentation of Italian desserts.','tradition-pastries.png','A selection of Italian pastries'],
    ['warehouse','Warehouse & Fulfillment','Sample department: help products move from our shelves to customer orders with accuracy, care and teamwork.','category-cakes-hd.png','Italian cakes from the product collection'],
    ['customer','Sales & Customer Care','Sample department: help customers discover our range, answer product questions and support every stage of their order.','cannoli-hero.png','Italian cannoli from the product collection']
   ] as $i=>[$key,$title,$description,$asset,$alt]){
    $department=CareerDepartment::firstOrNew(['demo_key'=>'demo-department-'.$key]);
    if(!$department->exists){
     $source=public_path('assets/images/mmc/'.$asset);if(!is_readable($source))throw new \RuntimeException('Demo department image missing: '.$asset);
     $path='career-departments/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,file_get_contents($source)))throw new \RuntimeException('Cannot save demo department image.');$createdFiles[]=$path;
     $department->fill(['title'=>$title,'description'=>$description,'image_path'=>$path,'image_alt'=>$alt,'is_active'=>true,'sort_order'=>($i+1)*10])->save();
    }
    $departments[$key]=$department;
   }
   $jobs=[
    ['pastry-chef','kitchen','Pastry Chef','Full-time',"Prepare and finish desserts with attention to quality and presentation. Work closely with the kitchen team to support daily orders.","Prepare and portion products\nFollow product handling instructions\nMaintain a clean and organized workspace","Pastry or bakery experience\nCareful attention to presentation\nClear communication and teamwork"],
    ['kitchen-assistant','kitchen','Kitchen Assistant','Part-time',"Support the kitchen team with preparation, organization and packing tasks.","Assist with daily preparation\nOrganize supplies and equipment\nSupport cleaning and packing routines","Interest in food preparation\nReliable and organized approach\nWillingness to learn"],
    ['warehouse-associate','warehouse','Warehouse Associate','Full-time',"Help receive, organize and pack products for pickup and delivery orders.","Check products against order lists\nScan and pack items accurately\nReport shortages and damaged items","Attention to detail\nComfort using a phone or barcode scanner\nStrong teamwork and organization"],
    ['customer-care','customer','Customer Care Coordinator','Full-time',"Support customers with product questions, order updates and collection arrangements.","Respond to customer enquiries\nCheck order details and follow up with the team\nKeep clear customer service notes","Friendly written and verbal communication\nComfort using online order systems\nA patient, helpful approach"]
   ];
   foreach($jobs as $i=>[$key,$department,$title,$type,$description,$responsibilities,$skills])CareerJob::firstOrCreate(['demo_key'=>'demo-job-'.$key],[
    'department_id'=>$departments[$department]->id,'title'=>$title.' (Sample)','location'=>'Vaughan, Ontario — sample location','employment_type'=>$type,'salary'=>null,
    'description'=>"DEMONSTRATION VACANCY — This is sample content, not an active recruitment announcement. Replace it with an approved vacancy before accepting applications.\n\n".$description,
    'responsibilities'=>$responsibilities,'skills'=>$skills,'benefits'=>"Sample benefit: supportive team environment\nSample benefit: learning and development opportunities",
    'application_email'=>'careers@example.test','application_instructions'=>'Demo only: replace the example email with your recruitment inbox. For a real vacancy, specify the documents applicants should send, such as a CV and introduction.',
    'closing_date'=>null,'is_active'=>true,'sort_order'=>($i+1)*10
   ]);
  }); } catch(\Throwable $e){foreach($createdFiles as $path)Storage::disk('local')->delete($path);throw $e;}
  $this->command?->info('Demo content ready: 4 policies, 3 departments with images and 4 sample vacancies. Existing seeded records were preserved. Review demo labels and application email before public use.');
 }
}
