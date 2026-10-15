import './bootstrap';

import Alpine from 'alpinejs';

import subirFotos from './catalogo/achicar-imagenes';
import grillaVariantes from './catalogo/grilla-variantes';
import itemsCompra from './compras/items-compra';
import devolucion from './ventas/devolucion';
import ventaRapida from './ventas/venta-rapida';

window.Alpine = Alpine;

Alpine.data('devolucion', devolucion);
Alpine.data('grillaVariantes', grillaVariantes);
Alpine.data('itemsCompra', itemsCompra);
Alpine.data('subirFotos', subirFotos);
Alpine.data('ventaRapida', ventaRapida);

Alpine.start();
