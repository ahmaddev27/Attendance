'use client';

import * as React from 'react';
import { Download, Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { saveBlobAsFile } from '@/lib/download-file';

type ExportCsvButtonProps = {
  filename: string;
  fetchCsv: () => Promise<{ data: Blob }>;
};

export function ExportCsvButton({ filename, fetchCsv }: ExportCsvButtonProps) {
  const [exporting, setExporting] = React.useState(false);

  const handleExport = async () => {
    setExporting(true);
    try {
      const { data } = await fetchCsv();
      saveBlobAsFile(data, filename);
    } catch {
      toast.error('تعذر تنزيل ملف CSV');
    } finally {
      setExporting(false);
    }
  };

  return (
    <Button type="button" variant="outline" className="gap-2" onClick={handleExport} disabled={exporting}>
      {exporting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
      تصدير CSV
    </Button>
  );
}
