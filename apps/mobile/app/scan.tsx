/**
 * QR scanner (modal). Scans the kiosk's rotating QR token and POSTs it to
 * `/api/scan/check-in` (or `/check-out`) along with the current user's
 * `employee_number`.
 *
 * The QR payload from the kiosk may be:
 *   - a bare 64-char token,
 *   - a URL like `https://attendees.taqatgaza.com/scan/<token>`,
 *   - or a URL with `?qrToken=<token>`.
 * `parseQrToken()` covers all three.
 *
 * Push notifications (expo-notifications) are wired at the app-shell
 * level. TODO for the user: run `npx expo install expo-notifications`
 * (already listed as a dep) and configure Firebase (google-services.json /
 * GoogleService-Info.plist) before publishing. See the README.
 */

import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { CameraView, useCameraPermissions } from 'expo-camera';
import * as Location from 'expo-location';

import { Button } from '../components/Button';
import { useAuth } from '../hooks/useAuth';
import { api, extractApiMessage } from '../lib/api';
import { colors, radius, spacing, typography } from '../lib/theme';

type Mode = 'check-in' | 'check-out';

/**
 * Best-effort geolocation for the scan payload. Mirrors the web kiosk's
 * behaviour: always resolves (never throws), returning `null` when the
 * user denies permission, the OS has no fix, or the request times out.
 *
 * The backend only enforces geo when the device's `enforce_geo=true` AND
 * the device has configured coordinates — so sending `null` from a
 * geo-off device still succeeds, while a geo-enforced device will 422
 * with a readable Arabic message.
 */
async function getCurrentPosition(): Promise<{
  latitude: number;
  longitude: number;
} | null> {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') return null;

    // 10s ceiling so a stuck GPS never blocks the scan flow. We race
    // the location request against a timer instead of relying on the
    // OS, whose per-provider timeouts are not consistent across
    // Android vendors.
    const position = await Promise.race<
      Location.LocationObject | null
    >([
      Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      }),
      new Promise<null>((resolve) => setTimeout(() => resolve(null), 10_000)),
    ]);

    if (!position) return null;
    return {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
    };
  } catch {
    return null;
  }
}

export default function ScanScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const params = useLocalSearchParams<{ mode?: string }>();

  const [permission, requestPermission] = useCameraPermissions();
  const [mode, setMode] = useState<Mode>(
    params.mode === 'check-out' ? 'check-out' : 'check-in',
  );
  const [submitting, setSubmitting] = useState(false);
  const lockRef = useRef(false); // Prevents double-fire while the camera keeps emitting scans.

  useEffect(() => {
    if (permission && !permission.granted && permission.canAskAgain) {
      void requestPermission();
    }
  }, [permission, requestPermission]);

  async function submit(qrToken: string) {
    if (!user?.employee_number) {
      Alert.alert('خطأ', 'لم يتم العثور على الرقم الوظيفي — يُرجى إعادة تسجيل الدخول.');
      return;
    }

    setSubmitting(true);
    try {
      // Attempt to attach a GPS fix unconditionally — cheap when
      // the device has no geofencing (backend ignores the coords),
      // and required when `enforce_geo=true` (backend would 422 on
      // missing coords otherwise). See getCurrentPosition() above
      // for the fail-open behaviour on denial/timeout.
      const position = await getCurrentPosition();
      await api.post(`/scan/${mode}`, {
        employee_number: user.employee_number,
        qr_token: qrToken,
        latitude: position?.latitude,
        longitude: position?.longitude,
      });
      Alert.alert(
        'تم بنجاح',
        mode === 'check-in' ? 'تم تسجيل حضورك.' : 'تم تسجيل انصرافك.',
        [{ text: 'حسناً', onPress: () => router.back() }],
      );
    } catch (err) {
      Alert.alert('فشل التسجيل', extractApiMessage(err));
      lockRef.current = false; // Allow retry after failure.
    } finally {
      setSubmitting(false);
    }
  }

  function onBarcodeScanned({ data }: { data: string }) {
    if (lockRef.current || submitting) return;
    const token = parseQrToken(data);
    if (!token) {
      // Silently ignore non-TAQAT codes — the user will try again.
      return;
    }
    lockRef.current = true;
    void submit(token);
  }

  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.title}>لا يمكن الوصول للكاميرا</Text>
        <Text style={styles.body}>
          لتسجيل الحضور، يحتاج التطبيق إذن استخدام الكاميرا لمسح رمز QR.
        </Text>
        <Button
          label="السماح بالكاميرا"
          onPress={() => void requestPermission()}
          size="lg"
        />
        <Button label="إلغاء" variant="ghost" onPress={() => router.back()} />
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={submitting ? undefined : onBarcodeScanned}
      />

      <View style={styles.overlay} pointerEvents="box-none">
        <View style={styles.topBar}>
          <View style={styles.modeSwitch}>
            <ModeButton
              label="حضور"
              active={mode === 'check-in'}
              onPress={() => setMode('check-in')}
            />
            <ModeButton
              label="انصراف"
              active={mode === 'check-out'}
              onPress={() => setMode('check-out')}
            />
          </View>
          <Pressable
            onPress={() => router.back()}
            style={styles.closeBtn}
            accessibilityLabel="إغلاق"
          >
            <Text style={styles.closeText}>×</Text>
          </Pressable>
        </View>

        <View style={styles.viewfinderWrap} pointerEvents="none">
          <View style={styles.viewfinder} />
        </View>

        <View style={styles.hintBox}>
          {submitting ? (
            <View style={styles.hintRow}>
              <ActivityIndicator color={colors.textOnPrimary} />
              <Text style={styles.hintText}>جاري التسجيل…</Text>
            </View>
          ) : (
            <Text style={styles.hintText}>
              وجّه الكاميرا نحو رمز QR الخاص بالجهاز
            </Text>
          )}
        </View>
      </View>
    </View>
  );
}

function ModeButton({
  label,
  active,
  onPress,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.modeBtn, active && styles.modeBtnActive]}
    >
      <Text style={[styles.modeText, active && styles.modeTextActive]}>
        {label}
      </Text>
    </Pressable>
  );
}

/**
 * Accept any of:
 *   - `abcd…` (64 hex chars, the raw token as stamped by the kiosk)
 *   - `https://…/scan/abcd…`
 *   - `https://…/anything?qrToken=abcd…` (or `?qr_token=…`)
 * Returns the token string, or null if we can't recognise it.
 */
export function parseQrToken(raw: string): string | null {
  const trimmed = raw.trim();
  if (!trimmed) return null;

  // Case 1 — bare token (64 alnum characters is the Laravel default).
  if (/^[a-zA-Z0-9]{32,128}$/.test(trimmed)) return trimmed;

  // Case 2/3 — URL with the token as either the last segment or a query param.
  try {
    const url = new URL(trimmed);
    const q = url.searchParams.get('qrToken') ?? url.searchParams.get('qr_token');
    if (q) return q;

    const segments = url.pathname.split('/').filter(Boolean);
    const last = segments[segments.length - 1];
    if (last && /^[a-zA-Z0-9]{32,128}$/.test(last)) return last;
  } catch {
    // Not a valid URL — fall through.
  }

  return null;
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: '#000' },
  center: {
    flex: 1,
    padding: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.md,
    backgroundColor: colors.bg,
  },
  title: { ...typography.h2, color: colors.text, textAlign: 'center' },
  body: {
    ...typography.body,
    color: colors.textMuted,
    textAlign: 'center',
    marginBottom: spacing.md,
  },

  overlay: {
    ...StyleSheet.absoluteFillObject,
    padding: spacing.lg,
    justifyContent: 'space-between',
  },
  topBar: {
    marginTop: spacing.xl,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  modeSwitch: {
    flexDirection: 'row',
    backgroundColor: 'rgba(0,0,0,0.55)',
    borderRadius: radius.pill,
    padding: 4,
  },
  modeBtn: {
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    borderRadius: radius.pill,
  },
  modeBtnActive: {
    backgroundColor: colors.primary,
  },
  modeText: {
    ...typography.bodyStrong,
    color: '#fff',
    opacity: 0.7,
  },
  modeTextActive: {
    color: colors.textOnPrimary,
    opacity: 1,
  },
  closeBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.55)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  closeText: { color: '#fff', fontSize: 28, lineHeight: 30 },

  viewfinderWrap: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  viewfinder: {
    width: 240,
    height: 240,
    borderWidth: 3,
    borderColor: colors.accent,
    borderRadius: radius.xl,
    backgroundColor: 'transparent',
  },

  hintBox: {
    alignSelf: 'center',
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing.md,
    backgroundColor: 'rgba(0,0,0,0.6)',
    borderRadius: radius.pill,
    marginBottom: spacing.xl,
  },
  hintRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  hintText: {
    ...typography.body,
    color: '#fff',
    textAlign: 'center',
  },
});
