import {
  PICKER_RULES,
  UPLOAD_RULES,
  type UploadKind,
  type UploadTarget,
} from "./attachment-config.ts"

export type FileValidationError = "unsupported" | "too_large"

/**
 * Jenis endpoint untuk berkas yang dipilih. `null` berarti pemilih "Foto &
 * Video" menerima berkas yang bukan gambar maupun video.
 */
export function resolveUploadKind(
  target: UploadTarget,
  file: File,
): UploadKind | null {
  if (target !== "media") return target

  if (file.type.startsWith("image/")) return "image"
  if (file.type.startsWith("video/")) return "video"

  return null
}

/** Validasi di klien sebelum unggah: server tetap memeriksa ulang. */
export function validateAttachmentFile(
  target: UploadTarget,
  file: File,
): FileValidationError | null {
  const kind = resolveUploadKind(target, file)

  if (kind === null) return "unsupported"
  if (!matchesAccept(file, PICKER_RULES[target].accept)) return "unsupported"
  if (file.size > UPLOAD_RULES[kind].maxBytes) return "too_large"

  return null
}

/** Batas ukuran yang berlaku untuk berkas ini, untuk pesan galat. */
export function attachmentSizeLimit(target: UploadTarget, file: File): number {
  const kind = resolveUploadKind(target, file)

  return kind === null ? PICKER_RULES[target].maxBytes : UPLOAD_RULES[kind].maxBytes
}

export function formatFileSize(bytes: number): string {
  if (bytes >= 1024 * 1024) {
    return `${Math.round(bytes / (1024 * 1024))} MB`
  }

  return `${Math.round(bytes / 1024)} KB`
}

function matchesAccept(file: File, accept: string): boolean {
  const mime = file.type.toLowerCase()
  const name = file.name.toLowerCase()

  return accept.split(",").some((rawToken) => {
    const token = rawToken.trim().toLowerCase()

    if (token === "") return false
    if (token.endsWith("/*")) return mime.startsWith(token.slice(0, -1))
    if (token.startsWith(".")) return name.endsWith(token)

    return mime === token
  })
}
