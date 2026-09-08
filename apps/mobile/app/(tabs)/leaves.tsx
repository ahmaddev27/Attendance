import {
  ActivityIndicator,
  Alert,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useQuery } from '@tanstack/react-query';

import { Button } from '../../components/Button';
import { Card } from '../../components/Card';
import { api, extractApiMessage } from '../../lib/api';
import { colors, radius, spacing, typography } from '../../lib/theme';

interface LeaveRequest {
  id: number;
  leave_type?: { name?: string; color?: string } | null;
  from_date?: string;
  to_date?: string;
  days?: number;
  status?: string;
  reason?: string | null;
}

interface Paginated<T> {
  data: T[];
}

/**
 * "My leaves" tab. Requesting a new leave is deep enough to warrant its
 * own form screen — this scaffold surfaces a placeholder alert so the
 * navigation shape is correct, and leaves the form for a follow-up.
 */
export default function LeavesScreen() {
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['me', 'leaves'],
    queryFn: async (): Promise<LeaveRequest[]> => {
      const res = await api.get<Paginated<LeaveRequest> | LeaveRequest[]>('/me/leaves');
      return Array.isArray(res.data) ? res.data : res.data.data;
    },
  });

  function onRequest() {
    Alert.alert(
      'طلب إجازة جديدة',
      'شاشة إنشاء الطلب ستُضاف قريباً. سيتم فتحها من هنا.',
    );
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.headerBar}>
        <Text style={styles.headerTitle}>إجازاتي</Text>
        <Button label="طلب جديد" size="sm" onPress={onRequest} />
      </View>

      {isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : isError ? (
        <View style={styles.center}>
          <Text style={styles.errorTitle}>تعذّر تحميل الإجازات</Text>
          <Text style={styles.errorBody}>{extractApiMessage(error)}</Text>
        </View>
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={(l) => String(l.id)}
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
          renderItem={({ item }) => <LeaveRow leave={item} />}
        />
      )}
    </View>
  );
}

function LeaveRow({ leave }: { leave: LeaveRequest }) {
  const statusColor = STATUS_COLORS[leave.status ?? ''] ?? colors.textMuted;
  return (
    <Card>
      <View style={styles.rowHeader}>
        <Text style={styles.title} numberOfLines={2}>
          {leave.leave_type?.name ?? 'إجازة'}
        </Text>
        {leave.status ? (
          <View style={[styles.pill, { backgroundColor: statusColor + '22' }]}>
            <Text style={[styles.pillText, { color: statusColor }]}>
              {STATUS_LABELS[leave.status] ?? leave.status}
            </Text>
          </View>
        ) : null}
      </View>

      <Text style={styles.dates}>
        {leave.from_date ?? '—'} → {leave.to_date ?? '—'}
        {typeof leave.days === 'number' ? `  •  ${leave.days} يوم` : ''}
      </Text>

      {leave.reason ? (
        <Text style={styles.reason} numberOfLines={2}>
          {leave.reason}
        </Text>
      ) : null}
    </Card>
  );
}

function Empty() {
  return (
    <View style={styles.center}>
      <Text style={styles.emptyTitle}>لا توجد طلبات إجازة</Text>
      <Text style={styles.emptyBody}>اضغط على "طلب جديد" لإنشاء أول طلب.</Text>
    </View>
  );
}

const STATUS_COLORS: Record<string, string> = {
  pending: colors.warning,
  approved: colors.success,
  rejected: colors.danger,
  cancelled: colors.textSubtle,
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'قيد المراجعة',
  approved: 'موافق',
  rejected: 'مرفوض',
  cancelled: 'ملغى',
};

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  headerBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.sm,
  },
  headerTitle: { ...typography.h2, color: colors.text },
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
  dates: { ...typography.small, color: colors.textMuted, marginTop: spacing.sm },
  reason: { ...typography.small, color: colors.text, marginTop: spacing.sm },
});
