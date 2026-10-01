<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class InitializeNumbering extends Command {
 protected $signature='app:initialize-numbering {--orders=3000} {--customers=4000}';
 protected $description='Raise the next order/customer IDs without renumbering or lowering existing sequences';
 public function handle():int {
  if(app()->environment('production')&&!app()->isDownForMaintenance()){$this->error('Run in maintenance mode on production.');return self::FAILURE;}
  $db=DB::connection();$driver=$db->getDriverName();
  if(!in_array($driver,['sqlite','mysql','mariadb'],true)){$this->error('Unsupported database driver.');return self::FAILURE;}
  foreach(['orders','customers'] as $table){
   $value=(string)$this->option($table);
   if(!ctype_digit($value)||(int)$value<1||(int)$value>2000000000){$this->error('Starting IDs must be between 1 and 2000000000.');return self::FAILURE;}
  }
  foreach(['orders','customers'] as $table){
   $floor=(int)$this->option($table);
   if($driver==='sqlite'){
    $next=$db->transaction(function()use($db,$table,$floor){
     $next=max($floor,(int)$db->table($table)->max('id')+1,(int)$db->table('sqlite_sequence')->where('name',$table)->value('seq')+1);
     $db->table('sqlite_sequence')->updateOrInsert(['name'=>$table],['seq'=>$next-1]);return $next;
    });
   }else{
    $sequence=$db->selectOne('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',[$db->getDatabaseName(),$table]);
    $next=max($floor,(int)$db->table($table)->max('id')+1,(int)($sequence->next_id??1));
    $db->statement("ALTER TABLE `$table` AUTO_INCREMENT = $next");
   }
   $this->info($table.': next ID is at least '.$next.($table==='orders'?' (mmc-'.$next.')':''));
  }
  return self::SUCCESS;
 }
}
