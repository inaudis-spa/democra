<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class UserService
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT u.id,u.name,u.email,u.role,u.status,u.organization_id,o.name organization_name,u.created_at FROM users u LEFT JOIN organizations o ON o.id=u.organization_id ORDER BY u.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(array $data): int
    {
        $name=trim((string)($data['name']??''));
        $email=mb_strtolower(trim((string)($data['email']??'')));
        $password=(string)($data['password']??'');
        $role=in_array(($data['role']??''),['admin','organizer'],true)?$data['role']:'organizer';
        $organizationId=!empty($data['organization_id'])?(int)$data['organization_id']:null;
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8) throw new \InvalidArgumentException('Nombre, email válido y contraseña de al menos 8 caracteres son obligatorios.');
        if($role==='organizer'&&!$organizationId) throw new \InvalidArgumentException('El organizador debe pertenecer a una organización.');
        $stmt=Database::connection()->prepare('INSERT INTO users (organization_id,name,email,password_hash,role,status) VALUES (:organization_id,:name,:email,:password_hash,:role,"active")');
        $stmt->execute(['organization_id'=>$role==='admin'?null:$organizationId,'name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
        $id=(int)Database::connection()->lastInsertId();
        Audit::log('user.created','user',$id,['email'=>$email,'role'=>$role]);
        return $id;
    }

    public static function toggleStatus(int $id): void
    {
        $stmt=Database::connection()->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=:id");
        $stmt->execute(['id'=>$id]);
        Audit::log('user.status_toggled','user',$id);
    }
}
