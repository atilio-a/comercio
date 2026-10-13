import './bootstrap';

import Alpine from 'alpinejs';

import subirFotos from './catalogo/achicar-imagenes';
import grillaVariantes from './catalogo/grilla-variantes';
import itemsCompra from './compras/items-compra';

window.Alpine = Alpine;

Alpine.data('grillaVariantes', grillaVariantes);
Alpine.data('itemsCompra', itemsCompra);
Alpine.data('subirFotos', subirFotos);

Alpine.start();
