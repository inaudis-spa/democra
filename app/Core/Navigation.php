<?php

declare(strict_types=1);

namespace InaudisVote\Core;

final class Navigation
{
    public static function sidebar(array $user, string $active, ?int $electionId = null, ?string $electionTitle = null): void
    {
        $role = (string) ($user['role'] ?? '');
        $name = htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $roleLabel = match ($role) {
            'admin' => 'Administrador',
            'organizer' => 'Organizador',
            'witness' => 'Ministro de Fe',
            default => 'Usuario',
        };
        $item = static function (string $key, string $href, string $icon, string $label) use ($active): string {
            $class = $active === $key ? ' class="active" aria-current="page"' : '';
            $tourKey = match ($key) {
                'elections' => 'elections',
                'users' => 'team',
                'audit' => 'audit',
                'guide' => 'guide',
                'control' => 'control',
                default => null,
            };
            $tour = $tourKey ? ' data-tour="'.$tourKey.'"' : '';
            return '<a'.$class.$tour.' href="'.$href.'"><span class="v-nav-icon" aria-hidden="true">'.$icon.'</span><span>'.$label.'</span></a>';
        };

        echo '<aside class="v-sidebar">';
        echo '<div class="v-brand"><img class="v-brandmark" src="/assets/democra-mark.png" alt=""><span><span class="v-brand-name">DEMOCRA<small>VOTO DIGITAL</small></span></span></div>';
        echo '<nav class="v-nav" aria-label="Navegación principal"><div class="v-nav-label">Plataforma</div>';
        if ($role !== 'witness') echo $item('dashboard', '/dashboard.php', '&#8962;', 'Inicio');
        echo $item('elections', '/elections.php', '&#10003;', 'Votaciones');
        if ($role === 'admin') echo $item('organizations', '/organizations.php', '&#9638;', 'Organizaciones');
        if ($role === 'admin') echo $item('users', '/users.php', '&#9817;', 'Equipo y accesos');
        if ($role !== 'organizer') echo $item($electionId ? 'platform-audit' : 'audit', '/audit.php', '&#9687;', $role === 'admin' ? 'Auditoría general' : 'Auditoría de procesos');
        echo $item('guide', '/manual.php?perfil='.rawurlencode($role), '?', 'Cómo funciona');

        if ($electionId !== null && $electionId > 0) {
            $id = (int) $electionId;
            $title = htmlspecialchars((string) ($electionTitle ?: 'Proceso seleccionado'), ENT_QUOTES, 'UTF-8');
            echo '<div class="v-nav-context"><div class="v-nav-label">Votación actual</div>';
            echo '<div class="v-nav-election" title="'.$title.'"><span>Proceso</span><strong>'.$title.'</strong></div>';
            if ($role === 'organizer') { echo $item('overview', '/control.php?election_id='.$id, '&#9679;', 'Resumen'); echo $item('census', '/census.php?election_id='.$id, '&#9817;', 'Padrón (consulta)'); }
            if ($role === 'admin') {
                echo $item('control', '/control.php?election_id='.$id, '&#9679;', 'Centro de control');
                echo $item('configuration', '/election.php?id='.$id, '&#9881;', 'Configuración');
                echo $item('census', '/census.php?election_id='.$id, '&#9817;', 'Participantes');
            }
            if ($role !== 'organizer') echo $item('witness', '/fiscalizacion.php?election_id='.$id, '&#9878;', 'Fiscalización');
            echo $item('results', '/results.php?election_id='.$id, '&#9638;', 'Resultados');
            if ($role !== 'organizer') echo $item('election-audit', '/audit.php?election_id='.$id, '&#9687;', 'Auditoría del proceso');
            echo '</div>';
        }
        echo '</nav>';
        echo '<div class="v-sidebar-foot"><div class="v-session-label">Sesión activa</div><strong>'.$name.'</strong><div class="v-role-caption">'.$roleLabel.'</div><a class="v-sidebar-logout" href="/logout.php">Cerrar sesión</a></div></aside>';
    }
}
