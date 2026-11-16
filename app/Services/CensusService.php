<?php
declare(strict_types=1);
namespace InaudisVote\Services;
use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
use PDOException;
final class CensusService
{
    public static function list(int $electionId): array
    {
        $q=Database::connection()->prepare('SELECT cm.*,cv.values_json FROM census_members cm LEFT JOIN census_custom_values cv ON cv.census_member_id=cm.id WHERE cm.election_id=? ORDER BY cm.name');
        $q->execute([$electionId]);$members=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($members as &$member){$member['custom_values']=$member['values_json']?json_decode($member['values_json'],true,512,JSON_THROW_ON_ERROR):[];unset($member['values_json']);}
        return $members;
    }
    private static function requireEditable(int $electionId): void
    {
        $q=Database::connection()->prepare('SELECT status,census_locked_at FROM elections WHERE id=?');$q->execute([$electionId]);$e=$q->fetch(PDO::FETCH_ASSOC);
        if(!$e||!in_array($e['status'],['draft','scheduled'],true)||!empty($e['census_locked_at']))throw new \RuntimeException('El padrón está bloqueado y ya no admite cambios.');
    }
    public static function validateFields(array $data,array $fields): void
    {
        foreach($fields as $field){
            $key=$field['key'];$value=trim((string)($data[$key]??''));
            if($field['required']&&$value==='')throw new \InvalidArgumentException('Falta campo obligatorio: '.$field['label']);
            if($value!==''&&$field['type']==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Email inválido: '.$field['label']);
            if($value!==''&&$field['type']==='number'&&!is_numeric($value))throw new \InvalidArgumentException('Número inválido: '.$field['label']);
            if($value!==''&&$field['type']==='date'){
                $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if(!$d||$d->format('Y-m-d')!==$value)throw new \InvalidArgumentException('Fecha inválida (AAAA-MM-DD): '.$field['label']);
            }
        }
        $rut=trim((string)($data['rut']??''));
        if($rut!==''&&self::normalizeRut($rut)===null)throw new \InvalidArgumentException('RUT inválido.');
        $weight=($data['vote_weight']??'')===''?1:$data['vote_weight'];
        if(!is_numeric($weight)||(float)$weight<=0)throw new \InvalidArgumentException('El peso del voto debe ser mayor que cero.');
    }
    private static function normalizeRut(string $rut): ?string
    {
        $value=strtoupper(preg_replace('/[\.\s-]/','',$rut)??'');
        if(!preg_match('/^([0-9]{1,8})([0-9K])$/',$value,$m))return null;
        $sum=0;$factor=2;
        foreach(array_reverse(str_split($m[1])) as $digit){$sum+=(int)$digit*$factor;$factor=$factor===7?2:$factor+1;}
        $dv=11-($sum%11);$expected=$dv===11?'0':($dv===10?'K':(string)$dv);
        return $expected===$m[2]?ltrim($m[1],'0').'-'.$m[2]:null;
    }
    public static function add(int $electionId,array $data): int
    {
        self::requireEditable($electionId);
        $fields=CensusTemplateService::election($electionId);
        self::validateFields($data,$fields);
        $name=trim((string)($data['name']??''));$email=mb_strtolower(trim((string)($data['email']??'')));
        if($name===''||mb_strlen($name)>150)throw new \InvalidArgumentException('Nombre inválido.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Email inválido.');
        $rut=trim((string)($data['rut']??''));$normalized=$rut!==''?self::normalizeRut($rut):null;
        $pdo=Database::connection();$ownTransaction=!$pdo->inTransaction();
        if($ownTransaction)$pdo->beginTransaction();
        try {
            if($normalized!==null){
                $q=$pdo->prepare('SELECT rut FROM census_members WHERE election_id=? AND rut IS NOT NULL FOR UPDATE');$q->execute([$electionId]);
                foreach($q->fetchAll(PDO::FETCH_COLUMN) as $existing)if(self::normalizeRut((string)$existing)===$normalized)throw new \InvalidArgumentException('Este RUT ya está registrado en el padrón.');
            }
            $q=$pdo->prepare('INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES (:eid,:rut,:name,:email,:phone,:weight,:segment,"eligible")');
            $q->execute(['eid'=>$electionId,'rut'=>$normalized,'name'=>$name,'email'=>$email,'phone'=>trim((string)($data['phone']??''))?:null,'weight'=>($data['vote_weight']??'')===''?1:(float)$data['vote_weight'],'segment'=>trim((string)($data['segment']??''))?:null]);
            $id=(int)$pdo->lastInsertId();$standard=array_flip(['rut','name','email','phone','vote_weight','segment']);
            $custom=array_diff_key($data,$standard);
            $custom=array_intersect_key($custom,array_flip(array_column($fields,'key')));
            if($custom){$q=$pdo->prepare('INSERT INTO census_custom_values(census_member_id,values_json) VALUES (?,?)');$q->execute([$id,json_encode($custom,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}
            Audit::log('census.member_added','census_member',$id,['election_id'=>$electionId,'email'=>$email]);
            if($ownTransaction)$pdo->commit();
            return $id;
        }catch(\Throwable $e){
            if($ownTransaction&&$pdo->inTransaction())$pdo->rollBack();
            if($e instanceof PDOException&&(string)$e->getCode()==='23000')throw new \InvalidArgumentException('Correo o identificador duplicado en el padrón.');
            throw $e;
        }
    }
    public static function previewCsv(int $electionId,string $csv): array
    {
        $fields=CensusTemplateService::election($electionId);$rows=CensusTemplateService::parse($csv,$fields);
        $errors=[];$valid=0;$seenEmails=[];$seenRuts=[];
        $q=Database::connection()->prepare('SELECT email,rut FROM census_members WHERE election_id=?');$q->execute([$electionId]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $member){
            $seenEmails[mb_strtolower(trim($member['email']))]=true;
            if($member['rut']&&($r=self::normalizeRut($member['rut'])))$seenRuts[$r]=true;
        }
        foreach($rows as $row){
            try {
                if($row['data']===null)throw new \InvalidArgumentException('Cantidad de columnas incorrecta.');
                $data=$row['data'];self::validateFields($data,$fields);
                $email=mb_strtolower(trim((string)($data['email']??'')));
                if(isset($seenEmails[$email]))throw new \InvalidArgumentException('Email duplicado.');
                $seenEmails[$email]=true;
                $rut=trim((string)($data['rut']??''));
                if($rut!==''){
                    $normalized=self::normalizeRut($rut);
                    if(isset($seenRuts[$normalized]))throw new \InvalidArgumentException('RUT duplicado.');
                    $seenRuts[$normalized]=true;
                }
                $valid++;
            }catch(\Throwable $e){$errors[]='Fila '.$row['line'].': '.$e->getMessage();}
        }
        return ['valid'=>$valid,'errors'=>$errors,'total'=>count($rows)];
    }
    public static function importCsv(int $electionId,string $csv): array
    {
        self::requireEditable($electionId);
        $preview=self::previewCsv($electionId,$csv);
        if($preview['errors'])return ['created'=>0,'errors'=>$preview['errors']];
        $rows=CensusTemplateService::parse($csv,CensusTemplateService::election($electionId));
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            foreach($rows as $row)self::add($electionId,$row['data']);
            Audit::log('census.csv_imported','election',$electionId,['created'=>count($rows),'errors'=>0]);
            $pdo->commit();
            return ['created'=>count($rows),'errors'=>[]];
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            return ['created'=>0,'errors'=>['Importación cancelada: '.$e->getMessage()]];
        }
    }
    public static function generateInvitation(int $electionId,int $memberId): array
    {
        $pdo=Database::connection();
        $raw=bin2hex(random_bytes(32));
        $hash=hash('sha256',$raw);
        $otp=(string)random_int(100000,999999);
        $otpHash=password_hash($otp,PASSWORD_DEFAULT);
        $ttl=max(60,(int)(getenv('OTP_TTL_SECONDS')?:600));
        $pdo->beginTransaction();
        try {
            // Lock election first, consistent with the voting and closure paths.
            $election=$pdo->prepare('SELECT status,otp_ttl_minutes FROM elections WHERE id=:id FOR UPDATE');
            $election->execute(['id'=>$electionId]);
            $electionRow=$election->fetch(PDO::FETCH_ASSOC);
            if(!$electionRow || $electionRow['status']!=='open')throw new \RuntimeException('Abre la votación antes de generar invitaciones.');
            $ttl=max(600,min(86400,(int)$electionRow['otp_ttl_minutes']*60));
            $stmt=$pdo->prepare('SELECT id,election_id,has_voted,status,email,name FROM census_members WHERE id=:id AND election_id=:eid FOR UPDATE');
            $stmt->execute(['id'=>$memberId,'eid'=>$electionId]);
            $member=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$member)throw new \RuntimeException('El votante seleccionado no pertenece a esta votación.');
            if($member['status']!=='eligible'||(int)$member['has_voted']===1)throw new \RuntimeException('El votante no está habilitado o ya emitió su voto.');
            // Preserve invitation history; revoke old active tokens without claiming they were used.
            $revoke=$pdo->prepare('UPDATE voter_tokens SET otp_locked_at=COALESCE(otp_locked_at,NOW()) WHERE census_member_id=:id AND election_id=:eid AND consumed_at IS NULL');
            $revoke->execute(['id'=>$memberId,'eid'=>$electionId]);
            $insert=$pdo->prepare('INSERT INTO voter_tokens (election_id,census_member_id,token_hash,otp_hash,otp_expires_at,created_at) VALUES (:election_id,:member_id,:token_hash,:otp_hash,DATE_ADD(NOW(),INTERVAL :ttl SECOND),NOW())');
            $insert->execute(['election_id'=>$electionId,'member_id'=>$memberId,'token_hash'=>$hash,'otp_hash'=>$otpHash,'ttl'=>$ttl]);
            Audit::log('voter.invitation_generated','census_member',$memberId,['election_id'=>$electionId,'channel'=>'demo']);
            $pdo->commit();
            return ['token'=>$raw,'otp'=>$otp,'email'=>$member['email'],'name'=>$member['name'],'expires_in_seconds'=>$ttl];
        } catch(\Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }
}
