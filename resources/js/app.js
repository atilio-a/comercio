import './bootstrap';

import Alpine from 'alpinejs';

import subirFotos from './catalogo/achicar-imagenes';
import grillaVariantes from './catalogo/grilla-variantes';

window.Alpine = Alpine;

Alpine.data('grillaVariantes', grillaVariantes);
Alpine.data('subirFotos', subirFotos);

Alpine.start();
