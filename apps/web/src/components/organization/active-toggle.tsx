'use client';

import { Switch } from '@/components/ui/switch';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type ActiveToggleProps = {
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  disabled?: boolean;
  pending?: boolean;
};

/** Shared active/inactive switch used in the departments/teams/positions tables. */
export function ActiveToggle({ checked, onCheckedChange, disabled, pending }: ActiveToggleProps) {
  return (
    <div className="flex items-center justify-center gap-2">
      <Switch
        checked={checked}
        onCheckedChange={onCheckedChange}
        disabled={disabled || pending}
        aria-label={checked ? 'نشط' : 'غير نشط'}
      />
      {pending ? (
        <Spinner className="h-3.5 w-3.5 text-muted" />
      ) : (
        <span className={cn('text-xs', checked ? 'text-success' : 'text-muted')}>
          {checked ? 'نشط' : 'غير نشط'}
        </span>
      )}
    </div>
  );
}
