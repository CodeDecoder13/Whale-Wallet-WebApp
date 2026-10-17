/** Shrinks an uploaded image to at most `max` px on its longest side and returns it as a PNG data URI. */
export function toDataUrl(file: File, max: number, type = 'image/png', quality = 0.9, square = false): Promise<string> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            // square: centre-crop to the shorter side first
            const side = square ? Math.min(img.width, img.height) : 0;
            const sx = square ? (img.width - side) / 2 : 0;
            const sy = square ? (img.height - side) / 2 : 0;
            const [sw, sh] = square ? [side, side] : [img.width, img.height];
            const scale = Math.min(1, max / Math.max(sw, sh));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(sw * scale));
            canvas.height = Math.max(1, Math.round(sh * scale));
            canvas
                .getContext('2d')
                ?.drawImage(img, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(img.src);
            resolve(canvas.toDataURL(type, quality));
        };
        img.onerror = () => reject(new Error('That file could not be read as an image.'));
        img.src = URL.createObjectURL(file);
    });
}
