<?php

namespace App\Enums;

/**
 * Permisos por módulo y acción (USR-02), con el formato `modulo.accion`.
 */
enum Permiso: string
{
    case UsuariosVer = 'usuarios.ver';
    case UsuariosGestionar = 'usuarios.gestionar';
    case ParametrosEditar = 'parametros.editar';
    case AuditoriaVer = 'auditoria.ver';
}
