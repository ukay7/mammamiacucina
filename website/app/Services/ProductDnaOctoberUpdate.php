<?php
namespace App\Services;

use App\Models\{Product,Category,ProductPricingDraft};
use Illuminate\Support\Facades\{DB,Storage};
use RuntimeException;
use ZipArchive;

class ProductDnaOctoberUpdate
{
    public function dataset(): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/product-dna-20261005.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function run(bool $apply = false): array
    {
        if ($apply && app()->environment('production') && !app()->isDownForMaintenance()) {
            throw new RuntimeException('Put the live website in maintenance mode before applying this update.');
        }
        $data = $this->dataset();
        $root = config('product_dna_update.backup_root', storage_path('app/private/product-dna-updates'));
        if (!is_dir($root) && !mkdir($root, 0700, true)) throw new RuntimeException('Cannot create private backup directory.');
        $lock = fopen($root.'/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another product update is running.');
        try {
            $marker = $root.'/'.$data['sha256'].'.done.json';
            if (is_file($marker)) return ['already_applied'=>true,'report'=>$marker];
            $report = ['source'=>$data['source'],'sha256'=>$data['sha256'],'mode'=>$apply?'apply':'preview','updated'=>0,'matched'=>0,'skipped'=>0,'rows'=>[]];
            $work = function () use ($data, $apply, &$report, $root) {
                $products = Product::orderBy('id')->lockForUpdate()->get();
                $used = []; $plan = [];
                foreach ($data['rows'] as $index => $row) {
                    $internal = trim($row['Internal code']);
                    $matches = $products->filter(fn($p)=>trim((string)$p->qr_code)===$internal && $internal!=='');
                    // Internal code is authoritative; never guess from a similar name.
                    $entry = ['row'=>$index+2,'internal_code'=>$internal,'name'=>$row['Mamma Mia name']];
                    if ($matches->count()!==1) {
                        $report['rows'][]=$entry+['status'=>'skipped','reason'=>$matches->isEmpty()?'Internal code not found':'Ambiguous internal code'];$report['skipped']++;continue;
                    }
                    $product=$matches->first();
                    if(isset($used[$product->id]))throw new RuntimeException('Duplicate source internal code: '.$internal);
                    $used[$product->id]=true;
                    $supplierCode=trim($row['Supplier SKU']);
                    if($supplierCode!=='' && $supplierCode!==trim((string)$product->product_code) && $products->contains(fn($p)=>$p->id!==$product->id && trim((string)$p->product_code)===$supplierCode)){
                        $report['rows'][]=$entry+['status'=>'skipped','reason'=>'Supplier code belongs to another product'];$report['skipped']++;continue;
                    }
                    [$fields,$inputs,$warnings]=$this->values($row,$product);
                    $report['matched']++;
                    $entry += ['id'=>$product->id,'status'=>$apply?'updated':'would update','business_before'=>$product->business_selling_price_cad,'business_after'=>$fields['business_selling_price_cad']??$product->business_selling_price_cad,'individual_before'=>$product->total_selling_price_cad,'individual_after'=>$fields['total_selling_price_cad']??$product->total_selling_price_cad,'warnings'=>$warnings];
                    $report['rows'][]=$entry;
                    $plan[]=[$product,$fields,$inputs];
                }
                if(!$apply)return;
                if(!$plan)throw new RuntimeException('No matching products. No changes made.');
                $backup=$root.'/backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
                if(!mkdir($backup,0700,true))throw new RuntimeException('Cannot create backup directory.');
                $this->backup($backup);
                $report['backup']=$backup;
                foreach($plan as [$product,$fields,$inputs]){
                    $product->forceFill($fields);$product->editor_revision++;$product->save();
                    $draft=$product->pricingDraft;
                    ProductPricingDraft::updateOrCreate(['product_id'=>$product->id],['inputs'=>array_replace($draft?->inputs??[],$inputs),'options'=>$draft?->options??[],'revision'=>($draft?->revision??0)+1,'updated_by'=>$draft?->updated_by]);
                    $report['updated']++;
                }
                $this->write($backup.'/report.json',$report);
            };
            DB::transaction($work);
            if($apply)$this->write($marker,$report);
            return $report;
        } finally {flock($lock, LOCK_UN);fclose($lock);}
    }

    private function number(array $row,string $key): ?float
    {
        $value=trim((string)($row[$key]??''));
        if($value==='')return null;
        if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>1000000000)throw new RuntimeException('Invalid '.$key.' for '.$row['Internal code']);
        return (float)$value;
    }

    public function values(array $row, Product $product): array
    {
        $warnings=[];$fields=[];$dna=$product->dna??[];
        foreach(['supplier'=>'Supplier','product_code'=>'Supplier SKU','premium_marketing_name'=>'Mamma Mia name','original_description'=>'Product name on source'] as $key=>$header){
            if(trim($row[$header])!=='')$fields[$key]=$row[$header];
        }
        foreach(['unit_weight_g'=>'Weight per piece, g','supplier_unit_price_eur'=>'Price per piece, EUR','supplier_discount'=>'Discount %','rate_exchange_cad'=>'CAD per 1 EUR','shipping_cost_cad'=>'Freight, CAD / piece','surcharge_increase_cad'=>'Other cost, CAD / piece'] as $key=>$header){
            $v=$this->number($row,$header);if($v!==null)$fields[$key]=$v;
        }
        foreach(['manufacturer'=>'Manufacturer','recipient'=>'Purchaser on document','delivery_to'=>'Delivery destination on source','allocated_to'=>'Internal brand allocation','owner_notes'=>'Review notes'] as $key=>$header){if(trim($row[$header])!=='')$dna[$key]=$row[$header];}
        $gluten=['Unknown'=>'unknown','Declared, verify'=>'declared','Verified by owner'=>'verified','No'=>'no'];
        if(isset($gluten[$row['Gluten-free status']]))$dna['gluten_status']=$gluten[$row['Gluten-free status']];
        $pieces=$this->number($row,'Pieces per carton');
        if($pieces!==null && ($pieces<1||floor($pieces)!==$pieces))throw new RuntimeException('Invalid carton count for '.$row['Internal code']);
        if($pieces!==null)$dna['pieces_per_carton']=(int)$pieces;
        $weight=$this->number($row,'Net carton weight, kg');if($weight!==null)$dna['net_carton_kg']=$weight;
        $unit=strtolower($row['Sell as']);if(!in_array($unit,['piece','carton'],true))throw new RuntimeException('Unsupported sale unit: '.$unit);
        $dna['sale_unit']=$unit;
        if(preg_match('/^(\d+) Carton = (\d+) Piece$/',$row['Minimum sale'],$m)){$dna['minimum_cartons']=(int)$m[1];$dna['minimum_pieces']=(int)$m[2];}
        $category=Category::all()->filter(fn($c)=>mb_strtolower(trim($c->name))===mb_strtolower(trim($row['Category'])));
        if($category->count()===1)$fields['category_id']=$category->first()->id;
        else {$dna['product_family']=$row['Category'];$warnings[]='Website category preserved; Excel category saved as product family: '.$row['Category'];}
        // File availability is not an upload or verification. Preserve image metadata and all file records.
        $warnings[]='Image status and ingredients-file declarations not applied to existing files.';
        $fields['dna']=$dna;
        $inputs=['profit_mode'=>'markup','profit_percent'=>'150','business_profit_percent'=>'50'];
        foreach(['purchase_price'=>'Supplier quoted price','discount'=>'Discount %','fx'=>'CAD per 1 EUR','freight_carton'=>'Freight, CAD / carton','other_unit_cost'=>'Other cost, CAD / piece','grams'=>'Weight per piece, g'] as $key=>$header){$v=$this->number($row,$header);if($v!==null)$inputs[$key]=(string)$row[$header];}
        if($pieces!==null)$inputs['pieces_per_carton']=(string)(int)$pieces;
        $basis=strtolower($row['Supplier price basis']);if(in_array($basis,['piece','carton','pack','kg'],true))$inputs['purchase_basis']=$basis;
        if(in_array($row['Purchase currency'],['EUR','CAD'],true))$inputs['currency']=$row['Purchase currency'];
        $cost=$this->number($row,'Total unit cost, CAD');$business=$this->number($row,$unit==='carton'?'Selling price / carton, CAD':'Selling price / piece, CAD');
        if($cost===null||$business===null||($unit==='carton'&&$pieces===null)){
            $warnings[]='Incomplete source pricing: BOTH existing selling prices preserved.';
        }else{
            // Match the existing calculator: round each piece before multiplying by carton quantity.
            $individualCents=(int)round($cost*2.5*100,0,PHP_ROUND_HALF_UP)*($unit==='carton'?(int)$pieces:1);
            $fields['total_selling_price_cad']=number_format($individualCents/100,2,'.','');
            $fields['business_selling_price_cad']=$row[$unit==='carton'?'Selling price / carton, CAD':'Selling price / piece, CAD'];
        }
        return [$fields,$inputs,$warnings];
    }

    private function write(string $path,array $data): void
    {
        if(file_put_contents($path,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Cannot write backup/report.');
        chmod($path,0600);
    }

    private function backup(string $directory): void
    {
        $tables=['products','product_pricing_drafts','product_price_reviews','product_media','product_documents','category_product'];
        $snapshot=[];foreach($tables as $table)$snapshot[$table]=DB::table($table)->get()->map(fn($r)=>(array)$r)->all();
        $this->write($directory.'/tables.json',$snapshot);
        $zip=new ZipArchive;
        if($zip->open($directory.'/product-files.zip',ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create image backup.');
        $manifest=[];
        try {
            foreach(array_merge($snapshot['product_media'],$snapshot['product_documents']) as $file){
                $path=$file['path'];if(isset($manifest[$path]))continue;
                if(!Storage::disk('local')->exists($path))throw new RuntimeException('Referenced product file missing; backup stopped: '.$path);
                $absolute=Storage::disk('local')->path($path);
                if(!$zip->addFile($absolute,$path))throw new RuntimeException('Cannot back up product file.');
                $manifest[$path]=hash_file('sha256',$absolute);
            }
            if(!$manifest)$zip->addFromString('NO_PRODUCT_FILES.txt','No referenced product files.');
        }finally {if(!$zip->close())throw new RuntimeException('Image backup could not be finalized.');}
        chmod($directory.'/product-files.zip',0600);$this->write($directory.'/file-hashes.json',$manifest);
    }
}
