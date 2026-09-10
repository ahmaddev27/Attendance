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
import { scanApi, type ScanCheckPayload } from '@/lib/api/endpoints/attendance';
import { formatTime } from '@/lib/attendance-format';
import type { ScanDeviceInfo, ScanResponse, ScanStatus } from '@/lib/api/types';

// The kiosk remembers the last successful employee here so the same person
// returning to check out doesn't have to retype their number. Cleared on
// the "تغيير" button below.
const STORAGE_KEY = 'taqat_kiosk_employee_number';

type ViewState =
  | 'loading-device'
  | 'device-error'
  | 'entering-number'
  | 'checking-status'
  | 'ready'
  | 'locating'
  | 'success'
  | 'day-complete';

type ReadyPayload = {
  status: ScanStatus;
  // Which action the ready screen offers based on the status.state.
  action: 'check-in' | 'check-out';
};

function readSavedNumber(): number | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const n = Number(raw);
    return Number.isFinite(n) && n > 0 ? n : null;
  } catch {
    return null;
  }
}

function saveNumber(n: number) {
  try {
    window.localStorage.setItem(STORAGE_KEY, String(n));
  } catch {
    // Private browsing / storage disabled — kiosk still works.
  }
}

function clearSavedNumber() {
  try {
    window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    /* ignore */
  }
}

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

export default function KioskScanPage() {
  const params = useParams<{ qrToken: string }>();
  const qrToken = params.qrToken;

  const [view, setView] = useState<ViewState>('loading-device');
  const [device, setDevice] = useState<ScanDeviceInfo | null>(null);
  const [employeeNumberInput, setEmployeeNumberInput] = useState('');
  const [ready, setReady] = useState<ReadyPayload | null>(null);
  const [successResult, setSuccessResult] = useState<{
    action: 'check-in' | 'check-out';
    response: ScanResponse;
  } | null>(null);
  const resetTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const loadDevice = useCallback(async () => {
    setView('loading-device');
    try {
      const info = await scanApi.deviceInfo(qrToken);
      setDevice(info);

      // If the last employee is remembered on this browser, jump straight
      // to the status probe. Otherwise show the number-entry screen.
      const saved = readSavedNumber();
      if (saved) {
        probeStatus(saved);
      } else {
        setView('entering-number');
      }
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

  /**
   * Query the backend for what THIS employee should do next — the whole
   * point of the new flow. `not_checked_in` → offer check-in;
   * `checked_in` → offer check-out; `checked_out` → show a done screen.
   */
  const probeStatus = async (employeeNumber: number) => {
    setView('checking-status');
    try {
      const status = await scanApi.status({ qr_token: qrToken, employee_number: employeeNumber });
      if (status.state === 'checked_out') {
        setReady({ status, action: 'check-out' });
        setView('day-complete');
        return;
      }
      const action = status.state === 'not_checked_in' ? 'check-in' : 'check-out';
      setReady({ status, action });
      setView('ready');
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'رقم وظيفي غير معروف أو غير مفعّل';
      toast.error(message);
      clearSavedNumber();
      setView('entering-number');
    }
  };

  const submitScan = async () => {
    if (!ready) return;
    setView('locating');

    // Only ask the browser for a location fix when the device actually
    // enforces geofencing. Prompting every time and then discarding the
    // answer produced the annoying "enable location" nag the user hit on
    // devices where geo is off. Also skips the 10-second timeout wait on
    // OS-level denials.
    const needsGeo = device?.enforce_geo === true;
    const position = needsGeo ? await getCurrentPosition() : null;
    const payload: ScanCheckPayload = {
      employee_number: ready.status.employee.employee_number,
      qr_token: qrToken,
      latitude: position?.coords.latitude ?? undefined,
      longitude: position?.coords.longitude ?? undefined,
    };
    try {
      const response =
        ready.action === 'check-in'
          ? await scanApi.checkIn(payload)
          : await scanApi.checkOut(payload);
      saveNumber(response.attendance.employee.employee_number);
      setSuccessResult({ action: ready.action, response });
      setView('success');
      // After a beat, reset back to the number-entry screen so the next
      // person in line at the kiosk can walk up.
      resetTimerRef.current = setTimeout(() => {
        setSuccessResult(null);
        setReady(null);
        setEmployeeNumberInput('');
        setView('entering-number');
      }, 4500);
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'حدث خطأ، حاول مرة أخرى';
      toast.error(message);
      setView('ready');
    }
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const number = Number(employeeNumberInput);
    if (!employeeNumberInput.trim() || !Number.isFinite(number) || number <= 0) {
      toast.error('أدخل رقماً وظيفياً صحيحاً');
      return;
    }
    probeStatus(number);
  };

  const changeEmployee = () => {
    clearSavedNumber();
    setReady(null);
    setEmployeeNumberInput('');
    setView('entering-number');
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

        {view === 'ready' && ready && (
          <div className="flex w-full max-w-sm flex-col items-center gap-6">
            <LiveClock />
            <p className="text-2xl font-bold text-ink">مرحباً {ready.status.employee.full_name}</p>
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
              onClick={submitScan}
              className={
                // Both buttons are solid, high-contrast, same shape —
                // only the color differs so the kiosk operator sees at a
                // glance which action they're about to take. Warning
                // (yellow) faded into the surface on the previous look
                // and read as disabled; a deep amber holds contrast on
                // the light kiosk ground.
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
              لست {ready.status.employee.full_name}؟ تغيير
            </button>
          </div>
        )}

        {view === 'day-complete' && ready && (
          <div className="flex w-full max-w-sm flex-col items-center gap-4 text-center">
            <CheckCircle2 className="h-16 w-16 text-success" />
            <p className="text-xl font-semibold text-ink">
              أنهيت دوامك يا {ready.status.employee.full_name}
            </p>
            {ready.status.check_in_at && ready.status.check_out_at && (
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
              موظف آخر
            </button>
          </div>
        )}

        {view === 'locating' && (
          <div className="flex flex-col items-center gap-3 text-muted">
            <Spinner className="h-8 w-8" />
            <p>جارٍ تحديد الموقع وتسجيل البيانات...</p>
          </div>
        )}

        {view === 'success' && successResult && (
          <div className="flex animate-in flex-col items-center gap-4 zoom-in-50 duration-300">
            <CheckCircle2 className="h-20 w-20 text-success" />
            <p className="text-center text-lg font-semibold text-ink">{successResult.response.message}</p>
            <p className="num text-4xl font-bold text-ink">
              {formatTime(
                successResult.action === 'check-in'
                  ? successResult.response.attendance.check_in_at
                  : successResult.response.attendance.check_out_at,
              )}
            </p>
          </div>
        )}
      </main>

      <footer className="border-t border-hairline py-4 text-center text-xs text-muted">
        {device?.device_name ?? ' '}
      </footer>
    </div>
  );
}
