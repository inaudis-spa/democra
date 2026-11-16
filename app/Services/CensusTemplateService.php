<?php
declare(strict_types=1);
namespace InaudisVote\Services;
use InaudisVote\Core\Database;
use InaudisVote\Core\Audit;
use PDO;
final class CensusTemplateService {
 public static function defaults():array {return [
 ['key'=>'rut','label'=>'RUT','type'=>'text','required'=>false,'show_on_display'=>false],
 ['key'=>'name','label'=>'Nombre','type'=>'text','required'=>true,'show_on_display'=>false],
 ['key'=>'email','label'=>'Email','type'=>'email','required'=>true,'show_on_display'=>false],
 ['key'=>'phone','label'=>'Teléfono','type'=>'text','required'=>false,'show_on_display'=>false],
 ['key'=>'vote_weight','label'=>'Peso del voto','type'=>'number','required'=>false,'show_on_display'=>false],
 ['key'=>'segment','label'=>'Segmento','type'=>'text','required'=>false,'show_on_display'=>false]];}
 public static function organization(int $id):array {
  $q=Database::connection()->prepare('SELECT fields_json FROM organization_census_templates WHERE organization_id=?');$q->execute([$id]);
  $json=$q->fetchColumn();return $json?json_decode($json,true,512,JSON_THROW_ON_ERROR):self::defaults();
 }
 public static function election(int $id):array {
  $q=Database::connection()->prepare('SELECT fields_json FROM election_census_templates WHERE election_id=?');$q->execute([$id]);$json=$q->fetchColumn();
  if($json)return json_decode($json,true,512,JSON_THROW_ON_ERROR);
  $q=Database::connection()->prepare('SELECT organization_id FROM elections WHERE id=?');$q->execute([$id]);$org=$q->fetchColumn();
  if(!$org)throw new \RuntimeException('Votación no encontrada.');return self::organization((int)$org);
 }
 public static function copy(int $id,int $org):void {
  $q=Database::connection()->prepare('INSERT INTO election_census_templates(election_id,fields_json) VALUES (?,?)');
  $q->execute([$id,json_encode(self::organization($org),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 }
 public static function save(string $scope,int $id,array $fields):void {
  if(!in_array($scope,['organization','election'],true))throw new \InvalidArgumentException('Ámbito inválido.');
  if($scope==='election'){
   $q=Database::connection()->prepare('SELECT status,census_locked_at FROM elections WHERE id=?');$q->execute([$id]);$e=$q->fetch(PDO::FETCH_ASSOC);
   if(!$e||!in_array($e['status'],['draft','scheduled'],true)||$e['census_locked_at'])throw new \RuntimeException('Padrón bloqueado.');
   $q=Database::connection()->prepare('SELECT COUNT(*) FROM census_members WHERE election_id=?');$q->execute([$id]);
   if($q->fetchColumn())throw new \RuntimeException('No se pueden modificar campos con votantes cargados.');
  }
  if(count($fields)>30)throw new \InvalidArgumentException('Máximo 30 campos.');
  $seen=[];$clean=[];
  foreach($fields as $f){
   $key=(string)($f['key']??'');$label=trim((string)($f['label']??''));$type=(string)($f['type']??'text');
   if(!preg_match('/^[a-z][a-z0-9_]{0,39}$/',$key)||isset($seen[$key])||$label===''||mb_strlen($label)>80||!in_array($type,['text','number','date','email'],true))throw new \InvalidArgumentException('Campo inválido o duplicado.');
   $publicAllowed=$type==='text'&&!in_array($key,['rut','name','email','phone','vote_weight'],true);
   $seen[$key]=true;$clean[]=['key'=>$key,'label'=>$label,'type'=>in_array($key,['name','email'],true)?($key==='email'?'email':'text'):$type,'required'=>in_array($key,['name','email'],true)||!empty($f['required']),'show_on_display'=>$publicAllowed&&!empty($f['show_on_display'])];
  }
  if(!isset($seen['name'],$seen['email']))throw new \InvalidArgumentException('Nombre y Email son obligatorios.');
  $table=$scope==='organization'?'organization_census_templates':'election_census_templates';$column=$scope==='organization'?'organization_id':'election_id';
  $q=Database::connection()->prepare("INSERT INTO $table ($column,fields_json) VALUES (:id,:json) ON DUPLICATE KEY UPDATE fields_json=VALUES(fields_json)");
  $q->execute(['id'=>$id,'json'=>json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
  Audit::log('census.template_updated',$scope,$id,['fields'=>count($clean)]);
 }
 public static function parse(string $csv,array $fields):array {
  $fp=fopen('php://temp','w+');fwrite($fp,preg_replace('/^\xEF\xBB\xBF/','',$csv));rewind($fp);
  $first=fgets($fp);rewind($fp);$sep=substr_count((string)$first,';')>=substr_count((string)$first,',')?';':',';
  $head=fgetcsv($fp,0,$sep,'"','');$keys=array_column($fields,'key');
  if(!$head||$head!==$keys){fclose($fp);throw new \InvalidArgumentException('Las columnas no coinciden con la plantilla vigente.');}
  $rows=[];$line=1;
  while(($values=fgetcsv($fp,0,$sep,'"',''))!==false){
   $line++;if($values===[null])continue;
   $rows[]=['line'=>$line,'data'=>count($values)===count($keys)?array_combine($keys,$values):null];
   if(count($rows)>10000){fclose($fp);throw new \InvalidArgumentException('Máximo 10.000 filas.');}
  }
  fclose($fp);return $rows;
 }
}
