import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';

import { Card } from '../../components/Card';
import { api, extractApiMessage } from '../../lib/api';
import { colors, radius, spacing, typography } from '../../lib/theme';

interface TaskItem {
  id: number;
  title: string;
  description?: string | null;
  status?: { name?: string; color?: string } | null;
  priority?: { name?: string; color?: string } | null;
  due_date?: string | null;
}

interface Paginated<T> {
  data: T[];
}

/**
 * "My tasks" tab — pulls from the same `/api/me/tasks` endpoint the web
 * app uses. Laravel returns a Resource collection wrapped in `data:[]`, so
 * we tolerate both shapes.
 */
export default function TasksScreen() {
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['me', 'tasks'],
    queryFn: async (): Promise<TaskItem[]> => {
      const res = await api.get<Paginated<TaskItem> | TaskItem[]>('/me/tasks');
      return Array.isArray(res.data) ? res.data : res.data.data;
    },
  });

  if (isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (isError) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorTitle}>تعذّر تحميل المهام</Text>
        <Text style={styles.errorBody}>{extractApiMessage(error)}</Text>
      </View>
    );
  }

  return (
    <FlatList
      data={data ?? []}
      keyExtractor={(t) => String(t.id)}
      contentContainerStyle={styles.list}
      ItemSeparatorComponent={() => <View style={{ height: spacing.md }} />}
      ListEmptyComponent={<Empty />}
      refreshControl={
        <RefreshControl
          refreshing={isRefetching}
          onRefresh={refetch}
          tintColor={colors.primary}
        />
      }
      renderItem={({ item }) => <TaskRow task={item} />}
    />
  );
}

function TaskRow({ task }: { task: TaskItem }) {
  return (
    <Card>
      <View style={styles.rowHeader}>
        <Text style={styles.title} numberOfLines={2}>
          {task.title}
        </Text>
        {task.status?.name ? (
          <View
            style={[
              styles.pill,
              { backgroundColor: (task.status.color ?? colors.primary) + '22' },
            ]}
          >
            <Text style={[styles.pillText, { color: task.status.color ?? colors.primary }]}>
              {task.status.name}
            </Text>
          </View>
        ) : null}
      </View>

      {task.description ? (
        <Text style={styles.desc} numberOfLines={2}>
          {task.description}
        </Text>
      ) : null}

      <View style={styles.meta}>
        {task.priority?.name ? (
          <Text style={[styles.metaLabel, { color: task.priority.color ?? colors.textMuted }]}>
            الأولوية: {task.priority.name}
          </Text>
        ) : null}
        {task.due_date ? (
          <Text style={styles.metaLabel}>الاستحقاق: {task.due_date}</Text>
        ) : null}
      </View>
    </Card>
  );
}

function Empty() {
  return (
    <View style={styles.center}>
      <Text style={styles.emptyTitle}>لا توجد مهام حالياً</Text>
      <Text style={styles.emptyBody}>
        عند إسناد مهمة إليك ستظهر هنا مباشرة.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  list: { padding: spacing.lg, flexGrow: 1 },
  center: {
    flex: 1,
    padding: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
  },
  errorTitle: { ...typography.h3, color: colors.danger },
  errorBody: { ...typography.small, color: colors.textMuted, textAlign: 'center' },
  emptyTitle: { ...typography.h3, color: colors.text },
  emptyBody: { ...typography.small, color: colors.textMuted, textAlign: 'center' },
  rowHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  title: { ...typography.bodyStrong, color: colors.text, flexShrink: 1 },
  pill: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radius.pill,
  },
  pillText: { ...typography.caption },
  desc: { ...typography.small, color: colors.textMuted, marginTop: spacing.sm },
  meta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
    marginTop: spacing.md,
  },
  metaLabel: { ...typography.caption, color: colors.textMuted },
});
