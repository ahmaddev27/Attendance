import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Button } from '../../components/Button';
import { useAuth } from '../../hooks/useAuth';
import { colors, radius, spacing, typography } from '../../lib/theme';

/**
 * Login screen. Uses local component state (not TanStack) because it's a
 * one-shot mutation whose only side-effect is populating the auth store.
 */
export default function LoginScreen() {
  const { login } = useAuth();

  const [employeeNumber, setEmployeeNumber] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const canSubmit = employeeNumber.trim().length > 0 && password.length > 0;

  async function onSubmit() {
    if (!canSubmit || submitting) return;
    setError(null);
    setSubmitting(true);
    try {
      await login({
        employee_number: Number(employeeNumber.trim()),
        password,
      });
      // Root layout's AuthGate handles the redirect.
    } catch (e) {
      setError(e instanceof Error ? e.message : 'فشل تسجيل الدخول');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'bottom']}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.flex}
      >
        <ScrollView
          contentContainerStyle={styles.scroll}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.brand}>
            <View style={styles.logoBadge}>
              <Text style={styles.logoText}>TAQAT</Text>
            </View>
            <Text style={styles.title}>مرحباً بك</Text>
            <Text style={styles.subtitle}>
              سجّل الدخول للوصول إلى حسابك في منصة طاقات.
            </Text>
          </View>

          <View style={styles.form}>
            <Field
              label="الرقم الوظيفي"
              value={employeeNumber}
              onChangeText={setEmployeeNumber}
              keyboardType="number-pad"
              placeholder="مثال: 1024"
              autoComplete="username"
              textContentType="username"
              editable={!submitting}
            />

            <Field
              label="كلمة المرور"
              value={password}
              onChangeText={setPassword}
              placeholder="••••••••"
              secureTextEntry
              autoComplete="password"
              textContentType="password"
              editable={!submitting}
            />

            {error ? <Text style={styles.error}>{error}</Text> : null}

            <Button
              label="تسجيل الدخول"
              size="lg"
              fullWidth
              loading={submitting}
              disabled={!canSubmit}
              onPress={onSubmit}
              style={{ marginTop: spacing.md }}
            />
          </View>

          <Text style={styles.footer}>© TAQAT — منصة إدارة الموظفين</Text>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

interface FieldProps extends React.ComponentProps<typeof TextInput> {
  label: string;
}

function Field({ label, style, ...rest }: FieldProps) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        {...rest}
        style={[styles.input, style]}
        placeholderTextColor={colors.textSubtle}
        // Latin digits render LTR even inside RTL layouts; keep the input
        // itself right-aligned so Arabic labels align with the caret.
        textAlign="right"
      />
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.bg },
  flex: { flex: 1 },
  scroll: {
    flexGrow: 1,
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing['2xl'],
    justifyContent: 'space-between',
  },
  brand: {
    alignItems: 'center',
    marginTop: spacing['2xl'],
    gap: spacing.md,
  },
  logoBadge: {
    width: 96,
    height: 96,
    borderRadius: radius.xl,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.sm,
  },
  logoText: {
    color: colors.textOnPrimary,
    fontSize: 22,
    fontWeight: '800',
    letterSpacing: 2,
  },
  title: { ...typography.h1, color: colors.text, textAlign: 'center' },
  subtitle: {
    ...typography.body,
    color: colors.textMuted,
    textAlign: 'center',
    maxWidth: 320,
  },
  form: {
    gap: spacing.lg,
    marginTop: spacing['2xl'],
  },
  field: {
    gap: spacing.xs,
  },
  fieldLabel: {
    ...typography.caption,
    color: colors.textMuted,
  },
  input: {
    ...typography.body,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    color: colors.text,
  },
  error: {
    ...typography.small,
    color: colors.danger,
    textAlign: 'right',
  },
  footer: {
    ...typography.caption,
    color: colors.textSubtle,
    textAlign: 'center',
    marginTop: spacing['2xl'],
  },
});
