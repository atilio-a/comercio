/**
 * Contador de combinaciones de la grilla color × talle. Solo informa al usuario:
 * el servidor interpreta los talles y valida el máximo.
 */
export default function grillaVariantes(colores = [], talles = '', usaTalles = true) {
    return {
        colores,
        talles,
        usaTalles,

        get cantidadTalles() {
            const valores = new Set();

            for (const parte of this.talles.split(',').map((texto) => texto.trim()).filter(Boolean)) {
                const rango = parte.match(/^(\d+)\s*-\s*(\d+)$/);

                if (rango) {
                    for (let talle = Number(rango[1]); talle <= Number(rango[2]) && valores.size < 1000; talle++) {
                        valores.add(String(talle));
                    }
                } else {
                    valores.add(parte.toUpperCase());
                }
            }

            return valores.size;
        },

        get cantidad() {
            return this.colores.length * (this.usaTalles ? this.cantidadTalles : 1);
        },
    };
}
