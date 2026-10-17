/** Shrinks an uploaded image to at most `max` px on its longest side and returns it as a PNG data URI. */
export function toDataUrl(file: File, max: number): Promise<string> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, max / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(img.width * scale));
            canvas.height = Math.max(1, Math.round(img.height * scale));
            canvas
                .getContext('2d')
                ?.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(img.src);
            resolve(canvas.toDataURL('image/png'));
        };
        img.onerror = () => reject(new Error('That file could not be read as an image.'));
        img.src = URL.createObjectURL(file);
    });
}
