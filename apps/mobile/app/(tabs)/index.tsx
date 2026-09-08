import { useMemo } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';

import { Button } from '../../components/Button';
import { Card } from '../../components/Card';
import { useAuth } from '../../hooks/useAuth';
import { fetchMyDashboard, type MyDashboardKpis } from '../../lib/endpoints/dashboard';
import { colors, radius, spacing, typography } from '../../lib/theme';

/**
 * Home tab — greeting + real today-status card + KPI grid + quick actions.
 * Everything below the greeting is fed by /me/dashboard/kpis (the same
 * endpoint the web /home page uses), pull-to-refresh re-runs it, and a
 * "checked in at HH:MM" line replaces the placeholder scaffold copy the
 * moment the user has an attendance row for the day.
 */
export default function HomeScreen() {
  const router = useRouter();
  const { user } = useAuth();

  const greeting = useMemo(() => greetingForHour(new Date().getHours()), []);

  const { data, isLoading, isRefetching, refetch } = useQuery({
    queryKey: ['me-dashboard'],
    queryFn: fetchMyDashboard,
    staleTime: 60_000,
  });

  return (
    <ScrollView
      contentContainerStyle={styles.scroll}
      refreshControl={
        <RefreshControl
          refreshing={isRefetching}
          onRefresh={refetch}
          tintColor={colors.primary}
        />
      }
    >
      <View style={styles.header}>
        <Text style={styles.hello}>{greeting}</Text>
        <Text style={styles.name}>{user?.name ?? 'موظف طاقات'}</Text>
        {user?.employee_number ? (
          <Text style={styles.employeeNumber}>
            الرقم الوظيفي: {user.employee_number}
          </Text>
        ) : null}
      </View>

      <TodayCard data={data} isLoading={isLoading} onScan={() => router.push('/scan')} />

      {data && <MonthGrid month={data.month} />}
      {data && <TasksRow tasks={data.tasks} onOpen={() => router.push('/tasks')} />}

      <Text style={styles.sectionTitle}>إجراءات سريعة</Text>
      <View style={styles.actionsGrid}>
        <QuickAction
          title="مهامي"
          hint={data ? `${data.tasks.open} مهمة مفتوحة` : 'اطّلع على المهام المسندة إليك'}
          onPress={() => router.push('/tasks')}
        />
        <QuickAction
          title="إجازاتي"
          hint={
            data ? `${data.leaves.pending} طلب قيد المعالجة` : 'تقديم طلب إجازة جديدة'
          }
          onPress={() => router.push('/leaves')}
        />
        <QuickAction
          title="الحضور"
          hint="سجّل حضورك أو انصرافك"
          onPress={() => router.push('/scan')}
        />
        <QuickAction
          title="ملفي"
          hint="بياناتك الوظيفية والإعدادات"
          onPress={() => router.push('/profile')}
        />
      </View>
    </ScrollView>
  );
}

function TodayCard({
  data,
  isLoading,
  onScan,
}: {
  data: MyDashboardKpis | undefined;
  isLoading: boolean;
  onScan: () => void;
}) {
  if (isLoading && !data) {
    return (
      <Card tone="primary" style={styles.statusCard}>
        <ActivityIndicator color={colors.textOnPrimary} />
      </Card>
    );
  }

  const today = data?.today;
  const status = today?.status;
  const checkedInAt = today?.checked_in_at ? formatClock(today.checked_in_at) : null;
  const checkedOutAt = today?.checked_out_at ? formatClock(today.checked_out_at) : null;

  const label = statusLabel(status);
  const showScanButton = !checkedInAt || !checkedOutAt;

  return (
    <Card tone="primary" style={styles.statusCard}>
      <Text style={styles.statusLabel}>حالة اليوم</Text>
      <Text style={styles.statusValue}>{label}</Text>
      {checkedInAt && (
        <Text style={styles.statusSub}>
          الحضور: {checkedInAt}
          {checkedOutAt ? `  ·  الانصراف: ${checkedOutAt}` : ''}
        </Text>
      )}
      {!checkedInAt && (
        <Text style={styles.statusSub}>
          امسح رمز QR للجهاز عند الوصول لتسجيل حضورك.
        </Text>
      )}
      {showScanButton && (
        <Button
          label={checkedInAt ? 'تسجيل الانصراف' : 'مسح رمز QR'}
          variant="secondary"
          size="md"
          fullWidth
          onPress={onScan}
          style={{ marginTop: spacing.lg }}
        />
      )}
    </Card>
  );
}

function MonthGrid({ month }: { month: MyDashboardKpis['month'] }) {
  const cells: Array<{ label: string; value: number; tone: 'default' | 'success' | 'warn' | 'danger' }> = [
    { label: 'حضور', value: month.present, tone: 'success' },
    { label: 'تأخير', value: month.late, tone: 'warn' },
    { label: 'غياب', value: month.absent, tone: 'danger' },
    { label: 'إجازة', value: month.leave, tone: 'default' },
  ];
  return (
    <View style={styles.gridSection}>
      <Text style={styles.sectionTitle}>هذا الشهر</Text>
      <View style={styles.grid}>
        {cells.map((c) => (
          <View key={c.label} style={styles.gridCell}>
            <Text style={[styles.gridNumber, toneColor(c.tone)]}>{c.value}</Text>
            <Text style={styles.gridLabel}>{c.label}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

function TasksRow({
  tasks,
  onOpen,
}: {
  tasks: MyDashboardKpis['tasks'];
  onOpen: () => void;
}) {
  return (
    <Card style={styles.tasksCard}>
      <View style={styles.tasksHead}>
        <Text style={styles.tasksTitle}>المهام</Text>
        <Button label="الكل" variant="ghost" size="sm" onPress={onOpen} />
      </View>
      <View style={styles.tasksRow}>
        <TaskStat label="مفتوحة" value={tasks.open} tone="default" />
        <TaskStat label="قيد التنفيذ" value={tasks.in_progress} tone="warn" />
        <TaskStat label="متأخرة" value={tasks.overdue} tone="danger" />
        <TaskStat label="أُنجزت هذا الأسبوع" value={tasks.completed_this_week} tone="success" />
      </View>
    </Card>
  );
}

function TaskStat({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone: 'default' | 'success' | 'warn' | 'danger';
}) {
  return (
    <View style={styles.taskStat}>
      <Text style={[styles.taskNumber, toneColor(tone)]}>{value}</Text>
      <Text style={styles.taskLabel}>{label}</Text>
    </View>
  );
}

function QuickAction({
  title,
  hint,
  onPress,
}: {
  title: string;
  hint: string;
  onPress: () => void;
}) {
  return (
    <Card style={styles.action}>
      <Text style={styles.actionTitle}>{title}</Text>
      <Text style={styles.actionHint}>{hint}</Text>
      <Button label="فتح" variant="ghost" size="sm" onPress={onPress} />
    </Card>
  );
}

function statusLabel(status: string | null | undefined): string {
  switch (status) {
    case 'present':
    case 'remote':
    case 'business_mission':
      return 'حاضر';
    case 'late':
      return 'حاضر (متأخر)';
    case 'early_leave':
      return 'انصراف مبكر';
    case 'absent':
      return 'لم يتم تسجيل الحضور بعد';
    case 'on_leave':
      return 'في إجازة';
    case 'holiday':
      return 'عطلة رسمية';
    case 'weekend':
      return 'عطلة أسبوعية';
    default:
      return 'لم يتم تسجيل الحضور بعد';
  }
}

function formatClock(iso: string): string {
  const d = new Date(iso);
  const h = d.getHours().toString().padStart(2, '0');
  const m = d.getMinutes().toString().padStart(2, '0');
  return `${h}:${m}`;
}

function greetingForHour(hour: number): string {
  if (hour < 12) return 'صباح الخير';
  if (hour < 17) return 'مساء الخير';
  return 'مساء النور';
}

function toneColor(tone: 'default' | 'success' | 'warn' | 'danger') {
  switch (tone) {
    case 'success':
      return { color: colors.success ?? '#1e9e7f' };
    case 'warn':
      return { color: colors.warning ?? '#f5a623' };
    case 'danger':
      return { color: colors.danger ?? '#c74f35' };
    default:
      return { color: colors.text };
  }
}

const styles = StyleSheet.create({
  scroll: {
    padding: spacing.lg,
    gap: spacing.lg,
  },
  header: {
    marginTop: spacing.sm,
    gap: spacing.xs,
  },
  hello: { ...typography.body, color: colors.textMuted },
  name: { ...typography.h1, color: colors.text },
  employeeNumber: { ...typography.small, color: colors.textSubtle },
  statusCard: {
    gap: spacing.xs,
  },
  statusLabel: {
    ...typography.caption,
    color: colors.primaryLight,
    letterSpacing: 1,
  },
  statusValue: {
    ...typography.h2,
    color: colors.textOnPrimary,
  },
  statusSub: {
    ...typography.small,
    color: colors.primaryLight,
    marginTop: spacing.xs,
  },
  gridSection: {
    gap: spacing.sm,
  },
  grid: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  gridCell: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
    paddingVertical: spacing.md,
    alignItems: 'center',
    gap: spacing.xs / 2,
  },
  gridNumber: { ...typography.h2, fontWeight: '800' },
  gridLabel: { ...typography.caption, color: colors.textMuted },
  tasksCard: { gap: spacing.md },
  tasksHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  tasksTitle: { ...typography.h3, color: colors.text },
  tasksRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
  },
  taskStat: { flexGrow: 1, flexBasis: '40%', gap: spacing.xs / 2 },
  taskNumber: { ...typography.h2, fontWeight: '800' },
  taskLabel: { ...typography.small, color: colors.textMuted },
  sectionTitle: {
    ...typography.h3,
    color: colors.text,
    marginTop: spacing.sm,
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
  },
  action: {
    flexBasis: '48%',
    flexGrow: 1,
    gap: spacing.xs,
    borderRadius: radius.lg,
  },
  actionTitle: { ...typography.bodyStrong, color: colors.text },
  actionHint: { ...typography.small, color: colors.textMuted },
});
