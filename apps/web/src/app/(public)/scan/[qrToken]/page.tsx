'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'next/navigation';
import Image from 'next/image';
import { toast } from 'sonner';
import { CheckCircle2, LogIn, LogOut, RefreshCw, TriangleAlert } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { LiveClock } from '@/components/attendance/live-clock';
import {
  scanApi,
  type ScanCheckPayload,
  type ScanRecordResponse,
} from '@/lib/api/endpoints/attendance';
import { formatTime } from '@/lib/attendance-format';
import type { ScanDeviceInfo, ScanResponse, ScanStatus } from '@/lib/api/types';

// Kiosks are shared devices: the page intentionally does NOT remember the
// previous employee across mounts. Auto-loading the last number greeted
// whoever walked up next by name and made an accidental check-in one tap
// away for the wrong person.

type ViewState =
  | 'loading-device'
  | 'device-error'
  | 'entering-pin'
  | 'entering-number'
  | 'checking-status'
  | 'ready'
  // Owner's rule 2026-10-04: check-in is one tap (instant), but check-out
  // asks the employee to confirm first — ending the day is one-way, so
  // a mistyped PIN on a shared kiosk should never silently close someone
  // else's shift. The confirm screen shows the check-in time so the
  // employee verifies before committing.
  | 'checkout-confirm'
  | 'locating'
  | 'success'
  | 'day-complete';

type ReadyPayload = {
  status: ScanStatus;
  // Which action the ready screen offers based on the status.state.
  action: 'check-in' | 'check-out';
};

type SuccessPayload = {
  action: 'check-in' | 'check-out';
  // The timestamp to show (either check_in_at or check_out_at from the
  // just-written attendance row).
  at: string | null;
  message: string;
};

/**
 * The checkout-confirm state carries the PIN forward from the entry
 * screen (so the "تأكيد" tap can submit the record call without the
 * employee re-typing) plus the earlier check-in time so the card can
 * show "you clocked in at X — close the day?". The pin is cleared the
 * moment the confirm screen leaves, same as every other PIN handling
 * path, so it never lingers on a shared kiosk.
 */
type CheckoutConfirmPayload = {
  pin: string;
  checkInAt: string | null;
};

const PIN_PATTERN = /^\d{4}$/;
// Owner's call 2026-10-04: the kiosk should sit on the success card long
// enough that a passerby reads "تم تسجيل حضورك" + the time without
// catching only the fade-out. 5 seconds is still short enough to clear
// before the next scanner reaches the input.
const SUCCESS_RESET_MS = 5000;

function getCurrentPosition(): Promise<GeolocationPosition | null> {
  // Geolocation is best-effort: if the device doesn't have it or the user
  // denies, we send null coordinates. The backend enforces geo ONLY when
  // enforce_geo is on for that device, so a null-coord scan on a
  // geo-disabled device still succeeds. On a geo-enforced device, the
  // backend will 422 with a clear reason.
  return new Promise((resolve) => {
    if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
      resolve(null);
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve(pos),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 10_000, maximumAge: 0 },
    );
  });
}

function readApiError(err: unknown): { status?: number; message?: string } {
  const response = (err as { response?: { status?: number; data?: { message?: string } } })?.response;
  return { status: response?.status, message: response?.data?.message };
}

export default function KioskScanPage() {
  const params = useParams<{ qrToken: string }>();
  const qrToken = params.qrToken;

  const [view, setView] = useState<ViewState>('loading-device');
  const [device, setDevice] = useState<ScanDeviceInfo | null>(null);
  const [employeeNumberInput, setEmployeeNumberInput] = useState('');
  const [pinInput, setPinInput] = useState('');
  const [ready, setReady] = useState<ReadyPayload | null>(null);
  const [success, setSuccess] = useState<SuccessPayload | null>(null);
  const [checkoutConfirm, setCheckoutConfirm] = useState<CheckoutConfirmPayload | null>(null);
  const resetTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pinRequired = device?.pin_required === true;

  const loadDevice = useCallback(async () => {
    setView('loading-device');
    try {
      const info = await scanApi.deviceInfo(qrToken);
      setDevice(info);
      // Pick the entry screen that matches the device's enforcement mode.
      // PIN-required = PIN-only identity (owner's call 2026-10-03 — see
      // memory project-pin-only-scan-decision). No employee number input.
      setView(info.pin_required ? 'entering-pin' : 'entering-number');
    } catch {
      setView('device-error');
    }
  }, [qrToken]);

  useEffect(() => {
    loadDevice();
    return () => {
      if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loadDevice]);

  const resetToEntry = useCallback(() => {
    setReady(null);
    setSuccess(null);
    setCheckoutConfirm(null);
    setPinInput('');
    setEmployeeNumberInput('');
    setView(pinRequired ? 'entering-pin' : 'entering-number');
  }, [pinRequired]);

  const returnToNumberEntry = () => {
    setReady(null);
    setPinInput('');
    setView(pinRequired ? 'entering-pin' : 'entering-number');
  };

  const scheduleAutoReset = useCallback(() => {
    if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
    resetTimerRef.current = setTimeout(resetToEntry, SUCCESS_RESET_MS);
  }, [resetToEntry]);

  // ---------- PIN-only flow ----------
  //
  // Owner's rule 2026-10-04:
  //  - check-IN is one tap (no "are you sure?" delay)
  //  - check-OUT asks the employee to confirm, with the check-in time
  //    shown on the confirm card, because ending the day is one-way and
  //    a mis-typed PIN on a shared kiosk should never silently close
  //    someone else's shift.
  //
  // The flow probes `/scan/status` first with the PIN. If the employee is
  // `not_checked_in` we proceed straight to `/scan/record` (same instant
  // feel as before). If they are `checked_in`, we park on the confirm
  // card with the previous check-in time until they tap "تأكيد".

  const submitPinOnly = async () => {
    if (!PIN_PATTERN.test(pinInput)) {
      toast.error('أدخل رمز الحضور المكوّن من 4 أرقام');
      return;
    }
    const pin = pinInput;
    setView('checking-status');

    try {
      const status = await scanApi.status({ qr_token: qrToken, pin });

      if (status.state === 'checked_out') {
        setPinInput('');
        setSuccess({
          action: 'check-out',
          at: status.check_out_at ?? null,
          message: 'سجّلت حضورك وانصرافك لليوم.',
        });
        setView('day-complete');
        scheduleAutoReset();
        return;
      }

      if (status.state === 'checked_in') {
        // Park on the confirm card. The PIN moves off the input into
        // `checkoutConfirm` so the entry screen is clean behind the
        // overlay AND so a cancel tap drops the PIN altogether.
        setPinInput('');
        setCheckoutConfirm({ pin, checkInAt: status.check_in_at ?? null });
        setView('checkout-confirm');
        return;
      }

      // not_checked_in → instant check-in path, same UX as before.
      await commitScanRecord(pin, 'check-in');
    } catch (err: unknown) {
      const { message } = readApiError(err);
      toast.error(message ?? 'رمز غير صحيح، حاول مرة أخرى');
      setPinInput('');
      setView('entering-pin');
    }
  };

  /**
   * Fires `/scan/record` and routes to the success / day-complete view
   * based on the server's answer. Shared by the instant check-in path
   * and the "تأكيد الانصراف" tap on the confirm card. The caller passes
   * the action it EXPECTS so the error path can speak the right word
   * ("حضور" vs "انصراف"), but the server's response is still trusted
   * as the source of truth for which action was actually recorded.
   */
  const commitScanRecord = async (pin: string, expected: 'check-in' | 'check-out') => {
    setView('locating');

    const needsGeo = device?.enforce_geo === true;
    const position = needsGeo ? await getCurrentPosition() : null;
    const payload: ScanCheckPayload = {
      qr_token: qrToken,
      pin,
      latitude: position?.coords.latitude ?? undefined,
      longitude: position?.coords.longitude ?? undefined,
    };

    try {
      const response: ScanRecordResponse = await scanApi.record(payload);
      // Clear PIN immediately so it never lingers on a shared kiosk.
      setPinInput('');
      setCheckoutConfirm(null);

      if (response.action === 'done') {
        setSuccess({
          action: 'check-out',
          at: response.check_out_at ?? null,
          message: response.message,
        });
        setView('day-complete');
        scheduleAutoReset();
        return;
      }

      setSuccess({
        action: response.action,
        at:
          response.action === 'check-in'
            ? response.attendance?.check_in_at ?? null
            : response.attendance?.check_out_at ?? null,
        message: response.message,
      });
      setView('success');
      scheduleAutoReset();
    } catch (err: unknown) {
      const { status, message } = readApiError(err);
      if (status === 409) {
        const payload409 = (err as { response?: { data?: ScanRecordResponse } })?.response?.data;
        setSuccess({
          action: 'check-out',
          at: payload409?.check_out_at ?? null,
          message: payload409?.message ?? 'سجّلت حضورك وانصرافك لليوم.',
        });
        setView('day-complete');
        scheduleAutoReset();
        return;
      }
      const fallback = expected === 'check-in'
        ? 'تعذّر تسجيل الحضور. حاول مرة أخرى.'
        : 'تعذّر تسجيل الانصراف. حاول مرة أخرى.';
      toast.error(message ?? fallback);
      setPinInput('');
      setCheckoutConfirm(null);
      setView('entering-pin');
    }
  };

  const confirmCheckoutNow = () => {
    if (!checkoutConfirm) return;
    void commitScanRecord(checkoutConfirm.pin, 'check-out');
  };

  const cancelCheckout = () => {
    setCheckoutConfirm(null);
    setPinInput('');
    setView('entering-pin');
  };

  // ---------- Legacy flow (PIN-off): employee_number → status → confirm ----------

  const probeStatus = async (employeeNumber: number) => {
    setView('checking-status');
    try {
      const status = await scanApi.status({
        qr_token: qrToken,
        employee_number: employeeNumber,
      });
      if (status.state === 'checked_out') {
        setReady({ status, action: 'check-out' });
        setView('day-complete');
        return;
      }
      const action = status.state === 'not_checked_in' ? 'check-in' : 'check-out';
      setReady({ status, action });
      setView('ready');
    } catch (err: unknown) {
      toast.error(readApiError(err).message ?? 'رقم وظيفي غير معروف أو غير مفعّل');
      returnToNumberEntry();
    }
  };

  const submitScanLegacy = async () => {
    if (!ready) return;
    setView('locating');

    const needsGeo = device?.enforce_geo === true;
    const position = needsGeo ? await getCurrentPosition() : null;
    const payload: ScanCheckPayload = {
      employee_number: ready.status.employee.employee_number,
      qr_token: qrToken,
      latitude: position?.coords.latitude ?? undefined,
      longitude: position?.coords.longitude ?? undefined,
    };
    try {
      const response: ScanResponse =
        ready.action === 'check-in'
          ? await scanApi.checkIn(payload)
          : await scanApi.checkOut(payload);
      setSuccess({
        action: ready.action,
        at:
          ready.action === 'check-in'
            ? response.attendance.check_in_at
            : response.attendance.check_out_at,
        message: response.message,
      });
      setView('success');
      scheduleAutoReset();
    } catch (err: unknown) {
      const { message } = readApiError(err);
      toast.error(message ?? 'حدث خطأ، حاول مرة أخرى');
      setView('ready');
    }
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (pinRequired) {
      submitPinOnly();
      return;
    }
    const number = Number(employeeNumberInput);
    if (!employeeNumberInput.trim() || !Number.isFinite(number) || number <= 0) {
      toast.error('أدخل رقماً وظيفياً صحيحاً');
      return;
    }
    probeStatus(number);
  };

  const changeEmployee = () => {
    if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
    resetToEntry();
  };

  return (
    <div className="flex min-h-screen flex-col bg-ground">
      <header className="flex justify-center pt-8">
        <Image src="/img/logo.png" alt="TAQAT" width={130} height={46} priority className="object-contain" />
      </header>

      <main className="flex flex-1 flex-col items-center justify-center gap-8 px-6 py-8">
        {view === 'loading-device' && (
          <div className="flex flex-col items-center gap-3 text-muted">
            <Spinner className="h-8 w-8" />
            <p>جارٍ تحميل بيانات الجهاز...</p>
          </div>
        )}

        {view === 'device-error' && (
          <div className="flex flex-col items-center gap-4 text-center">
            <TriangleAlert className="h-14 w-14 text-danger" />
            <p className="text-lg font-semibold text-ink">الجهاز غير موجود أو غير نشط</p>
            <p className="max-w-sm text-sm text-muted">
              تأكد من مسح رمز QR الصحيح أو تواصل مع إدارة الموارد البشرية
            </p>
            <Button type="button" onClick={loadDevice} className="mt-2 min-h-[56px] px-8">
              إعادة المحاولة
            </Button>
          </div>
        )}

        {/*
          PIN-only entry screen: a single 4-digit input. The server decides
          whether this scan is a check-in or a check-out, so the kiosk has
          exactly one button and no middle "confirm" screen.
        */}
        {view === 'entering-pin' && (
          <form onSubmit={handleManualSubmit} className="flex w-full max-w-sm flex-col items-center gap-6">
            <LiveClock />
            <div className="flex flex-col items-center gap-2 text-center">
              <p className="text-lg font-semibold text-ink">أدخل رمز الحضور</p>
              <p className="text-xs text-muted">4 أرقام — يحدد النظام تلقائياً حضور أو انصراف</p>
            </div>
            <Input
              id="scan-pin"
              type="password"
              inputMode="numeric"
              autoFocus
              autoComplete="off"
              maxLength={4}
              aria-label="رمز الحضور"
              value={pinInput}
              onChange={(e) => setPinInput(e.target.value.replace(/[^0-9]/g, '').slice(0, 4))}
              className="num h-20 w-full rounded-2xl text-center text-5xl font-bold tracking-[0.5em]"
              dir="ltr"
            />
            <Button
              type="submit"
              className="min-h-[64px] w-full bg-brand text-xl font-bold text-white hover:bg-brand-hover"
            >
              تسجيل
            </Button>
          </form>
        )}

        {/* Legacy flow (PIN enforcement off): number entry + status + confirm. */}
        {view === 'entering-number' && (
          <form onSubmit={handleManualSubmit} className="flex w-full max-w-sm flex-col items-center gap-6">
            <LiveClock />
            <div className="flex flex-col items-center gap-2 text-center">
              <p className="text-lg font-semibold text-ink">أدخل رقمك الوظيفي</p>
              <p className="text-xs text-muted">سنعرض الزر المناسب لك — حضور أو انصراف</p>
            </div>
            <Input
              type="tel"
              inputMode="numeric"
              autoFocus
              aria-label="الرقم الوظيفي"
              value={employeeNumberInput}
              onChange={(e) => setEmployeeNumberInput(e.target.value.replace(/[^0-9]/g, ''))}
              className="num h-20 w-full rounded-2xl text-center text-4xl font-bold tracking-widest"
              dir="ltr"
            />
            <Button
              type="submit"
              className="min-h-[56px] w-full bg-brand text-lg font-bold text-white hover:bg-brand-hover"
            >
              متابعة
            </Button>
          </form>
        )}

        {view === 'checking-status' && (
          <div className="flex flex-col items-center gap-3 text-muted">
            <Spinner className="h-8 w-8" />
            <p>جارٍ التحقق من حالتك اليومية...</p>
          </div>
        )}

        {view === 'checkout-confirm' && checkoutConfirm && (
          <div className="flex w-full max-w-sm animate-in flex-col items-center gap-6 zoom-in-50 duration-300">
            <div className="flex w-full flex-col items-center gap-5 rounded-3xl bg-amber-50 p-10 shadow-sm ring-1 ring-amber-500/40">
              <div className="grid h-24 w-24 place-items-center rounded-full bg-white shadow-sm ring-4 ring-amber-500/40">
                <LogOut className="h-12 w-12 text-amber-700" aria-hidden="true" />
              </div>

              <div className="flex flex-col items-center gap-2 text-center">
                <p className="text-2xl font-bold text-ink">تأكيد الانصراف</p>
                {checkoutConfirm.checkInAt && (
                  <p className="text-sm text-ink-2">
                    سُجّل حضورك عند{' '}
                    <span className="num font-bold text-ink" dir="ltr">
                      {formatTime(checkoutConfirm.checkInAt)}
                    </span>
                  </p>
                )}
              </div>

              <div className="flex w-full flex-col gap-2">
                <Button
                  type="button"
                  onClick={confirmCheckoutNow}
                  className="min-h-[64px] w-full bg-amber-600 text-xl font-bold text-white shadow-sm hover:bg-amber-700"
                >
                  <LogOut className="me-2 h-6 w-6" />
                  تأكيد الانصراف
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  onClick={cancelCheckout}
                  className="min-h-[48px] w-full text-ink-2 hover:bg-surface-2"
                >
                  إلغاء
                </Button>
              </div>
            </div>
          </div>
        )}

        {view === 'ready' && ready && (
          <div className="flex w-full max-w-sm flex-col items-center gap-6">
            <LiveClock />
            {ready.status.employee.full_name ? (
              <div className="flex flex-col items-center gap-1 text-center">
                <p className="text-3xl font-bold text-ink">
                  مرحباً {ready.status.employee.full_name}
                </p>
                <p className="text-sm text-muted">
                  الرقم الوظيفي{' '}
                  <span className="num" dir="ltr">{ready.status.employee.employee_number}</span>
                </p>
              </div>
            ) : (
              <p className="text-2xl font-bold text-ink">
                مرحباً — الرقم الوظيفي{' '}
                <span className="num" dir="ltr">{ready.status.employee.employee_number}</span>
              </p>
            )}
            {ready.action === 'check-out' && ready.status.check_in_at && (
              <p className="text-sm text-muted">
                سُجّل حضورك عند{' '}
                <span className="num font-semibold text-ink">
                  {formatTime(ready.status.check_in_at)}
                </span>
              </p>
            )}
            <Button
              type="button"
              onClick={submitScanLegacy}
              className={
                ready.action === 'check-in'
                  ? 'min-h-[64px] w-full bg-brand text-xl font-bold text-white shadow-sm hover:bg-brand-hover'
                  : 'min-h-[64px] w-full bg-amber-600 text-xl font-bold text-white shadow-sm hover:bg-amber-700'
              }
            >
              {ready.action === 'check-in' ? <LogIn className="h-6 w-6" /> : <LogOut className="h-6 w-6" />}
              {ready.action === 'check-in' ? 'تسجيل الحضور' : 'تسجيل الانصراف'}
            </Button>
            <button
              type="button"
              onClick={changeEmployee}
              className="mt-1 flex items-center gap-2 text-sm text-muted underline-offset-4 hover:text-ink hover:underline"
            >
              <RefreshCw className="h-3.5 w-3.5" />
              ليس رقمك؟ تغيير
            </button>
          </div>
        )}

        {view === 'day-complete' && (
          <div className="flex w-full max-w-sm flex-col items-center gap-4 text-center">
            <CheckCircle2 className="h-16 w-16 text-success" />
            <p className="text-xl font-semibold text-ink">
              {success?.message ?? 'سجّلت حضورك وانصرافك لليوم.'}
            </p>
            {ready?.status.check_in_at && ready?.status.check_out_at && (
              <p className="text-sm text-muted">
                <span className="num">{formatTime(ready.status.check_in_at)}</span> →{' '}
                <span className="num">{formatTime(ready.status.check_out_at)}</span>
              </p>
            )}
            <button
              type="button"
              onClick={changeEmployee}
              className="mt-2 flex items-center gap-2 text-sm text-muted underline-offset-4 hover:text-ink hover:underline"
            >
              <RefreshCw className="h-3.5 w-3.5" />
              {pinRequired ? 'إغلاق' : 'موظف آخر'}
            </button>
          </div>
        )}

        {view === 'locating' && (
          <div className="flex flex-col items-center gap-3 text-muted">
            <Spinner className="h-8 w-8" />
            <p>جارٍ تسجيل البيانات...</p>
          </div>
        )}

        {view === 'success' && success && (
          <SuccessCard action={success.action} message={success.message} at={success.at} />
        )}
      </main>

      <footer className="border-t border-hairline py-4 text-center text-xs text-muted">
        {device?.device_name ?? ' '}
      </footer>
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/* Success card                                                               */
/* -------------------------------------------------------------------------- */

/**
 * Full-height confirmation after a successful scan — owner's 2026-10-04
 * ask: "I want something that clearly shows the scan was recorded, and a
 * beautiful confirmation when they clock out." The card doubles the icon
 * size, lays the headline above the time, and tints the whole surface so
 * someone walking past the kiosk reads the result from four feet away.
 */
function SuccessCard({
  action,
  message,
  at,
}: {
  action: 'check-in' | 'check-out';
  message: string;
  at: string | null;
}) {
  const isCheckIn = action === 'check-in';
  const Icon = isCheckIn ? LogIn : LogOut;
  // Light surface tint + a solid accent ring. Keeps the brand palette the
  // rest of the admin uses (success green for check-in, amber for
  // check-out) without inventing a new background.
  const surface = isCheckIn
    ? 'bg-success-soft/50 ring-success/30 text-success'
    : 'bg-amber-50 ring-amber-500/40 text-amber-700';
  const headline = isCheckIn ? 'تم تسجيل حضورك' : 'تم تسجيل انصرافك';

  return (
    <div className="flex w-full max-w-sm animate-in flex-col items-center gap-6 zoom-in-50 duration-300">
      <div
        className={`flex w-full flex-col items-center gap-5 rounded-3xl p-10 shadow-sm ring-1 ${surface}`}
      >
        <div
          className={`grid h-28 w-28 place-items-center rounded-full bg-white shadow-sm ${
            isCheckIn ? 'ring-4 ring-success/30' : 'ring-4 ring-amber-500/40'
          }`}
        >
          <Icon className="h-14 w-14" aria-hidden="true" />
        </div>

        <div className="flex flex-col items-center gap-2 text-center">
          <p className="text-3xl font-bold text-ink">{headline}</p>
          {at && (
            <p className="num text-5xl font-black tracking-tight text-ink" dir="ltr">
              {formatTime(at)}
            </p>
          )}
        </div>

        <div className="flex items-center gap-2 text-sm font-medium">
          <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
          <span>{message}</span>
        </div>
      </div>
    </div>
  );
}
