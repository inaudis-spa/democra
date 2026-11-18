<?php
declare(strict_types=1);
namespace InaudisVote\Services;
use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
use PDOException;
final class UserService
{
    private const ROLES = ['admin','organizer','witness'];

    public static function all(): array
    {
        return Database::connection()->query('SELECT u.id,u.name,u.email,u.role,u.status,u.organization_id,o.name organization_name,u.created_at FROM users u LEFT JOIN organizations o ON o.id=u.organization_id ORDER BY u.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function allForActor(array $actor): array
    {
        if(($actor['role']??'')==='admin')return self::all();
        $stmt=Database::connection()->prepare("SELECT u.id,u.name,u.email,u.role,u.status,u.organization_id,o.name organization_name,u.created_at FROM users u JOIN organizations o ON o.id=u.organization_id WHERE u.organization_id=:org AND u.role IN ('organizer','witness') ORDER BY u.created_at DESC");
        $stmt->execute(['org'=>(int)($actor['organization_id']??0)]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function create(array $data,?array $actor=null): int
    {
        $pdo=Database::connection();$name=trim((string)($data['name']??''));$email=mb_strtolower(trim((string)($data['email']??'')));$password=(string)($data['password']??'');
        $role=in_array(($data['role']??''),['admin','organizer','witness'],true)?(string)$data['role']:'organizer';$organizationId=!empty($data['organization_id'])?(int)$data['organization_id']:null;
        if(($actor['role']??'')==='organizer'){$organizationId=(int)$actor['organization_id'];if(!in_array($role,['organizer','witness'],true))throw new \RuntimeException('Un organizador no puede crear administradores DEMOCRA.');}
        if($name==='')throw new \InvalidArgumentException('El nombre es obligatorio.');
        if(mb_strlen($name)>150)throw new \InvalidArgumentException('El nombre no puede superar 150 caracteres.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Ingresa un correo electrónico válido.');
        if(strlen($password)<8)throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        if(in_array($role,['organizer','witness'],true)&&!$organizationId)throw new \InvalidArgumentException('Selecciona la organización a la que pertenecerá el organizador.');
        if(in_array($role,['organizer','witness'],true)){$check=$pdo->prepare("SELECT COUNT(*) FROM organizations WHERE id=:id AND status='active'");$check->execute(['id'=>$organizationId]);if(!(int)$check->fetchColumn())throw new \InvalidArgumentException('La organización seleccionada no existe o está inactiva.');}
        try{$stmt=$pdo->prepare('INSERT INTO users (organization_id,name,email,password_hash,role,status) VALUES (:organization_id,:name,:email,:password_hash,:role,"active")');$stmt->execute(['organization_id'=>$role==='admin'?null:$organizationId,'name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);}
        catch(PDOException $e){if((string)$e->getCode()==='23000')throw new \InvalidArgumentException('Ya existe un usuario registrado con ese correo electrónico.');throw $e;}
        $id=(int)$pdo->lastInsertId();Audit::log('user.created','user',$id,['email'=>$email,'role'=>$role]);return $id;
    }

    public static function update(int $id,array $data,array $actor): void
    {
        $current=self::manageableUser($id,$actor);
        $pdo=Database::connection();
        $name=trim((string)($data['name']??''));
        $email=mb_strtolower(trim((string)($data['email']??'')));
        $role=in_array(($data['role']??''),self::ROLES,true)?(string)$data['role']:(string)$current['role'];
        $organizationId=!empty($data['organization_id'])?(int)$data['organization_id']:null;
        if($name===''||mb_strlen($name)>150)throw new \InvalidArgumentException('Ingresa un nombre de hasta 150 caracteres.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Ingresa un correo electrónico válido.');
        if(($actor['role']??'')!=='admin'){$role=(string)$current['role'];$organizationId=(int)$actor['organization_id'];}
        if((int)$actor['id']===$id){$role=(string)$current['role'];$organizationId=$current['organization_id']!==null?(int)$current['organization_id']:null;}
        if($role==='admin')$organizationId=null;
        if(in_array($role,['organizer','witness'],true)){
            if(!$organizationId)throw new \InvalidArgumentException('Selecciona el cliente al que pertenece el usuario.');
            $check=$pdo->prepare("SELECT COUNT(*) FROM organizations WHERE id=:id AND status='active'");$check->execute(['id'=>$organizationId]);
            if(!(int)$check->fetchColumn())throw new \InvalidArgumentException('El cliente seleccionado no existe o está inactivo.');
        }
        $pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare('UPDATE users SET organization_id=:organization_id,name=:name,email=:email,role=:role WHERE id=:id');
            $stmt->execute(['organization_id'=>$organizationId,'name'=>$name,'email'=>$email,'role'=>$role,'id'=>$id]);
            $managerCleanup=$pdo->prepare("DELETE em FROM election_managers em JOIN elections e ON e.id=em.election_id WHERE em.user_id=:id AND (:role<>'organizer' OR e.organization_id<>:organization_id)");
            $managerCleanup->execute(['id'=>$id,'role'=>$role,'organization_id'=>$organizationId??0]);
            $witnessCleanup=$pdo->prepare("DELETE ew FROM election_witnesses ew JOIN elections e ON e.id=ew.election_id WHERE ew.user_id=:id AND (:role<>'witness' OR e.organization_id<>:organization_id)");
            $witnessCleanup->execute(['id'=>$id,'role'=>$role,'organization_id'=>$organizationId??0]);
            $pdo->commit();
        }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')throw new \InvalidArgumentException('Ya existe un usuario registrado con ese correo electrónico.');throw $e;}
        Audit::log('user.updated','user',$id,['email'=>$email,'role'=>$role,'organization_id'=>$organizationId]);
    }

    public static function resetPassword(int $id,string $password,string $confirmation,array $actor): void
    {
        self::manageableUser($id,$actor);
        if(strlen($password)<8)throw new \InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres.');
        if($password!==$confirmation)throw new \InvalidArgumentException('La confirmación de contraseña no coincide.');
        $stmt=Database::connection()->prepare('UPDATE users SET password_hash=:password_hash WHERE id=:id');
        $stmt->execute(['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);
        Audit::log('user.password_reset','user',$id);
    }
    public static function toggleStatus(int $id,?array $actor=null): void
    {
        if($actor)self::manageableUser($id,$actor);
        if($actor&&(int)($actor['id']??0)===$id)throw new \RuntimeException('No puedes desactivar tu propia cuenta.');
        $stmt=Database::connection()->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=:id");$stmt->execute(['id'=>$id]);
        if($stmt->rowCount()!==1)throw new \RuntimeException('El usuario indicado no existe.');
        Audit::log('user.status_toggled','user',$id);
    }

    private static function manageableUser(int $id,array $actor): array
    {
        if($id<=0)throw new \InvalidArgumentException('Usuario no válido.');
        $stmt=Database::connection()->prepare('SELECT id,organization_id,name,email,role,status FROM users WHERE id=:id');
        $stmt->execute(['id'=>$id]);$target=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$target)throw new \RuntimeException('El usuario indicado no existe.');
        if(($actor['role']??'')==='admin')return $target;
        $allowed=(int)($target['organization_id']??0)===(int)($actor['organization_id']??0)&&in_array($target['role'],['organizer','witness'],true);
        if(!$allowed)throw new \RuntimeException('No puedes administrar ese usuario.');
        return $target;
    }
}
