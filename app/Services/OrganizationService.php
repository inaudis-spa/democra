<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
use PDOException;

final class OrganizationService
{
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT id, name, tax_id, slug, logo_path, status, created_at FROM organizations ORDER BY created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function logoPath(int $organizationId): ?string
    {
        $stmt=Database::connection()->prepare('SELECT logo_path FROM organizations WHERE id=:id');
        $stmt->execute(['id'=>$organizationId]);
        $path=$stmt->fetchColumn();
        return is_string($path) && preg_match('~^/uploads/organization-logos/[a-f0-9]{32}\.(png|jpg|webp)$~',$path) ? $path : null;
    }

    public static function saveLogo(int $organizationId,array $file): void
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Selecciona una imagen válida.');
        $size=(int)($file['size']??0);
        if($size<1||$size>2*1024*1024)throw new \InvalidArgumentException('El logo debe pesar hasta 2 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_uploaded_file($tmp))throw new \InvalidArgumentException('Archivo de carga inválido.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if(!isset($extensions[$mime])||@getimagesize($tmp)===false)throw new \InvalidArgumentException('Utiliza PNG, JPG o WebP.');
        $directory=dirname(__DIR__,2).'/public/uploads/organization-logos';
        if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException('No fue posible preparar el directorio de logos.');
        $path='/uploads/organization-logos/'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];
        $target=dirname(__DIR__,2).'/public'.$path;
        if(!move_uploaded_file($tmp,$target))throw new \RuntimeException('No fue posible guardar el logo.');
        $pdo=Database::connection();
        try{
            $stmt=$pdo->prepare('UPDATE organizations SET logo_path=:path WHERE id=:id');
            $stmt->execute(['path'=>$path,'id'=>$organizationId]);
            if($stmt->rowCount()!==1)throw new \RuntimeException('Organización no encontrada.');
            Audit::log('organization.logo_updated','organization',$organizationId);
        }catch(\Throwable $e){@unlink($target);throw $e;}
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Nombre y slug son obligatorios.');
        }

        $asciiSlug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $normalizedSlug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $asciiSlug) ?? ''), '-');
        if ($normalizedSlug === '') {
            throw new \InvalidArgumentException('El identificador web debe contener letras o números.');
        }
        if (mb_strlen($name) > 150 || strlen($normalizedSlug) > 120) {
            throw new \InvalidArgumentException('El nombre o identificador web es demasiado largo.');
        }
        $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true)
            ? (string) ($data['status'] ?? 'active')
            : 'active';
        $stmt = $pdo->prepare(
            'INSERT INTO organizations (name, tax_id, slug, status) VALUES (:name, :tax_id, :slug, :status)'
        );
        try {
            $stmt->execute([
                'name' => $name,
                'tax_id' => trim((string) ($data['tax_id'] ?? '')) ?: null,
                'slug' => $normalizedSlug,
                'status' => $status,
            ]);
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                throw new \InvalidArgumentException('El identificador web ya está siendo utilizado por otra organización.');
            }
            throw $exception;
        }

        $id = (int) $pdo->lastInsertId();
        Audit::log('organization.created', 'organization', $id, ['name' => $name]);
        return $id;
    }
}
