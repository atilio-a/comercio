/**
 * Lista de ítems de una orden de compra o de una recepción.
 *
 * Modos:
 * - "orden": se agregan variantes con el buscador o el escáner; cantidad y costo editables.
 * - "libre": recepción sin orden; igual que "orden" más la marca de actualizar costo.
 * - "contraOrden": recepción de una orden; los ítems son los de la orden (con lo pendiente),
 *   no se agregan ni quitan, y el escáner suma 1 al ítem con ese código.
 *
 * Solo ayuda a cargar: el servidor valida variantes, cantidades, pendientes y costos.
 */
export default function itemsCompra({ items = [], modo = 'orden', urlBuscar = '', errores = {} } = {}) {
    return {
        items: items.map((item) => ({ ...item, cantidad: String(item.cantidad ?? ''), costo: String(item.costo ?? '') })),
        modo,
        urlBuscar,
        // Server validation messages by field ("items.0.cantidad").
        errores,
        busqueda: '',
        resultados: [],
        buscando: false,
        codigo: '',
        mensaje: '',
        temporizador: null,

        get agregaItems() {
            return this.modo !== 'contraOrden';
        },

        /**
         * Total en pesos (cantidad × costo), calculado en centavos enteros solo para mostrar.
         */
        get total() {
            const centavos = this.items.reduce((suma, item) => {
                const cantidad = Number.parseInt(item.cantidad, 10) || 0;
                const costo = Math.round((Number.parseFloat(item.costo) || 0) * 100);

                return suma + cantidad * costo;
            }, 0);

            return (centavos / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        get unidades() {
            return this.items.reduce((suma, item) => suma + (Number.parseInt(item.cantidad, 10) || 0), 0);
        },

        excede(item) {
            return item.pendiente !== undefined && (Number.parseInt(item.cantidad, 10) || 0) > item.pendiente;
        },

        buscar() {
            clearTimeout(this.temporizador);

            if (this.busqueda.trim().length < 2) {
                this.resultados = [];

                return;
            }

            this.temporizador = setTimeout(async () => {
                this.buscando = true;
                this.resultados = await this.consultar(this.busqueda.trim(), false);
                this.buscando = false;
                this.mensaje = this.resultados.length === 0 ? 'No se encontraron variantes.' : `${this.resultados.length} variantes encontradas.`;
            }, 250);
        },

        async consultar(texto, exacto) {
            const parametros = new URLSearchParams({ q: texto });

            if (exacto) {
                parametros.set('exacto', '1');
            }

            try {
                const respuesta = await fetch(`${this.urlBuscar}?${parametros}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!respuesta.ok) {
                    return [];
                }

                return (await respuesta.json()).variantes ?? [];
            } catch {
                this.mensaje = 'No se pudo buscar. Revisá la conexión.';

                return [];
            }
        },

        agregar(variante) {
            const existente = this.items.find((item) => item.variante_id === variante.variante_id);

            if (existente) {
                existente.cantidad = String((Number.parseInt(existente.cantidad, 10) || 0) + 1);
                this.mensaje = `${variante.producto} ${variante.descripcion}: ${existente.cantidad} unidades.`;
            } else {
                this.items.push({ ...variante, cantidad: '1', costo: String(variante.costo), actualizar_costo: false });
                this.mensaje = `Se agregó ${variante.producto} ${variante.descripcion}.`;
            }

            this.busqueda = '';
            this.resultados = [];
        },

        /**
         * Lectores de código de barras: escriben el código y Enter. Suma una unidad.
         */
        async escanear() {
            const codigo = this.codigo.trim();
            this.codigo = '';

            if (codigo === '') {
                return;
            }

            const local = this.items.find((item) => item.codigo === codigo || item.sku === codigo);

            if (local) {
                this.agregar(local);

                return;
            }

            if (!this.agregaItems) {
                this.mensaje = `El código ${codigo} no está en la orden o ya se recibió todo. Registralo como recepción sin orden.`;

                return;
            }

            const encontradas = await this.consultar(codigo, true);

            if (encontradas.length === 1) {
                this.agregar(encontradas[0]);
            } else {
                this.mensaje = `No se encontró una variante activa con el código ${codigo}.`;
            }
        },

        quitar(indice) {
            const [quitado] = this.items.splice(indice, 1);
            // Positions shift: server messages no longer match their rows.
            this.errores = {};
            this.mensaje = `Se quitó ${quitado.producto} ${quitado.descripcion}.`;
        },

        recibirTodo() {
            this.items.forEach((item) => {
                item.cantidad = String(item.pendiente);
            });
            this.mensaje = 'Se cargó todo lo pendiente.';
        },

        ponerEnCero() {
            this.items.forEach((item) => {
                item.cantidad = '0';
            });
            this.mensaje = 'Cantidades en 0.';
        },
    };
}
