'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'next/navigation';
import Image from 'next/image';
import { toast } from 'sonner';
import { CheckCircle2, LogIn, LogOut, TriangleAlert } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { LiveClock } from '@/components/attendance/live-clock';
import { scanApi, type ScanCheckPayload } from '@/lib/api/endpoints/attendance';
import { formatTime } from '@/lib/attendance-format';
import type { ScanDeviceInfo, ScanResponse } from '@/lib/api/types';

const STORAGE_KEY = 'taqat_kiosk_employee';

type SavedEmployee = { employee_number: number; full_name: string };
type ScanAction = 'check-in' | 'check-out';
type ViewState = 'loading' | 'device-error' | 'idle' | 'entering-number' | 'locating' | 'success';

function readSavedEmployee(): SavedEmployee | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (typeof parsed?.employee_number === 'number' && typeof parsed?.full_name === 'string') {
      return parsed;
    }
    return null;
  } catch {
    return null;
  }
}

function saveEmployee(employee: SavedEmployee) {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(employee));
  } catch {
    // Private browsing / storage disabled — the kiosk still works, it just
    // won't remember the employee for next time.
  }
}

function clearSavedEmployee() {
  try {
    window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    // ignore
  }
}

function getCurrentPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
      reject(new Error('المتصفح لا يدعم تحديد الموقع'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      resolve,
      () => reject(new Error('يرجى تفعيل خدمة الموقع للمتابعة')),
      { enableHighAccuracy: true, timeout: 10_000, maximumAge: 0 }
    );
  });
}

const ACTION_LABEL: Record<ScanAction, string> = {
  'check-in': 'تسجيل الحضور',
  'check-out': 'تسجيل الانصراف',
};

export default function KioskScanPage() {
  const params = useParams<{ qrToken: string }>();
  const qrToken = params.qrToken;

  const [view, setView] = useState<ViewState>('loading');
  const [device, setDevice] = useState<ScanDeviceInfo | null>(null);
  const [savedEmployee, setSavedEmployee] = useState<SavedEmployee | null>(null);
  const [pendingAction, setPendingAction] = useState<ScanAction | null>(null);
  const [employeeNumberInput, setEmployeeNumberInput] = useState('');
  const [successResult, setSuccessResult] = useState<{ action: ScanAction; response: ScanResponse } | null>(null);
  const resetTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const loadDevice = useCallback(async () => {
    setView('loading');
    try {
      const info = await scanApi.deviceInfo(qrToken);
      setDevice(info);
      setView('idle');
    } catch {
      setView('device-error');
    }
  }, [qrToken]);

  useEffect(() => {
    setSavedEmployee(readSavedEmployee());
    loadDevice();
    return () => {
      if (resetTimerRef.current) clearTimeout(resetTimerRef.current);
    };
  }, [loadDevice]);

  const backToIdle = () => {
    setPendingAction(null);
    setEmployeeNumberInput('');
    setView('idle');
  };

  const startAction = (action: ScanAction) => {
    setPendingAction(action);
    if (savedEmployee) {
      submitScan(action, savedEmployee.employee_number, { fromManualEntry: false });
    } else {
      setView('entering-number');
    }
  };

  const submitScan = async (
    action: ScanAction,
    employeeNumber: number,
    { fromManualEntry }: { fromManualEntry: boolean }
  ) => {
    setView('locating');
    try {
      const position = await getCurrentPosition();
      const payload: ScanCheckPayload = {
        employee_number: employeeNumber,
        qr_token: qrToken,
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      };
      const response = action === 'check-in' ? await scanApi.checkIn(payload) : await scanApi.checkOut(payload);

      const employee: SavedEmployee = {
        employee_number: response.attendance.employee.employee_number,
        full_name: response.attendance.employee.full_name,
      };
      saveEmployee(employee);
      setSavedEmployee(employee);
      setSuccessResult({ action, response });
      setView('success');

      resetTimerRef.current = setTimeout(() => {
        setSuccessResult(null);
        backToIdle();
      }, 4000);
    } catch (err: any) {
      const message = err?.response?.data?.message || err?.message || 'حدث خطأ، حاول مرة أخرى';
      toast.error(message);
      setView(fromManualEntry ? 'entering-number' : 'idle');
    }
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const number = Number(employeeNumberInput);
    if (!employeeNumberInput.trim() || Number.isNaN(number)) {
      toast.error('أدخل رقماً وظيفياً صحيحاً');
      return;
    }
    if (pendingAction) submitScan(pendingAction, number, { fromManualEntry: true });
  };

  const handleChangeEmployee = () => {
    clearSavedEmployee();
    setSavedEmployee(null);
  };

  return (
    <div className="flex min-h-screen flex-col bg-ground">
      <header className="flex justify-center pt-8">
        <Image src="/img/logo.png" alt="TAQAT" width={130} height={46} priority className="object-contain" />
      </header>

      <main className="flex flex-1 flex-col items-center justify-center gap-8 px-6 py-8">
        {view === 'loading' && (
          <div className="flex flex-col items-center gap-3 text-muted">
            <Spinner className="h-8 w-8" />
            <p>جارٍ تحميل بيانات الجهاز...</p>
          </div>
        )}

        {view === 'device-error' && (
          <div className="flex flex-col items-center gap-4 text-center">
            <TriangleAlert className="h-14 w-14 text-danger" />
            <p className="text-lg font-semibold text-ink">الجهاز غير موجود أو غير نشط</p>
            <p className="max-w-sm text-sm text-muted">تأكد من مسح رمز QR الصحيح أو تواصل مع إدارة الموارد البشرية</p>
            <Button type="button" onClick={loadDevice} className="mt-2 min-h-[56px] px-8">
              إعادة المحاولة
            </Button>
          </div>
        )}

        {view === 'idle' && (
          <div className="flex w-full max-w-sm flex-col items-center gap-8">
            <LiveClock />

            {savedEmployee ? (
              <div className="flex w-full flex-col items-center gap-4">
                <p className="text-xl font-semibold text-ink">أنت {savedEmployee.full_name}؟</p>
                <div className="flex w-full flex-col gap-3">
                  <Button
                    type="button"
                    onClick={() => startAction('check-in')}
                    className="min-h-[56px] w-full bg-brand text-lg font-bold text-white hover:bg-brand-hover"
                  >
                    <LogIn className="h-5 w-5" />
                    تسجيل الحضور
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => startAction('check-out')}
                    className="min-h-[56px] w-full text-lg font-bold"
                  >
                    <LogOut className="h-5 w-5" />
                    تسجيل الانصراف
                  </Button>
                  <button
                    type="button"
                    onClick={handleChangeEmployee}
                    className="mt-1 min-h-[44px] text-sm text-muted underline-offset-4 hover:text-ink hover:underline"
                  >
                    تغيير
                  </button>
                </div>
              </div>
            ) : (
              <div className="flex w-full flex-col gap-3">
                <Button
                  type="button"
                  onClick={() => startAction('check-in')}
                  className="min-h-[56px] w-full bg-brand text-lg font-bold text-white hover:bg-brand-hover"
                >
                  <LogIn className="h-5 w-5" />
                  تسجيل الحضور
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => startAction('check-out')}
                  className="min-h-[56px] w-full text-lg font-bold"
                >
                  <LogOut className="h-5 w-5" />
                  تسجيل الانصراف
                </Button>
              </div>
            )}
          </div>
        )}

        {view === 'entering-number' && pendingAction && (
          <form onSubmit={handleManualSubmit} className="flex w-full max-w-sm flex-col items-center gap-6">
            <p className="text-lg font-semibold text-ink">{ACTION_LABEL[pendingAction]}</p>
            <p className="text-sm text-muted">أدخل رقمك الوظيفي</p>
            <Input
              type="tel"
              inputMode="numeric"
              autoFocus
              value={employeeNumberInput}
              onChange={(e) => setEmployeeNumberInput(e.target.value.replace(/[^0-9]/g, ''))}
              className="num h-20 w-full rounded-2xl text-center text-4xl font-bold tracking-widest"
              dir="ltr"
            />
            <div className="flex w-full flex-col gap-3">
              <Button
                type="submit"
                className="min-h-[56px] w-full bg-brand text-lg font-bold text-white hover:bg-brand-hover"
              >
                متابعة
              </Button>
              <Button type="button" variant="ghost" onClick={backToIdle} className="min-h-[48px] w-full">
                إلغاء
              </Button>
            </div>
          </form>
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
                  : successResult.response.attendance.check_out_at
              )}
            </p>
          </div>
        )}
      </main>

      <footer className="border-t border-hairline py-4 text-center text-xs text-muted">
        {device?.device_name ?? ' '}
      </footer>
    </div>
  );
}
