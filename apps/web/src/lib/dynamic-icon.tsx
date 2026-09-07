import * as LucideIcons from 'lucide-react';

/**
 * Resolves a Lucide icon component by its export name (e.g. "FileText"),
 * as stored in `RequestType.icon`. Shared by the request-type form's live
 * preview and every place a request type's icon is rendered in a list.
 * Returns null for an unknown/empty name so callers can fall back to a
 * plain colored dot.
 */
export type DynamicIconComponent = React.ComponentType<{ className?: string; style?: React.CSSProperties }>;

export function resolveLucideIcon(name: string | null | undefined): DynamicIconComponent | null {
  if (!name) return null;
  const icons = LucideIcons as unknown as Record<string, DynamicIconComponent>;
  const Icon = icons[name];
  return typeof Icon === 'function' || (typeof Icon === 'object' && Icon !== null) ? Icon : null;
}
