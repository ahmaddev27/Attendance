/**
 * Leave-request modal.
 *
 * Fields:
 *   - leave_type_id  (Select — fetched from /leave-types)
 *   - start_date     (YYYY-MM-DD)
 *   - end_date       (YYYY-MM-DD)
 *   - reason         (optional multi-line)
 *
 * Posts to /me/leaves (self-service submit — admin /leave-requests
 * requires employee_id and the manage-leaves permission) then pops the
 * modal and invalidates the leaves list so the caller sees the pending
 * row immediately.
 *
 * Date pickers are deferred — a plain TextInput with a YYYY-MM-DD hint keeps
 * the dep list clean for wave 3.
 */

import { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { Button } from '../components/Button';
import { api, extractApiMessage } from '../lib/api';
import { colors, radius, spacing, typography } from '../lib/theme';

interface LeaveType {
  id: number;
  name?: string;
  color?: string | null;
}

/** Shape returned by `GET /me/leaves/balances` — only the fields we use. */
interface LeaveBalanceRow {
  id: number;
  leave_type_id: number;
  leave_type?: LeaveType | null;
}

interface Paginated<T> {
  data: T[];
}

interface FormState {
  leave_type_id: number | null;
  start_date: string;
  end_date: string;
  reason: string;
}

const INITIAL: FormState = {
  leave_type_id: null,
  start_date: '',
  end_date: '',
  reason: '',
};

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export default function LeaveRequestScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [form, setForm] = useState<FormState>(INITIAL);
  const [error, setError] = useState<string | null>(null);

  // `/leave-types` requires the `approve-leaves` permission (admin-only),
  // so a regular employee 403's there. `/me/leaves/balances` is scoped to
  // the caller's own balances and embeds the leave type — we distill the
  // distinct types out of it for the picker.
  const types = useQuery({
    queryKey: ['me', 'leaves', 'balances', 'types'],
    queryFn: async (): Promise<LeaveType[]> => {
      const res = await api.get<Paginated<LeaveBalanceRow> | LeaveBalanceRow[]>(
        '/me/leaves/balances',
      );
      const rows = Array.isArray(res.data) ? res.data : res.data.data;

      // Dedupe by leave_type_id — an employee usually has one balance
      // per type per year, but guard against a duplicate slipping through
      // (e.g. multi-year history if the endpoint ever widens).
      const seen = new Map<number, LeaveType>();
      for (const row of rows) {
        const type = row.leave_type;
        if (!type) continue;
        if (!seen.has(type.id)) seen.set(type.id, type);
      }
      return Array.from(seen.values());
    },
    staleTime: 5 * 60_000,
  });

  const submit = useMutation({
    mutationFn: () =>
      api.post('/me/leaves', {
        leave_type_id: form.leave_type_id,
        start_date: form.start_date,
        end_date: form.end_date,
        reason: form.reason || null,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['me', 'leaves'] });
      router.back();
    },
    onError: (err) => setError(extractApiMessage(err, 'تعذّر إرسال الطلب')),
  });

  const canSubmit = useMemo(
    () =>
      form.leave_type_id != null &&
      DATE_RE.test(form.start_date) &&
      DATE_RE.test(form.end_date),
    [form],
  );

  function setField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }));
    if (error) setError(null);
  }

  function onSubmit() {
    // Guard rapid double-taps at the source — the button's disabled
    // state doesn't always re-render before the second press lands,
    // and firing the mutation twice would create two pending requests.
    if (submit.isPending) return;
    setError(null);
    if (!canSubmit) {
      setError('يرجى تعبئة الحقول المطلوبة بصيغة صحيحة (YYYY-MM-DD).');
      return;
    }
    submit.mutate();
  }

  return (
    <KeyboardAvoidingView
      style={styles.wrap}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.header}>
        <Text style={styles.title}>طلب إجازة جديدة</Text>
        <Pressable
          onPress={() => router.back()}
          style={styles.closeBtn}
          accessibilityLabel="إغلاق"
        >
          <Text style={styles.closeText}>×</Text>
        </Pressable>
      </View>

      <ScrollView
        contentContainerStyle={styles.form}
        keyboardShouldPersistTaps="handled"
      >
        <Field label="نوع الإجازة">
          {types.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : types.isError ? (
            <Text style={styles.errText}>تعذّر تحميل أنواع الإجازات</Text>
          ) : (
            <TypeSelect
              options={types.data ?? []}
              value={form.leave_type_id}
              onChange={(id) => setField('leave_type_id', id)}
            />
          )}
        </Field>

        <Field label="من تاريخ">
          <TextInput
            value={form.start_date}
            onChangeText={(v) => setField('start_date', v.trim())}
            placeholder="YYYY-MM-DD"
            placeholderTextColor={colors.textSubtle}
            autoCapitalize="none"
            style={styles.input}
          />
        </Field>

        <Field label="إلى تاريخ">
          <TextInput
            value={form.end_date}
            onChangeText={(v) => setField('end_date', v.trim())}
            placeholder="YYYY-MM-DD"
            placeholderTextColor={colors.textSubtle}
            autoCapitalize="none"
            style={styles.input}
          />
        </Field>

        <Field label="السبب (اختياري)">
          <TextInput
            value={form.reason}
            onChangeText={(v) => setField('reason', v)}
            placeholder="اكتب سبباً موجزاً…"
            placeholderTextColor={colors.textSubtle}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
            style={[styles.input, styles.inputMultiline]}
          />
        </Field>

        {error ? <Text style={styles.errText}>{error}</Text> : null}

        <Button
          label="إرسال الطلب"
          size="lg"
          fullWidth
          loading={submit.isPending}
          disabled={!canSubmit}
          onPress={onSubmit}
          style={{ marginTop: spacing.sm }}
        />
        <Button
          label="إلغاء"
          variant="ghost"
          size="md"
          fullWidth
          onPress={() => router.back()}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      {children}
    </View>
  );
}

function TypeSelect({
  options,
  value,
  onChange,
}: {
  options: LeaveType[];
  value: number | null;
  onChange: (id: number) => void;
}) {
  if (options.length === 0) {
    return <Text style={styles.errText}>لا توجد أنواع إجازات متاحة.</Text>;
  }
  return (
    <View style={styles.chipsWrap}>
      {options.map((opt) => {
        const active = value === opt.id;
        return (
          <Pressable
            key={opt.id}
            onPress={() => onChange(opt.id)}
            style={[styles.chip, active && styles.chipActive]}
          >
            <Text style={[styles.chipText, active && styles.chipTextActive]}>
              {opt.name ?? `#${opt.id}`}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
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
  title: { ...typography.h2, color: colors.text },
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

  form: {
    padding: spacing.lg,
    gap: spacing.lg,
    paddingBottom: spacing.xl * 2,
  },
  field: { gap: spacing.sm },
  fieldLabel: { ...typography.bodyStrong, color: colors.text },

  input: {
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  inputMultiline: {
    minHeight: 100,
  },

  chipsWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  chip: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radius.pill,
    backgroundColor: colors.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
  },
  chipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  chipText: { ...typography.caption, color: colors.text },
  chipTextActive: { color: colors.textOnPrimary, fontWeight: '700' },

  errText: { ...typography.small, color: colors.danger },
});
