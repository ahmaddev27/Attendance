'use client';

import * as React from 'react';
import { Code2, GripVertical, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { SortableList } from '@/components/workflow/sortable-list';
import { FORM_FIELD_TYPE_OPTIONS } from '@/lib/constants/request-options';
import type { FormField, FormFieldType } from '@/lib/api/types';

/** Field types that get an inline "placeholder" input in the builder. */
const PLACEHOLDER_TYPES: FormFieldType[] = ['text', 'textarea', 'number', 'select'];

// A stable, purely-client-side id for drag-and-drop — kept separate from the
// user-editable `key` (which can be blank/duplicated mid-edit) so reordering
// never breaks while someone is still typing a field's key.
type BuilderField = FormField & { _uid: string };

function makeUid() {
  return typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `f_${Math.random().toString(36).slice(2)}`;
}

function toBuilderFields(fields: FormField[]): BuilderField[] {
  return fields.map((field) => ({ ...field, _uid: makeUid() }));
}

function stripUid(fields: BuilderField[]): FormField[] {
  return fields.map(({ _uid: _drop, ...field }) => field);
}

type FormSchemaBuilderProps = {
  value: FormField[];
  onChange: (fields: FormField[]) => void;
  /**
   * Changing this value re-syncs the builder's internal list from `value` —
   * the same "reset on identity change" pattern the rest of the app's forms
   * use (`form.reset()` keyed on the record id), needed here because a plain
   * `value` prop can't tell reorderable rows apart from one render to the next.
   */
  resetToken?: string | number;
};

/** Visual editor for a RequestType's `form_schema` — add/edit/reorder/remove fields, with a JSON preview. */
export function FormSchemaBuilder({ value, onChange, resetToken }: FormSchemaBuilderProps) {
  const [fields, setFields] = React.useState<BuilderField[]>(() => toBuilderFields(value));
  const [showJson, setShowJson] = React.useState(false);

  React.useEffect(() => {
    setFields(toBuilderFields(value));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resetToken]);

  const emit = (next: BuilderField[]) => {
    setFields(next);
    onChange(stripUid(next));
  };

  const addField = () => {
    emit([
      ...fields,
      { _uid: makeUid(), key: '', label: '', type: 'text', required: false },
    ]);
  };

  const updateField = (uid: string, patch: Partial<FormField>) => {
    emit(fields.map((field) => (field._uid === uid ? { ...field, ...patch } : field)));
  };

  const removeField = (uid: string) => {
    emit(fields.filter((field) => field._uid !== uid));
  };

  return (
    <div className="space-y-3 rounded-xl border border-hairline bg-surface p-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-ink">حقول النموذج</h3>
        <div className="flex items-center gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => setShowJson((v) => !v)} className="gap-1.5">
            <Code2 className="h-3.5 w-3.5" />
            {showJson ? 'إخفاء JSON' : 'معاينة JSON'}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={addField} className="gap-1.5">
            <Plus className="h-3.5 w-3.5" />
            إضافة حقل
          </Button>
        </div>
      </div>

      {fields.length === 0 && (
        <p className="rounded-lg border border-dashed border-hairline py-6 text-center text-sm text-muted">
          لا توجد حقول بعد — أضف أول حقل لنموذج هذا الطلب
        </p>
      )}

      {fields.length > 0 && (
        <SortableList
          items={fields}
          getId={(field) => field._uid}
          onReorder={emit}
          renderItem={(field, dragHandle) => (
            <div className="flex items-start gap-2 rounded-lg border border-hairline bg-ground p-3">
              <button
                type="button"
                className="mt-2 cursor-grab touch-none text-muted hover:text-ink-2 active:cursor-grabbing"
                aria-label="اسحب لإعادة الترتيب"
                {...dragHandle.attributes}
                {...dragHandle.listeners}
              >
                <GripVertical className="h-4 w-4" />
              </button>

              <div className="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-12">
                <div className="sm:col-span-3">
                  <Label className="text-xs text-muted">المفتاح (key)</Label>
                  <Input
                    dir="ltr"
                    className="mt-1 text-right"
                    value={field.key}
                    placeholder="field_key"
                    onChange={(e) => updateField(field._uid, { key: e.target.value })}
                  />
                </div>
                <div className="sm:col-span-3">
                  <Label className="text-xs text-muted">التسمية</Label>
                  <Input
                    className="mt-1"
                    value={field.label}
                    placeholder="تسمية الحقل"
                    onChange={(e) => updateField(field._uid, { label: e.target.value })}
                  />
                </div>
                <div className="sm:col-span-3">
                  <Label className="text-xs text-muted">النوع</Label>
                  <Select
                    value={field.type}
                    onValueChange={(next: FormFieldType) => updateField(field._uid, { type: next })}
                  >
                    <SelectTrigger className="mt-1">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {FORM_FIELD_TYPE_OPTIONS.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex items-end sm:col-span-2">
                  <label className="flex cursor-pointer items-center gap-2 text-sm text-ink">
                    <Checkbox
                      checked={field.required}
                      onCheckedChange={(checked) => updateField(field._uid, { required: checked === true })}
                    />
                    مطلوب
                  </label>
                </div>
                <div className="flex items-end justify-end sm:col-span-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                    title="حذف الحقل"
                    onClick={() => removeField(field._uid)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>

                {field.type === 'select' && (
                  <div className="sm:col-span-12">
                    <Label className="text-xs text-muted">الخيارات (مفصولة بفاصلة)</Label>
                    <Input
                      className="mt-1"
                      value={(field.options ?? []).join(', ')}
                      placeholder="خيار ١, خيار ٢, خيار ٣"
                      onChange={(e) =>
                        updateField(field._uid, {
                          options: e.target.value
                            .split(',')
                            .map((option) => option.trim())
                            .filter(Boolean),
                        })
                      }
                    />
                  </div>
                )}

                {field.type === 'number' && (
                  <>
                    <div className="sm:col-span-6">
                      <Label className="text-xs text-muted">الحد الأدنى</Label>
                      <Input
                        type="number"
                        dir="ltr"
                        className="num mt-1 text-right"
                        value={field.min ?? ''}
                        onChange={(e) =>
                          updateField(field._uid, { min: e.target.value === '' ? undefined : Number(e.target.value) })
                        }
                      />
                    </div>
                    <div className="sm:col-span-6">
                      <Label className="text-xs text-muted">الحد الأقصى</Label>
                      <Input
                        type="number"
                        dir="ltr"
                        className="num mt-1 text-right"
                        value={field.max ?? ''}
                        onChange={(e) =>
                          updateField(field._uid, { max: e.target.value === '' ? undefined : Number(e.target.value) })
                        }
                      />
                    </div>
                  </>
                )}

                {PLACEHOLDER_TYPES.includes(field.type) && (
                  <div className="sm:col-span-12">
                    <Label className="text-xs text-muted">نص توضيحي (اختياري)</Label>
                    <Input
                      className="mt-1"
                      value={field.placeholder ?? ''}
                      onChange={(e) => updateField(field._uid, { placeholder: e.target.value || undefined })}
                    />
                  </div>
                )}
              </div>
            </div>
          )}
        />
      )}

      {showJson && (
        <pre className="num overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs text-ink-2" dir="ltr">
          {JSON.stringify(stripUid(fields), null, 2)}
        </pre>
      )}
    </div>
  );
}
