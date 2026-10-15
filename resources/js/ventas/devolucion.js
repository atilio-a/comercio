/**
 * Componente Alpine para el formulario de devolución (VEN-03).
 *
 * Controla la visualización condicional según la resolución elegida:
 * - Saldo a favor: búsqueda y alta rápida de cliente si la venta fue a consumidor final.
 * - Reembolso: selección del medio de pago de egreso y referencia opcional.
 */
export default function devolucion(config = {}) {
    return {
        resolucion: config.resolucion || 'nota_credito_saldo_a_favor',
        urls: config.urls || {},
        ventaEsConsumidorFinal: Boolean(config.ventaEsConsumidorFinal),
        puedeReembolsar: Boolean(config.puedeReembolsar),
        admiteReembolso: Boolean(config.admiteReembolso),
        cajaAbierta: Boolean(config.cajaAbierta),

        cliente: config.cliente || null,
        clienteBusqueda: '',
        clientes: [],
        clienteNuevo: { nombre: '', telefono: '', dni_cuit: '' },
        erroresCliente: {},
        temporizadorClientes: null,
        mensaje: '',

        buscarClientes() {
            clearTimeout(this.temporizadorClientes);

            if (this.clienteBusqueda.trim().length < 2) {
                this.clientes = [];

                return;
            }

            this.temporizadorClientes = setTimeout(async () => {
                try {
                    const respuesta = await fetch(`${this.urls.clientes}?${new URLSearchParams({ q: this.clienteBusqueda.trim() })}`, {
                        headers: this.cabeceras(),
                    });
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
            this.mensaje = 'Sin cliente asignado.';
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

        cabeceras(json = false) {
            const cabeceras = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };

            if (json) {
                cabeceras['Content-Type'] = 'application/json';
            }

            return cabeceras;
        },
    };
}
