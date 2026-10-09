/**
 * Achica las fotos en el navegador antes de subirlas (el servidor no tiene GD):
 * imagen de hasta 1600 px y miniatura de 400 px, en WebP o, si no hay soporte, JPEG.
 * Si algo falla, se suben los originales y el servidor valida igual.
 */
const LADO_IMAGEN = 1600;
const LADO_MINIATURA = 400;
const CALIDAD = 0.82;

async function exportar(bitmap, ladoMaximo, nombreBase) {
    const escala = Math.min(1, ladoMaximo / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * escala);
    canvas.height = Math.round(bitmap.height * escala);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    let blob = await new Promise((resolver) => canvas.toBlob(resolver, 'image/webp', CALIDAD));

    // Some browsers return PNG when they cannot encode WebP.
    if (!blob || blob.type !== 'image/webp') {
        blob = await new Promise((resolver) => canvas.toBlob(resolver, 'image/jpeg', CALIDAD));
    }

    if (!blob) {
        throw new Error('No se pudo generar la imagen.');
    }

    const extension = blob.type === 'image/webp' ? 'webp' : 'jpg';

    return new File([blob], `${nombreBase}.${extension}`, { type: blob.type });
}

export async function achicar(archivo) {
    // Respects the EXIF orientation of phone photos.
    const bitmap = await createImageBitmap(archivo, { imageOrientation: 'from-image' });
    const nombreBase = archivo.name.replace(/\.[^.]+$/, '') || 'foto';

    try {
        return {
            imagen: await exportar(bitmap, LADO_IMAGEN, nombreBase),
            miniatura: await exportar(bitmap, LADO_MINIATURA, `${nombreBase}-min`),
        };
    } finally {
        bitmap.close();
    }
}

export default function subirFotos() {
    return {
        procesando: false,
        previas: [],

        async elegir(evento) {
            const input = evento.target;
            const archivos = [...input.files];

            this.previas.forEach((previa) => URL.revokeObjectURL(previa.url));
            this.previas = [];

            if (archivos.length === 0 || typeof DataTransfer === 'undefined' || typeof createImageBitmap === 'undefined') {
                return;
            }

            this.procesando = true;

            const fotos = new DataTransfer();
            const miniaturas = new DataTransfer();
            let conMiniaturas = true;

            for (const archivo of archivos) {
                try {
                    const { imagen, miniatura } = await achicar(archivo);
                    fotos.items.add(imagen);
                    miniaturas.items.add(miniatura);
                } catch {
                    fotos.items.add(archivo);
                    conMiniaturas = false;
                }
            }

            input.files = fotos.files;

            // Thumbnails are matched by index, so they are sent only if every photo has one.
            if (conMiniaturas) {
                this.$refs.miniaturas.files = miniaturas.files;
            } else {
                this.$refs.miniaturas.value = '';
            }

            this.previas = [...fotos.files].map((archivo) => ({
                nombre: archivo.name,
                url: URL.createObjectURL(archivo),
                kb: Math.round(archivo.size / 1024),
            }));
            this.procesando = false;
        },
    };
}
