'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Mail, Pencil, Phone, Plus, Star, StarOff, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { clientContactsApi } from '@/lib/api/endpoints/recruitment';
import { cn } from '@/lib/utils';
import type { ClientContact, ClientContactPayload } from '@/lib/api/types';

type Props = {
  clientId: number;
  contacts: ClientContact[];
  canManage: boolean;
};

/**
 * CRUD panel for a Client's contacts. Primary flag is exclusive — the
 * backend enforces that at most one contact per client is primary and
 * flips any current primary when this one is toggled.
 */
export function ContactsPanel({ clientId, contacts, canManage }: Props) {
  const [dialogOpen, setDialogOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<ClientContact | null>(null);
  const [deleting, setDeleting] = React.useState<ClientContact | null>(null);

  const openCreate = () => {
    setEditing(null);
    setDialogOpen(true);
  };

  const openEdit = (c: ClientContact) => {
    setEditing(c);
    setDialogOpen(true);
  };

  return (
    <div className="rounded-xl border border-hairline bg-surface p-5">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-ink">جهات الاتصال</h2>
        {canManage && (
          <Button type="button" size="sm" variant="outline" className="gap-1.5" onClick={openCreate}>
            <Plus className="h-3.5 w-3.5" />
            إضافة
          </Button>
        )}
      </div>
      {contacts.length === 0 ? (
        <p className="py-6 text-center text-sm text-muted">لا جهات اتصال مسجلة.</p>
      ) : (
        <ul className="divide-y divide-hairline">
          {contacts.map((c) => (
            <li key={c.id} className="flex items-start gap-3 py-3">
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate text-sm font-semibold text-ink" title={c.full_name}>{c.full_name}</p>
                  {c.is_primary && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-[10px] font-semibold text-warn-ink">
                      <Star className="h-3 w-3 fill-warn-ink" /> أساسي
                    </span>
                  )}
                </div>
                {c.position && <p className="text-xs text-ink-2">{c.position}</p>}
                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-2">
                  {c.email && (
                    <a href={`mailto:${c.email}`} className="flex items-center gap-1 hover:text-brand-ink" dir="ltr">
                      <Mail className="h-3 w-3" /> {c.email}
                    </a>
                  )}
                  {c.phone && (
                    <a href={`tel:${c.phone}`} className="flex items-center gap-1 hover:text-brand-ink" dir="ltr">
                      <Phone className="h-3 w-3" /> {c.phone}
                    </a>
                  )}
                </div>
              </div>
              {canManage && (
                <div className="flex shrink-0 items-center gap-1">
                  <TogglePrimaryButton clientId={clientId} contact={c} />
                  <Button type="button" variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(c)} aria-label="تعديل">
                    <Pencil className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-danger hover:bg-danger-soft hover:text-danger"
                    onClick={() => setDeleting(c)}
                    aria-label="حذف"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      <ContactFormDialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        clientId={clientId}
        contact={editing}
      />

      <AlertDialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف جهة الاتصال</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف {deleting?.full_name}. الإجراء نهائي.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <DeleteAction clientId={clientId} contact={deleting} onDone={() => setDeleting(null)} />
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function TogglePrimaryButton({ clientId, contact }: { clientId: number; contact: ClientContact }) {
  const qc = useQueryClient();
  const mutation = useMutation({
    mutationFn: () => clientContactsApi.update(clientId, contact.id, { is_primary: !contact.is_primary }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['clients', clientId] });
      toast.success(contact.is_primary ? 'أُلغي وسم الأساسي' : 'تم تعيين كجهة اتصال أساسية');
    },
    onError: () => toast.error('تعذر تحديث الحالة'),
  });
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      className={cn('h-8 w-8', contact.is_primary ? 'text-warn-ink' : 'text-ink-2')}
      title={contact.is_primary ? 'إلغاء الأساسي' : 'تعيين كأساسي'}
      onClick={() => mutation.mutate()}
      disabled={mutation.isPending}
    >
      {contact.is_primary ? <StarOff className="h-3.5 w-3.5" /> : <Star className="h-3.5 w-3.5" />}
    </Button>
  );
}

function DeleteAction({
  clientId,
  contact,
  onDone,
}: {
  clientId: number;
  contact: ClientContact | null;
  onDone: () => void;
}) {
  const qc = useQueryClient();
  const mutation = useMutation({
    mutationFn: (id: number) => clientContactsApi.delete(clientId, id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['clients', clientId] });
      toast.success('تم الحذف');
      onDone();
    },
    onError: () => toast.error('تعذر الحذف'),
  });
  return (
    <AlertDialogAction
      className="bg-danger text-white hover:bg-danger/90"
      onClick={(e) => {
        e.preventDefault();
        if (contact) mutation.mutate(contact.id);
      }}
    >
      حذف
    </AlertDialogAction>
  );
}

function ContactFormDialog({
  open,
  onOpenChange,
  clientId,
  contact,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  clientId: number;
  contact: ClientContact | null;
}) {
  const qc = useQueryClient();
  const [values, setValues] = React.useState<ClientContactPayload>({
    full_name: '',
    position: '',
    email: '',
    phone: '',
    linkedin_url: '',
    is_primary: false,
    notes: '',
  });

  React.useEffect(() => {
    if (open) {
      setValues({
        full_name: contact?.full_name ?? '',
        position: contact?.position ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        linkedin_url: contact?.linkedin_url ?? '',
        is_primary: contact?.is_primary ?? false,
        notes: contact?.notes ?? '',
      });
    }
  }, [open, contact]);

  const mutation = useMutation({
    mutationFn: () => {
      const payload: ClientContactPayload = {
        full_name: values.full_name.trim(),
        position: values.position?.trim() || null,
        email: values.email?.trim() || null,
        phone: values.phone?.trim() || null,
        linkedin_url: values.linkedin_url?.trim() || null,
        is_primary: !!values.is_primary,
        notes: values.notes?.trim() || null,
      };
      if (contact) return clientContactsApi.update(clientId, contact.id, payload);
      return clientContactsApi.create(clientId, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['clients', clientId] });
      toast.success(contact ? 'تم تحديث جهة الاتصال' : 'تمت الإضافة');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  const canSubmit = values.full_name.trim().length > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{contact ? 'تعديل جهة اتصال' : 'جهة اتصال جديدة'}</DialogTitle>
        </DialogHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (canSubmit) mutation.mutate();
          }}
          className="space-y-3"
        >
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Label className="text-xs font-semibold text-ink-2">الاسم</Label>
              <Input className="mt-1.5" value={values.full_name} onChange={(e) => setValues({ ...values, full_name: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">المسمى</Label>
              <Input className="mt-1.5" value={values.position ?? ''} onChange={(e) => setValues({ ...values, position: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">البريد</Label>
              <Input type="email" className="mt-1.5" value={values.email ?? ''} onChange={(e) => setValues({ ...values, email: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الهاتف</Label>
              <Input className="mt-1.5" value={values.phone ?? ''} onChange={(e) => setValues({ ...values, phone: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">لينكدإن</Label>
              <Input className="mt-1.5" value={values.linkedin_url ?? ''} onChange={(e) => setValues({ ...values, linkedin_url: e.target.value })} />
            </div>
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">ملاحظات</Label>
            <Textarea className="mt-1.5" rows={3} value={values.notes ?? ''} onChange={(e) => setValues({ ...values, notes: e.target.value })} />
          </div>
          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={!!values.is_primary}
              onChange={(e) => setValues({ ...values, is_primary: e.target.checked })}
              className="h-4 w-4 rounded border-hairline"
            />
            جهة اتصال أساسية
          </label>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>إلغاء</Button>
            <Button type="submit" disabled={!canSubmit || mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
              {mutation.isPending && <Spinner className="text-white" />}
              حفظ
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
