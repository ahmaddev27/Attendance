'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { CornerDownLeft, Pencil, Send, Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
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
import { MentionInput, mentionToken, MENTION_REGEX, type MentionedEmployee } from '@/components/tasks/mention-input';
import { taskCommentsApi } from '@/lib/api/endpoints/task-comments';
import { formatRelativeTime } from '@/lib/task-format';
import type { TaskComment } from '@/lib/api/types';
import { cn } from '@/lib/utils';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

/** Renders a comment body as plain text with `@[Name]` tokens highlighted. */
function renderCommentBody(body: string): React.ReactNode[] {
  const parts: React.ReactNode[] = [];
  const regex = new RegExp(MENTION_REGEX.source, 'g');
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = regex.exec(body))) {
    if (match.index > lastIndex) parts.push(body.slice(lastIndex, match.index));
    parts.push(
      <span key={`${match.index}-${match[1]}`} className="font-medium text-brand-ink">
        @{match[1]}
      </span>
    );
    lastIndex = match.index + match[0].length;
  }
  if (lastIndex < body.length) parts.push(body.slice(lastIndex));
  return parts;
}

/** Derives the final mention id list: only tokens still present in the submitted body count. */
function resolveActiveMentions(body: string, mentions: MentionedEmployee[]): number[] {
  return mentions.filter((m) => body.includes(mentionToken(m.full_name))).map((m) => m.id);
}

function initials(name: string) {
  return name.trim().charAt(0).toUpperCase() || '؟';
}

function CommentAvatar({ user }: { user: TaskComment['user'] }) {
  if (user.avatar_url) {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={user.avatar_url} alt={user.name} className="h-8 w-8 shrink-0 rounded-full object-cover" />;
  }
  return (
    <div className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-semibold text-brand-ink">
      {initials(user.name)}
    </div>
  );
}

type CommentRowProps = {
  comment: TaskComment;
  isReply: boolean;
  onReply: (comment: TaskComment) => void;
  onEdit: (comment: TaskComment) => void;
  onDelete: (comment: TaskComment) => void;
  editing: boolean;
  editValue: string;
  onEditValueChange: (value: string) => void;
  onEditSave: () => void;
  onEditCancel: () => void;
  isSaving: boolean;
};

function CommentRow({
  comment,
  isReply,
  onReply,
  onEdit,
  onDelete,
  editing,
  editValue,
  onEditValueChange,
  onEditSave,
  onEditCancel,
  isSaving,
}: CommentRowProps) {
  return (
    <div className={cn('flex gap-3', isReply && 'ms-11')}>
      <CommentAvatar user={comment.user} />
      <div className="min-w-0 flex-1 space-y-1.5">
        <div className="flex flex-wrap items-baseline gap-2">
          <p className="text-sm font-semibold text-ink">{comment.user.name}</p>
          <p className="text-xs text-muted">{formatRelativeTime(comment.created_at)}</p>
          {comment.edited_at && <p className="text-xs text-muted">(تم التعديل)</p>}
        </div>

        {editing ? (
          <div className="space-y-2">
            <Textarea
              value={editValue}
              onChange={(e) => onEditValueChange(e.target.value)}
              rows={2}
              autoFocus
              className="text-sm"
            />
            <div className="flex items-center gap-2">
              <Button type="button" size="sm" disabled={isSaving} onClick={onEditSave} className="bg-brand text-white hover:bg-brand-hover">
                {isSaving && <Spinner className="text-white" />}
                حفظ
              </Button>
              <Button type="button" size="sm" variant="outline" onClick={onEditCancel} disabled={isSaving}>
                إلغاء
              </Button>
            </div>
          </div>
        ) : (
          <p className="whitespace-pre-wrap break-words text-sm text-ink">{renderCommentBody(comment.body)}</p>
        )}

        {!editing && (
          <div className="flex items-center gap-3 text-xs text-muted">
            {!isReply && (
              <button type="button" onClick={() => onReply(comment)} className="flex items-center gap-1 hover:text-ink-2">
                <CornerDownLeft className="h-3.5 w-3.5" />
                رد
              </button>
            )}
            {comment.can_edit && (
              <button type="button" onClick={() => onEdit(comment)} className="flex items-center gap-1 hover:text-ink-2">
                <Pencil className="h-3.5 w-3.5" />
                تعديل
              </button>
            )}
            {comment.can_delete && (
              <button type="button" onClick={() => onDelete(comment)} className="flex items-center gap-1 text-danger hover:text-danger/80">
                <Trash2 className="h-3.5 w-3.5" />
                حذف
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

type TaskCommentsProps = {
  taskId: number;
  initialComments?: TaskComment[];
};

/**
 * Comment thread for a task: reverse-chronological top-level comments, each
 * with its replies (one level, oldest first) indented beneath it, and a
 * mention-aware editor at the bottom.
 */
export function TaskComments({ taskId, initialComments }: TaskCommentsProps) {
  const queryClient = useQueryClient();

  const { data: comments, isLoading } = useQuery({
    queryKey: ['task-comments', taskId],
    queryFn: async () => (await taskCommentsApi.list(taskId)).data.data,
    initialData: initialComments,
  });

  const [body, setBody] = React.useState('');
  const [mentions, setMentions] = React.useState<MentionedEmployee[]>([]);
  const [replyTarget, setReplyTarget] = React.useState<TaskComment | null>(null);
  const [editingId, setEditingId] = React.useState<number | null>(null);
  const [editValue, setEditValue] = React.useState('');
  const [deleteTarget, setDeleteTarget] = React.useState<TaskComment | null>(null);

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['task-comments', taskId] });
    queryClient.invalidateQueries({ queryKey: ['tasks', 'detail', taskId] });
  };

  const createMutation = useMutation({
    mutationFn: () =>
      taskCommentsApi.create(taskId, {
        body,
        mentions: resolveActiveMentions(body, mentions),
        parent_id: replyTarget?.id ?? null,
      }),
    onSuccess: () => {
      setBody('');
      setMentions([]);
      setReplyTarget(null);
      invalidate();
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر إضافة التعليق')),
  });

  const updateMutation = useMutation({
    // Inline edits use a plain Textarea (no mention picker), so the comment's
    // own existing mention ids are carried through unchanged rather than
    // being recomputed from the new-comment editor's `mentions` draft state.
    mutationFn: ({ id, value, mentions: existingMentions }: { id: number; value: string; mentions: number[] }) =>
      taskCommentsApi.update(id, { body: value, mentions: existingMentions }),
    onSuccess: () => {
      setEditingId(null);
      setEditValue('');
      invalidate();
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر تعديل التعليق')),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => taskCommentsApi.delete(id),
    onSuccess: () => {
      setDeleteTarget(null);
      invalidate();
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر حذف التعليق')),
  });

  const allComments = comments ?? [];
  const rootComments = [...allComments]
    .filter((c) => !c.parent_id)
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
  const repliesOf = (id: number) =>
    allComments
      .filter((c) => c.parent_id === id)
      .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());

  const startEdit = (comment: TaskComment) => {
    setEditingId(comment.id);
    setEditValue(comment.body);
  };

  const submitDisabled = body.trim().length === 0 || createMutation.isPending;

  return (
    <div className="space-y-5">
      {isLoading && <p className="text-sm text-muted">جارٍ تحميل التعليقات...</p>}

      {!isLoading && rootComments.length === 0 && (
        <p className="py-4 text-center text-sm text-muted">لا توجد تعليقات بعد. كن أول من يعلق.</p>
      )}

      <div className="space-y-4">
        {rootComments.map((comment) => (
          <div key={comment.id} className="space-y-3">
            <CommentRow
              comment={comment}
              isReply={false}
              onReply={setReplyTarget}
              onEdit={startEdit}
              onDelete={setDeleteTarget}
              editing={editingId === comment.id}
              editValue={editValue}
              onEditValueChange={setEditValue}
              onEditSave={() => updateMutation.mutate({ id: comment.id, value: editValue, mentions: comment.mentions })}
              onEditCancel={() => setEditingId(null)}
              isSaving={updateMutation.isPending && editingId === comment.id}
            />
            {repliesOf(comment.id).map((reply) => (
              <CommentRow
                key={reply.id}
                comment={reply}
                isReply
                onReply={setReplyTarget}
                onEdit={startEdit}
                onDelete={setDeleteTarget}
                editing={editingId === reply.id}
                editValue={editValue}
                onEditValueChange={setEditValue}
                onEditSave={() => updateMutation.mutate({ id: reply.id, value: editValue, mentions: reply.mentions })}
                onEditCancel={() => setEditingId(null)}
                isSaving={updateMutation.isPending && editingId === reply.id}
              />
            ))}
          </div>
        ))}
      </div>

      <div className="space-y-2 border-t border-hairline pt-4">
        {replyTarget && (
          <div className="flex items-center justify-between rounded-md bg-surface-2 px-3 py-1.5 text-xs text-ink-2">
            <span>الرد على {replyTarget.user.name}</span>
            <button type="button" onClick={() => setReplyTarget(null)} aria-label="إلغاء الرد">
              <X className="h-3.5 w-3.5" />
            </button>
          </div>
        )}
        <MentionInput
          value={body}
          onChange={setBody}
          mentions={mentions}
          onMentionsChange={setMentions}
          onSubmit={() => !submitDisabled && createMutation.mutate()}
          placeholder="اكتب تعليقاً... استخدم @ للإشارة إلى موظف"
          rows={3}
        />
        <div className="flex items-center justify-between">
          <p className="text-xs text-muted">Ctrl+Enter للإرسال</p>
          <Button
            type="button"
            size="sm"
            disabled={submitDisabled}
            onClick={() => createMutation.mutate()}
            className="gap-1.5 bg-brand text-white hover:bg-brand-hover"
          >
            {createMutation.isPending ? <Spinner className="text-white" /> : <Send className="h-3.5 w-3.5" />}
            إرسال
          </Button>
        </div>
      </div>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف التعليق</AlertDialogTitle>
            <AlertDialogDescription>هل أنت متأكد من حذف هذا التعليق؟ لا يمكن التراجع عن هذا الإجراء.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              disabled={deleteMutation.isPending}
              className="bg-danger text-white hover:bg-danger/90"
              onClick={(e) => {
                e.preventDefault();
                if (deleteTarget) deleteMutation.mutate(deleteTarget.id);
              }}
            >
              {deleteMutation.isPending && <Spinner className="text-white" />}
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
