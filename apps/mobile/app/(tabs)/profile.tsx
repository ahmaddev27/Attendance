import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Button } from '../../components/Button';
import { Card } from '../../components/Card';
import { useAuth } from '../../hooks/useAuth';
import { API_BASE_URL } from '../../lib/api';
import { colors, radius, spacing, typography } from '../../lib/theme';

export default function ProfileScreen() {
  const { user, logout } = useAuth();

  function onLogout() {
    Alert.alert('تسجيل الخروج', 'هل أنت متأكد من رغبتك في تسجيل الخروج؟', [
      { text: 'إلغاء', style: 'cancel' },
      {
        text: 'تسجيل الخروج',
        style: 'destructive',
        onPress: () => {
          void logout();
        },
      },
    ]);
  }

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      <View style={styles.avatarWrap}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>
            {(user?.name ?? '؟').trim().charAt(0)}
          </Text>
        </View>
        <Text style={styles.name}>{user?.name ?? '—'}</Text>
        {user?.employee_number ? (
          <Text style={styles.sub}>الرقم الوظيفي: {user.employee_number}</Text>
        ) : null}
      </View>

      <Card>
        <Row label="الاسم" value={user?.name ?? '—'} />
        <Divider />
        <Row
          label="الرقم الوظيفي"
          value={user?.employee_number ? String(user.employee_number) : '—'}
        />
        {user?.email ? (
          <>
            <Divider />
            <Row label="البريد الإلكتروني" value={user.email} />
          </>
        ) : null}
        {user?.role ? (
          <>
            <Divider />
            <Row label="الصلاحية" value={String(user.role)} />
          </>
        ) : null}
      </Card>

      <Card tone="muted">
        <Row label="خادم API" value={API_BASE_URL} />
      </Card>

      <Button
        label="تسجيل الخروج"
        variant="danger"
        size="lg"
        fullWidth
        onPress={onLogout}
      />
    </ScrollView>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue} numberOfLines={2}>
        {value}
      </Text>
    </View>
  );
}

function Divider() {
  return <View style={styles.divider} />;
}

const styles = StyleSheet.create({
  scroll: {
    padding: spacing.lg,
    gap: spacing.lg,
  },
  avatarWrap: {
    alignItems: 'center',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
  avatar: {
    width: 84,
    height: 84,
    borderRadius: radius.pill,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: colors.textOnPrimary,
    fontSize: 32,
    fontWeight: '700',
  },
  name: { ...typography.h2, color: colors.text },
  sub: { ...typography.small, color: colors.textMuted },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    gap: spacing.md,
  },
  rowLabel: { ...typography.small, color: colors.textMuted },
  rowValue: {
    ...typography.bodyStrong,
    color: colors.text,
    flexShrink: 1,
    textAlign: 'left',
  },
  divider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: colors.border,
  },
});
