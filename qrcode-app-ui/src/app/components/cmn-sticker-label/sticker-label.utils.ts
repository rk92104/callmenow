/** Matches server `FormatStickerDisplayCode` / physical sticker (CMN + id). */
export function formatStickerDisplayCode(publicId: string): string {
  const u = publicId.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  return u.startsWith('CMN') ? u : `CMN${u}`;
}
