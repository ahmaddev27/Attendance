'use client';

import * as React from 'react';
import { Download, Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { saveBlobAsFile } from '@/lib/download-file';

export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

const EXPORT_LABEL: Record<ExportFormat, string> = {
  csv: 'CSV',
  xlsx: 'Excel',
  pdf: 'PDF',
};

type ExportMenuButtonProps = {
  /** File name without extension; the picked format supplies the suffix. */
  basename: string;
  fetchers: Record<ExportFormat, () => Promise<{ data: Blob }>>;
};

export function ExportMenuButton({ basename, fetchers }: ExportMenuButtonProps) {
  const [open, setOpen] = React.useState(false);
  const [downloading, setDownloading] = React.useState<ExportFormat | null>(null);

  const handleDownload = async (format: ExportFormat) => {
    setOpen(false);
    setDownloading(format);
    try {
      const { data } = await fetchers[format]();
      saveBlobAsFile(data, `${basename}.${format}`);
    } catch {
      toast.error('تعذّر تحميل الملف');
    } finally {
      setDownloading(null);
    }
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline" className="gap-2" disabled={downloading !== null}>
          {downloading !== null ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
          تصدير
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-40 p-1">
        {(Object.keys(EXPORT_LABEL) as ExportFormat[]).map((format) => (
          <button
            key={format}
            type="button"
            onClick={() => handleDownload(format)}
            disabled={downloading !== null}
            className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-ink hover:bg-surface-2 disabled:opacity-60"
          >
            <Download className="h-4 w-4" />
            {EXPORT_LABEL[format]}
          </button>
        ))}
      </PopoverContent>
    </Popover>
  );
}
