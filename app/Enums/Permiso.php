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
    case CatalogoVer = 'catalogo.ver';
    case CatalogoGestionar = 'catalogo.gestionar';
    case StockVer = 'stock.ver';
    case StockAjustar = 'stock.ajustar';
    case CajaOperar = 'caja.operar';
    case CajaSupervisar = 'caja.supervisar';
    case CajaConfigurar = 'caja.configurar';
    case ComprasVer = 'compras.ver';
    case ComprasGestionar = 'compras.gestionar';
    case ComprasRecibir = 'compras.recibir';
    case ClientesVer = 'clientes.ver';
    case ClientesGestionar = 'clientes.gestionar';
    case CuentasCorrientesVer = 'cuentas_corrientes.ver';
}
