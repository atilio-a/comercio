/**
 * Venta rápida en el mostrador (VEN-01).
 *
 * Solo arma el pedido: el servidor toma los precios, calcula descuentos, recargos, IVA y lo que
 * falta pagar (POST de previsualización) y valida todo al registrar. Acá no hay reglas de
 * negocio, solo ayudas para cargar desde el celular: buscador, lector de códigos (escribe el
 * código y Enter), elección de talle (si el parámetro está habilitado) y color con su foto,
 * carrito, cliente, vendedor y pagos combinados.
 */
export default function ventaRapida(config = {}) {
    const formato = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return {
        usarTalles: Boolean(config.usarTalles),
        puedeAutorizar: Boolean(config.puedeAutorizar),
        medios: config.medios ?? [],
        urls: config.urls ?? {},

        busqueda: '',
        resultados: [],
        buscando: false,
        codigo: '',
        seleccion: null,
        mensaje: '',
        temporizador: null,
        temporizadorClientes: null,

        items: [],
        descuento: '',
        mostrarDescuentos: false,

        cliente: null,
        clienteBusqueda: '',
        clientes: [],
        clienteNuevo: { nombre: '', telefono: '', dni_cuit: '' },
        erroresCliente: {},

        vendedorId: config.vendedorId ? String(config.vendedorId) : '',
        puntoVentaId: config.puntoVentaId ? String(config.puntoVentaId) : '',
        pagos: [],
        autorizacion: { email: '', password: '' },

        calculo: null,
        previsualizando: null,
        errores: {},
        enviando: false,

        init() {
            const efectivo = this.medios.find((medio) => medio.tipo === 'efectivo') ?? this.medios[0];

            if (efectivo) {
                this.pagos.push(this.nuevoPago(efectivo.id));
            }
        },

        importe(valor) {
            return formato.format(Number.parseFloat(valor ?? 0) || 0);
        },

        error(campo) {
            return (this.errores[campo] ?? [])[0] ?? '';
        },

        // --- Products ---------------------------------------------------------------

        buscar() {
            clearTimeout(this.temporizador);
            this.seleccion = null;

            if (this.busqueda.trim().length < 2) {
                this.resultados = [];

                return;
            }

            this.temporizador = setTimeout(async () => {
                this.buscando = true;
                this.resultados = await this.consultar(this.busqueda.trim(), false);
                this.buscando = false;
                this.mensaje = this.resultados.length === 0 ? 'No se encontraron productos.' : `${this.resultados.length} productos encontrados.`;
            }, 250);
        },

        async consultar(texto, exacto) {
            const parametros = new URLSearchParams({ q: texto });

            if (exacto) {
                parametros.set('exacto', '1');
            }

            try {
                const respuesta = await fetch(`${this.urls.buscar}?${parametros}`, { headers: this.cabeceras() });

                return respuesta.ok ? ((await respuesta.json()).productos ?? []) : [];
            } catch {
                this.mensaje = 'No se pudo buscar. Revisá la conexión.';

                return [];
            }
        },

        /**
         * Lectores de código de barras: escriben el código y Enter. Agrega una unidad.
         */
        async escanear() {
            const codigo = this.codigo.trim();
            this.codigo = '';

            if (codigo === '') {
                return;
            }

            const productos = await this.consultar(codigo, true);
            const variantes = productos.flatMap((producto) => producto.variantes.map((variante) => ({ producto, variante })));

            if (variantes.length === 1) {
                this.agregar(variantes[0].variante, variantes[0].producto);
            } else {
                this.mensaje = `No se encontró un producto activo con el código ${codigo}.`;
            }
        },

        elegirProducto(producto) {
            if (producto.variantes.length === 1) {
                this.agregar(producto.variantes[0], producto);

                return;
            }

            this.seleccion = { producto, talle: null, colorId: null };
            this.mensaje = this.pideTalle() ? `Elegí talle y color de ${producto.nombre}.` : `Elegí el color de ${producto.nombre}.`;
        },

        pideTalle() {
            return this.usarTalles && Boolean(this.seleccion?.producto.usa_talles);
        },

        talles() {
            if (!this.pideTalle()) {
                return [];
            }

            return [...new Set(this.seleccion.producto.variantes.map((variante) => variante.talle).filter((talle) => talle !== null && talle !== undefined))];
        },

        colores() {
            if (!this.seleccion) {
                return [];
            }

            if (this.pideTalle() && this.seleccion.talle === null) {
                return [];
            }

            const ids = new Set(this.variantesElegibles(null).map((variante) => variante.color_id));

            return this.seleccion.producto.colores.filter((color) => ids.has(color.color_id));
        },

        /**
         * Variantes del producto con el talle elegido (si corresponde) y el color indicado.
         */
        variantesElegibles(colorId) {
            return this.seleccion.producto.variantes.filter((variante) => (!this.pideTalle() || variante.talle === this.seleccion.talle)
                && (colorId === null || variante.color_id === colorId));
        },

        elegirTalle(talle) {
            this.seleccion.talle = talle;
            this.seleccion.colorId = null;
        },

        elegirColor(colorId) {
            this.seleccion.colorId = colorId;
            const variantes = this.variantesElegibles(colorId);

            if (variantes.length === 1) {
                this.agregar(variantes[0], this.seleccion.producto);
            }
        },

        fotoDe(producto, colorId) {
            return producto.colores.find((color) => color.color_id === colorId)?.foto ?? null;
        },

        agregar(variante, producto) {
            const existente = this.items.find((item) => item.variante_id === variante.variante_id);

            if (existente) {
                existente.cantidad = String((Number.parseInt(existente.cantidad, 10) || 0) + 1);
                this.mensaje = `${variante.producto} ${variante.descripcion}: ${existente.cantidad} unidades.`;
            } else {
                this.items.push({
                    variante_id: variante.variante_id,
                    producto: variante.producto,
                    descripcion: variante.descripcion,
                    sku: variante.sku,
                    precio_final: variante.precio_final,
                    stock: variante.stock,
                    foto: this.fotoDe(producto, variante.color_id),
                    cantidad: '1',
                    descuento: '',
                });
                this.mensaje = `Se agregó ${variante.producto} ${variante.descripcion}.`;
            }

            this.seleccion = null;
            this.busqueda = '';
            this.resultados = [];
            this.previsualizar();
        },

        cambiarCantidad(item, delta) {
            item.cantidad = String(Math.max(1, (Number.parseInt(item.cantidad, 10) || 0) + delta));
            this.previsualizar();
        },

        quitar(indice) {
            const [quitado] = this.items.splice(indice, 1);
            // Positions shift: server messages no longer match their rows.
            this.errores = {};
            this.mensaje = `Se quitó ${quitado.producto} ${quitado.descripcion}.`;
            this.previsualizar();
        },

        // --- Customer --------------------------------------------------------------

        buscarClientes() {
            clearTimeout(this.temporizadorClientes);

            if (this.clienteBusqueda.trim().length < 2) {
                this.clientes = [];

                return;
            }

            this.temporizadorClientes = setTimeout(async () => {
                try {
                    const respuesta = await fetch(`${this.urls.clientes}?${new URLSearchParams({ q: this.clienteBusqueda.trim() })}`, { headers: this.cabeceras() });
                    this.clientes = respuesta.ok ? ((await respuesta.json()).clientes ?? []) : [];
                } catch {
                    this.clientes = [];
                }

                this.mensaje = this.clientes.length === 0 ? 'No se encontraron clientes.' : `${this.clientes.length} clientes encontrados.`;
            }, 250);
        },

        elegirCliente(cliente) {
            this.cliente = cliente;
            this.clientes = [];
            this.clienteBusqueda = '';
            this.mensaje = `Cliente: ${cliente.nombre}.`;
        },

        quitarCliente() {
            this.cliente = null;
            this.mensaje = 'Venta a consumidor final.';
        },

        async crearCliente() {
            this.erroresCliente = {};

            try {
                const respuesta = await fetch(this.urls.clienteRapido, {
                    method: 'POST',
                    headers: this.cabeceras(true),
                    body: JSON.stringify(this.clienteNuevo),
                });
                const datos = await respuesta.json();

                if (respuesta.status === 201) {
                    this.elegirCliente(datos.cliente);
                    this.clienteNuevo = { nombre: '', telefono: '', dni_cuit: '' };
                } else {
                    this.erroresCliente = datos.errors ?? {};
                    this.mensaje = 'Revisá los datos del cliente.';
                }
            } catch {
                this.mensaje = 'No se pudo crear el cliente. Revisá la conexión.';
            }
        },

        // --- Payments --------------------------------------------------------------

        nuevoPago(medioId, importe = '') {
            return { medio_pago_id: String(medioId), plan_cuota_id: '', importe, referencia: '', lote: '', cupon: '' };
        },

        medio(pago) {
            return this.medios.find((medio) => String(medio.id) === String(pago.medio_pago_id)) ?? null;
        },

        cambiarMedio(pago) {
            const planes = this.medio(pago)?.planes ?? [];
            pago.plan_cuota_id = planes.length > 0 ? String(planes[0].id) : '';
            this.previsualizar();
        },

        agregarPago() {
            const pendiente = Number.parseFloat(this.calculo?.pendiente ?? '0');
            const efectivo = this.medios.find((medio) => medio.tipo === 'efectivo') ?? this.medios[0];
            this.pagos.push(this.nuevoPago(efectivo.id, pendiente > 0 ? this.calculo.pendiente : ''));
            this.previsualizar();
        },

        quitarPago(indice) {
            this.pagos.splice(indice, 1);
            this.errores = {};
            this.previsualizar();
        },

        /**
         * Suma al pago lo que falta (o lo descuenta si sobra).
         */
        completar(pago) {
            const actual = Math.round((Number.parseFloat(pago.importe) || 0) * 100);
            const pendiente = Math.round((Number.parseFloat(this.calculo?.pendiente ?? '0') || 0) * 100);
            pago.importe = (Math.max(0, actual + pendiente) / 100).toFixed(2);
            this.previsualizar();
        },

        recargoDe(indice) {
            return this.calculo?.pagos?.[indice]?.recargo ?? '0.00';
        },

        // --- Server ----------------------------------------------------------------

        get hayDescuentos() {
            return (Number.parseFloat(this.descuento) || 0) > 0 || this.items.some((item) => (Number.parseFloat(item.descuento) || 0) > 0);
        },

        get requiereAutorizacion() {
            return this.hayDescuentos && !this.puedeAutorizar;
        },

        get pendiente() {
            return Number.parseFloat(this.calculo?.pendiente ?? 'NaN');
        },

        get listo() {
            return this.items.length > 0 && this.calculo !== null && this.pendiente === 0 && this.vendedorId !== '' && !this.enviando;
        },

        pedido() {
            return {
                items: this.items.map((item) => ({ variante_id: item.variante_id, cantidad: item.cantidad, descuento: item.descuento || '0' })),
                descuento: this.descuento || '0',
                pagos: this.pagos.map((pago) => ({
                    medio_pago_id: pago.medio_pago_id,
                    plan_cuota_id: pago.plan_cuota_id || null,
                    importe: pago.importe || '0',
                    referencia: pago.referencia || null,
                    lote: pago.lote || null,
                    cupon: pago.cupon || null,
                })),
            };
        },

        previsualizar() {
            clearTimeout(this.previsualizando);

            if (this.items.length === 0) {
                this.calculo = null;

                return;
            }

            this.previsualizando = setTimeout(async () => {
                try {
                    const respuesta = await fetch(this.urls.previsualizar, {
                        method: 'POST',
                        headers: this.cabeceras(true),
                        body: JSON.stringify(this.pedido()),
                    });
                    const datos = await respuesta.json();

                    if (respuesta.ok) {
                        this.calculo = datos;
                        this.errores = {};
                    } else {
                        this.calculo = null;
                        this.errores = datos.errors ?? {};
                    }
                } catch {
                    this.mensaje = 'No se pudieron calcular los totales. Revisá la conexión.';
                }
            }, 300);
        },

        async confirmar() {
            if (!this.listo) {
                return;
            }

            this.enviando = true;
            this.errores = {};

            try {
                const respuesta = await fetch(this.urls.registrar, {
                    method: 'POST',
                    headers: this.cabeceras(true),
                    body: JSON.stringify({
                        ...this.pedido(),
                        cliente_id: this.cliente?.id ?? null,
                        vendedor_id: this.vendedorId,
                        punto_venta_id: this.puntoVentaId || null,
                        autorizacion: this.requiereAutorizacion ? this.autorizacion : null,
                    }),
                });
                const datos = await respuesta.json();

                if (respuesta.status === 201) {
                    this.mensaje = datos.mensaje;
                    window.location.assign(datos.url);

                    return;
                }

                this.errores = datos.errors ?? {};
                this.mensaje = Object.values(this.errores)[0]?.[0] ?? datos.message ?? 'No se pudo registrar la venta.';
            } catch {
                this.mensaje = 'No se pudo registrar la venta. Revisá la conexión.';
            }

            this.enviando = false;
        },

        cabeceras(json = false) {
            const cabeceras = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

            if (json) {
                cabeceras['Content-Type'] = 'application/json';
                cabeceras['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            }

            return cabeceras;
        },
    };
}
