'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ChevronLeft, Download, FileUp, Loader2, Upload } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { candidatesApi } from '@/lib/api/endpoints/candidates';
import { cn } from '@/lib/utils';
import type { CandidateImportDryRunResult, CandidateImportRowError } from '@/lib/api/types';

const MAX_BYTES = 5 * 1024 * 1024;
const ACCEPTED = /\.(csv|xlsx)$/i;
const POLL_MS = 2000;

const apiMessage = (err: unknown, fallback: string) =>
  (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback;

export default function CandidateImportPage() {
  const params = useParams<{ id: string }>();
  const jobId = Number(params.id);

  const [file, setFile] = React.useState<File | null>(null);
  const [dryRun, setDryRun] = React.useState<CandidateImportDryRunResult | null>(null);
  const [importJobId, setImportJobId] = React.useState<number | null>(null);

  const selectFile = (next: File | null) => {
    if (next && !ACCEPTED.test(next.name)) return void toast.error('الصيغة المسموحة: CSV أو XLSX');
    if (next && next.size > MAX_BYTES) return void toast.error('الحد الأقصى لحجم الملف 5 ميغابايت');
    setFile(next);
    setDryRun(null);
  };

  const template = useMutation({
    mutationFn: async () => (await candidatesApi.importTemplate(jobId)).data,
    onSuccess: (blob) => {
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'candidates-import-template.csv';
      link.click();
      URL.revokeObjectURL(url);
    },
    onError: () => toast.error('تعذر تنزيل القالب'),
  });

  const dryRunMutation = useMutation({
    mutationFn: async (f: File) => (await candidatesApi.importDryRun(jobId, f)).data.data,
    onSuccess: setDryRun,
    onError: (err) => toast.error(apiMessage(err, 'فشلت التجربة')),
  });

  const store = useMutation({
    mutationFn: async (f: File) => (await candidatesApi.importStore(jobId, f)).data.data,
    onSuccess: (job) => setImportJobId(job.id),
    onError: (err) => toast.error(apiMessage(err, 'فشل الاستيراد')),
  });

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/jobs" className="hover:text-brand-ink">الوظائف</Link>
        <ChevronLeft className="h-3 w-3" />
        <Link href={`/recruitment/jobs/${jobId}/candidates`} className="hover:text-brand-ink">المرشّحون</Link>
        <ChevronLeft className="h-3 w-3" />
        <span>استيراد CSV</span>
      </nav>
      <h1 className="text-2xl font-bold text-ink">استيراد مرشّحين</h1>

      {importJobId !== null ? (
        <ImportStatus jobId={jobId} importJobId={importJobId} />
      ) : (
        <>
          <Step n={1} title="تنزيل القالب">
            <Button variant="outline" className="gap-2" onClick={() => template.mutate()} disabled={template.isPending}>
              <Download className="h-4 w-4" /> تنزيل القالب
            </Button>
          </Step>

          <Step n={2} title="رفع الملف">
            <DropZone file={file} onSelect={selectFile} />
          </Step>

          <Step n={3} title="تجربة قبل الاستيراد">
            <Button
              variant="outline"
              className="gap-2"
              disabled={!file || dryRunMutation.isPending}
              onClick={() => file && dryRunMutation.mutate(file)}
            >
              {dryRunMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileUp className="h-4 w-4" />}
              تجربة
            </Button>
            {dryRun && <DryRunResult result={dryRun} />}
          </Step>

          <Step n={4} title="استيراد">
            <Button
              className="gap-2 bg-brand text-white hover:bg-brand-hover"
              disabled={!file || !dryRun || dryRun.parsed_rows === 0 || store.isPending}
              onClick={() => file && store.mutate(file)}
            >
              {store.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
              استيراد
            </Button>
            {dryRun && dryRun.errors.length > 0 && (
              <p className="mt-2 text-xs text-amber-700">
                الصفوف التي بها أخطاء سيتم تخطّيها.
              </p>
            )}
          </Step>
        </>
      )}
    </div>
  );
}

function Step({ n, title, children }: { n: number; title: string; children: React.ReactNode }) {
  return (
    <Card className="border-hairline bg-surface shadow-none">
      <CardContent className="space-y-3 p-6">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-ink">
          <span className="num flex h-6 w-6 items-center justify-center rounded-full bg-brand-soft text-xs text-brand-ink" dir="ltr">
            {n}
          </span>
          {title}
        </h2>
        {children}
      </CardContent>
    </Card>
  );
}

function DropZone({ file, onSelect }: { file: File | null; onSelect: (f: File | null) => void }) {
  const [dragging, setDragging] = React.useState(false);
  const inputRef = React.useRef<HTMLInputElement>(null);

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={() => inputRef.current?.click()}
      onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
      onDragOver={(e) => {
        e.preventDefault();
        setDragging(true);
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={(e) => {
        e.preventDefault();
        setDragging(false);
        onSelect(e.dataTransfer.files[0] ?? null);
      }}
      className={cn(
        'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-hairline p-8 text-center text-sm text-ink-2',
        dragging && 'border-brand bg-brand-soft',
      )}
    >
      <Upload className="h-6 w-6 text-muted" />
      {file ? (
        <span className="num font-medium text-ink" dir="ltr">{file.name}</span>
      ) : (
        <span>اسحب الملف هنا أو اضغط للاختيار (CSV / XLSX، حتى 5 ميغابايت)</span>
      )}
      <input
        ref={inputRef}
        type="file"
        accept=".csv,.xlsx"
        className="hidden"
        onChange={(e) => onSelect(e.target.files?.[0] ?? null)}
      />
    </div>
  );
}

function ErrorsTable({ errors }: { errors: CandidateImportRowError[] }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-hairline">
      <table className="w-full text-sm">
        <thead className="bg-surface-2 text-xs text-muted">
          <tr>
            <th className="px-3 py-2 text-start">الصف</th>
            <th className="px-3 py-2 text-start">الحقل</th>
            <th className="px-3 py-2 text-start">الخطأ</th>
          </tr>
        </thead>
        <tbody>
          {errors.map((e, i) => (
            <tr key={i} className="border-t border-hairline">
              <td className="num px-3 py-2" dir="ltr">{e.row}</td>
              <td className="px-3 py-2">{e.field ?? '—'}</td>
              <td className="px-3 py-2 text-danger">{e.message}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function DryRunResult({ result }: { result: CandidateImportDryRunResult }) {
  const rows = result.preview.slice(0, 10);
  const columns = rows.length ? Object.keys(rows[0]) : [];
  return (
    <div className="space-y-3">
      <p className="text-sm text-ink">
        عدد الصفوف المقروءة: <span className="num font-semibold" dir="ltr">{result.parsed_rows}</span>
        {' — '}الأخطاء: <span className="num font-semibold text-danger" dir="ltr">{result.errors.length}</span>
      </p>
      {result.errors.length > 0 && <ErrorsTable errors={result.errors} />}
      {rows.length > 0 && (
        <div className="overflow-x-auto rounded-lg border border-hairline">
          <table className="w-full text-xs">
            <thead className="bg-surface-2 text-muted">
              <tr>
                {columns.map((c) => (
                  <th key={c} className="px-3 py-2 text-start">{c}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, i) => (
                <tr key={i} className="border-t border-hairline">
                  {columns.map((c) => (
                    <td key={c} className="px-3 py-2">{row[c] ?? '—'}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function ImportStatus({ jobId, importJobId }: { jobId: number; importJobId: number }) {
  const { data: job } = useQuery({
    queryKey: ['candidate-import', jobId, importJobId],
    queryFn: async () => (await candidatesApi.importStatus(jobId, importJobId)).data.data,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'completed' || status === 'failed' ? false : POLL_MS;
    },
  });

  const running = !job || job.status === 'pending' || job.status === 'processing';

  return (
    <Card className="border-hairline bg-surface shadow-none">
      <CardContent className="space-y-4 p-6">
        {running ? (
          <p className="flex items-center gap-2 text-sm text-ink">
            <Loader2 className="h-4 w-4 animate-spin" /> جارٍ الاستيراد...
          </p>
        ) : (
          <p className={cn('text-sm font-semibold', job.status === 'completed' ? 'text-success' : 'text-danger')}>
            {job.status === 'completed' ? 'اكتمل الاستيراد' : 'فشل الاستيراد'}
          </p>
        )}
        {job && !running && (
          <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Stat label="إجمالي الصفوف" value={job.total_rows} />
            <Stat label="مرشّحون جدد" value={job.created_count} />
            <Stat label="مرشّحون موجودون" value={job.reused_count} />
            <Stat label="مكرّر (تم تخطّيه)" value={job.duplicates_skipped} />
          </dl>
        )}
        {job && !running && job.errors.length > 0 && <ErrorsTable errors={job.errors} />}
        {!running && (
          <Button asChild className="bg-brand text-white hover:bg-brand-hover">
            <Link href={`/recruitment/jobs/${jobId}/candidates`}>عرض المرشّحين</Link>
          </Button>
        )}
      </CardContent>
    </Card>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-hairline p-3">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="num mt-1 text-xl font-bold text-ink" dir="ltr">{value}</dd>
    </div>
  );
}
