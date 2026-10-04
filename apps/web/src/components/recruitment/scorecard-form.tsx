'use client';

import * as React from 'react';
import { Star } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ScreeningSchema, ScreeningSchemaField } from '@/lib/api/types';

type Props = {
  schema: ScreeningSchema;
  value: Record<string, string | number>;
  onChange: (next: Record<string, string | number>) => void;
  disabled?: boolean;
};

/**
 * Dynamic scorecard renderer — mirrors the three input types the
 * backend's CandidateScreeningService validates against (`rating_1_5`,
 * `number`, `select`). `text` is rendered as a textarea for context but
 * doesn't contribute to the overall score. The backend recomputes the
 * score and pass flag, so this component never does arithmetic itself.
 */
export function ScorecardForm({ schema, value, onChange, disabled }: Props) {
  const setField = (key: string, next: string | number) => {
    onChange({ ...value, [key]: next });
  };

  return (
    <div className="space-y-4">
      {schema.fields.map((field) => (
        <FieldRow key={field.key} field={field}>
          {field.type === 'rating_1_5' && (
            <StarRating
              value={typeof value[field.key] === 'number' ? (value[field.key] as number) : null}
              onChange={(v) => setField(field.key, v)}
              disabled={disabled}
            />
          )}
          {field.type === 'number' && (
            <Input
              type="number"
              dir="ltr"
              value={value[field.key] ?? ''}
              onChange={(e) => setField(field.key, e.target.value ? Number(e.target.value) : '')}
              disabled={disabled}
            />
          )}
          {field.type === 'select' && (
            <Select
              value={value[field.key] ? String(value[field.key]) : undefined}
              onValueChange={(v) => setField(field.key, v)}
              disabled={disabled}
            >
              <SelectTrigger>
                <SelectValue placeholder="اختر" />
              </SelectTrigger>
              <SelectContent>
                {(field.options ?? []).map((option) => (
                  <SelectItem key={option} value={option}>
                    {option}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {field.type === 'text' && (
            <Textarea
              value={value[field.key] ? String(value[field.key]) : ''}
              onChange={(e) => setField(field.key, e.target.value)}
              rows={2}
              disabled={disabled}
            />
          )}
        </FieldRow>
      ))}
    </div>
  );
}

function FieldRow({ field, children }: { field: ScreeningSchemaField; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label className="flex items-baseline justify-between text-xs text-ink-2">
        <span>{field.label}</span>
        {typeof field.weight === 'number' && field.weight > 0 && (
          <span className="num text-[11px] text-muted" dir="ltr">
            وزن: {field.weight}
          </span>
        )}
      </Label>
      {children}
      {field.description && <p className="text-[11px] text-muted">{field.description}</p>}
    </div>
  );
}

function StarRating({
  value,
  onChange,
  disabled,
}: {
  value: number | null;
  onChange: (next: number) => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex items-center gap-1">
      {[1, 2, 3, 4, 5].map((star) => {
        const active = value !== null && star <= value;
        return (
          <button
            key={star}
            type="button"
            disabled={disabled}
            onClick={() => onChange(star)}
            className={cn(
              'rounded p-1 transition-colors',
              disabled && 'cursor-not-allowed opacity-60',
              !disabled && 'hover:bg-surface-2',
            )}
            aria-label={`${star} / 5`}
          >
            <Star
              className={cn('h-5 w-5', active ? 'fill-amber-500 text-amber-500' : 'text-muted')}
            />
          </button>
        );
      })}
      {value !== null && (
        <span className="num ms-2 text-xs text-muted" dir="ltr">
          {value} / 5
        </span>
      )}
    </div>
  );
}
