<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailTemplate extends Model {
 protected $primaryKey='key';public $incrementing=false;protected $keyType='string';protected $guarded=['key'];
 public static function renderMessage(string $key,array $values):array {
  $definition=config('email_templates.'.$key);if(!$definition)throw new \InvalidArgumentException('Unknown email template');
  $template=static::find($key);$replace=[];
  foreach($definition['tokens'] as $token)$replace['{{'.$token.'}}']=(string)($values[$token]??'');
  return ['subject'=>str_replace(["\r","\n"],' ',strtr($template?->subject??$definition['subject'],$replace)),'body'=>strtr($template?->body??$definition['body'],$replace)];
 }
}
