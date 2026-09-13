/**
 * Hands a Blob fetched through the authenticated API client to the browser
 * as a download. A plain link to the API would skip the session cookie +
 * XSRF header pair the client attaches.
 */
export function saveBlobAsFile(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
