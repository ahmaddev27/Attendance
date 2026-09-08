'use client';

import * as React from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Download, FileText, Trash2, Upload } from 'lucide-react';

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
import { Spinner } from '@/components/ui/spinner';
import { apiClient } from '@/lib/api/client';
import { taskAttachmentsApi } from '@/lib/api/endpoints/task-attachments';
import { useAuthStore } from '@/lib/stores/auth-store';
import { formatDate } from '@/lib/attendance-format';
import { formatFileSize } from '@/lib/task-format';
import type { TaskAttachment } from '@/lib/api/types';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

/** Triggers a browser download for an authenticated file — a plain `<a href>` can't carry the Bearer token the API requires. */
async function downloadAttachment(attachment: TaskAttachment) {
  try {
    const response = await apiClient.get<Blob>(attachment.download_url, { responseType: 'blob' });
    const blobUrl = window.URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = attachment.file_name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(blobUrl);
  } catch {
    toast.error('تعذر تنزيل الملف');
  }
}

type PendingUpload = {
  key: string;
  fileName: string;
  progress: number;
  error?: string;
};

type TaskAttachmentsProps = {
  taskId: number;
  initialAttachments?: TaskAttachment[];
};

export function TaskAttachments({ taskId, initialAttachments }: TaskAttachmentsProps) {
  const queryClient = useQueryClient();
  const currentUserId = useAuthStore((s) => s.user?.id);
  const fileInputRef = React.useRef<HTMLInputElement>(null);

  const [isDragging, setIsDragging] = React.useState(false);
  const [pendingUploads, setPendingUploads] = React.useState<PendingUpload[]>([]);
  const [deleteTarget, setDeleteTarget] = React.useState<TaskAttachment | null>(null);
  const [deletingId, setDeletingId] = React.useState<number | null>(null);

  const { data: attachments, isLoading } = useQuery({
    queryKey: ['task-attachments', taskId],
    queryFn: async () => (await taskAttachmentsApi.list(taskId)).data.data,
    initialData: initialAttachments,
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['task-attachments', taskId] });
    queryClient.invalidateQueries({ queryKey: ['tasks', 'detail', taskId] });
  };

  const uploadFiles = async (files: File[]) => {
    for (const file of files) {
      const key = `${file.name}-${file.size}-${Date.now()}`;
      setPendingUploads((prev) => [...prev, { key, fileName: file.name, progress: 0 }]);

      try {
        await taskAttachmentsApi.upload(taskId, file, (event) => {
          const total = event.total ?? file.size;
          const progress = total ? Math.round((event.loaded / total) * 100) : 0;
          setPendingUploads((prev) => prev.map((u) => (u.key === key ? { ...u, progress } : u)));
        });
        setPendingUploads((prev) => prev.filter((u) => u.key !== key));
        toast.success(`تم رفع ${file.name}`);
        invalidate();
      } catch (err) {
        const message = extractErrorMessage(err, `تعذر رفع ${file.name}`);
        setPendingUploads((prev) => prev.map((u) => (u.key === key ? { ...u, error: message } : u)));
        toast.error(message);
      }
    }
  };

  const handleFileList = (fileList: FileList | null) => {
    if (!fileList || fileList.length === 0) return;
    void uploadFiles(Array.from(fileList));
  };

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragging(false);
    handleFileList(e.dataTransfer.files);
  };

  const handleDelete = async (attachment: TaskAttachment) => {
    setDeletingId(attachment.id);
    try {
      await taskAttachmentsApi.delete(attachment.id);
      toast.success('تم حذف المرفق');
      invalidate();
    } catch (err) {
      toast.error(extractErrorMessage(err, 'تعذر حذف المرفق'));
    } finally {
      setDeletingId(null);
      setDeleteTarget(null);
    }
  };

  return (
    <div className="space-y-3">
      <div
        onDragOver={(e) => {
          e.preventDefault();
          setIsDragging(true);
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter') fileInputRef.current?.click();
        }}
        className={cnDropzone(isDragging)}
      >
        <Upload className="h-5 w-5 text-muted" />
        <p className="text-sm text-ink-2">
          اسحب الملفات هنا أو <span className="font-medium text-brand-ink">اختر ملفات</span>
        </p>
        <input
          ref={fileInputRef}
          type="file"
          multiple
          className="hidden"
          onChange={(e) => {
            handleFileList(e.target.files);
            e.target.value = '';
          }}
        />
      </div>

      {pendingUploads.length > 0 && (
        <div className="space-y-2">
          {pendingUploads.map((upload) => (
            <div key={upload.key} className="rounded-md border border-hairline p-2.5">
              <div className="flex items-center justify-between gap-2 text-xs">
                <span className="truncate text-ink-2">{upload.fileName}</span>
                <span className="num text-muted">{upload.progress}%</span>
              </div>
              <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                <div
                  className={upload.error ? 'h-full bg-danger' : 'h-full bg-brand transition-all'}
                  style={{ width: `${upload.error ? 100 : upload.progress}%` }}
                />
              </div>
              {upload.error && <p className="mt-1 text-xs text-danger">{upload.error}</p>}
            </div>
          ))}
        </div>
      )}

      {isLoading && <p className="text-sm text-muted">جارٍ تحميل المرفقات...</p>}

      {!isLoading && (attachments?.length ?? 0) === 0 && pendingUploads.length === 0 && (
        <p className="py-2 text-center text-sm text-muted">لا توجد مرفقات بعد</p>
      )}

      {!isLoading && (attachments?.length ?? 0) > 0 && (
        <ul className="space-y-2">
          {attachments!.map((attachment) => (
            <li key={attachment.id} className="flex items-center gap-3 rounded-md border border-hairline p-2.5">
              <FileText className="h-5 w-5 shrink-0 text-muted" />
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-ink">{attachment.file_name}</p>
                <p className="text-xs text-muted">
                  {formatFileSize(attachment.size)} · {attachment.uploaded_by?.full_name ?? '—'} ·{' '}
                  <span className="num" dir="ltr">
                    {formatDate(attachment.created_at.slice(0, 10))}
                  </span>
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                title="تنزيل"
                aria-label="تنزيل"
                onClick={() => downloadAttachment(attachment)}
              >
                <Download className="h-4 w-4" />
              </Button>
              {attachment.uploaded_by?.id === currentUserId && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  title="حذف"
                  aria-label="حذف"
                  className="text-danger hover:bg-danger-soft hover:text-danger"
                  onClick={() => setDeleteTarget(attachment)}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المرفق</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من حذف الملف &quot;{deleteTarget?.file_name}&quot;؟ لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deletingId !== null}>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              disabled={deletingId !== null}
              className="bg-danger text-white hover:bg-danger/90"
              onClick={(e) => {
                e.preventDefault();
                if (deleteTarget) void handleDelete(deleteTarget);
              }}
            >
              {deletingId !== null && <Spinner className="text-white" />}
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function cnDropzone(isDragging: boolean) {
  return [
    'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed p-5 text-center transition-colors',
    isDragging ? 'border-brand bg-brand-soft' : 'border-hairline hover:border-hairline-strong',
  ].join(' ');
}
