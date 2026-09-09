/**
 * Task-detail modal.
 *
 * GET  /tasks/{id}                → title, description, status, priority,
 *                                    due_date, progress_percent, assignee,
 *                                    comments[]
 * POST /tasks/{id}/comments       → { body }
 *
 * Presented as a modal (see app/_layout.tsx). Comments post from the input
 * at the bottom of the screen; on success the detail query is invalidated
 * so the new comment appears without a manual refetch.
 */

import { useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { Button } from '../../components/Button';
import { Card } from '../../components/Card';
import { api, extractApiMessage } from '../../lib/api';
import { colors, radius, spacing, typography } from '../../lib/theme';

interface Author {
  id?: number;
  /** Backend `TaskResource` returns `full_name` for creator/assignee. */
  full_name?: string | null;
  /** Comment author on some endpoints ships a plain `name`. */
  name?: string | null;
}

interface Comment {
  id: number;
  body?: string | null;
  created_at?: string | null;
  author?: Author | null;
  user?: Author | null;
}

interface TaskDetail {
  id: number;
  title: string;
  description?: string | null;
  status?: { name?: string; color?: string } | null;
  priority?: { name?: string; color?: string } | null;
  due_date?: string | null;
  progress_percent?: number | null;
  assignee?: Author | null;
  comments?: Comment[];
}

const detailKey = (id: string) => ['tasks', id] as const;

export default function TaskDetailScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { id } = useLocalSearchParams<{ id: string }>();
  const taskId = String(id ?? '');

  const [draft, setDraft] = useState('');
  const [postError, setPostError] = useState<string | null>(null);

  const detail = useQuery({
    queryKey: detailKey(taskId),
    enabled: taskId.length > 0,
    queryFn: async (): Promise<TaskDetail> => {
      const res = await api.get<{ data: TaskDetail } | TaskDetail>(
        `/tasks/${taskId}`,
      );
      return 'data' in res.data ? (res.data as { data: TaskDetail }).data : (res.data as TaskDetail);
    },
  });

  const addComment = useMutation({
    mutationFn: (body: string) =>
      api.post(`/tasks/${taskId}/comments`, { body }),
    onSuccess: () => {
      setDraft('');
      setPostError(null);
      void queryClient.invalidateQueries({ queryKey: detailKey(taskId) });
    },
    onError: (err) => setPostError(extractApiMessage(err, 'تعذّر إرسال التعليق')),
  });

  function onSend() {
    // Guard rapid double-taps at the source — the button's disabled
    // state doesn't always re-render between two quick presses, and
    // firing twice would post the comment twice.
    if (addComment.isPending) return;
    const body = draft.trim();
    if (!body) return;
    addComment.mutate(body);
  }

  const task = detail.data;
  const comments = task?.comments ?? [];

  return (
    <KeyboardAvoidingView
      style={styles.wrap}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.header}>
        <Text style={styles.headerTitle} numberOfLines={1}>
          تفاصيل المهمة
        </Text>
        <Pressable
          onPress={() => router.back()}
          style={styles.closeBtn}
          accessibilityLabel="إغلاق"
        >
          <Text style={styles.closeText}>×</Text>
        </Pressable>
      </View>

      {detail.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : detail.isError || !task ? (
        <View style={styles.center}>
          <Text style={styles.errorTitle}>تعذّر تحميل المهمة</Text>
          <Text style={styles.errorBody}>{extractApiMessage(detail.error)}</Text>
        </View>
      ) : (
        <FlatList
          data={comments}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={styles.list}
          keyboardShouldPersistTaps="handled"
          ListHeaderComponent={<TaskHeader task={task} />}
          ListEmptyComponent={
            <Text style={styles.emptyComments}>لا توجد تعليقات بعد.</Text>
          }
          ItemSeparatorComponent={() => <View style={{ height: spacing.sm }} />}
          renderItem={({ item }) => <CommentRow comment={item} />}
        />
      )}

      <View style={styles.composer}>
        {postError ? <Text style={styles.errorBody}>{postError}</Text> : null}
        <View style={styles.composerRow}>
          <TextInput
            value={draft}
            onChangeText={setDraft}
            placeholder="اكتب تعليقاً…"
            placeholderTextColor={colors.textSubtle}
            multiline
            style={styles.composerInput}
          />
          <Button
            label="إرسال"
            size="md"
            loading={addComment.isPending}
            disabled={draft.trim().length === 0}
            onPress={onSend}
          />
        </View>
      </View>
    </KeyboardAvoidingView>
  );
}

function TaskHeader({ task }: { task: TaskDetail }) {
  const progress = clampPercent(task.progress_percent);
  return (
    <View style={{ gap: spacing.md, marginBottom: spacing.lg }}>
      <Text style={styles.title}>{task.title}</Text>

      <View style={styles.metaRow}>
        {task.status?.name ? (
          <Pill color={task.status.color ?? colors.primary} label={task.status.name} />
        ) : null}
        {task.priority?.name ? (
          <Pill
            color={task.priority.color ?? colors.warning}
            label={`الأولوية: ${task.priority.name}`}
          />
        ) : null}
        {task.due_date ? (
          <Text style={styles.metaText}>الاستحقاق: {task.due_date}</Text>
        ) : null}
      </View>

      {task.description ? (
        <Card>
          <Text style={styles.description}>{task.description}</Text>
        </Card>
      ) : null}

      <View style={styles.progressWrap}>
        <View style={styles.progressLabelRow}>
          <Text style={styles.progressLabel}>التقدّم</Text>
          <Text style={styles.progressValue}>{progress}%</Text>
        </View>
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${progress}%` }]} />
        </View>
      </View>

      {task.assignee?.full_name ? (
        <Text style={styles.metaText}>المسند إليه: {task.assignee.full_name}</Text>
      ) : null}

      <Text style={styles.commentsTitle}>التعليقات</Text>
    </View>
  );
}

function CommentRow({ comment }: { comment: Comment }) {
  const author = comment.author ?? comment.user;
  return (
    <View style={styles.comment}>
      <View style={styles.commentHead}>
        <Text style={styles.commentAuthor}>{author?.name ?? 'مستخدم'}</Text>
        {comment.created_at ? (
          <Text style={styles.commentTime}>{comment.created_at}</Text>
        ) : null}
      </View>
      {comment.body ? <Text style={styles.commentBody}>{comment.body}</Text> : null}
    </View>
  );
}

function Pill({ color, label }: { color: string; label: string }) {
  return (
    <View style={[styles.pill, { backgroundColor: color + '22' }]}>
      <Text style={[styles.pillText, { color }]}>{label}</Text>
    </View>
  );
}

function clampPercent(value: number | null | undefined): number {
  const num = typeof value === 'number' && Number.isFinite(value) ? value : 0;
  return Math.max(0, Math.min(100, Math.round(num)));
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.xl,
    paddingBottom: spacing.md,
    gap: spacing.md,
  },
  headerTitle: { ...typography.h3, color: colors.text, flexShrink: 1 },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  closeText: { color: colors.text, fontSize: 24, lineHeight: 26 },

  center: {
    flex: 1,
    padding: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
  },
  errorTitle: { ...typography.h3, color: colors.danger },
  errorBody: { ...typography.small, color: colors.textMuted, textAlign: 'center' },

  list: { padding: spacing.lg, paddingBottom: spacing.xl },

  title: { ...typography.h2, color: colors.text },
  metaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: spacing.sm,
  },
  metaText: { ...typography.small, color: colors.textMuted },
  description: { ...typography.body, color: colors.text },

  pill: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radius.pill,
  },
  pillText: { ...typography.caption, fontWeight: '600' },

  progressWrap: { gap: spacing.xs },
  progressLabelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  progressLabel: { ...typography.small, color: colors.textMuted },
  progressValue: { ...typography.small, color: colors.text, fontWeight: '700' },
  progressTrack: {
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.surfaceMuted,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: colors.primary,
    borderRadius: 4,
  },

  commentsTitle: { ...typography.h3, color: colors.text, marginTop: spacing.md },
  emptyComments: {
    ...typography.small,
    color: colors.textMuted,
    textAlign: 'center',
    paddingVertical: spacing.lg,
  },
  comment: {
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
    gap: spacing.xs,
  },
  commentHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: spacing.sm,
  },
  commentAuthor: { ...typography.bodyStrong, color: colors.text },
  commentTime: { ...typography.caption, color: colors.textSubtle },
  commentBody: { ...typography.small, color: colors.text },

  composer: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
    backgroundColor: colors.surface,
    padding: spacing.md,
    gap: spacing.sm,
  },
  composerRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: spacing.sm,
  },
  composerInput: {
    flex: 1,
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.surfaceMuted,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    minHeight: 44,
    maxHeight: 120,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
});
