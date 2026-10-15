import { formatFileSize } from "./format";

/**
 * A photo the courier picked, prepared for upload.
 *
 * Phone cameras hand over files well past the API's 5 MB limit, so the image is
 * redrawn to a sane edge length before it goes anywhere near the multipart body.
 */

export interface PickedPhoto {
    file: File;
    previewUrl: string;
    name: string;
    sizeLabel: string;
}

const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.82;

async function downscale(file: File): Promise<File> {
    if (!file.type.startsWith("image/")) {
        return file;
    }

    const bitmap = await createImageBitmap(file).catch(() => null);

    if (bitmap === null) {
        return file;
    }

    const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));

    if (scale === 1 && file.size <= 2 * 1024 * 1024) {
        bitmap.close();
        return file;
    }

    const canvas = document.createElement("canvas");
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));

    const context = canvas.getContext("2d");

    if (context === null) {
        bitmap.close();
        return file;
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, "image/jpeg", JPEG_QUALITY));

    if (blob === null) {
        return file;
    }

    const name = `${file.name.replace(/\.[^.]+$/, "") || "foto"}.jpg`;

    return new File([blob], name, { type: "image/jpeg" });
}

export async function preparePhoto(file: File): Promise<PickedPhoto> {
    const prepared = await downscale(file);

    return {
        file: prepared,
        previewUrl: URL.createObjectURL(prepared),
        name: prepared.name,
        sizeLabel: formatFileSize(prepared.size)
    };
}

export function releasePhoto(photo: PickedPhoto | null): void {
    if (photo !== null) {
        URL.revokeObjectURL(photo.previewUrl);
    }
}
