<?php
declare(strict_types=1);
namespace InaudisVote\Services;
use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
use PDOException;
final class CensusService
{
    public static function list(int $electionId): array{$stmt=Database::connection()->prepare('SELECT * FROM census_members WHERE election_id=:id ORDER BY name');$stmt->execute(['id'=>$electionId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);}
    private static function requireEditable(int $electionId): void{$stmt=Database::connection()->prepare('SELECT status,census_locked_at FROM elections WHERE id=:id');$stmt->execute(['id'=>$electionId]);$e=$stmt->fetch(PDO::FETCH_ASSOC);if(!$e||!in_array($e['status'],['draft','scheduled'],true)||!empty($e['census_locked_at']))throw new \RuntimeException('El padrón está bloqueado y ya no admite cambios.');}
    public static function add(int $electionId,array $data): int
    {
        self::requireEditable($electionId);$name=trim((string)($data['name']??''));$email=mb_strtolower(trim((string)($data['email']??'')));$rawWeight=$data['vote_weight']??1;
        if($name==='')throw new \InvalidArgumentException('El nombre del votante es obligatorio.');
        if(mb_strlen($name)>150)throw new \InvalidArgumentException('El nombre del votante es demasiado largo.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Ingresa un correo electrónico válido para el votante.');
        if(!is_numeric($rawWeight)||(float)$rawWeight<=0)throw new \InvalidArgumentException('El peso del voto debe ser un número mayor que cero.');
        $stmt=Database::connection()->prepare('INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES (:election_id,:rut,:name,:email,:phone,:weight,:segment,"eligible")');
        try{$stmt->execute(['election_id'=>$electionId,'rut'=>trim((string)($data['rut']??''))?:null,'name'=>$name,'email'=>$email,'phone'=>trim((string)($data['phone']??''))?:null,'weight'=>(float)$rawWeight,'segment'=>trim((string)($data['segment']??''))?:null]);}
        catch(PDOException $e){if((string)$e->getCode()==='23000')throw new \InvalidArgumentException('Este correo ya está registrado en el padrón de la votación.');throw $e;}
        $id=(int)Database::connection()->lastInsertId();Audit::log('census.member_added','census_member',$id,['election_id'=>$electionId,'email'=>$email]);return $id;
    }
    public static function importCsv(int $electionId,string $csvContent): array
    {
        self::requireEditable($electionId);$lines=preg_split('/\r\n|\r|\n/',trim($csvContent))?:[];if(count($lines)>10000)throw new \InvalidArgumentException('El archivo supera el máximo de 10.000 filas.');$created=0;$errors=[];
        foreach($lines as $index=>$line){if(trim($line)==='')continue;$row=str_getcsv($line,';');if(count($row)<3)$row=str_getcsv($line,',');if($index===0&&isset($row[0])&&preg_match('/rut|nombre|name/i',implode(' ',$row)))continue;try{self::add($electionId,['rut'=>$row[0]??null,'name'=>$row[1]??'','email'=>$row[2]??'','phone'=>$row[3]??null,'vote_weight'=>$row[4]??1,'segment'=>$row[5]??null]);$created++;}catch(\Throwable $e){$errors[]='Fila '.($index+1).': '.user_error_message($e);}}
        Audit::log('census.csv_imported','election',$electionId,['created'=>$created,'errors'=>count($errors)]);return ['created'=>$created,'errors'=>$errors];
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
