import { useMemo } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';

import { Button } from '../../components/Button';
import { Card } from '../../components/Card';
import { useAuth } from '../../hooks/useAuth';
import { colors, radius, spacing, typography } from '../../lib/theme';

/**
 * Home tab: greeting + today's attendance summary + quick actions.
 *
 * The "today status" card is deliberately mocked — the API exposes
 * `/api/attendance` and `/api/me/tasks`, but this scaffold leaves the wire
 * up to the concrete widgets that come next. Swap the `todayStatus` const
 * for a `useQuery(['attendance', 'today'], …)` call when ready.
 */
export default function HomeScreen() {
  const router = useRouter();
  const { user } = useAuth();

  const greeting = useMemo(() => greetingForHour(new Date().getHours()), []);

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      <View style={styles.header}>
        <Text style={styles.hello}>{greeting}</Text>
        <Text style={styles.name}>{user?.name ?? 'موظف طاقات'}</Text>
        {user?.employee_number ? (
          <Text style={styles.employeeNumber}>
            الرقم الوظيفي: {user.employee_number}
          </Text>
        ) : null}
      </View>

      <Card tone="primary" style={styles.statusCard}>
        <Text style={styles.statusLabel}>حالة اليوم</Text>
        <Text style={styles.statusValue}>لم يتم تسجيل الحضور بعد</Text>
        <Text style={styles.statusSub}>
          امسح رمز QR للجهاز عند الوصول لتسجيل حضورك.
        </Text>
        <Button
          label="مسح رمز QR"
          variant="secondary"
          size="md"
          fullWidth
          onPress={() => router.push('/scan')}
          style={{ marginTop: spacing.lg }}
        />
      </Card>

      <Text style={styles.sectionTitle}>إجراءات سريعة</Text>
      <View style={styles.actionsGrid}>
        <QuickAction
          title="مهامي"
          hint="اطّلع على المهام المسندة إليك"
          onPress={() => router.push('/tasks')}
        />
        <QuickAction
          title="إجازاتي"
          hint="تقديم طلب إجازة جديدة"
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

function greetingForHour(hour: number): string {
  if (hour < 12) return 'صباح الخير';
  if (hour < 17) return 'مساء الخير';
  return 'مساء النور';
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
